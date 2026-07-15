import * as Dialog from "@radix-ui/react-dialog";
import { ExternalLink, Menu, X } from "lucide-react";
import { NavLink } from "react-router-dom";
import { useState } from "react";

import { TABS } from "./TabNav";
import { DASHBOARD_ITEMS } from "./DashboardTab";
import { useAuthStore } from "@/auth/store";
import { IconTile } from "@/components/ui/IconTile";
import { isAdmin, LEVEL } from "@/lib/permissions";

interface MobileNavProps {
	siteName: string;
	wwwRoot: string;
}

const linkClass = (isActive: boolean) =>
	[
		"flex items-center gap-2.5 rounded-md px-3 py-2 text-[14px] font-medium transition-colors",
		isActive ? "bg-accent-soft text-accent" : "text-text-2 hover:bg-hover hover:text-text",
	].join(" ");

/**
 * Mobile navigation — a hamburger button (shown below `lg`, where the desktop
 * TabNav row is hidden) that opens a left-edge drawer mirroring the full nav:
 * the Dashboard sub-screens followed by the top-level tabs, each gated by the
 * user's level. Tapping any destination closes the drawer.
 */
export const MobileNav = ({ siteName, wwwRoot }: MobileNavProps) => {
	const [open, setOpen] = useState(false);
	const user = useAuthStore((s) => s.user);
	const userLevel = user?.level ?? LEVEL.NORMAL;
	const admin = isAdmin(user);

	const dashboardItems = DASHBOARD_ITEMS.filter((item) => !item.adminOnly || admin);
	const tabs = TABS.filter((tab) => tab.id !== "dashboard" && userLevel >= tab.level);

	const close = () => setOpen(false);

	return (
		<Dialog.Root open={open} onOpenChange={setOpen}>
			<Dialog.Trigger asChild>
				<button
					aria-label="Open navigation"
					className="grid size-[30px] cursor-pointer place-items-center rounded-md text-text-2 transition-colors hover:bg-hover hover:text-text lg:hidden"
					type="button"
				>
					<Menu size={18} />
				</button>
			</Dialog.Trigger>

			<Dialog.Portal>
				<Dialog.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-[1px] data-[state=open]:animate-in data-[state=open]:fade-in" />
				<Dialog.Content className="fixed inset-y-0 left-0 z-50 flex w-[min(300px,85vw)] flex-col border-r border-border bg-surface shadow-lg outline-none data-[state=open]:animate-in data-[state=open]:slide-in-from-left">
					<div className="flex items-center justify-between gap-3 border-b border-border bg-surface-2 px-4 py-3">
						<Dialog.Title className="flex items-center gap-2.5 text-[14px] font-semibold tracking-[-0.01em] text-text">
							<IconTile size="xs" tone="brand">
								<svg
									aria-hidden="true"
									fill="currentColor"
									height="14"
									viewBox="0 0 24 24"
									width="14"
								>
									<path d="M12 2 4 12h4v8h8v-8h4L12 2Z" />
								</svg>
							</IconTile>
							{siteName}
						</Dialog.Title>

						<Dialog.Close
							aria-label="Close navigation"
							className="rounded-md p-1 text-text-3 hover:bg-hover hover:text-text"
						>
							<X size={16} />
						</Dialog.Close>
					</div>

					<nav className="flex-1 overflow-y-auto p-2">
						<p className="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-[0.08em] text-text-3">
							Dashboard
						</p>
						{dashboardItems.map(({ label, to, icon: Icon }) => (
							<NavLink
								className={({ isActive }) => linkClass(isActive)}
								end={to === "/dashboard"}
								key={to}
								to={to}
								onClick={close}
							>
								<Icon className="shrink-0" size={16} />
								<span>{label}</span>
							</NavLink>
						))}

						<div className="my-2 h-px bg-border" />

						{tabs.map(({ id, label, path, icon: Icon }) => (
							<NavLink
								className={({ isActive }) => linkClass(isActive)}
								key={id}
								to={path}
								onClick={close}
							>
								<Icon className="shrink-0" size={16} />
								<span>{label}</span>
							</NavLink>
						))}
					</nav>

					<a
						className="flex items-center gap-2 border-t border-border px-4 py-3 text-[13px] text-text-2 transition-colors hover:bg-hover hover:text-text"
						href={wwwRoot}
						rel="noopener noreferrer"
						target="_blank"
						onClick={close}
					>
						<ExternalLink className="shrink-0" size={14} />
						<span>View site</span>
					</a>
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
