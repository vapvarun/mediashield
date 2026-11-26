/**
 * The loader shown while an admin screen's code arrives (React.lazy).
 *
 * @package MediaShield
 */

import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * @return {JSX.Element} Loader.
 */
export default function Loading() {
	return (
		<div className="mediashield-loader" role="status" aria-live="polite">
			<Spinner />
			<span className="mediashield-loader__text">{ __( 'Loading…', 'mediashield' ) }</span>
		</div>
	);
}
