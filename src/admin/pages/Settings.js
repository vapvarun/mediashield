/**
 * MediaShield Admin – Settings Page
 *
 * Auto-save on change with debounce. Fetches from mediashield/v1/settings.
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
	Card,
	CardBody,
	CardHeader,
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

const Settings = () => {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const debounceRef = useRef( null );

	const { createSuccessNotice, createErrorNotice } = useDispatch( noticesStore );

	// Fetch settings on mount.
	useEffect( () => {
		let cancelled = false;

		apiFetch( {
			url: `${ config.restUrl }settings`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => {
				if ( ! cancelled ) {
					setSettings( res );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( err.message || __( 'Failed to load settings.', 'mediashield' ) );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	/**
	 * Persist a single field change with debounce.
	 *
	 * @param {string} key   Setting key (dot-path supported by backend).
	 * @param {*}      value New value.
	 */
	const saveField = useCallback(
		( key, value ) => {
			if ( debounceRef.current ) {
				clearTimeout( debounceRef.current );
			}

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
						createSuccessNotice( __( 'Settings saved.', 'mediashield' ), {
							type: 'snackbar',
						} );
					} )
					.catch( ( err ) => {
						createErrorNotice(
							err.message || __( 'Failed to save settings.', 'mediashield' ),
							{ type: 'snackbar' }
						);
					} );
			}, DEBOUNCE_MS );
		},
		[ createSuccessNotice, createErrorNotice ]
	);

	/**
	 * Update local state and trigger auto-save.
	 *
	 * @param {string} key   Setting key.
	 * @param {*}      value New value.
	 */
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
			</header>

			{ /* ── General ─────────────────────────────── */ }
			<Card className="mediashield-settings__section">
				<CardHeader>
					<span className="mediashield-card__title">
						{ __( 'General', 'mediashield' ) }
					</span>
				</CardHeader>
				<CardBody>
					<ToggleControl
						label={ __( 'Enabled', 'mediashield' ) }
						checked={ !! settings?.enabled }
						onChange={ ( val ) => updateSetting( 'enabled', val ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Protection Level', 'mediashield' ) }
						value={ settings?.protection_level || 'standard' }
						options={ PROTECTION_OPTIONS }
						onChange={ ( val ) => updateSetting( 'protection_level', val ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Require Login', 'mediashield' ) }
						checked={ !! settings?.require_login }
						onChange={ ( val ) => updateSetting( 'require_login', val ) }
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			{ /* ── Watermark ───────────────────────────── */ }
			<Card className="mediashield-settings__section">
				<CardHeader>
					<span className="mediashield-card__title">
						{ __( 'Watermark', 'mediashield' ) }
					</span>
				</CardHeader>
				<CardBody>
					<RangeControl
						label={ __( 'Opacity', 'mediashield' ) }
						value={ settings?.watermark_opacity ?? 0.5 }
						onChange={ ( val ) => updateSetting( 'watermark_opacity', val ) }
						min={ 0 }
						max={ 1 }
						step={ 0.1 }
						__nextHasNoMarginBottom
					/>
					<div className="mediashield-settings__color-field">
						<label className="components-base-control__label">
							{ __( 'Watermark Color', 'mediashield' ) }
						</label>
						<ColorPicker
							color={ settings?.watermark_color || '#ffffff' }
							onChange={ ( val ) => updateSetting( 'watermark_color', val ) }
							enableAlpha={ false }
						/>
					</div>
					<TextControl
						label={ __( 'Swap Interval (seconds)', 'mediashield' ) }
						type="number"
						value={ settings?.watermark_swap_interval ?? 30 }
						onChange={ ( val ) =>
							updateSetting( 'watermark_swap_interval', parseInt( val, 10 ) || 0 )
						}
						min={ 0 }
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			{ /* ── Domains ─────────────────────────────── */ }
			<Card className="mediashield-settings__section">
				<CardHeader>
					<span className="mediashield-card__title">
						{ __( 'Allowed Domains', 'mediashield' ) }
					</span>
				</CardHeader>
				<CardBody>
					<TextareaControl
						label={ __( 'One domain per line', 'mediashield' ) }
						value={ settings?.allowed_domains || '' }
						onChange={ ( val ) => updateSetting( 'allowed_domains', val ) }
						rows={ 4 }
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			{ /* ── Streams ─────────────────────────────── */ }
			<Card className="mediashield-settings__section">
				<CardHeader>
					<span className="mediashield-card__title">
						{ __( 'Concurrent Streams', 'mediashield' ) }
					</span>
				</CardHeader>
				<CardBody>
					<RangeControl
						label={ __( 'Max Concurrent Streams', 'mediashield' ) }
						value={ settings?.max_concurrent_streams ?? 1 }
						onChange={ ( val ) => updateSetting( 'max_concurrent_streams', val ) }
						min={ 1 }
						max={ 5 }
						step={ 1 }
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			{ /* ── Upload ──────────────────────────────── */ }
			<Card className="mediashield-settings__section">
				<CardHeader>
					<span className="mediashield-card__title">
						{ __( 'Upload', 'mediashield' ) }
					</span>
				</CardHeader>
				<CardBody>
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
				</CardBody>
			</Card>

			{ /* ── Detection ───────────────────────────── */ }
			<Card className="mediashield-settings__section">
				<CardHeader>
					<span className="mediashield-card__title">
						{ __( 'Detection', 'mediashield' ) }
					</span>
				</CardHeader>
				<CardBody>
					<TextareaControl
						label={ __( 'Custom URL Patterns (one per line)', 'mediashield' ) }
						value={ settings?.custom_url_patterns || '' }
						onChange={ ( val ) => updateSetting( 'custom_url_patterns', val ) }
						rows={ 4 }
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>
		</div>
	);
};

export default Settings;
