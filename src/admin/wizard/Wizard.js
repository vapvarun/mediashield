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
			<div className="ms-wizard">
				<div className="ms-wizard__loader">
					<Spinner />
					<span>{ __( 'Loading wizard...', 'mediashield' ) }</span>
				</div>
			</div>
		);
	}

	const StepComponent = step.Component;

	return (
		<div className="ms-wizard">
			<div className="ms-wizard__header">
				<div className="ms-wizard__logo">
					<span className="dashicons dashicons-shield" />
				</div>
				<h1>{ __( 'Welcome to MediaShield', 'mediashield' ) }</h1>
				<p>{ __( 'Let\'s set up video protection for your site.', 'mediashield' ) }</p>
			</div>

			<div className="ms-wizard__stepper">
				{ STEPS.map( ( s, idx ) => {
					let state = '';
					if ( idx < currentStep ) {
						state = 'is-complete';
					} else if ( idx === currentStep ) {
						state = 'is-active';
					}
					return (
						<div key={ s.key } className={ `ms-wizard__stepper-item ${ state }` }>
							<div className="ms-wizard__stepper-circle">
								{ idx < currentStep ? (
									<span className="dashicons dashicons-yes-alt" />
								) : (
									<span>{ idx + 1 }</span>
								) }
							</div>
							<span className="ms-wizard__stepper-label">{ s.label }</span>
						</div>
					);
				} ) }
			</div>

			<div className="ms-wizard__card">
				<StepComponent onSave={ saveSettings } saving={ saving } initialData={ initialSettings } />
			</div>

			<div className="ms-wizard__actions">
				<div className="ms-wizard__actions-left">
					{ ! isFirst && (
						<Button
							className="ms-wizard__btn ms-wizard__btn--back"
							onClick={ handleBack }
							disabled={ saving }
						>
							<span className="dashicons dashicons-arrow-left-alt2" />
							{ __( 'Back', 'mediashield' ) }
						</Button>
					) }
				</div>
				<div className="ms-wizard__actions-right">
					<Button
						className="ms-wizard__btn ms-wizard__btn--skip"
						onClick={ handleSkip }
						disabled={ saving }
					>
						{ __( 'Skip this step', 'mediashield' ) }
					</Button>
					<Button
						className="ms-wizard__btn ms-wizard__btn--next"
						variant="primary"
						onClick={ handleNext }
						isBusy={ saving }
					>
						{ isLast ? __( 'Finish Setup', 'mediashield' ) : __( 'Save & Continue', 'mediashield' ) }
						{ ! isLast && <span className="dashicons dashicons-arrow-right-alt2" /> }
					</Button>
				</div>
			</div>

			<div className="ms-wizard__footer">
				<p>
					{ __( 'Step', 'mediashield' ) } { currentStep + 1 } { __( 'of', 'mediashield' ) } { STEPS.length }
					{ ' — ' }
					{ __( 'You can always change these settings later.', 'mediashield' ) }
				</p>
			</div>
		</div>
	);
}
