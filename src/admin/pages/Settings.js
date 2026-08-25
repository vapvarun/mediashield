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
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import Icon from '../components/Icon';

const config = window.mediashieldAdmin || {};
const lmsConfig = window.mediashieldProLMS || {};
const DEBOUNCE_MS = 800;

const LMS_PCT_OPTIONS = [
	{ label: '25%', value: 25 },
	{ label: '50%', value: 50 },
	{ label: '75%', value: 75 },
	{ label: '90%', value: 90 },
	{ label: '100%', value: 100 },
];

const PROTECTION_OPTIONS = [
	{ label: __( 'None', 'mediashield' ), value: 'none' },
	{ label: __( 'Basic', 'mediashield' ), value: 'basic' },
	{ label: __( 'Standard', 'mediashield' ), value: 'standard' },
	{ label: __( 'Strict', 'mediashield' ), value: 'strict' },
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

const SectionCard = ( { icon, title, description, children } ) => (
	<div className="mediashield-settings__section">
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

	const { createSuccessNotice, createErrorNotice } = useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;

		apiFetch( {
			url: `${ config.restUrl }settings`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => {
				if ( ! cancelled ) setSettings( res );
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) setError( err.message || __( 'Failed to load settings.', 'mediashield' ) );
			} )
			.finally( () => {
				if ( ! cancelled ) setLoading( false );
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	const saveField = useCallback(
		( key, value ) => {
			if ( debounceRef.current ) clearTimeout( debounceRef.current );

			debounceRef.current = setTimeout( () => {
				apiFetch( {
					url: `${ config.restUrl }settings`,
					method: 'PUT',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.nonce,
					},
					data: { [ key ]: value },
				} )
					.then( () => {
						createSuccessNotice( __( 'Settings saved.', 'mediashield' ), { type: 'snackbar' } );
					} )
					.catch( ( err ) => {
						createErrorNotice(
							err.message || __( 'Failed to save.', 'mediashield' ),
							{ type: 'snackbar' }
						);
					} );
			}, DEBOUNCE_MS );
		},
		[ createSuccessNotice, createErrorNotice ]
	);

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

	return (
		<div className="mediashield-page mediashield-settings">
			<header className="mediashield-page__header">
				<h1>{ __( 'Settings', 'mediashield' ) }</h1>
				<span style={ {
					fontSize: '12px',
					background: 'var(--ms-color-success-light)',
					padding: '4px 10px',
					borderRadius: 'var(--ms-radius-full)',
					fontWeight: 600,
					color: 'var(--ms-color-success)',
				} }>
					{ __( 'Auto-save enabled', 'mediashield' ) }
				</span>
			</header>

			<SectionCard
				icon="admin-settings"
				title={ __( 'General', 'mediashield' ) }
				description={ __( 'Core plugin behavior and protection defaults.', 'mediashield' ) }
			>
				<ToggleControl
					label={ __( 'Enable MediaShield', 'mediashield' ) }
					help={ __( 'Turn video protection on or off globally.', 'mediashield' ) }
					checked={ !! settings?.ms_enabled }
					onChange={ ( val ) => updateSetting( 'ms_enabled', val ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Default Protection Level', 'mediashield' ) }
					help={ __( 'Applied to new videos unless overridden per-video.', 'mediashield' ) }
					value={ settings?.ms_default_protection || 'standard' }
					options={ PROTECTION_OPTIONS }
					onChange={ ( val ) => updateSetting( 'ms_default_protection', val ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				{ /* Protection level descriptions */ }
				<div className="mediashield-settings__protection-descriptions" style={ {
					fontSize: '13px',
					color: 'var(--ms-color-text-tertiary, #757575)',
					lineHeight: '1.6',
					marginTop: '-8px',
					marginBottom: '16px',
					paddingLeft: '2px',
				} }>
					<div><strong>{ __( 'None:', 'mediashield' ) }</strong> { __( 'No protection applied', 'mediashield' ) }</div>
					<div><strong>{ __( 'Basic:', 'mediashield' ) }</strong> { __( 'Login required, right-click disabled', 'mediashield' ) }</div>
					<div><strong>{ __( 'Standard:', 'mediashield' ) }</strong> { __( 'Basic + watermark + session tracking', 'mediashield' ) }</div>
					<div><strong>{ __( 'Strict:', 'mediashield' ) }</strong> { __( 'Standard + devtools detection + source hiding', 'mediashield' ) }</div>
				</div>
				<ToggleControl
					label={ __( 'Require Login', 'mediashield' ) }
					help={ __( 'Only logged-in users can view protected videos.', 'mediashield' ) }
					checked={ !! settings?.ms_require_login }
					onChange={ ( val ) => updateSetting( 'ms_require_login', val ) }
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			<SectionCard
				icon="shield"
				title={ __( 'Protection', 'mediashield' ) }
				description={ __( 'Anti-download behavior applied to the player.', 'mediashield' ) }
			>
				<ToggleControl
					label={ __( 'Block Right-Click', 'mediashield' ) }
					help={ __( 'Prevent the browser context menu inside the player.', 'mediashield' ) }
					checked={ settings?.ms_block_right_click !== false }
					onChange={ ( val ) => updateSetting( 'ms_block_right_click', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Block Save Shortcut (Ctrl+S / Cmd+S)', 'mediashield' ) }
					help={ __( 'Intercept the save keyboard shortcut when the player is focused.', 'mediashield' ) }
					checked={ settings?.ms_block_keyboard !== false }
					onChange={ ( val ) => updateSetting( 'ms_block_keyboard', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Hide Video Source URL', 'mediashield' ) }
					help={ __(
						'Self-hosted videos only: play them through a permission-checked URL so the real file path never appears in the page. Does not apply to YouTube, Vimeo, Wistia or Bunny embeds - those players are iframes whose address necessarily contains the provider’s video ID.',
						'mediashield'
					) }
					checked={ settings?.ms_hide_source !== false }
					onChange={ ( val ) => updateSetting( 'ms_hide_source', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Detect Developer Tools', 'mediashield' ) }
					help={ __( 'Detect when the viewer opens browser DevTools and log a warning. Skipped on mobile and touch devices.', 'mediashield' ) }
					checked={ settings?.ms_detect_devtools !== false }
					onChange={ ( val ) => updateSetting( 'ms_detect_devtools', val ) }
					__nextHasNoMarginBottom
				/>
				{ settings?.ms_detect_devtools !== false && (
					<>
						<ToggleControl
							label={ __( 'Pause Video When Detected', 'mediashield' ) }
							help={ __( 'Pause playback and show an overlay when DevTools is detected. Off by default — detection is logged either way.', 'mediashield' ) }
							checked={ !! settings?.ms_pause_on_devtools }
							onChange={ ( val ) => updateSetting( 'ms_pause_on_devtools', val ) }
							__nextHasNoMarginBottom
						/>
						{ settings?.ms_pause_on_devtools && (
							<>
								<TextControl
									label={ __( 'Overlay Title', 'mediashield' ) }
									value={ settings?.ms_devtools_title || '' }
									placeholder={ __( 'Developer Tools Detected', 'mediashield' ) }
									onChange={ ( val ) => updateSetting( 'ms_devtools_title', val ) }
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ __( 'Overlay Message', 'mediashield' ) }
									value={ settings?.ms_devtools_message || '' }
									placeholder={ __( 'Please close developer tools to continue watching this video.', 'mediashield' ) }
									onChange={ ( val ) => updateSetting( 'ms_devtools_message', val ) }
									__nextHasNoMarginBottom
								/>
							</>
						) }
					</>
				) }
			</SectionCard>

			<SectionCard
				icon="art"
				title={ __( 'Watermark', 'mediashield' ) }
				description={ __( 'Dynamic overlay that identifies the viewer.', 'mediashield' ) }
			>
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
				<TextControl
					label={ __( 'Position Swap Interval', 'mediashield' ) }
					help={ __( 'Seconds between watermark position changes. Moving the watermark stops a viewer cropping it out, so lower is safer and higher is less distracting.', 'mediashield' ) }
					type="number"
					value={ settings?.ms_watermark_swap_interval ?? 30 }
					onChange={ ( val ) =>
						updateSetting( 'ms_watermark_swap_interval', parseInt( val, 10 ) || 30 )
					}
					min={ 1 }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Show MediaShield Badge', 'mediashield' ) }
					help={ __( 'Display a small "Protected by MediaShield" badge on the player.', 'mediashield' ) }
					checked={ !! settings?.ms_show_badge }
					onChange={ ( val ) => updateSetting( 'ms_show_badge', val ) }
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			<SectionCard
				icon="admin-site-alt3"
				title={ __( 'Allowed Domains', 'mediashield' ) }
				description={ __( 'Restrict video playback to specific domains.', 'mediashield' ) }
			>
				<TextareaControl
					label={ __( 'Allowed Domains', 'mediashield' ) }
					help={ __( 'One domain per line, or separated by commas - both work. A full URL is fine too; only the domain is used. Leave empty to allow all domains.', 'mediashield' ) }
					value={ settings?.ms_allowed_domains || '' }
					onChange={ ( val ) => updateSetting( 'ms_allowed_domains', val ) }
					rows={ 4 }
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			<SectionCard
				icon="admin-users"
				title={ __( 'Concurrent Streams', 'mediashield' ) }
				description={ __( 'Limit how many videos a user can watch simultaneously.', 'mediashield' ) }
			>
				<RangeControl
					label={ __( 'Max Concurrent Streams', 'mediashield' ) }
					help={ __( 'Number of simultaneous video sessions per user.', 'mediashield' ) }
					value={ settings?.ms_max_concurrent_streams ?? 1 }
					onChange={ ( val ) => updateSetting( 'ms_max_concurrent_streams', val ) }
					min={ 1 }
					max={ 5 }
					step={ 1 }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</SectionCard>

			<SectionCard
				icon="backup"
				title={ __( 'Analytics Retention', 'mediashield' ) }
				description={ __(
					'How long to keep watch history in your reports.',
					'mediashield'
				) }
			>
				<TextControl
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
					type="number"
					min={ 0 }
					max={ 120 }
					value={ settings?.ms_session_retention_months ?? 0 }
					onChange={ ( val ) =>
						updateSetting(
							'ms_session_retention_months',
							parseInt( val, 10 ) || 0
						)
					}
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			<SectionCard
				icon="lock"
				title={ __( 'Login & Access Messages', 'mediashield' ) }
				description={ __( 'Customize the messages shown when users need to log in or lack access.', 'mediashield' ) }
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
			</SectionCard>

			<SectionCard
				icon="controls-play"
				title={ __( 'Player Controls', 'mediashield' ) }
				description={ __( 'Customize the video player behavior and features.', 'mediashield' ) }
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
			</SectionCard>

			<SectionCard
				icon="megaphone"
				title={ __( 'Video Ads', 'mediashield' ) }
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
						<TextControl
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
							type="number"
							min={ 0 }
							max={ 10 }
							value={ settings?.ms_ads_midroll_count ?? 3 }
							onChange={ ( val ) =>
								updateSetting(
									'ms_ads_midroll_count',
									Math.max( 0, Math.min( 10, parseInt( val, 10 ) || 0 ) )
								)
							}
							__nextHasNoMarginBottom
						/>
						<ToggleControl
							label={ __( 'Require Full View', 'mediashield' ) }
							help={ __( 'Viewer must watch each ad in full — the Skip button never appears. Overrides the skip delay below. Default: off.', 'mediashield' ) }
							checked={ !! settings?.ms_ads_require_full_view }
							onChange={ ( val ) => updateSetting( 'ms_ads_require_full_view', val ) }
							__nextHasNoMarginBottom
						/>
						{ ! settings?.ms_ads_require_full_view && (
							<TextControl
								label={ __( 'Skip Delay (seconds)', 'mediashield' ) }
								help={ __( 'Seconds before the Skip button unlocks on each ad. 0 = skippable immediately. Clamped 0–60. Default: 5.', 'mediashield' ) }
								type="number"
								min={ 0 }
								max={ 60 }
								value={ settings?.ms_ads_skip_after ?? 5 }
								onChange={ ( val ) =>
									updateSetting(
										'ms_ads_skip_after',
										Math.max( 0, Math.min( 60, parseInt( val, 10 ) || 0 ) )
									)
								}
								__nextHasNoMarginBottom
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

			{ settings?.ms_connected_platforms && settings.ms_connected_platforms.length > 0 && (
				<SectionCard
					icon="upload"
					title={ __( 'Upload & Storage', 'mediashield' ) }
					description={ __( 'Choose where new videos are stored.', 'mediashield' ) }
				>
					<SelectControl
						label={ __( 'Default Upload Target', 'mediashield' ) }
						help={ __( 'Where new uploads go when you do not pick a platform yourself. "Auto" uses the first connected cloud platform.', 'mediashield' ) }
						value={ settings?.ms_default_upload_target ?? 'auto' }
						options={ [
							{ label: __( 'Auto (use connected platform)', 'mediashield' ), value: 'auto' },
							{ label: __( 'Self-hosted (local server)', 'mediashield' ), value: 'self' },
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

			{ lmsConfig.isLMSActive && settings?.ms_lms_auto_complete !== undefined && (
				<SectionCard
					icon="welcome-learn-more"
					title={ __( 'LMS Integration', 'mediashield' ) }
					description={ lmsConfig.lmsLabel
						? __( 'Settings for', 'mediashield' ) + ' ' + lmsConfig.lmsLabel + ' ' + __( 'integration.', 'mediashield' )
						: __( 'Automatically complete lessons when students finish watching videos.', 'mediashield' )
					}
				>
					<ToggleControl
						label={ __( 'Auto-complete lessons', 'mediashield' ) }
						help={ __( 'Mark the associated LMS lesson as complete when a student finishes the video.', 'mediashield' ) }
						checked={ !! settings?.ms_lms_auto_complete }
						onChange={ ( val ) => updateSetting( 'ms_lms_auto_complete', val ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Require enrollment', 'mediashield' ) }
						help={ __( 'Master switch for enrollment checks. When on, each video\'s own "Require enrollment" setting decides. Turn off to disable enrollment checks everywhere.', 'mediashield' ) }
						checked={ !! settings?.ms_lms_enrollment_check }
						onChange={ ( val ) => updateSetting( 'ms_lms_enrollment_check', val ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Global completion %', 'mediashield' ) }
						help={ __( 'Minimum video watch percentage required to trigger lesson completion.', 'mediashield' ) }
						value={ settings?.ms_lms_complete_pct ?? 100 }
						options={ LMS_PCT_OPTIONS }
						onChange={ ( val ) => updateSetting( 'ms_lms_complete_pct', parseInt( val, 10 ) ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</SectionCard>
			) }

			{ ! config.isProActive && (
				<div className="ms-upsell-section">
					<div className="ms-upsell-section__header">
						<Icon name="lock" />
						<div>
							<strong>{ __( 'Pro Features', 'mediashield' ) }</strong>
							<span>{ __( 'Available with MediaShield Pro', 'mediashield' ) }</span>
						</div>
					</div>
					<div className="ms-upsell-section__features">
						<div className="ms-upsell-feature">
							<strong>{ __( 'Advanced Watermark', 'mediashield' ) }</strong> — { __( '7 configurable fields (username, email, IP, user ID, timestamp, site name, custom text)', 'mediashield' ) }
						</div>
						<div className="ms-upsell-feature">
							<strong>{ __( 'Email Gate', 'mediashield' ) }</strong> — { __( 'Capture emails before video access with webhook integration for CRMs', 'mediashield' ) }
						</div>
						<div className="ms-upsell-feature">
							<strong>{ __( 'DRM Encryption', 'mediashield' ) }</strong> — { __( 'Widevine ClearKey via Bunny Stream or Shaka Packager', 'mediashield' ) }
						</div>
						<div className="ms-upsell-feature">
							<strong>{ __( 'LMS Integration', 'mediashield' ) }</strong> — { __( 'Auto-complete LearnDash, Tutor LMS, or LifterLMS lessons on video completion', 'mediashield' ) }
						</div>
						<div className="ms-upsell-feature">
							<strong>{ __( 'Platform Browsers', 'mediashield' ) }</strong> — { __( 'Browse & bulk import videos from Bunny, YouTube, Vimeo, Wistia', 'mediashield' ) }
						</div>
					</div>
					<a href="https://wbcomdesigns.com/downloads/mediashield-pro/" target="_blank" rel="noopener noreferrer" className="ms-upsell-section__btn">
						{ __( 'Get MediaShield Pro', 'mediashield' ) } &rarr;
					</a>
				</div>
			) }
		</div>
	);
};

export default Settings;
