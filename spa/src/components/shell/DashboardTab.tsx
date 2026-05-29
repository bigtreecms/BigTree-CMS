import { NavLink } from "react-router-dom";
import {
	Activity,
	Bell,
	ChevronDown,
	LayoutDashboard,
	type LucideIcon,
	Mail,
	ShieldCheck,
	Unlink,
} from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { isAdmin } from "@/lib/permissions";

interface DropdownItem {
	label: string;
	to: string;
	icon: LucideIcon;
	/** When true, only render for Administrator+ (legacy `level => 1`). */
	adminOnly?: boolean;
}

/**
 * The Dashboard tab — a NavLink to the overview that reveals a dropdown of the
 * dashboard's sub-screens on hover / keyboard focus. Mirrors the legacy
 * `_nav-tree.php` dashboard children (Pending Changes, Messages, Analytics,
 * 404 Report, Site Integrity); the last two are Administrator-gated.
 *
 * Hover/focus-within (rather than a click menu) keeps the Dashboard link itself
 * directly clickable, matching the prototype's `nav.dropdown` behaviour.
 */
const ITEMS: DropdownItem[] = [
	{ label: "Overview", to: "/dashboard", icon: LayoutDashboard },
	{ label: "Pending Changes", to: "/pending-changes", icon: Bell },
	{ label: "Messages", to: "/messages", icon: Mail },
	{ label: "Analytics", to: "/analytics", icon: Activity, adminOnly: true },
	{ label: "404 Report", to: "/dashboard/404s", icon: Unlink, adminOnly: true },
	{ label: "Site Integrity", to: "/dashboard/integrity", icon: ShieldCheck, adminOnly: true },
];

const tabClass = (isActive: boolean) =>
	[
		"flex items-center gap-1.5 px-3 py-2.5 text-[13px] font-medium transition-colors",
		"border-b-2 -mb-px",
		isActive ? "border-accent text-text" : "border-transparent text-text-3 hover:text-text",
	].join(" ");

export const DashboardTab = () => {
	const user = useAuthStore((s) => s.user);
	const admin = isAdmin(user);
	const items = ITEMS.filter((item) => !item.adminOnly || admin);

	return (
		<div className="group relative">
			<NavLink to="/dashboard" role="tab" className={({ isActive }) => tabClass(isActive)}>
				<LayoutDashboard size={14} />
				<span>Dashboard</span>
				<ChevronDown size={12} className="text-text-3" />
			</NavLink>

			<div
				className="invisible absolute left-0 top-full z-50 min-w-52 -translate-y-1 rounded-md border border-border bg-surface p-1 opacity-0 shadow-md transition-[opacity,transform] group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100"
				role="menu"
			>
				{items.map(({ label, to, icon: Icon }) => (
					<NavLink
						key={to}
						to={to}
						end={to === "/dashboard"}
						role="menuitem"
						className={({ isActive }) =>
							[
								"flex items-center gap-2 rounded px-2.5 py-1.5 text-[13px] transition-colors",
								isActive
									? "bg-accent-soft text-accent"
									: "text-text-2 hover:bg-hover hover:text-text",
							].join(" ")
						}
					>
						<Icon size={14} className="shrink-0" />
						<span>{label}</span>
					</NavLink>
				))}
			</div>
		</div>
	);
};
