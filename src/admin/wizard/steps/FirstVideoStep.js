/**
 * Wizard Step 3: Protect your first video.
 *
 * The link is checked before anything is saved: in the browser
 * (isPlayableLink) and again by the server, which refuses a link it cannot
 * play (Platforms::recognise(), Basecamp 10545260436). On success the video
 * is shown as it now is in the library: title, platform, length, level.
 */
import { __, sprintf } from '@wordpress/i18n';
import { TextControl, Button, Spinner } from '@wordpress/components';
import { useState, useRef, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { decodeEntities } from '@wordpress/html-entities';
import { detectPlatform, extractVideoId, isPlayableLink } from '../../utils/platform';
import { protectionLevels, defaultLevel } from '../../utils/protection';
import { formatDuration } from '../../utils/time';
import Icon from '../../components/Icon';

const config = window.mediashieldAdmin || {};

const PLATFORM_NAMES = {
	youtube: 'YouTube',
	vimeo: 'Vimeo',
	wistia: 'Wistia',
	bunny: 'Bunny Stream',
	self: __( 'Video file link', 'mediashield' ),
};

export default function FirstVideoStep( { hosts = [], created = null, setCreated = () => {}, setBeforeContinue = () => {} } ) {
	const [ url, setUrl ] = useState( '' );
	const [ creating, setCreating ] = useState( false );
	const [ failed, setFailed ] = useState( '' );

	const refusal = config.unplayableMessage || __( 'This link is not a video MediaShield can play. Use a YouTube, Vimeo or Wistia link, a link to an .mp4 file, or upload a file.', 'mediashield' );

	// Adds the pasted link; true once the video exists.
	const handleCreate = async () => {
		const link = url.trim();
		if ( ! link ) return false;

		setFailed( '' );
		if ( ! isPlayableLink( link ) ) {
			setFailed( refusal );
			return false;
		}

		setCreating( true );
		const platform = detectPlatform( link );
		const platformVideoId = extractVideoId( link, platform );

		try {
			const video = await apiFetch( {
				path: '/wp/v2/mediashield-videos',
				method: 'POST',
				data: {
					// Never the platform ID: the server gives the video the platform's
					// title or "Video 123", and a slug to match.
					title: platformVideoId ? '' : link.substring( 0, 60 ),
					status: 'publish',
					meta: {
						_ms_platform: platform,
						_ms_platform_video_id: platformVideoId,
						// No protection level: the video follows the site default.
						_ms_source_url: link,
					},
				},
			} );

			setCreated( {
				id: video.id,
				title: decodeEntities( video.title?.raw ?? video.title?.rendered ?? '' ),
				platform: video.meta?._ms_platform || platform,
				duration: video.meta?._ms_duration || 0,
			} );
			setCreating( false );
			return true;
		} catch ( err ) {
			setFailed( window.mediashieldRequest.message( err, __( 'The video could not be added. Try again.', 'mediashield' ) ) );
		}

		setCreating( false );
		return false;
	};

	// Save & Continue adds a pasted link first (Basecamp MSUX-01): with a
	// link the step is done only when the video exists; a refused link keeps
	// the step open with the reason. An empty field moves on, not done.
	const latest = useRef( handleCreate );
	latest.current = handleCreate;
	useEffect( () => {
		setBeforeContinue( async () => {
			if ( created ) {
				return { proceed: true, done: true };
			}
			if ( ! url.trim() ) {
				return { proceed: true, done: false };
			}
			const ok = await latest.current();
			return { proceed: ok, done: ok };
		} );
	}, [ url, created ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const level = protectionLevels()[ defaultLevel() ];
	const named = hosts.filter( ( h ) => PLATFORM_NAMES[ h ] && 'self' !== h ).map( ( h ) => PLATFORM_NAMES[ h ] );
	const shortcode = created ? `[mediashield id="${ created.id }"]` : '';

	return (
		<div className="ms-wizard__step">
			<div className="ms-wizard__step-header">
				<Icon name="video-alt3" className="ms-wizard__step-icon" />
				<div>
					<h2>{ __( 'Protect your first video', 'mediashield' ) }</h2>
					<p>
						{ named.length
							? sprintf(
								/* translators: %s: platform names the owner picked, e.g. "YouTube, Vimeo". */
								__( 'Paste a %s link, or a link to an .mp4 file. You can skip this and add videos later.', 'mediashield' ),
								named.join( ', ' )
							)
							: config.wizardLinkHint || __( 'Paste a YouTube, Vimeo or Wistia link, or a link to an .mp4 file. You can skip this and add videos later.', 'mediashield' ) }
					</p>
				</div>
			</div>

			<div className="ms-wizard__step-fields">
				{ ! created ? (
					<div className="ms-wizard__url-input">
						<TextControl
							label={ __( 'Video link', 'mediashield' ) }
							value={ url }
							onChange={ ( v ) => {
								setUrl( v );
								setFailed( '' );
							} }
							placeholder="https://www.youtube.com/watch?v=..."
							type="url"
							aria-invalid={ failed ? 'true' : undefined }
							aria-describedby={ failed ? 'ms-wizard-link-error' : undefined }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<Button
							variant="secondary"
							onClick={ handleCreate }
							disabled={ creating || ! url.trim() }
							className="ms-wizard__btn--protect"
							__next40pxDefaultSize
						>
							{ creating ? <Spinner /> : __( 'Check and add', 'mediashield' ) }
						</Button>
						{ failed && (
							<p className="ms-wizard__error" id="ms-wizard-link-error" role="alert">{ failed }</p>
						) }
						{ hosts.includes( 'self' ) && (
							<p className="ms-wizard__hint">
								<a href={ `${ config.adminUrl }post-new.php?post_type=mediashield_video` }>{ __( 'Upload a file instead', 'mediashield' ) }</a>
							</p>
						) }
					</div>
				) : (
					<div className="ms-wizard__success-banner" role="status">
						<Icon name="yes-alt" />
						<div>
							<strong>{ created.title || __( '(no title)', 'mediashield' ) }</strong>
							<span>
								{ [
									PLATFORM_NAMES[ created.platform ] || created.platform,
									created.duration ? formatDuration( created.duration ) : null,
									level ? sprintf( /* translators: %s: protection level, e.g. Standard. */ __( '%s protection', 'mediashield' ), level.label ) : null,
									__( 'Ready', 'mediashield' ),
								].filter( Boolean ).join( ' · ' ) }
							</span>
							<span className="ms-wizard__success-next">
								{ sprintf(
									/* translators: %s: the video's shortcode. */
									__( 'Paste %s on any page, or use the MediaShield Video block.', 'mediashield' ),
									shortcode
								) }
							</span>
						</div>
					</div>
				) }
			</div>
		</div>
	);
}
