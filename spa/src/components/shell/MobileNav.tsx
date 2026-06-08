import * as Dialog from "@radix-ui/react-dialog";
import { ExternalLink, Menu, X } from "lucide-react";
import { NavLink } from "react-router-dom";
import { useState } from "react";

import { TABS } from "./TabNav";
import { DASHBOARD_ITEMS } from "./DashboardTab";
import { useAuthStore } from "@/auth/store";
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
					type="button"
					aria-label="Open navigation"
					className="grid h-[30px] w-[30px] cursor-pointer place-items-center rounded-md text-text-2 transition-colors hover:bg-hover hover:text-text lg:hidden"
				>
					<Menu size={18} />
				</button>
			</Dialog.Trigger>

			<Dialog.Portal>
				<Dialog.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-[1px] data-[state=open]:animate-in data-[state=open]:fade-in" />
				<Dialog.Content className="fixed inset-y-0 left-0 z-50 flex w-[min(300px,85vw)] flex-col border-r border-border bg-surface shadow-lg outline-none data-[state=open]:animate-in data-[state=open]:slide-in-from-left">
					<div className="flex items-center justify-between gap-3 border-b border-border bg-surface-2 px-4 py-3">
						<Dialog.Title className="flex items-center gap-2.5 text-[14px] font-semibold tracking-[-0.01em] text-text">
							<span className="grid h-[26px] w-[26px] place-items-center rounded-md bg-accent text-accent-fg">
								<svg
									width="14"
									height="14"
									viewBox="0 0 24 24"
									fill="currentColor"
									aria-hidden="true"
								>
									<path d="M12 2 4 12h4v8h8v-8h4L12 2Z" />
								</svg>
							</span>
							{siteName}
						</Dialog.Title>

						<Dialog.Close
							className="rounded-md p-1 text-text-3 hover:bg-hover hover:text-text"
							aria-label="Close navigation"
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
								key={to}
								to={to}
								end={to === "/dashboard"}
								onClick={close}
								className={({ isActive }) => linkClass(isActive)}
							>
								<Icon size={16} className="shrink-0" />
								<span>{label}</span>
							</NavLink>
						))}

						<div className="my-2 h-px bg-border" />

						{tabs.map(({ id, label, path, icon: Icon }) => (
							<NavLink
								key={id}
								to={path}
								onClick={close}
								className={({ isActive }) => linkClass(isActive)}
							>
								<Icon size={16} className="shrink-0" />
								<span>{label}</span>
							</NavLink>
						))}
					</nav>

					<a
						href={wwwRoot}
						target="_blank"
						rel="noopener noreferrer"
						onClick={close}
						className="flex items-center gap-2 border-t border-border px-4 py-3 text-[13px] text-text-2 transition-colors hover:bg-hover hover:text-text"
					>
						<ExternalLink size={14} className="shrink-0" />
						<span>View site</span>
					</a>
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
