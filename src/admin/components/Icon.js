/**
 * Icon — renders a Lucide icon by its (legacy Dashicons-derived) name.
 *
 * Lets us migrate the admin SPA off Dashicons by swapping
 * `<span className="dashicons dashicons-x" />` for `<Icon name="x" />` while
 * keeping the same name tokens (incl. the dynamic sidebar route icons).
 * Stroke 1.75 per the ux-foundation icon spec. Lucide has no brand icons, so
 * platform brands map to generic equivalents.
 */
import {
	LayoutDashboard,
	Film,
	ListVideo,
	Users,
	Tag,
	Flag,
	Settings,
	Video,
	Shield,
	CircleCheck,
	Check,
	ArrowLeft,
	ArrowRight,
	Palette,
	AreaChart,
	Play,
	Download,
	Megaphone,
	Network,
	Lock,
	Cloud,
	AlertTriangle,
	MonitorPlay,
	Clipboard,
	CircleHelp,
} from 'lucide-react';

const MAP = {
	dashboard: LayoutDashboard,
	'format-video': Film,
	'playlist-audio': ListVideo,
	'playlist-video': ListVideo,
	groups: Users,
	tag: Tag,
	flag: Flag,
	'admin-generic': Settings,
	'video-alt3': Video,
	'video-alt': Film,
	shield: Shield,
	'yes-alt': CircleCheck,
	yes: Check,
	'arrow-left-alt2': ArrowLeft,
	'arrow-right-alt2': ArrowRight,
	art: Palette,
	'chart-area': AreaChart,
	'controls-play': Play,
	download: Download,
	megaphone: Megaphone,
	networking: Network,
	lock: Lock,
	cloud: Cloud,
	warning: AlertTriangle,
	youtube: MonitorPlay,
	clipboard: Clipboard,
	'admin-page': Clipboard,
};

const Icon = ( { name, size = 20, className = '', ...rest } ) => {
	const Cmp = MAP[ name ] || CircleHelp;
	return (
		<Cmp
			size={ size }
			strokeWidth={ 1.75 }
			aria-hidden="true"
			focusable="false"
			className={ `ms-icon ${ className }`.trim() }
			{ ...rest }
		/>
	);
};

export default Icon;
