/**
 * MediaShield Video Block — Registration.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	edit: Edit,
	// No save — dynamic block rendered via render.php.
	save: () => null,
} );
