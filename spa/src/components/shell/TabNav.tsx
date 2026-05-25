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

/** The 8 top-level navigation tabs from the prototype. Order matches the original. */
interface Tab {
	id: string;
	label: string;
	path: string;
	icon: LucideIcon;
}

export const TABS: Tab[] = [
	{ id: "dashboard", label: "Dashboard", path: "/dashboard", icon: LayoutDashboard },
	{ id: "pages", label: "Pages", path: "/pages", icon: FileText },
	{ id: "modules", label: "Modules", path: "/modules", icon: Boxes },
	{ id: "files", label: "Files", path: "/files", icon: Folder },
	{ id: "users", label: "Users", path: "/users", icon: Users },
	{ id: "settings", label: "Settings", path: "/settings", icon: SettingsIcon },
	{ id: "tags", label: "Tags", path: "/tags", icon: Tag },
	{ id: "developer", label: "Developer", path: "/developer", icon: Code2 },
];

/**
 * Tab navigation — sits under the TopBar. NavLink handles active state via
 * the `active` data attribute the prototype's CSS keys off of.
 */
export const TabNav = () => {
	return (
		<nav
			className="flex items-center gap-0.5 border-b border-border bg-surface px-4"
			role="tablist"
		>
			{TABS.map(({ id, label, path, icon: Icon }) => (
				<NavLink
					key={id}
					to={path}
					role="tab"
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
			))}
		</nav>
	);
};
