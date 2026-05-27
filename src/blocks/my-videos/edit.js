/**
 * MediaShield My Videos Block — Editor preview.
 *
 * The block has no attributes (it shows the current viewer's watched
 * videos), so the editor renders a static placeholder card explaining
 * what will appear on the frontend for a logged-in viewer.
 *
 * @package MediaShield
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'ms-block-placeholder ms-block-placeholder--my-videos',
		style: {
			background: '#f6f7f7',
			border: '1px dashed #c3c4c7',
			borderRadius: '8px',
			padding: '32px 24px',
			textAlign: 'center',
			color: '#50575e',
		},
	} );

	return (
		<div { ...blockProps }>
			<div
				style={ {
					width: 36,
					height: 36,
					margin: '0 auto 12px',
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'center',
					borderRadius: 8,
					background: '#eef1fd',
					color: '#3858e9',
				} }
				aria-hidden="true"
			>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
					<path d="m12 8-9.04 9.06a2.82 2.82 0 1 0 3.98 3.98L16 12" />
					<circle cx="17" cy="7" r="5" />
				</svg>
			</div>
			<div style={ { fontWeight: 600, color: '#1d2327', marginBottom: 4 } }>
				{ __( 'My Videos', 'mediashield' ) }
			</div>
			<div style={ { fontSize: 13, lineHeight: 1.5, maxWidth: 420, margin: '0 auto' } }>
				{ __( 'On the frontend, this shows the signed-in viewer their watched videos with progress + resume links. Logged-out visitors see nothing.', 'mediashield' ) }
			</div>
		</div>
	);
}
