import {
	Activity,
	AlertTriangle,
	BarChart3,
	Box,
	Briefcase,
	Calendar,
	Copy,
	CornerUpRight,
	Database,
	Download,
	Eye,
	EyeOff,
	FileText,
	Folder,
	Globe,
	Image as ImageIcon,
	Layers,
	List,
	type LucideIcon,
	Mail,
	Network,
	Newspaper,
	Pencil,
	Plus,
	RefreshCw,
	Server,
	Settings as SettingsIcon,
	Tag as TagIcon,
	Trash2,
	Truck,
	Upload,
	Users as UsersIcon,
} from "lucide-react";

/**
 * Maps a BigTree legacy icon slug onto a Lucide component. The slugs come from
 * the original sprite-sheet keys in `core/admin/css/main.less` (icon_small_*).
 * Both the module `icon` (rendered on the Modules landing) and the module
 * action `class` glyph (rendered in the module sub-nav) draw from this same
 * vocabulary, so the map serves both. Anything unmapped falls back to <Box />.
 */
const LEGACY_ICON_MAP: Record<string, LucideIcon> = {
	// Module / content icons
	news: Newspaper,
	newspaper: Newspaper,
	tags: TagIcon,
	tag: TagIcon,
	folder: Folder,
	file: FileText,
	page: FileText,
	calendar: Calendar,
	calendar2: Calendar,
	users: UsersIcon,
	user: UsersIcon,
	image: ImageIcon,
	picture: ImageIcon,
	mail: Mail,
	email: Mail,
	world: Globe,
	globe: Globe,
	server: Server,
	gear: SettingsIcon,
	setup: SettingsIcon,
	settings: SettingsIcon,
	truck: Truck,
	car: Truck,
	list: List,
	modules: Layers,
	database: Database,
	briefcase: Briefcase,
	business: Briefcase,
	activity: Activity,
	// Action glyphs (module sub-nav / nav_icon)
	add: Plus,
	edit: Pencil,
	delete: Trash2,
	refresh: RefreshCw,
	duplicate: Copy,
	view: Eye,
	error: AlertTriangle,
	ignored: EyeOff,
	redirect: CornerUpRight,
	up: Upload,
	down: Download,
	network: Network,
	bar_graph: BarChart3,
};

export const iconFor = (slug: string | undefined): LucideIcon => {
	if (!slug) {
		return Box;
	}

	return LEGACY_ICON_MAP[slug.toLowerCase()] ?? Box;
};
