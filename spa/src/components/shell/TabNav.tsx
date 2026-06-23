import { NavLink } from "react-router-dom";
import {
	Boxes,
	Code2,
	FileText,
	Folder,
	LayoutDashboard,
	type LucideIcon,
	Settings as SettingsIcon,
	Tag,
	Users,
} from "lucide-react";

import { DashboardTab } from "./DashboardTab";
import { useAuthStore } from "@/auth/store";
import { LEVEL } from "@/lib/permissions";

/** The 8 top-level navigation tabs from the prototype. Order matches the original. */
interface Tab {
	id: string;
	label: string;
	path: string;
	icon: LucideIcon;
	/** Minimum `user.level` required to see this tab. Mirrors `_nav-tree.php`. */
	level: number;
}

export const TABS: Tab[] = [
	{
		id: "dashboard",
		label: "Dashboard",
		path: "/dashboard",
		icon: LayoutDashboard,
		level: LEVEL.NORMAL,
	},
	{ id: "pages", label: "Pages", path: "/pages", icon: FileText, level: LEVEL.NORMAL },
	{ id: "modules", label: "Modules", path: "/modules", icon: Boxes, level: LEVEL.NORMAL },
	{ id: "files", label: "Files", path: "/files", icon: Folder, level: LEVEL.NORMAL },
	{ id: "users", label: "Users", path: "/users", icon: Users, level: LEVEL.ADMINISTRATOR },
	{
		id: "settings",
		label: "Settings",
		path: "/settings",
		icon: SettingsIcon,
		level: LEVEL.ADMINISTRATOR,
	},
	{ id: "tags", label: "Tags", path: "/tags", icon: Tag, level: LEVEL.ADMINISTRATOR },
	{
		id: "developer",
		label: "Developer",
		path: "/developer",
		icon: Code2,
		level: LEVEL.DEVELOPER,
	},
];

/**
 * Tab navigation — sits under the TopBar. NavLink handles active state via
 * the `active` data attribute the prototype's CSS keys off of.
 */
export const TabNav = () => {
	const userLevel = useAuthStore((s) => s.user?.level ?? LEVEL.NORMAL);
	const tabs = TABS.filter((tab) => userLevel >= tab.level);

	return (
		<nav className="hidden items-center gap-0.5 border-b border-border bg-surface px-4 lg:flex">
			{tabs.map(({ id, label, path, icon: Icon }) =>
				id === "dashboard" ? (
					<DashboardTab key={id} />
				) : (
					<NavLink
						key={id}
						to={path}
						className={({ isActive }) =>
							[
								"flex items-center gap-1.5 px-3 py-2.5 text-[13px] font-medium transition-colors",
								"border-b-2 -mb-px",
								isActive
									? "border-accent text-text"
									: "border-transparent text-text-3 hover:text-text",
							].join(" ")
						}
					>
						<Icon size={14} />
						<span>{label}</span>
					</NavLink>
				)
			)}
		</nav>
	);
};
