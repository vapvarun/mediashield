/**
 * MediaShield Admin -- Settings Page
 *
 * Premium settings with section cards, icons, descriptions,
 * and auto-save with debounce.
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import {
	ToggleControl,
	TextControl,
	RangeControl,
	ColorPicker,
	TextareaControl,
	SelectControl,
	Spinner,
	Button,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
import Icon from '../components/Icon';
import LevelPicker from '../components/LevelPicker';
import { protectionLevels } from '../utils/protection';
import '../components/upsell.css';
import './settings.css';

const config = window.mediashieldAdmin || {};
const lmsConfig = window.mediashieldProLMS || {};
// Learnomy 2.4.1+ owns "watch the video to complete" (Integrations\Learnomy::owns_watch_rule()).
const learnomyOwnsWatchRule = !! ( config.isLearnomyActive && config.learnomyOwnsWatchRule );
const DEBOUNCE_MS = 800;

/**
 * A whole-number setting that can be retyped.
 *
 * The field keeps what is typed, so it can be emptied; only a whole number
 * inside min..max is saved. It used to save `parseInt( val ) || default` on
 * every keystroke, so emptying the field put the default back and typing 8
 * after it saved 308. An exponent cannot be typed; a decimal such as 1.5,
 * or a value outside the range, is not saved and the field says why instead
 * of quietly going back on blur.
 *
 * @param {Object}   props          TextControl props.
 * @param {number}   props.value    Saved value.
 * @param {Function} props.onCommit Called with each valid number.
 * @param {number}   props.min      Smallest allowed value.
 * @param {number}   [props.max]    Largest allowed value.
 * @param {string}   [props.help]   Help text, replaced by the reason while a value is refused.
 */
