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
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';

const config = window.mediashieldAdmin || {};
const DEBOUNCE_MS = 800;

const PROTECTION_OPTIONS = [
	{ label: __( 'None', 'mediashield' ), value: 'none' },
	{ label: __( 'Basic', 'mediashield' ), value: 'basic' },
	{ label: __( 'Standard', 'mediashield' ), value: 'standard' },
	{ label: __( 'Strict', 'mediashield' ), value: 'strict' },
];

const SectionCard = ( { icon, title, description, children } ) => (
	<div className="mediashield-settings__section">
		<div className="mediashield-settings__section-header">
			<div className="mediashield-settings__section-icon">
				<span className={ `dashicons dashicons-${ icon }` } />
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
					help={ __( 'Seconds between watermark position changes. 0 = static.', 'mediashield' ) }
					type="number"
					value={ settings?.ms_watermark_swap_interval ?? 30 }
					onChange={ ( val ) =>
						updateSetting( 'ms_watermark_swap_interval', parseInt( val, 10 ) || 0 )
					}
					min={ 0 }
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
					help={ __( 'One domain per line. Leave empty to allow all domains.', 'mediashield' ) }
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
				icon="upload"
				title={ __( 'Upload & Storage', 'mediashield' ) }
				description={ __( 'Configure where videos are stored and upload limits.', 'mediashield' ) }
			>
				{ settings?.ms_connected_platforms && settings.ms_connected_platforms.length > 0 && (
					<SelectControl
						label={ __( 'Default Upload Target', 'mediashield' ) }
						help={ __( 'When set to "Auto", new uploads go to the first connected cloud platform. No video files are stored locally when a cloud service is connected.', 'mediashield' ) }
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
					/>
				) }
				<TextControl
					label={ __( 'Max Upload Size (MB)', 'mediashield' ) }
					help={ __( 'Maximum file size for video uploads. Set to 0 for unlimited (server limit applies).', 'mediashield' ) }
					type="number"
					value={ settings?.ms_max_upload_size ?? 500 }
					onChange={ ( val ) =>
						updateSetting( 'ms_max_upload_size', parseInt( val, 10 ) || 0 )
					}
					min={ 0 }
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			<SectionCard
				icon="search"
				title={ __( 'Auto-Detection', 'mediashield' ) }
				description={ __( 'Custom URL patterns for wrapping third-party embeds.', 'mediashield' ) }
			>
				<TextareaControl
					label={ __( 'Custom URL Patterns', 'mediashield' ) }
					help={ __( 'One regex pattern per line. Matches iframe src attributes for auto-wrapping.', 'mediashield' ) }
					value={ settings?.ms_custom_url_patterns || '' }
					onChange={ ( val ) => updateSetting( 'ms_custom_url_patterns', val ) }
					rows={ 4 }
					__nextHasNoMarginBottom
				/>
			</SectionCard>
		</div>
	);
};

export default Settings;
