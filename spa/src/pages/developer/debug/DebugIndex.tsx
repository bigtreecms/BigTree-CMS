import { Link } from "react-router-dom";
import { Activity, Archive, History, ShieldCheck, UserCog, ArrowUpCircle } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

interface DebugCard {
	to: string;
	icon: React.ReactNode;
	title: string;
	description: string;
}

const CARDS: DebugCard[] = [
	{
		to: "/developer/debug/status",
		icon: <Activity size={16} />,
		title: "Site Status",
		description: "Directory permissions, content warnings, and PHP server parameter checks.",
	},
	{
		to: "/developer/debug/security",
		icon: <ShieldCheck size={16} />,
		title: "Security policy",
		description: "Brute-force rules, password requirements, login bans, and IP restrictions.",
	},
	{
		to: "/developer/backups",
		icon: <Archive size={16} />,
		title: "Backups",
		description: "Generate, download, and prune on-demand SQL database backups.",
	},
	{
		to: "/developer/debug/emulator",
		icon: <UserCog size={16} />,
		title: "User emulator",
		description: "Assume another user's identity to debug permissions and visibility.",
	},
	{
		to: "/developer/debug/audit",
		icon: <History size={16} />,
		title: "Audit trail",
		description: "Browse the change history per user, table, and date range.",
	},
	{
		to: "/developer/debug/upgrade",
		icon: <ArrowUpCircle size={16} />,
		title: "System upgrade",
		description: "Check for new BigTree releases and run the core + database upgrade.",
	},
];

export const DebugIndex = () => (
	<div className="mx-auto max-w-screen-2xl px-6 py-4">
		<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: "Debug" }]} />

		<PageHead
			title="Debug"
			sub="Diagnostics, security policy, backups, and the install's structural health."
		/>

		<DeveloperSectionNav />

		<div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
			{CARDS.map((c) => (
				<Link
					key={c.to}
					to={c.to}
					className="group flex items-start gap-3 rounded-xl border border-border bg-surface p-4 transition-colors hover:border-accent-ring hover:bg-surface-2"
				>
					<span className="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-accent-soft text-accent">
						{c.icon}
					</span>
					<div className="min-w-0">
						<div className="text-[13.5px] font-semibold text-text group-hover:text-accent">
							{c.title}
						</div>
						<div className="mt-0.5 text-[12px] text-text-3">{c.description}</div>
					</div>
				</Link>
			))}
		</div>
	</div>
);
