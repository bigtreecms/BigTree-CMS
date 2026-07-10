import { Activity, Archive, History, ShieldCheck, UserCog, ArrowUpCircle } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { TileLinkGrid, type TileLink } from "@/components/ui/TileLinkGrid";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

const CARDS: TileLink[] = [
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
	<PageContainer width="wide">
		<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: "Debug" }]} />

		<PageHead
			title="Debug"
			sub="Diagnostics, security policy, backups, and the install's structural health."
		/>

		<DeveloperSectionNav />

		<TileLinkGrid items={CARDS} />
	</PageContainer>
);
