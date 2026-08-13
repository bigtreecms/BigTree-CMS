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
	Atom,
	Award,
	CalendarDays,
	Camera,
	ClipboardList,
	Clock,
	CloudSun,
	Coffee,
	Coins,
	CreditCard,
	Dices,
	Facebook,
	FolderTree,
	HelpCircle,
	Key,
	LifeBuoy,
	Lock,
	Map as MapIcon,
	MapPin,
	MessageSquare,
	Monitor,
	Orbit,
	Package,
	Receipt,
	Rss,
	Search,
	Share2,
	ShoppingCart,
	Shovel,
	Target,
	Ticket,
	TrafficCone,
	Trophy,
	Twitter,
	Video,
} from "lucide-react";

/**
 * Maps a BigTree legacy icon slug onto a Lucide component. The slugs come from
 * the original sprite-sheet keys in `core/admin/css/main.less` (icon_small_*).
 * Both the module `icon` (rendered on the Modules landing) and the module
 * action `class` glyph (rendered in the module sub-nav) draw from this same
 * vocabulary, so the map serves both. Anything unmapped falls back to <Box />.
 *
 * This map is presentation only — it turns a slug into a glyph. The *vocabulary*
 * (which slugs the picker offers) is not hardcoded here anymore: it is served by
 * `GET /module-icons` from the one PHP source (`BigTree\Api\ModuleIcons`) and
 * fetched via {@link useModuleIcons}. That is why there is no `MODULE_ICON_SLUGS`
 * export — the SPA no longer keeps its own copy of the list to drift from core.
 * Every slug the endpoint can return must have a key here, or it renders as
 * <Box />; the AIFieldTypeDomain guard asserts this map covers the whole
 * vocabulary.
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
	// Remaining BigTreeAdmin::$IconClasses glyphs (approximated with Lucide).
	token: Coins,
	export: Share2,
	help: HelpCircle,
	question: HelpCircle,
	clock: Clock,
	key: Key,
	lock_key: Lock,
	search: Search,
	computer: Monitor,
	events: CalendarDays,
	blog: Rss,
	form: ClipboardList,
	category: FolderTree,
	map: MapIcon,
	pin: MapPin,
	sports: Trophy,
	credit_card: CreditCard,
	cart: ShoppingCart,
	cash_register: Receipt,
	comments: MessageSquare,
	weather: CloudSun,
	planet: Orbit,
	mug: Coffee,
	atom: Atom,
	shovel: Shovel,
	cone: TrafficCone,
	lifesaver: LifeBuoy,
	target: Target,
	ribbon: Award,
	dice: Dices,
	ticket: Ticket,
	pallet: Package,
	camera: Camera,
	video: Video,
	twitter: Twitter,
	facebook: Facebook,
};

export const iconFor = (slug: string | undefined): LucideIcon => {
	if (!slug) {
		return Box;
	}

	return LEGACY_ICON_MAP[slug.toLowerCase()] ?? Box;
};

/**
 * Same lookup as {@link iconFor}, but `undefined` when the slug is unknown
 * instead of falling back to `<Box />`. Used by view-action icon resolution
 * so an unmapped `icon_*` class can keep searching rather than rendering a
 * generic box.
 */
export const knownIconFor = (slug: string | undefined): LucideIcon | undefined => {
	if (!slug) {
		return undefined;
	}

	return LEGACY_ICON_MAP[slug.toLowerCase()];
};
