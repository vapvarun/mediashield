/**
 * Wizard Step 4: Watermark Configuration.
 */
import { __ } from '@wordpress/i18n';
import { RangeControl, ColorPicker, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

export default function WatermarkStep( { onSave, initialData } ) {
	const [ opacity, setOpacity ] = useState( initialData?.ms_watermark_opacity ?? 0.5 );
	const [ color, setColor ] = useState( initialData?.ms_watermark_color || '#ffffff' );
	const [ swapInterval, setSwapInterval ] = useState( initialData?.ms_watermark_swap_interval ?? 30 );

	return (
		<div className="mediashield-wizard__step">
			<h2>{ __( 'Watermark Settings', 'mediashield' ) }</h2>
			<p>{ __( 'Configure how the watermark appears on your videos. It shows the viewer\'s username and IP address.', 'mediashield' ) }</p>

			<RangeControl
				label={ __( 'Opacity', 'mediashield' ) }
				value={ opacity }
				onChange={ ( val ) => { setOpacity( val ); onSave( { ms_watermark_opacity: val } ); } }
				min={ 0.1 }
				max={ 1.0 }
				step={ 0.1 }
			/>

			<div style={ { marginBottom: '24px' } }>
				<p style={ { marginBottom: '8px', fontWeight: 600 } }>
					{ __( 'Text Color', 'mediashield' ) }
				</p>
				<ColorPicker
					color={ color }
					onChange={ ( val ) => { setColor( val ); onSave( { ms_watermark_color: val } ); } }
					enableAlpha={ false }
				/>
			</div>

			<TextControl
				label={ __( 'Position swap interval (seconds)', 'mediashield' ) }
				help={ __( 'How often the watermark moves to a new position.', 'mediashield' ) }
				type="number"
				value={ swapInterval }
				onChange={ ( val ) => { setSwapInterval( parseInt( val, 10 ) ); onSave( { ms_watermark_swap_interval: parseInt( val, 10 ) } ); } }
				min={ 5 }
				max={ 120 }
			/>

			<div className="mediashield-wizard__preview" style={ {
				background: '#000',
				borderRadius: '8px',
				padding: '40px',
				marginTop: '16px',
				position: 'relative',
				minHeight: '120px',
			} }>
				<span style={ {
					color: color,
					opacity: opacity,
					fontSize: '14px',
					fontFamily: 'sans-serif',
				} }>
					{ __( 'John Doe', 'mediashield' ) } &middot; 192.168.1.1
				</span>
			</div>
		</div>
	);
}