const NumberField = ( { value, onCommit, min, max, help, ...props } ) => {
	const [ draft, setDraft ] = useState( null );
	const [ refused, setRefused ] = useState( null );
	// A value is committed once typing has settled (or on blur), never per
	// key: typing "1.5" committed the "1" on the way and then refused 1.5,
	// so the setting quietly became 1 (Basecamp 10499264493).
	const pending = useRef( null );
	const timer = useRef( null );
	const commitPending = () => {
		clearTimeout( timer.current );
		if ( null !== pending.current ) {
			const n = pending.current;
			pending.current = null;
			onCommit( n );
		}
	};
	useEffect( () => () => commitPending(), [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const rule = undefined === max
		? sprintf( /* translators: %d: smallest allowed number. */ __( 'Enter a whole number, %d or more.', 'mediashield' ), min )
		: sprintf( /* translators: 1: smallest allowed number, 2: largest allowed number. */ __( 'Enter a whole number from %1$d to %2$d.', 'mediashield' ), min, max );

	return (
		<TextControl
			{ ...props }
			className={ refused !== null ? 'mediashield-number-field is-refused' : 'mediashield-number-field' }
			type="number"
			inputMode="numeric"
			step={ 1 }
			min={ min }
			max={ max }
			value={ draft ?? value }
			help={ refused !== null
				? sprintf( /* translators: 1: the value that was typed, 2: the rule it broke. */ __( '%1$s was not saved. %2$s', 'mediashield' ), refused, rule )
				: help }
			// A dot or comma is let through so "1.5" is refused with the rule
			// below; swallowing it saved "15".
			onKeyDown={ ( e ) => {
				if ( [ 'e', 'E', '+' ].includes( e.key ) ) {
					e.preventDefault();
				}
			} }
			onChange={ ( val ) => {
				setDraft( val );
				clearTimeout( timer.current );
				pending.current = null;
				if ( '' === val ) return;
				const n = Number( val );
				// "1." is still being typed: wait for what follows the dot.
				if ( /[.,]$/.test( val ) ) return;
				if ( Number.isInteger( n ) && n >= min && ( undefined === max || n <= max ) ) {
					setRefused( null );
					pending.current = n;
					timer.current = setTimeout( commitPending, DEBOUNCE_MS );
				} else {
					setRefused( val );
				}
			} }
			onBlur={ () => {
				commitPending();
				setDraft( null );
			} }
			__nextHasNoMarginBottom
		/>
	);
};

const LMS_PCT_OPTIONS = [
	{ label: '25%', value: 25 },
	{ label: '50%', value: 50 },
	{ label: '75%', value: 75 },
	{ label: '90%', value: 90 },
	{ label: '100%', value: 100 },
];


// Reference video used only to make the mid-roll spacing concrete in the
// help text below -- picking a real-feeling lesson length (46 minutes) so
// "3 mid-rolls" reads as "roughly 13:48, 23:00, 32:12" instead of an
// abstract count. The engine itself spaces breaks across 10%-90% of the
// ACTUAL video's duration (see AdManagerBridge::supply_breaks()); this is
// illustration only.
const AD_EXAMPLE_DURATION = 46 * 60;

const formatTimecode = ( totalSeconds ) => {
	const minutes = Math.floor( totalSeconds / 60 );
	const seconds = Math.round( totalSeconds % 60 );
	return `${ minutes }:${ String( seconds ).padStart( 2, '0' ) }`;
};

const midrollExampleTimes = ( count ) => {
	if ( ! count || count < 1 ) {
		return '';
	}
	const start = AD_EXAMPLE_DURATION * 0.1;
	const span = AD_EXAMPLE_DURATION * 0.8;
	const times = [];
	for ( let i = 1; i <= count; i++ ) {
		times.push( formatTimecode( start + ( span * i ) / ( count + 1 ) ) );
	}
	return times.join( ', ' );
};

/**
 * The Settings sections in the left-hand navigation (Basecamp 10530930776):
 * one nav item per card, named as the card is (Basecamp 10545260577). A
 * card whose plugin or feature is not on this site is not rendered and has
 * no nav item. `integrations` keeps its key for old #/settings links.
 */
const SETTINGS_GROUPS = [
	{ key: 'protection', label: __( 'Protection', 'mediashield' ) },
	{ key: 'access', label: __( 'Who can watch', 'mediashield' ) },
	{ key: 'watermark', label: __( 'Watermark', 'mediashield' ) },
	{ key: 'player', label: __( 'Player', 'mediashield' ) },
	{ key: 'messages', label: __( 'Messages', 'mediashield' ) },
	{ key: 'ads', label: __( 'Video ads', 'mediashield' ) },
	{ key: 'uploads', label: __( 'Uploads', 'mediashield' ) },
	{ key: 'history', label: __( 'Viewing history', 'mediashield' ) },
	{ key: 'integrations', label: __( 'Lesson completion', 'mediashield' ) },
];

/**
 * The card title for a group: the same words as its nav item.
 *
 * @param {string} key Group key.
 * @return {string} Label.
 */
const groupLabel = ( key ) => SETTINGS_GROUPS.find( ( g ) => g.key === key )?.label || '';

/**
 * Jump to a group's first section and move focus there for keyboard users.
 *
 * @param {string}  key    Group key.
 * @param {boolean} smooth Animate the scroll (a click) or jump (a link opened).
 */
const goToGroup = ( key, smooth = true ) => {
	const card = document.querySelector( `[data-group="${ key }"]:not([hidden])` );
	if ( card ) {
		card.scrollIntoView( { behavior: smooth ? 'smooth' : 'auto', block: 'start' } );
		card.focus( { preventScroll: true } );
	}
};

/**
 * The section a deep link names: `#/settings/<group>`, for example
 * `#/settings/integrations` (Lesson Completion). Any group in the section
 * list works, Pro's sections from `mediashield_settings_sections` included.
 *
 * @return {string} Group key, or '' for plain `#/settings`.
 */
const linkedGroup = () => {
	const m = window.location.hash.match( /^#\/settings\/([\w-]+)/ );
	return m ? m[ 1 ] : '';
};

/**
 * Left-hand section navigation and the settings search.
 *
 * The search hides every section card (Pro's included) whose words do not
 * match, so a setting is two steps away: type its name, or pick its section.
 * On a phone the section list is a select.
 */
/**
 * A card's text with its pieces kept apart ("Email IP Address", not
 * "EmailIP Address"), so a search can match at the start of a word.
 *
 * @param {HTMLElement} el Card.
 * @return {string} Text.
 */
const wordsOf = ( el ) => {
	const walker = document.createTreeWalker( el, window.NodeFilter.SHOW_TEXT );
	const parts = [];
	while ( walker.nextNode() ) {
		parts.push( walker.currentNode.nodeValue );
	}
	return parts.join( ' ' );
};

const SettingsNav = ( { groups } ) => {
	const [ query, setQuery ] = useState( '' );
	const [ active, setActive ] = useState( groups[ 0 ]?.key || '' );
	const [ shown, setShown ] = useState( -1 );

	useEffect( () => {
		const q = query.trim();
		// Match at the start of a word, so "IP" finds "IP address", not "skip".
		const re = '' === q ? null : new RegExp( '(^|[^\\p{L}\\p{N}])' + q.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ), 'iu' );
		let count = 0;
		document.querySelectorAll( '.mediashield-settings__section' ).forEach( ( card ) => {
			const match = ! re || re.test( wordsOf( card ) );
			card.hidden = ! match;
			count += match ? 1 : 0;
		} );
		setShown( '' === q ? -1 : count );
	}, [ query ] );

	// The nav follows the scroll: the item lit is the card at the top of the
	// screen, not the last one clicked (Basecamp 10545260577).
	useEffect( () => {
		let frame = 0;
		const spy = () => {
			cancelAnimationFrame( frame );
			frame = requestAnimationFrame( () => {
				let current = '';
				document.querySelectorAll( '.mediashield-settings__sections [data-group]:not([hidden])' ).forEach( ( card ) => {
					if ( card.getBoundingClientRect().top <= 140 ) {
						current = card.dataset.group;
					}
				} );
				if ( current ) {
					setActive( current );
				}
			} );
		};
		window.addEventListener( 'scroll', spy, { passive: true } );
		return () => {
			cancelAnimationFrame( frame );
			window.removeEventListener( 'scroll', spy );
		};
	}, [] );

	const pick = ( key ) => {
		setActive( key );
		goToGroup( key );
		// The address names the section, so it can be copied and shared.
		window.history.replaceState( null, '', '#/settings/' + key );
	};

	// Deep link (#/settings/<group>): open at that section on first load and
	// whenever the hash changes; a group not in the list opens at the top.
	// Sections above may still be loading their own data (Pro's Watermark
	// fields), so for two seconds the section is kept in place as they grow,
	// unless the owner scrolls or types first.
	useEffect( () => {
		let stopHold = () => {};
		const hold = ( key ) => {
			stopHold();
			const column = document.querySelector( '.mediashield-settings__sections' );
			if ( ! column || ! window.ResizeObserver ) {
				return;
			}
			const observer = new window.ResizeObserver( () => goToGroup( key, false ) );
			const events = [ 'wheel', 'touchstart', 'keydown', 'mousedown' ];
			const timer = setTimeout( () => stopHold(), 2000 );
			stopHold = () => {
				observer.disconnect();
				clearTimeout( timer );
				events.forEach( ( ev ) => window.removeEventListener( ev, stopHold ) );
				stopHold = () => {};
			};
			observer.observe( column );
			events.forEach( ( ev ) => window.addEventListener( ev, stopHold, { passive: true } ) );
		};
		const follow = () => {
			const key = linkedGroup();
			if ( key && groups.some( ( g ) => g.key === key ) ) {
				setActive( key );
				goToGroup( key, false );
				hold( key );
			} else if ( key ) {
				window.history.replaceState( null, '', '#/settings' );
				window.scrollTo( 0, 0 );
			}
		};
		follow();
		window.addEventListener( 'hashchange', follow );
		return () => {
			stopHold();
			window.removeEventListener( 'hashchange', follow );
		};
	}, [ groups.map( ( g ) => g.key ).join() ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<aside className="mediashield-settings__nav">
			<div className="mediashield-settings__search">
				<label htmlFor="mediashield-settings-search" className="screen-reader-text">
					{ __( 'Search settings', 'mediashield' ) }
				</label>
				<input
					id="mediashield-settings-search"
					type="search"
					className="mediashield-settings__search-input"
					placeholder={ __( 'Search settings', 'mediashield' ) }
					value={ query }
					onChange={ ( e ) => setQuery( e.target.value ) }
				/>
				<span className="mediashield-settings__search-result" role="status">
					{ 0 === shown && __( 'No settings match.', 'mediashield' ) }
					{ shown > 0 && sprintf( /* translators: %d: number of matching sections. */ _n( '%d section matches.', '%d sections match.', shown, 'mediashield' ), shown ) }
				</span>
			</div>
			<nav aria-label={ __( 'Settings sections', 'mediashield' ) }>
				<ul className="mediashield-settings__nav-list">
					{ groups.map( ( g ) => (
						<li key={ g.key }>
							<a
								href={ `#/settings/${ g.key }` }
								className={ 'mediashield-settings__nav-link' + ( active === g.key ? ' is-active' : '' ) }
								aria-current={ active === g.key ? 'true' : undefined }
								onClick={ ( e ) => {
									e.preventDefault();
									pick( g.key );
								} }
							>
								{ g.label }
							</a>
						</li>
					) ) }
				</ul>
				<SelectControl
					className="mediashield-settings__nav-select"
					label={ __( 'Go to section', 'mediashield' ) }
					value={ active }
					options={ groups.map( ( g ) => ( { label: g.label, value: g.key } ) ) }
					onChange={ pick }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</nav>
		</aside>
	);
};

const SectionCard = ( { icon, title, description, group, children } ) => (
	<div className="mediashield-settings__section" data-group={ group } tabIndex={ -1 }>
		<div className="mediashield-settings__section-header">
			<div className="mediashield-settings__section-icon">
				<Icon name={ icon } />
			</div>
			<div>
				<div className="mediashield-settings__section-title">{ title }</div>
				{ description && (
					<div className="mediashield-settings__section-desc">{ description }</div>
				) }
			</div>
		</div>
		<div className="mediashield-settings__section-body">
			{ children }
		</div>
	</div>
);

const Settings = () => {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const debounceRef = useRef( null );
	// The values the server last confirmed. A save that fails puts these
	// back, so a control never shows a value that was not saved.
	const savedRef = useRef( {} );
	// What the watermark preview says. Pro owns its field names and sends
	// { text, caption } whenever its Watermark Fields change.
	const [ previewWords, setPreviewWords ] = useState( null );

	useEffect( () => {
		const onPreview = ( e ) => setPreviewWords( e.detail || null );
		window.addEventListener( 'mediashield:watermark-preview', onPreview );
		return () => window.removeEventListener( 'mediashield:watermark-preview', onPreview );
	}, [] );

	const { createSuccessNotice, createErrorNotice, createInfoNotice } = useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;

		apiFetch( {
			url: `${ config.restUrl }settings`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => {
				if ( ! cancelled ) {
					setSettings( res );
					savedRef.current = res;
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) setError( window.mediashieldRequest.message( err, __( 'Failed to load settings.', 'mediashield' ) ) );
			} )
			.finally( () => {
				if ( ! cancelled ) setLoading( false );
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	// Changes made within DEBOUNCE_MS of each other are saved together. The
	// timer used to carry only the last key, so the earlier change was lost
	// while the screen still said saved.
	const pendingRef = useRef( {} );

	const flush = useCallback(
		( keepalive = false ) => {
			clearTimeout( debounceRef.current );
			const data = pendingRef.current;
			if ( ! Object.keys( data ).length ) return;
			pendingRef.current = {};

			// One notice id: "Saving..." is replaced by Saved or by the error.
			const notice = { type: 'snackbar', id: 'mediashield-autosave' };
			createInfoNotice( __( 'Saving…', 'mediashield' ), notice );

			apiFetch( {
				url: `${ config.restUrl }settings`,
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				data,
				keepalive,
			} )
				.then( ( res ) => {
					// The server's answer is the truth: a value it refused (out of
					// range, wrong type) comes back as the stored one, so the
					// control goes back to it instead of showing a value not kept.
					// Text is left as typed (the server trims it), so only switches,
					// numbers and choices are compared.
					const keys = Object.keys( data ).filter( ( k ) => 'string' !== typeof data[ k ] || [ 'ms_default_protection' ].includes( k ) );
					const refused = keys.filter( ( k ) => k in res && JSON.stringify( res[ k ] ) !== JSON.stringify( data[ k ] ) );
					Object.keys( data ).forEach( ( k ) => {
						savedRef.current[ k ] = k in res ? res[ k ] : data[ k ];
					} );
					if ( 'ms_legacy_switches' in res ) savedRef.current.ms_legacy_switches = res.ms_legacy_switches;
					setSettings( ( prev ) => {
						const next = { ...prev, ms_legacy_switches: res.ms_legacy_switches };
						keys.forEach( ( k ) => {
							// Only keys not changed again since this save started.
							if ( k in res && ! ( k in pendingRef.current ) ) next[ k ] = res[ k ];
						} );
						return next;
					} );
					if ( refused.length ) {
						createErrorNotice( __( 'Not saved: that value is not allowed, so the setting is back to what it was.', 'mediashield' ), notice );
					} else {
						createSuccessNotice( __( 'Saved', 'mediashield' ), notice );
					}
				} )
				.catch( ( err ) => {
					// Switch the controls back to what is saved, and say so; Try
					// again sends the same change (Basecamp 10545260577).
					setSettings( ( prev ) => {
						const next = { ...prev };
						Object.keys( data ).forEach( ( k ) => {
							if ( ! ( k in pendingRef.current ) ) next[ k ] = savedRef.current[ k ];
						} );
						return next;
					} );
					createErrorNotice(
						sprintf(
							/* translators: %s: why the save failed. */
							__( 'Not saved, so the setting is switched back. %s', 'mediashield' ),
							window.mediashieldRequest.message( err, __( 'The site could not be reached.', 'mediashield' ) )
						),
						{
							...notice,
							actions: [ {
								label: __( 'Try again', 'mediashield' ),
								onClick: () => {
									setSettings( ( prev ) => ( { ...prev, ...data } ) );
									pendingRef.current = { ...data, ...pendingRef.current };
									flush();
								},
							} ],
						}
					);
				} );
		},
		[ createSuccessNotice, createErrorNotice, createInfoNotice ]
	);

	const saveField = useCallback(
		( key, value ) => {
			pendingRef.current[ key ] = value;
			clearTimeout( debounceRef.current );
			debounceRef.current = setTimeout( flush, DEBOUNCE_MS );
		},
		[ flush ]
	);

	// Leaving the screen, or the page, within DEBOUNCE_MS still saves.
	useEffect( () => {
		const onUnload = () => flush( true );
		window.addEventListener( 'beforeunload', onUnload );
		return () => {
			window.removeEventListener( 'beforeunload', onUnload );
			flush();
		};
	}, [ flush ] );

	const updateSetting = useCallback(
		( key, value ) => {
			setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
			saveField( key, value );
		},
		[ saveField ]
	);

	if ( loading ) {
		return (
			<div className="mediashield-page mediashield-settings">
				<div className="mediashield-loader">
					<Spinner />
					<span className="mediashield-loader__text">
						{ __( 'Loading settings...', 'mediashield' ) }
					</span>
				</div>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="mediashield-page mediashield-settings">
				<div className="mediashield-notice mediashield-notice--error">
					{ error }
				</div>
			</div>
		);
	}

	// Which cards this site shows; a card that cannot work here is not
	// rendered and has no nav item (Basecamp 10545260577).
	const lmsName = lmsConfig.lmsName || '';
	const shows = {
		ads: !! config.isAdManagerActive,
		uploads: !! ( settings?.ms_connected_platforms && settings.ms_connected_platforms.length ),
		integrations: !! ( config.isLearnomyActive || lmsConfig.enforcesWatchRule || ( lmsConfig.isLMSActive && settings?.ms_lms_auto_complete !== undefined ) ),
	};

	// Sections other plugins add (Pro's Watermark fields) render inside the
	// settings column, get a nav entry after the group they follow, and are
	// covered by the search (Basecamp 10530930776).
	const extras = applyFilters( 'mediashield_settings_sections', [] ).filter( ( x ) => x && x.key && x.component );
	const navGroups = [];
	SETTINGS_GROUPS.filter( ( g ) => false !== shows[ g.key ] ).forEach( ( g ) => {
		navGroups.push( g );
		extras.filter( ( x ) => x.after === g.key ).forEach( ( x ) => navGroups.push( { key: x.key, label: x.label } ) );
	} );
	const renderExtras = ( after ) => extras
		.filter( ( x ) => x.after === after )
		.map( ( x ) => {
			const Extra = x.component;
			return (
				<div key={ x.key } className="mediashield-settings__extra" data-group={ x.key }>
					<Extra />
				</div>
			);
		} );

	const levels = protectionLevels();
	const legacy = Array.isArray( settings?.ms_legacy_switches ) ? settings.ms_legacy_switches : [];
	const legacyNames = {
		ms_hide_source: __( 'Hide the file address', 'mediashield' ),
		ms_detect_devtools: __( 'Detect developer tools', 'mediashield' ),
		ms_pause_on_devtools: __( 'Pause when developer tools open', 'mediashield' ),
	};
	const pickLevel = ( val ) => {
		updateSetting( 'ms_default_protection', val );
		// Videos reads the default from the page config, printed once at
		// load; keep it current so "Site default (X)" is right without a reload.
		if ( window.mediashieldAdmin ) window.mediashieldAdmin.defaultProtection = val;
	};

	return (
		<div className="mediashield-page mediashield-settings">
			<header className="mediashield-page__header">
				<h1>{ __( 'Settings', 'mediashield' ) }</h1>
				<span className="mediashield-settings__autosave">
					{ __( 'Changes save automatically', 'mediashield' ) }
				</span>
			</header>

			<div className="mediashield-settings__layout">
				<SettingsNav groups={ navGroups } />
				<div className="mediashield-settings__sections">

			<SectionCard
				icon="shield"
				group="protection"
				title={ groupLabel( 'protection' ) }
				description={ __( 'The level a video uses unless it sets its own. Each level includes the one above it.', 'mediashield' ) }
			>
				<ToggleControl
					label={ __( 'Protect videos', 'mediashield' ) }
					help={ __( 'Off: every video plays with no MediaShield protection.', 'mediashield' ) }
					checked={ !! settings?.ms_enabled }
					onChange={ ( val ) => updateSetting( 'ms_enabled', val ) }
					__nextHasNoMarginBottom
				/>
				{ legacy.length > 0 && (
					<div className="mediashield-settings__rule-note" role="note">
						<strong>{ __( 'Your site keeps its earlier protection switches.', 'mediashield' ) }</strong>{ ' ' }
						{ sprintf(
							/* translators: %s: the switches still on, e.g. "Hide the file address, Detect developer tools". */
							__( 'Before this version these were site-wide switches. On your site these are on, and keep working for every protected video, so nothing changed for your members: %s. Pick a level below to use the levels instead: then hiding the file address and detecting developer tools belong to Strict.', 'mediashield' ),
							legacy.map( ( k ) => legacyNames[ k ] || k ).join( ', ' )
						) }
						<div>
							<Button variant="secondary" onClick={ () => pickLevel( settings?.ms_default_protection || 'standard' ) } __next40pxDefaultSize>
								{ sprintf(
									/* translators: %s: the current protection level, e.g. Standard. */
									__( 'Use the levels (keep %s)', 'mediashield' ),
									levels[ settings?.ms_default_protection || 'standard' ]?.label || ''
								) }
							</Button>
						</div>
					</div>
				) }
				<LevelPicker
					legend={ __( 'Protection level', 'mediashield' ) }
					value={ settings?.ms_default_protection || 'standard' }
					onChange={ pickLevel }
					note={ __( 'Login is set once, under Who can watch, and applies at every level.', 'mediashield' ) }
				/>
				<ToggleControl
					label={ __( 'Block right-click on every video', 'mediashield' ) }
					help={ __( 'Basic, Standard and Strict always block it. On: videos set to None block it too.', 'mediashield' ) }
					checked={ settings?.ms_block_right_click !== false }
					onChange={ ( val ) => updateSetting( 'ms_block_right_click', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Block the save shortcut (Ctrl+S / Cmd+S)', 'mediashield' ) }
					help={ __( 'While the player has focus. Only the save shortcut: View Source and developer tools shortcuts are not blocked.', 'mediashield' ) }
					checked={ !! settings?.ms_block_keyboard }
					onChange={ ( val ) => updateSetting( 'ms_block_keyboard', val ) }
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			<SectionCard
				icon="admin-users"
				group="access"
				title={ groupLabel( 'access' ) }
				description={ __( 'Who may play protected videos, wherever they are shown.', 'mediashield' ) }
			>
				<ToggleControl
					label={ __( 'Require login', 'mediashield' ) }
					help={ __( 'Only logged-in members can watch. Applies at every protection level. A video can also allow only one role.', 'mediashield' ) }
					checked={ !! settings?.ms_require_login }
					onChange={ ( val ) => updateSetting( 'ms_require_login', val ) }
					__nextHasNoMarginBottom
				/>
				<RangeControl
					label={ __( 'Streams at once per member', 'mediashield' ) }
					help={ __( 'How many videos one member can play at the same time, on any devices.', 'mediashield' ) }
					value={ settings?.ms_max_concurrent_streams ?? 1 }
					onChange={ ( val ) => updateSetting( 'ms_max_concurrent_streams', val ) }
					min={ 1 }
					max={ 5 }
					step={ 1 }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<TextareaControl
					label={ __( 'Allowed domains', 'mediashield' ) }
					help={ __( 'Sites allowed to show your videos. One domain per line, or separated by commas. A full URL is fine too; only the domain is used. Leave empty to allow all.', 'mediashield' ) }
					value={ settings?.ms_allowed_domains || '' }
					onChange={ ( val ) => updateSetting( 'ms_allowed_domains', val ) }
					rows={ 3 }
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			<SectionCard
				icon="art"
				group="watermark"
				title={ groupLabel( 'watermark' ) }
				description={ __( 'Text over the video that names the viewer, so a recording points to the account it came from.', 'mediashield' ) }
			>
				{ /* What a viewer sees, from the values on this screen. A sketch of
				     the player, not the player: it needs no video and no session. */ }
				<div className="mediashield-settings__preview" aria-hidden="true">
					{ !! settings?.ms_show_badge && (
						<span
							className="mediashield-settings__preview-badge"
							style={ settings?.ms_player_accent_color ? { '--ms-preview-accent': settings.ms_player_accent_color } : undefined }
						>
							{ __( 'Protected by MediaShield', 'mediashield' ) }
						</span>
					) }
					<span
						className={ 'mediashield-settings__preview-watermark' + ( settings?.ms_watermark_edges_only ? ' mediashield-settings__preview-watermark--edge' : '' ) }
						style={ {
							color: settings?.ms_watermark_color || '#ffffff',
							opacity: settings?.ms_watermark_opacity ?? 0.5,
						} }
					>
						{ previewWords?.text || [ __( 'Viewer Name', 'mediashield' ), settings?.ms_watermark_show_email ? 'viewer@example.com' : '', settings?.ms_watermark_show_ip ? '203.0.113.7' : '' ].filter( Boolean ).join( ' · ' ) }
					</span>
					<span
						className="mediashield-settings__preview-button"
						style={ settings?.ms_player_accent_color ? { background: settings.ms_player_accent_color } : undefined }
					>
						{ settings?.ms_login_button_text || __( 'Log In', 'mediashield' ) }
					</span>
				</div>
				<p className="mediashield-settings__preview-caption">
					{ previewWords?.caption || __( 'Preview. Each viewer sees their own name, and moves around the video.', 'mediashield' ) }
				</p>
				{ ! config.isProActive && (
					<fieldset className="mediashield-settings__fieldset">
						<legend>{ __( 'Show on the video', 'mediashield' ) }</legend>
						<p className="mediashield-settings__fieldset-note">{ __( 'The member\'s name is always shown.', 'mediashield' ) }</p>
						<ToggleControl
							label={ __( 'Email address', 'mediashield' ) }
							checked={ !! settings?.ms_watermark_show_email }
							onChange={ ( val ) => updateSetting( 'ms_watermark_show_email', val ) }
							__nextHasNoMarginBottom
						/>
						<ToggleControl
							label={ __( 'IP address', 'mediashield' ) }
							checked={ !! settings?.ms_watermark_show_ip }
							onChange={ ( val ) => updateSetting( 'ms_watermark_show_ip', val ) }
							__nextHasNoMarginBottom
						/>
						<p className="mediashield-settings__fieldset-note">
							{ __( 'Email and IP address are personal data, so both start off. Turn one on only if your privacy policy says you show it. A site that set them keeps its choice.', 'mediashield' ) }
						</p>
					</fieldset>
				) }
				<RangeControl
					label={ __( 'Opacity', 'mediashield' ) }
					value={ settings?.ms_watermark_opacity ?? 0.5 }
					onChange={ ( val ) => updateSetting( 'ms_watermark_opacity', val ) }
					min={ 0 }
					max={ 1 }
					step={ 0.05 }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<div className="mediashield-settings__color-field">
					<label>{ __( 'Watermark Color', 'mediashield' ) }</label>
					<ColorPicker
						color={ settings?.ms_watermark_color || '#ffffff' }
						onChange={ ( val ) => updateSetting( 'ms_watermark_color', val ) }
						enableAlpha={ false }
					/>
				</div>
				<NumberField
					label={ __( 'Position Swap Interval', 'mediashield' ) }
					help={ __( 'Seconds between watermark position changes. Moving the watermark stops a viewer cropping it out, so lower is safer and higher is less distracting.', 'mediashield' ) }
					value={ settings?.ms_watermark_swap_interval ?? 30 }
					onCommit={ ( val ) => updateSetting( 'ms_watermark_swap_interval', val ) }
					min={ 1 }
				/>
				<ToggleControl
					label={ __( 'Keep the watermark at the edges', 'mediashield' ) }
					help={ __( 'Keeps the watermark in the corners, off the centre of the video.', 'mediashield' ) }
					checked={ !! settings?.ms_watermark_edges_only }
					onChange={ ( val ) => updateSetting( 'ms_watermark_edges_only', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Show MediaShield Badge', 'mediashield' ) }
					help={ __( 'Off by default. Shows a small "Protected by MediaShield" label on the player, in your Brand Color, so viewers know the video is protected.', 'mediashield' ) }
					checked={ !! settings?.ms_show_badge }
					onChange={ ( val ) => updateSetting( 'ms_show_badge', val ) }
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			{ renderExtras( 'watermark' ) }

			<SectionCard
				icon="controls-play"
				group="player"
				title={ groupLabel( 'player' ) }
				description={ __( 'How the player behaves for every video.', 'mediashield' ) }
			>
				<ToggleControl
					label={ __( 'Speed Control', 'mediashield' ) }
					help={ __( 'Show playback speed selector (0.5x to 2x) on self-hosted and Bunny videos. Platform players (YouTube, Vimeo, Wistia) use their own speed controls.', 'mediashield' ) }
					checked={ !! settings?.ms_player_speed_control }
					onChange={ ( val ) => updateSetting( 'ms_player_speed_control', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Keyboard Shortcuts', 'mediashield' ) }
					help={ __( 'Space = play/pause, \u2190 \u2192 = seek 5s, \u2191 \u2193 = volume, M = mute, F = fullscreen. Only active when player is focused.', 'mediashield' ) }
					checked={ !! settings?.ms_player_keyboard }
					onChange={ ( val ) => updateSetting( 'ms_player_keyboard', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Resume Playback', 'mediashield' ) }
					help={ __( 'Remember where the viewer left off and offer to resume on return.', 'mediashield' ) }
					checked={ !! settings?.ms_player_resume }
					onChange={ ( val ) => updateSetting( 'ms_player_resume', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Prevent Skipping Ahead', 'mediashield' ) }
					help={ __( 'Stop viewers seeking past the furthest point they have watched. Rewinding stays allowed. Useful for course and compliance videos.', 'mediashield' ) }
					checked={ !! settings?.ms_player_prevent_forward_seek }
					onChange={ ( val ) =>
						updateSetting( 'ms_player_prevent_forward_seek', val )
					}
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Sticky Player', 'mediashield' ) }
					help={ __( 'Float the player in a corner when the viewer scrolls past it.', 'mediashield' ) }
					checked={ !! settings?.ms_player_sticky }
					onChange={ ( val ) => updateSetting( 'ms_player_sticky', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'End Screen', 'mediashield' ) }
					help={ __( 'Show a call-to-action overlay when the video finishes.', 'mediashield' ) }
					checked={ !! settings?.ms_player_endscreen }
					onChange={ ( val ) => updateSetting( 'ms_player_endscreen', val ) }
					__nextHasNoMarginBottom
				/>
				{ settings?.ms_player_endscreen && (
					<>
						<TextControl
							label={ __( 'End Screen Message', 'mediashield' ) }
							value={ settings?.ms_player_endscreen_text || '' }
							onChange={ ( val ) => updateSetting( 'ms_player_endscreen_text', val ) }
							placeholder={ __( 'Enjoyed this video? Explore more content.', 'mediashield' ) }
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __( 'End Screen Button URL', 'mediashield' ) }
							type="url"
							value={ settings?.ms_player_endscreen_url || '' }
							onChange={ ( val ) => updateSetting( 'ms_player_endscreen_url', val ) }
							placeholder="https://..."
							__nextHasNoMarginBottom
						/>
					</>
				) }
				<div className="mediashield-settings__color-field">
					<label>{ __( 'Brand Color', 'mediashield' ) }</label>
					<p className="mediashield-settings__color-help">
						{ settings?.ms_player_accent_color
							? __( 'Used for the player\'s buttons, focus outlines and the current playlist item.', 'mediashield' )
							: __( 'Following your theme\'s primary color. Pick a color to use your own for the player\'s buttons, focus outlines and the current playlist item.', 'mediashield' ) }
					</p>
					<ColorPicker
						color={ settings?.ms_player_accent_color || '#2271b1' }
						onChange={ ( val ) => updateSetting( 'ms_player_accent_color', val ) }
						enableAlpha={ false }
					/>
					{ !! settings?.ms_player_accent_color && (
						<Button
							variant="secondary"
							onClick={ () => updateSetting( 'ms_player_accent_color', '' ) }
							__next40pxDefaultSize
						>
							{ __( 'Use the theme color', 'mediashield' ) }
						</Button>
					) }
				</div>
			</SectionCard>

			<SectionCard
				icon="lock"
				group="messages"
				title={ groupLabel( 'messages' ) }
				description={ __( 'What viewers read on the player when they cannot watch yet.', 'mediashield' ) }
			>
				<TextControl
					label={ __( 'Login Overlay Text', 'mediashield' ) }
					help={ __( 'Message shown on the video overlay when login is required.', 'mediashield' ) }
					value={ settings?.ms_login_overlay_text || '' }
					onChange={ ( val ) => updateSetting( 'ms_login_overlay_text', val ) }
					placeholder={ __( 'Please log in to watch this video', 'mediashield' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Login Button Text', 'mediashield' ) }
					help={ __( 'Label for the login button on the video overlay.', 'mediashield' ) }
					value={ settings?.ms_login_button_text || '' }
					onChange={ ( val ) => updateSetting( 'ms_login_button_text', val ) }
					placeholder={ __( 'Log In', 'mediashield' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Access Denied Text', 'mediashield' ) }
					help={ __( 'Message shown when a logged-in user does not have permission to view a video.', 'mediashield' ) }
					value={ settings?.ms_access_denied_text || '' }
					onChange={ ( val ) => updateSetting( 'ms_access_denied_text', val ) }
					placeholder={ __( 'You do not have access to this video', 'mediashield' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Membership Page', 'mediashield' ) }
					help={ __( 'Where a logged-in member who may not watch a video can get access, such as your membership or pricing page. Leave empty to show no button. A course link from your LMS is used instead when there is one.', 'mediashield' ) }
					type="url"
					value={ settings?.ms_access_denied_url || '' }
					onChange={ ( val ) => updateSetting( 'ms_access_denied_url', val ) }
					placeholder="https://..."
					__nextHasNoMarginBottom
				/>
				{ !! settings?.ms_access_denied_url && (
					<TextControl
						label={ __( 'Membership Button Text', 'mediashield' ) }
						value={ settings?.ms_access_denied_button_text || '' }
						onChange={ ( val ) => updateSetting( 'ms_access_denied_button_text', val ) }
						placeholder={ __( 'See membership options', 'mediashield' ) }
						__nextHasNoMarginBottom
					/>
				) }
				{ /* Course words only where a course plugin can link a video to a course (Basecamp 10536749905). */ }
				{ ( config.isLearnomyActive || lmsConfig.isLMSActive ) && (
				<>
				<TextControl
					label={ __( 'Course Access Text', 'mediashield' ) }
					help={ /* translators: %s is typed as written; the site replaces it with the course name. */ __( 'Shown to a member who is not enrolled in the course a video belongs to, above a button to the course. %s is replaced by the course name.', 'mediashield' ) }
					value={ settings?.ms_course_denied_text || '' }
					onChange={ ( val ) => updateSetting( 'ms_course_denied_text', val ) }
					placeholder={ __( 'This video is part of %s. Enroll to watch it.', 'mediashield' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Course Button Text', 'mediashield' ) }
					value={ settings?.ms_view_course_text || '' }
					onChange={ ( val ) => updateSetting( 'ms_view_course_text', val ) }
					placeholder={ __( 'View course', 'mediashield' ) }
					__nextHasNoMarginBottom
				/>
				</>
				) }
				<TextControl
					label={ __( 'Unavailable Video Text', 'mediashield' ) }
					help={ __( 'Shown when the video host says the video was removed or made private.', 'mediashield' ) }
					value={ settings?.ms_unavailable_text || '' }
					onChange={ ( val ) => updateSetting( 'ms_unavailable_text', val ) }
					placeholder={ __( 'This video is unavailable.', 'mediashield' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Developer Tools Title (Strict)', 'mediashield' ) }
					help={ __( 'Shown on a Strict video that pauses because developer tools are open.', 'mediashield' ) }
					value={ settings?.ms_devtools_title || '' }
					placeholder={ __( 'Developer Tools Detected', 'mediashield' ) }
					onChange={ ( val ) => updateSetting( 'ms_devtools_title', val ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Developer Tools Message (Strict)', 'mediashield' ) }
					value={ settings?.ms_devtools_message || '' }
					placeholder={ __( 'Please close developer tools to continue watching this video.', 'mediashield' ) }
					onChange={ ( val ) => updateSetting( 'ms_devtools_message', val ) }
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			{ /* Only with WB Ad Manager's video ads installed: it is where the ads come from. */ }
			{ shows.ads && (
			<SectionCard
				icon="megaphone"
				group="ads"
				title={ groupLabel( 'ads' ) }
				description={ __( 'Site-wide placement for in-video ad breaks sourced from WB Ad Manager. Individual videos can override this under the video’s "Video Ads" box.', 'mediashield' ) }
			>
				<ToggleControl
					label={ __( 'Enable In-Video Ads', 'mediashield' ) }
					help={ __( 'Master switch for ad breaks inside the player. Off means no ads play anywhere, no matter what is set below. Default: on.', 'mediashield' ) }
					checked={ settings?.ms_ads_enabled !== false }
					onChange={ ( val ) => updateSetting( 'ms_ads_enabled', val ) }
					__nextHasNoMarginBottom
				/>
				{ settings?.ms_ads_enabled !== false && (
					<>
						<ToggleControl
							label={ __( 'Pre-roll', 'mediashield' ) }
							help={ __( 'Play one ad before the video starts. Default: on.', 'mediashield' ) }
							checked={ settings?.ms_ads_preroll !== false }
							onChange={ ( val ) => updateSetting( 'ms_ads_preroll', val ) }
							__nextHasNoMarginBottom
						/>
						<NumberField
							label={ __( 'Mid-roll Count', 'mediashield' ) }
							help={
								( settings?.ms_ads_midroll_count ?? 3 ) > 0
									? sprintf(
										/* translators: 1: number of mid-roll breaks, 2: example timecodes on a 46-minute video */
										__( 'Ad breaks spaced evenly across the middle 10%%–90%% of the video (0–10, default: 3). On a 46-minute video, %1$d mid-roll(s) would land at roughly %2$s.', 'mediashield' ),
										settings?.ms_ads_midroll_count ?? 3,
										midrollExampleTimes( settings?.ms_ads_midroll_count ?? 3 )
									)
									: __( 'No mid-roll breaks will play. When enabled, breaks are spaced evenly across the middle of the video, between the 10th and 90th percent mark (0-10, default: 3).', 'mediashield' )
							}
							min={ 0 }
							max={ 10 }
							value={ settings?.ms_ads_midroll_count ?? 3 }
							onCommit={ ( val ) => updateSetting( 'ms_ads_midroll_count', val ) }
						/>
						<ToggleControl
							label={ __( 'Require Full View', 'mediashield' ) }
							help={ __( 'Viewer must watch each ad in full — the Skip button never appears. Overrides the skip delay below. Default: off.', 'mediashield' ) }
							checked={ !! settings?.ms_ads_require_full_view }
							onChange={ ( val ) => updateSetting( 'ms_ads_require_full_view', val ) }
							__nextHasNoMarginBottom
						/>
						{ ! settings?.ms_ads_require_full_view && (
							<NumberField
								label={ __( 'Skip Delay (seconds)', 'mediashield' ) }
								help={ __( 'Seconds before the Skip button unlocks on each ad. 0 = skippable immediately. An ad that sets its own skip delay in WB Ad Manager keeps it; an ad whose own delay is blank or 0 uses this value. Clamped 0–60. Default: 5.', 'mediashield' ) }
								min={ 0 }
								max={ 60 }
								value={ settings?.ms_ads_skip_after ?? 5 }
								onCommit={ ( val ) => updateSetting( 'ms_ads_skip_after', val ) }
							/>
						) }
						<ToggleControl
							label={ __( 'Show Break Markers', 'mediashield' ) }
							help={ __( 'Display a marker on the seek bar for each upcoming ad break. Default: on.', 'mediashield' ) }
							checked={ settings?.ms_ads_show_markers !== false }
							onChange={ ( val ) => updateSetting( 'ms_ads_show_markers', val ) }
							__nextHasNoMarginBottom
						/>
					</>
				) }
			</SectionCard>
			) }

			{ shows.uploads && (
				<SectionCard
					icon="upload"
					group="uploads"
					title={ groupLabel( 'uploads' ) }
					description={ __( 'Where a file uploaded on Add video goes.', 'mediashield' ) }
				>
					<SelectControl
						label={ __( 'Default Upload Target', 'mediashield' ) }
						help={ __( 'Where new uploads go when you do not pick a platform yourself. "Auto" uses the first connected cloud platform.', 'mediashield' ) }
						value={ settings?.ms_default_upload_target ?? 'auto' }
						options={ [
							{ label: __( 'Auto (use connected platform)', 'mediashield' ), value: 'auto' },
							{ label: __( 'This site (uploaded file)', 'mediashield' ), value: 'self' },
							...( settings.ms_connected_platforms || [] ).map( ( p ) => ( {
								label: ( p.platform.charAt( 0 ).toUpperCase() + p.platform.slice( 1 ) ),
								value: p.platform,
							} ) ),
						] }
						onChange={ ( val ) => updateSetting( 'ms_default_upload_target', val ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</SectionCard>
			) }

			<SectionCard
				icon="backup"
				group="history"
				title={ groupLabel( 'history' ) }
				description={ __( 'How long viewings stay in your reports.', 'mediashield' ) }
			>
				<NumberField
					label={ __( 'Keep watch history for (months)', 'mediashield' ) }
					help={
						Number( settings?.ms_session_retention_months ?? 0 ) > 0
							? __(
									'Warning: sessions older than this are moved out of your reports every month. Views, completion rates and top-video figures will stop including anything older, and this cannot be undone from here. Set 0 to keep everything.',
									'mediashield'
							  )
							: __(
									'0 keeps every watch session forever, which is the recommended setting. Set a number of months only if you have a data-retention policy that requires older sessions to be moved out of reporting.',
									'mediashield'
							  )
					}
					min={ 0 }
					max={ 120 }
					value={ settings?.ms_session_retention_months ?? 0 }
					onCommit={ ( val ) => updateSetting( 'ms_session_retention_months', val ) }
				/>
			</SectionCard>

			{ /* One card for lesson completion (Basecamp 10545260590): Learnomy sets it on
			     each lesson; LearnDash has the one % here; Tutor LMS and LifterLMS
			     complete on a milestone. */ }
			{ shows.integrations && (
				<SectionCard
					icon="welcome-learn-more"
					group="integrations"
					title={ groupLabel( 'integrations' ) }
					description={ __( 'When a lesson that plays a protected video counts as complete.', 'mediashield' ) }
				>
					{ learnomyOwnsWatchRule && (
						<p className="mediashield-settings__rule-note">
							{ ( settings?.ms_lms_require_watch_pct ?? 0 ) > 0
								? sprintf(
									/* translators: %d: percent of the video to watch, for example 80. */
									__( 'Learnomy: set this on each lesson (Completion: Auto: video watched). New protected video lessons start at %d%%.', 'mediashield' ),
									settings.ms_lms_require_watch_pct
								)
								: __( 'Learnomy: set this on each lesson (Completion: Auto: video watched).', 'mediashield' ) }
						</p>
					) }
					{ ( ( config.isLearnomyActive && ! learnomyOwnsWatchRule ) || lmsConfig.enforcesWatchRule ) && (
					<NumberField
						label={ lmsConfig.enforcesWatchRule
							? __( 'LearnDash lessons: require this % of the video watched', 'mediashield' )
							: __( 'Require this % of the video watched before Complete', 'mediashield' ) }
						help={ lmsConfig.enforcesWatchRule
							? __( '0 turns this off. With 80, the Mark Complete button of a LearnDash lesson linked to a video stays disabled until the member has watched 80% of it, and the lesson completes by itself at that point when Auto-complete is on. Time spent watching counts; skipping ahead does not, and a part watched twice counts once. Site administrators are not held back.', 'mediashield' )
							: __( '0 turns this off. With 80, the lesson\'s Complete button stays disabled with "Watch 80% to complete" until the member has watched 80% of the lesson video (time spent watching; skipping ahead does not count, and a part watched twice counts once). Site administrators are not held back.', 'mediashield' ) }
						value={ settings?.ms_lms_require_watch_pct ?? 0 }
						onCommit={ ( val ) => updateSetting( 'ms_lms_require_watch_pct', val ) }
						min={ 0 }
						max={ 100 }
					/>
					) }
					{ lmsConfig.isLMSActive && settings?.ms_lms_auto_complete !== undefined && (
						<>
							<ToggleControl
								label={ __( 'Auto-complete lessons', 'mediashield' ) }
								help={ lmsConfig.lmsLabel
									? sprintf( /* translators: %s: LMS name, e.g. Tutor LMS. */ __( 'Mark the linked %s lesson complete when the member reaches the % below.', 'mediashield' ), lmsConfig.lmsLabel )
									: __( 'Mark the linked lesson complete when the member reaches the % below.', 'mediashield' ) }
								checked={ !! settings?.ms_lms_auto_complete }
								onChange={ ( val ) => updateSetting( 'ms_lms_auto_complete', val ) }
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __( 'Require enrollment', 'mediashield' ) }
								help={ __( 'On: a video linked to a lesson plays only for members enrolled in its course, where the video\'s own box asks for it. Off: enrollment is never checked.', 'mediashield' ) }
								checked={ !! settings?.ms_lms_enrollment_check }
								onChange={ ( val ) => updateSetting( 'ms_lms_enrollment_check', val ) }
								__nextHasNoMarginBottom
							/>
							{ /* LearnDash completes at its required % while that is above 0; at 0
							     the stored % below decides, so it is shown, never a hidden rule. */ }
							{ ( 'learndash' !== lmsName || 0 === Number( settings?.ms_lms_require_watch_pct ?? 0 ) ) && (
								<SelectControl
									label={ 'learndash' === lmsName
										? __( 'LearnDash lessons complete at (while the required % above is 0)', 'mediashield' )
										: __( 'Complete lessons at', 'mediashield' ) }
									help={ 'learndash' === lmsName
										? __( 'Set a required % above and the lesson completes there instead; this choice then no longer applies and is hidden.', 'mediashield' )
										: __( 'The share of the video watched that completes the lesson. A video can set its own.', 'mediashield' ) }
									value={ settings?.ms_lms_complete_pct ?? 100 }
									options={ LMS_PCT_OPTIONS }
									onChange={ ( val ) => updateSetting( 'ms_lms_complete_pct', parseInt( val, 10 ) ) }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							) }
						</>
					) }
				</SectionCard>
			) }

			{ /* One line, not a sales box (Basecamp 10545260681). */ }
			{ ! config.isProActive && (
				<p className="ms-upsell-line">
					{ __( 'MediaShield Pro adds watermark fields, Bunny Stream, LMS lesson completion and viewing heatmaps.', 'mediashield' ) }{ ' ' }
					<a href="https://wbcomdesigns.com/downloads/mediashield-pro/" target="_blank" rel="noopener noreferrer">{ __( 'See MediaShield Pro', 'mediashield' ) }</a>
				</p>
			) }
				</div>
			</div>
		</div>
	);
};

export default Settings;
