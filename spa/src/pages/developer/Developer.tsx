import {
	BookTemplate,
	FileText,
	Folder,
	Layers,
	Layout,
	Megaphone,
	Package,
	Rss,
	Settings as SettingsIcon,
	Wrench,
} from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { TileLinkGrid, type TileLink } from "@/components/ui/TileLinkGrid";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

/**
 * /developer landing — the Create group's index page. Surfaces a card per
 * JSONDB resource we expose CRUD for. The Configure and Debug groups are
 * Phase 8 follow-ups; they'll get their own index pages under their own
 * routes when those land.
 */

const SUBJECTS: TileLink[] = [
	{
		to: "/developer/templates",
		icon: <Layout size={16} />,
		title: "Templates",
		description: "Page templates and their content resources.",
	},
	{
		to: "/developer/modules",
		icon: <Layers size={16} />,
		title: "Modules",
		description: "Module designer — forms, views, reports, embedded forms.",
	},
	{
		to: "/developer/module-groups",
		icon: <Folder size={16} />,
		title: "Module groups",
		description: "Group modules together on the Modules tab.",
	},
	{
		to: "/developer/callouts",
		icon: <Megaphone size={16} />,
		title: "Callouts",
		description: "Reusable content blocks the Callouts field draws from.",
	},
	{
		to: "/developer/callout-groups",
		icon: <BookTemplate size={16} />,
		title: "Callout groups",
		description: "Bundle callouts so a field can restrict which types are pickable.",
	},
	{
		to: "/developer/field-types",
		icon: <Wrench size={16} />,
		title: "Field types",
		description: "Custom form field types (alongside the built-ins).",
	},
	{
		to: "/developer/feeds",
		icon: <Rss size={16} />,
		title: "Feeds",
		description: "Public RSS / JSON / XML endpoints driven from module tables.",
	},
	{
		to: "/developer/settings",
		icon: <SettingsIcon size={16} />,
		title: "Settings",
		description: "Define new settings (the user-facing edit screen is under /settings).",
	},
	{
		to: "/developer/extensions",
		icon: <Package size={16} />,
		title: "Extensions",
		description: "Build, install, upgrade, and uninstall extension packages.",
	},
];

export const Developer = () => (
	<PageContainer width="wide">
		<Breadcrumb items={[{ label: "Developer" }]} />

		<PageHead
			sub="Create + configure the structural pieces editors use day-to-day."
			title="Developer"
		/>

		<DeveloperSectionNav />

		<TileLinkGrid items={SUBJECTS} />

		<p className="mt-6 flex items-center gap-2 text-[12px] text-text-3">
			<FileText size={12} />
			Configure third-party integrations and inspect system health from the Configure and
			Debug tabs above.
		</p>
	</PageContainer>
);
