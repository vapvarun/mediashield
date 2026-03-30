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
					color: 'var(--ms-color-text-tertiary)',
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
					checked={ !! settings?.enabled }
					onChange={ ( val ) => updateSetting( 'enabled', val ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Default Protection Level', 'mediashield' ) }
					help={ __( 'Applied to new videos unless overridden per-video.', 'mediashield' ) }
					value={ settings?.protection_level || 'standard' }
					options={ PROTECTION_OPTIONS }
					onChange={ ( val ) => updateSetting( 'protection_level', val ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Require Login', 'mediashield' ) }
					help={ __( 'Only logged-in users can view protected videos.', 'mediashield' ) }
					checked={ !! settings?.require_login }
					onChange={ ( val ) => updateSetting( 'require_login', val ) }
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
					value={ settings?.watermark_opacity ?? 0.5 }
					onChange={ ( val ) => updateSetting( 'watermark_opacity', val ) }
					min={ 0 }
					max={ 1 }
					step={ 0.05 }
					__nextHasNoMarginBottom
				/>
				<div className="mediashield-settings__color-field">
					<label>{ __( 'Watermark Color', 'mediashield' ) }</label>
					<ColorPicker
						color={ settings?.watermark_color || '#ffffff' }
						onChange={ ( val ) => updateSetting( 'watermark_color', val ) }
						enableAlpha={ false }
					/>
				</div>
				<TextControl
					label={ __( 'Position Swap Interval', 'mediashield' ) }
					help={ __( 'Seconds between watermark position changes. 0 = static.', 'mediashield' ) }
					type="number"
					value={ settings?.watermark_swap_interval ?? 30 }
					onChange={ ( val ) =>
						updateSetting( 'watermark_swap_interval', parseInt( val, 10 ) || 0 )
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
					label={ __( 'Domain Whitelist', 'mediashield' ) }
					help={ __( 'One domain per line. Leave empty to allow all domains.', 'mediashield' ) }
					value={ settings?.allowed_domains || '' }
					onChange={ ( val ) => updateSetting( 'allowed_domains', val ) }
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
					value={ settings?.max_concurrent_streams ?? 1 }
					onChange={ ( val ) => updateSetting( 'max_concurrent_streams', val ) }
					min={ 1 }
					max={ 5 }
					step={ 1 }
					__nextHasNoMarginBottom
				/>
			</SectionCard>

			<SectionCard
				icon="upload"
				title={ __( 'Upload', 'mediashield' ) }
				description={ __( 'File upload limits for self-hosted videos.', 'mediashield' ) }
			>
				<TextControl
					label={ __( 'Max Upload Size (MB)', 'mediashield' ) }
					type="number"
					value={ settings?.max_upload_size ?? 500 }
					onChange={ ( val ) =>
						updateSetting( 'max_upload_size', parseInt( val, 10 ) || 0 )
					}
					min={ 1 }
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
					value={ settings?.custom_url_patterns || '' }
					onChange={ ( val ) => updateSetting( 'custom_url_patterns', val ) }
					rows={ 4 }
					__nextHasNoMarginBottom
				/>
			</SectionCard>
		</div>
	);
};

export default Settings;
