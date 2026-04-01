/**
 * MediaShield Setup Wizard — 4-step onboarding flow.
 *
 * Each step auto-saves to /settings. Finish sets ms_wizard_completed.
 *
 * @package MediaShield
 */
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import GeneralStep from './steps/GeneralStep';
import WatermarkStep from './steps/WatermarkStep';
import PlatformStep from './steps/PlatformStep';
import FirstVideoStep from './steps/FirstVideoStep';

const config = window.mediashieldAdmin || {};

const STEPS = [
	{ key: 'general', label: __( 'General', 'mediashield' ), Component: GeneralStep },
	{ key: 'platform', label: __( 'Platform', 'mediashield' ), Component: PlatformStep },
	{ key: 'first-video', label: __( 'First Video', 'mediashield' ), Component: FirstVideoStep },
	{ key: 'watermark', label: __( 'Watermark', 'mediashield' ), Component: WatermarkStep },
];

export default function Wizard() {
	const [ currentStep, setCurrentStep ] = useState( 0 );
	const [ saving, setSaving ] = useState( false );
	const [ initialSettings, setInitialSettings ] = useState( null );
	const [ loadingSettings, setLoadingSettings ] = useState( true );

	const { createErrorNotice } = useDispatch( noticesStore );

	// Fetch existing settings on mount so steps can pre-fill.
	useEffect( () => {
		apiFetch( {
			url: config.restUrl + 'settings',
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => {
				setInitialSettings( res );
			} )
			.catch( () => {
				// Non-blocking — steps will use their own defaults.
				setInitialSettings( {} );
			} )
			.finally( () => {
				setLoadingSettings( false );
			} );
	}, [] );

	const step = STEPS[ currentStep ];
	const isFirst = currentStep === 0;
	const isLast = currentStep === STEPS.length - 1;

	const saveSettings = async ( data ) => {
		setSaving( true );
		try {
			await apiFetch( {
				url: config.restUrl + 'settings',
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				data,
			} );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( 'MediaShield wizard: save failed', err );
			createErrorNotice(
				err.message || __( 'Failed to save settings. Please try again.', 'mediashield' ),
				{ type: 'snackbar' }
			);
		}
		setSaving( false );
	};

	const handleNext = () => {
		if ( isLast ) {
			handleFinish();
		} else {
			setCurrentStep( currentStep + 1 );
		}
	};

	const handleBack = () => {
		if ( ! isFirst ) {
			setCurrentStep( currentStep - 1 );
		}
	};

	const handleSkip = () => {
		if ( isLast ) {
			handleFinish();
		} else {
			setCurrentStep( currentStep + 1 );
		}
	};

	const handleFinish = async () => {
		setSaving( true );
		try {
			await apiFetch( {
				url: config.restUrl + 'wizard/complete',
				method: 'POST',
				headers: { 'X-WP-Nonce': config.nonce },
			} );
			// Redirect to admin dashboard.
			window.location.href = config.adminUrl + 'admin.php?page=mediashield#/dashboard';
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( 'MediaShield wizard: finish failed', err );
			createErrorNotice(
				err.message || __( 'Failed to complete the wizard. Please try again.', 'mediashield' ),
				{ type: 'snackbar' }
			);
			setSaving( false );
		}
	};

	if ( loadingSettings ) {
		return (
			<div className="mediashield-wizard__container">
				<div className="mediashield-loader">
					<Spinner />
					<span className="mediashield-loader__text">
						{ __( 'Loading wizard...', 'mediashield' ) }
					</span>
				</div>
			</div>
		);
	}

	const StepComponent = step.Component;

	return (
		<div className="mediashield-wizard__container">
			<div className="mediashield-wizard__header">
				<h1>{ __( 'Welcome to MediaShield', 'mediashield' ) }</h1>
				<p>{ __( 'Let\'s set up video protection for your site.', 'mediashield' ) }</p>
			</div>

			<div className="mediashield-wizard__progress">
				{ STEPS.map( ( s, idx ) => (
					<div
						key={ s.key }
						className={
							'mediashield-wizard__step-indicator' +
							( idx === currentStep ? ' is-current' : '' ) +
							( idx < currentStep ? ' is-complete' : '' )
						}
					>
						<span className="mediashield-wizard__step-num">{ idx + 1 }</span>
						<span className="mediashield-wizard__step-label">{ s.label }</span>
					</div>
				) ) }
			</div>

			<div className="mediashield-wizard__content">
				<StepComponent onSave={ saveSettings } saving={ saving } initialData={ initialSettings } />
			</div>

			<div className="mediashield-wizard__actions">
				{ ! isFirst && (
					<Button variant="tertiary" onClick={ handleBack } disabled={ saving }>
						{ __( 'Back', 'mediashield' ) }
					</Button>
				) }
				<Button variant="tertiary" onClick={ handleSkip } disabled={ saving }>
					{ __( 'Skip', 'mediashield' ) }
				</Button>
				<Button variant="primary" onClick={ handleNext } isBusy={ saving }>
					{ isLast ? __( 'Finish', 'mediashield' ) : __( 'Next', 'mediashield' ) }
				</Button>
			</div>
		</div>
	);
}
