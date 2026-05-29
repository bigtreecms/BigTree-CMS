import { Link } from "react-router-dom";
import {
	BookTemplate,
	FileText,
	Folder,
	Layers,
	Layout,
	Megaphone,
	Rss,
	Settings as SettingsIcon,
	Wrench,
} from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

/**
 * /developer landing — the Create group's index page. Surfaces a card per
 * JSONDB resource we expose CRUD for. The Configure and Debug groups are
 * Phase 8 follow-ups; they'll get their own index pages under their own
 * routes when those land.
 */

interface SubjectCard {
	to: string;
	icon: React.ReactNode;
	title: string;
	description: string;
}

const SUBJECTS: SubjectCard[] = [
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
];

export const Developer = () => (
	<div className="mx-auto max-w-screen-2xl px-6 py-4">
		<Breadcrumb items={[{ label: "Developer" }]} />

		<PageHead
			title="Developer"
			sub="Create + configure the structural pieces editors use day-to-day."
		/>

		<DeveloperSectionNav />

		<div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
			{SUBJECTS.map((s) => (
				<Link
					key={s.to}
					to={s.to}
					className="group flex items-start gap-3 rounded-xl border border-border bg-surface p-4 transition-colors hover:border-accent-ring hover:bg-surface-2"
				>
					<span className="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-accent-soft text-accent">
						{s.icon}
					</span>
					<div className="min-w-0">
						<div className="text-[13.5px] font-semibold text-text group-hover:text-accent">
							{s.title}
						</div>
						<div className="mt-0.5 text-[12px] text-text-3">{s.description}</div>
					</div>
				</Link>
			))}
		</div>

		<p className="mt-6 flex items-center gap-2 text-[12px] text-text-3">
			<FileText size={12} />
			Configure third-party integrations and inspect system health from the Configure and
			Debug tabs above.
		</p>
	</div>
);
