import { useQuery } from "@tanstack/react-query";
import { Link, useNavigate } from "react-router-dom";
import { api } from "@/api/client";
import { authApi } from "@/auth/endpoints";
import { messagesApi } from "@/api/endpoints/dashboard";
import { pluralize } from "@/lib/number";
import { queryKeys } from "@/lib/queryKeys";
import * as DropdownMenu from "@radix-ui/react-dropdown-menu";
import {
	Bell,
	ChevronDown,
	ExternalLink,
	LogOut,
	Moon,
	Search,
	Sparkles,
	Sun,
	User,
} from "lucide-react";
import { useAuthStore } from "@/auth/store";
import { Avatar } from "@/components/ui/Avatar";
import { IconTile } from "@/components/ui/IconTile";
import { MobileNav } from "./MobileNav";

/**
 * Top bar — site title (from root page nav_title), View Site link, global search
 * trigger (opens ⌘K palette), theme toggle, notifications, and user menu.
 *
 * Stays sticky at the top with a hairline bottom border. Heights and spacing
 * come straight from the prototype (52px row).
 */

interface TopBarProps {
	/** Whether the AI assistant is enabled for this user (Configure → AI). */
	aiChatEnabled: boolean;
	dark: boolean;
	onOpenChat: () => void;
	onOpenSearch: () => void;
	onToggleDark: () => void;
}

export const TopBar = ({
	dark,
	onToggleDark,
	onOpenSearch,
	aiChatEnabled,
	onOpenChat,
}: TopBarProps) => {
	const navigate = useNavigate();
	const user = useAuthStore((s) => s.user);

	// Poll unread count every 60s (server stale threshold is generous). Skip
	// initial fetch dance on focus loss to avoid stacking refetches when the
	// user tabs back into a long-running session.
	const unreadQ = useQuery({
		queryKey: queryKeys.messages.unreadCount(),
		queryFn: () => messagesApi.unreadCount(),
		refetchInterval: 60_000,
		refetchOnWindowFocus: false,
	});
	const unread = unreadQ.data?.unread ?? 0;

	const siteQ = useQuery({
		queryKey: queryKeys.system.site(),
		queryFn: () => api.get<{ nav_title: string; www_root: string }>("/system/site"),
		staleTime: Infinity,
	});
	const siteName = siteQ.data?.nav_title ?? "BigTree";
	const wwwRoot = siteQ.data?.www_root || "/";

	return (
		<header className="sticky top-0 z-30 flex h-[52px] items-center gap-2 border-b border-border bg-surface px-3 sm:px-5 lg:gap-4">
			<MobileNav siteName={siteName} wwwRoot={wwwRoot} />

			{/* Brand */}
			<div className="flex shrink-0 items-center gap-2.5">
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
				<span className="text-[14px] font-semibold tracking-[-0.01em]">{siteName}</span>
			</div>

			<div className="hidden h-[22px] w-px bg-border sm:block" />

			<a
				className="hidden shrink-0 cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-md border border-border bg-surface px-2.5 py-1 text-[12.5px] text-text-2 transition-colors hover:border-border-strong hover:bg-hover sm:inline-flex"
				href={wwwRoot}
				rel="noopener noreferrer"
				target="_blank"
			>
				<ExternalLink size={13} />
				<span>View site</span>
			</a>

			<div className="flex-1" />

			<button
				className="hidden w-60 cursor-text items-center gap-2 rounded-md border border-border bg-surface-2 px-2.5 py-1.5 text-[12.5px] text-text-3 transition-colors hover:border-border-strong sm:flex"
				type="button"
				onClick={onOpenSearch}
			>
				<Search size={13} />
				<span>Search pages, modules…</span>
				<kbd className="ml-auto rounded border border-border bg-surface px-1.5 py-px font-mono text-[10px] text-text-3">
					⌘K
				</kbd>
			</button>

			<button
				aria-label="Search"
				className="grid size-[30px] cursor-pointer place-items-center rounded-md bg-transparent text-text-2 transition-colors hover:bg-hover hover:text-text sm:hidden"
				title="Search"
				type="button"
				onClick={onOpenSearch}
			>
				<Search size={15} />
			</button>

			{aiChatEnabled && (
				<button
					aria-label="AI assistant"
					className="grid size-[30px] cursor-pointer place-items-center rounded-md bg-transparent text-accent transition-colors hover:bg-accent/10"
					title="AI assistant"
					type="button"
					onClick={onOpenChat}
				>
					<Sparkles size={15} />
				</button>
			)}

			<button
				className="grid size-[30px] cursor-pointer place-items-center rounded-md bg-transparent text-text-2 transition-colors hover:bg-hover hover:text-text"
				title={dark ? "Light mode" : "Dark mode"}
				type="button"
				onClick={onToggleDark}
			>
				{dark ? <Sun size={15} /> : <Moon size={15} />}
			</button>

			<button
				className="relative grid size-[30px] cursor-pointer place-items-center rounded-md bg-transparent text-text-2 transition-colors hover:bg-hover hover:text-text"
				title={unread > 0 ? pluralize(unread, "unread message") : "Messages"}
				type="button"
				onClick={() => navigate("/messages")}
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
						className="flex cursor-pointer items-center gap-2 rounded-md py-1 pl-1 pr-2 transition-colors hover:bg-hover"
						title="Account"
						type="button"
					>
						<Avatar name={user?.name} size={24} />
						<span className="text-[13px] font-medium">
							{user?.name?.split(" ")[0] ?? "User"}
						</span>
						<ChevronDown className="text-text-3" size={12} />
					</button>
				</DropdownMenu.Trigger>
				<DropdownMenu.Portal>
					<DropdownMenu.Content
						align="end"
						className="min-w-56 rounded-md border border-border bg-surface p-1 shadow-md z-50"
						sideOffset={6}
					>
						<div className="px-3 py-2 border-b border-border">
							<div className="font-medium text-[13px]">{user?.name}</div>
							<div className="text-text-3 text-[12px] truncate">{user?.email}</div>
						</div>
						<DropdownMenu.Item asChild>
							<Link
								className="flex cursor-pointer select-none items-center gap-2 rounded px-3 py-1.5 text-[13px] text-text-2 outline-none transition-colors hover:bg-hover hover:text-text data-highlighted:bg-hover data-highlighted:text-text"
								to="/profile"
							>
								<User size={14} />
								<span>Profile</span>
							</Link>
						</DropdownMenu.Item>
						<DropdownMenu.Item
							className="flex cursor-pointer select-none items-center gap-2 rounded px-3 py-1.5 text-[13px] text-text-2 outline-none transition-colors hover:bg-hover hover:text-text data-highlighted:bg-hover data-highlighted:text-text"
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
