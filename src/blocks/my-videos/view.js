/**
 * MediaShield My Videos Block — Frontend View.
 *
 * Handles client-side filtering (All / In Progress / Completed)
 * for the my-videos grid rendered by render.php.
 *
 * @package MediaShield
 */
(function () {
	'use strict';

	function init() {
		var containers = document.querySelectorAll( '.ms-my-videos' );
		containers.forEach( initFilter );
	}

	function initFilter( container ) {
		if ( container.dataset.msInit ) return;
		container.dataset.msInit = '1';

		var buttons = container.querySelectorAll( '.ms-my-videos-filter-btn' );
		var items = container.querySelectorAll( '.ms-my-videos-item' );

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var filter = btn.dataset.filter;

				// Update active button.
				buttons.forEach( function ( b ) { b.classList.remove( 'is-active' ); } );
				btn.classList.add( 'is-active' );

				// Filter items.
				items.forEach( function ( item ) {
					var pct = parseFloat( item.dataset.completionPct || '0' );

					if ( filter === 'all' ) {
						item.style.display = '';
					} else if ( filter === 'in-progress' ) {
						item.style.display = ( pct > 0 && pct < 100 ) ? '' : 'none';
					} else if ( filter === 'completed' ) {
						item.style.display = pct >= 100 ? '' : 'none';
					}
				} );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
