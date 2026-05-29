import { useQuery } from "@tanstack/react-query";
import { Link, useNavigate } from "react-router-dom";
import { api } from "@/api/client";
import { authApi } from "@/auth/endpoints";
import { messagesApi } from "@/api/endpoints/dashboard";
import * as DropdownMenu from "@radix-ui/react-dropdown-menu";
import { Bell, ChevronDown, ExternalLink, LogOut, Moon, Search, Sun, User } from "lucide-react";
import { useAuthStore } from "@/auth/store";

/**
 * Top bar — site title (from root page nav_title), View Site link, global search
 * trigger (opens ⌘K palette), theme toggle, notifications, and user menu.
 *
 * Stays sticky at the top with a hairline bottom border. Heights and spacing
 * come straight from the prototype (52px row).
 */

interface TopBarProps {
	dark: boolean;
	onToggleDark: () => void;
	onOpenSearch: () => void;
}

export const TopBar = ({ dark, onToggleDark, onOpenSearch }: TopBarProps) => {
	const navigate = useNavigate();
	const user = useAuthStore((s) => s.user);

	// Poll unread count every 60s (server stale threshold is generous). Skip
	// initial fetch dance on focus loss to avoid stacking refetches when the
	// user tabs back into a long-running session.
	const unreadQ = useQuery({
		queryKey: ["messages", "unread-count"],
		queryFn: () => messagesApi.unreadCount(),
		refetchInterval: 60_000,
		refetchOnWindowFocus: false,
	});
	const unread = unreadQ.data?.unread ?? 0;
	const initials = user?.name
		? user.name
				.split(/\s+/)
				.map((w) => w[0])
				.filter(Boolean)
				.slice(0, 2)
				.join("")
				.toUpperCase()
		: "?";

	const siteQ = useQuery({
		queryKey: ["system", "site"],
		queryFn: () => api.get<{ nav_title: string; www_root: string }>("/system/site"),
		staleTime: Infinity,
	});
	const siteName = siteQ.data?.nav_title ?? "BigTree";
	const wwwRoot = siteQ.data?.www_root || "/";

	return (
		<header className="sticky top-0 z-30 flex h-[52px] items-center gap-4 border-b border-border bg-surface px-5">
			{/* Brand */}
			<div className="flex items-center gap-2.5">
				<div className="grid h-[26px] w-[26px] place-items-center rounded-md bg-accent text-accent-fg">
					<svg
						width="14"
						height="14"
						viewBox="0 0 24 24"
						fill="currentColor"
						aria-hidden="true"
					>
						<path d="M12 2 4 12h4v8h8v-8h4L12 2Z" />
					</svg>
				</div>
				<span className="text-[14px] font-semibold tracking-[-0.01em]">{siteName}</span>
			</div>

			<div className="h-[22px] w-px bg-border" />

			<a
				href={wwwRoot}
				target="_blank"
				rel="noopener noreferrer"
				className="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 py-1 text-[12.5px] text-text-2 transition-colors hover:border-border-strong hover:bg-hover"
			>
				<ExternalLink size={13} />
				<span>View site</span>
			</a>

			<div className="flex-1" />

			<button
				type="button"
				onClick={onOpenSearch}
				className="flex w-60 cursor-text items-center gap-2 rounded-md border border-border bg-surface-2 px-2.5 py-1.5 text-[12.5px] text-text-3 transition-colors hover:border-border-strong"
			>
				<Search size={13} />
				<span>Search pages, modules…</span>
				<kbd className="ml-auto rounded border border-border bg-surface px-1.5 py-px font-mono text-[10px] text-text-3">
					⌘K
				</kbd>
			</button>

			<button
				type="button"
				onClick={onToggleDark}
				title={dark ? "Light mode" : "Dark mode"}
				className="grid h-[30px] w-[30px] cursor-pointer place-items-center rounded-md bg-transparent text-text-2 transition-colors hover:bg-hover hover:text-text"
			>
				{dark ? <Sun size={15} /> : <Moon size={15} />}
			</button>

			<button
				type="button"
				title={
					unread > 0 ? `${unread} unread message${unread === 1 ? "" : "s"}` : "Messages"
				}
				onClick={() => navigate("/messages")}
				className="relative grid h-[30px] w-[30px] cursor-pointer place-items-center rounded-md bg-transparent text-text-2 transition-colors hover:bg-hover hover:text-text"
			>
				<Bell size={15} />
				{unread > 0 && (
					<span className="absolute -right-0.5 -top-0.5 grid h-[14px] min-w-[14px] place-items-center rounded-full bg-danger px-1 text-[9px] font-bold text-white">
						{unread > 99 ? "99+" : unread}
					</span>
				)}
			</button>

			<DropdownMenu.Root>
				<DropdownMenu.Trigger asChild>
					<button
						type="button"
						title="Account"
						className="flex cursor-pointer items-center gap-2 rounded-md py-1 pl-1 pr-2 transition-colors hover:bg-hover"
					>
						<div className="grid h-6 w-6 place-items-center rounded bg-accent-soft text-[11px] font-semibold text-accent">
							{initials}
						</div>
						<span className="text-[13px] font-medium">
							{user?.name?.split(" ")[0] ?? "User"}
						</span>
						<ChevronDown size={12} className="text-text-3" />
					</button>
				</DropdownMenu.Trigger>
				<DropdownMenu.Portal>
					<DropdownMenu.Content
						className="min-w-56 rounded-md border border-border bg-surface p-1 shadow-md z-50"
						align="end"
						sideOffset={6}
					>
						<div className="px-3 py-2 border-b border-border">
							<div className="font-medium text-[13px]">{user?.name}</div>
							<div className="text-text-3 text-[12px] truncate">{user?.email}</div>
						</div>
						<DropdownMenu.Item asChild>
							<Link
								to="/profile"
								className="flex cursor-pointer select-none items-center gap-2 rounded px-3 py-1.5 text-[13px] text-text-2 outline-none transition-colors hover:bg-hover hover:text-text data-[highlighted]:bg-hover data-[highlighted]:text-text"
							>
								<User size={14} />
								<span>Profile</span>
							</Link>
						</DropdownMenu.Item>
						<DropdownMenu.Item
							className="flex cursor-pointer select-none items-center gap-2 rounded px-3 py-1.5 text-[13px] text-text-2 outline-none transition-colors hover:bg-hover hover:text-text data-[highlighted]:bg-hover data-[highlighted]:text-text"
							onSelect={async () => {
								await authApi.logout();
							}}
						>
							<LogOut size={14} />
							<span>Log out</span>
						</DropdownMenu.Item>
					</DropdownMenu.Content>
				</DropdownMenu.Portal>
			</DropdownMenu.Root>
		</header>
	);
};
