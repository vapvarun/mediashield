/**
 * MediaShield Admin – Toast / Snackbar Notices
 *
 * Uses @wordpress/notices store via @wordpress/data.
 * Auto-dismisses "success" notices after 3 seconds.
 *
 * @package MediaShield
 */

import { useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { SnackbarList } from '@wordpress/components';
import { store as noticesStore } from '@wordpress/notices';

const AUTO_DISMISS_MS = 3000;

const Toast = () => {
	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices(),
		[]
	);

	const { removeNotice } = useDispatch( noticesStore );

	// Auto-dismiss success notices after 3 s.
	useEffect( () => {
		const successNotices = notices.filter(
			( n ) => n.status === 'success' && n.type === 'snackbar'
		);

		const timers = successNotices.map( ( n ) =>
			setTimeout( () => removeNotice( n.id ), AUTO_DISMISS_MS )
		);

		return () => timers.forEach( clearTimeout );
	}, [ notices, removeNotice ] );

	const snackbarNotices = notices.filter( ( n ) => n.type === 'snackbar' );

	const handleRemove = useCallback(
		( id ) => removeNotice( id ),
		[ removeNotice ]
	);

	if ( ! snackbarNotices.length ) {
		return null;
	}

	return (
		<div className="mediashield-toast">
			<SnackbarList
				notices={ snackbarNotices }
				onRemove={ handleRemove }
			/>
		</div>
	);
};

export default Toast;
