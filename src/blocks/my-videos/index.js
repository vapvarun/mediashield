/**
 * MediaShield My Videos Block — Registration.
 *
 * Dynamic block (server-rendered via render.php). Without this client-side
 * `registerBlockType()` call, Gutenberg shows the block as "support missing"
 * even though the PHP side registered it correctly. See UX sweep finding
 * 2026-05-27.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	edit: Edit,
	// No save — dynamic block rendered via render.php.
	save: () => null,
} );
