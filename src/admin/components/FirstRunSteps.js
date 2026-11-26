/**
 * The first-run steps: what a new owner does before MediaShield has
 * anything to show (Basecamp 10478806397, 10530930901).
 *
 * One list for the Dashboard's Get started card and the Analytics screens'
 * notice. Each step is done from the owner's own content (no sample data);
 * the state comes from AnalyticsController::first_run_state(). Once someone
 * has pressed play (has_view) the site has real data and neither shows.
 *
 * @package MediaShield
 */

import { __, sprintf } from '@wordpress/i18n';
import Icon from './Icon';

const config = window.mediashieldAdmin || {};

/**
 * The four steps, with where each is done.
 *
 * @param {Object} status first_run_state().
 * @return {Array} Steps.
 */
const steps = ( status ) => [
	{
		key: 'has_video',
		title: __( 'Add your first video', 'mediashield' ),
		body: config.linkHint || __( 'A YouTube, Vimeo or Wistia link, a link to an .mp4 file, or upload a file.', 'mediashield' ),
		href: config.newVideoUrl || '#/videos',
		cta: __( 'Add a video', 'mediashield' ),
	},
	{
		key: 'has_protected',
		title: __( 'Protect it', 'mediashield' ),
		body: __( 'Pick a protection level above None, for one video or as the site default.', 'mediashield' ),
		href: '#/settings/protection',
		cta: __( 'Choose protection', 'mediashield' ),
	},
	{
		key: 'has_embed',
		title: __( 'Place it on a page', 'mediashield' ),
		body: __( 'Add the MediaShield Video block to a page or post, or paste the video\'s shortcode from the Videos list, and publish it.', 'mediashield' ),
		href: config.newPageUrl || '#/videos',
		cta: __( 'Create a page', 'mediashield' ),
	},
	{
		key: 'has_view',
		title: __( 'Watch it as a member', 'mediashield' ),
		body: __( 'Open the page in a private window, log in as a member and press play. Viewings, completion and milestones start from here.', 'mediashield' ),
		href: status?.watch_url || '',
		cta: __( 'Open the page', 'mediashield' ),
		external: true,
	},
];

/**
 * Should the first-run state show? Only until the first viewing.
 *
 * @param {Object|undefined} status first_run_state().
 * @return {boolean} Whether to show it.
 */
export const showFirstRun = ( status ) => !! status && ! status.has_view;

/**
 * The checklist.
 *
 * @param {Object} props        Props.
 * @param {Object} props.status first_run_state().
 * @param {string} props.title  Heading.
 * @param {string} props.intro  Optional line under the heading.
 * @return {JSX.Element} Checklist.
 */
export default function FirstRunSteps( { status, title, intro = '' } ) {
	const list = steps( status );
	const done = list.filter( ( step ) => status?.[ step.key ] ).length;
	// The first step not done is the one to do next; later ones wait for it.
	const next = list.find( ( step ) => ! status?.[ step.key ] );
	return (
		<section className="mediashield-first-run" aria-labelledby="mediashield-first-run-title">
			<div className="mediashield-first-run__head">
				<h2 id="mediashield-first-run-title">{ title }</h2>
				<span className="mediashield-first-run__count">
					{ sprintf( /* translators: 1: steps done, 2: total steps. */ __( '%1$d of %2$d done', 'mediashield' ), done, list.length ) }
				</span>
			</div>
			{ intro && <p className="mediashield-first-run__intro">{ intro }</p> }
			<ol className="mediashield-first-run__steps">
				{ list.map( ( step, i ) => {
					const isDone = !! status?.[ step.key ];
					const showCta = ! isDone && step.href && step === next;
					return (
						<li key={ step.key } className={ 'mediashield-first-run__step' + ( isDone ? ' is-done' : '' ) }>
							<span className="mediashield-first-run__mark" aria-hidden="true">
								{ isDone ? <Icon name="yes" /> : i + 1 }
							</span>
							<div className="mediashield-first-run__text">
								<strong>
									{ step.title }
									{ isDone && <span className="screen-reader-text">{ ' ' + __( '(done)', 'mediashield' ) }</span> }
								</strong>
								<span>{ step.body }</span>
							</div>
							{ showCta && (
								<a
									href={ step.href }
									className="components-button is-secondary mediashield-first-run__cta"
									{ ...( step.external ? { target: '_blank', rel: 'noopener noreferrer' } : {} ) }
								>
									{ step.cta }
									{ step.external && <span className="screen-reader-text">{ ' ' + __( '(opens in a new tab)', 'mediashield' ) }</span> }
								</a>
							) }
						</li>
					);
				} ) }
			</ol>
		</section>
	);
}
