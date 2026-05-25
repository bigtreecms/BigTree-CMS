import { Bell, ChevronDown, ChevronsUpDown, ExternalLink, Moon, Search, Sun } from "lucide-react";
import { useAuthStore } from "@/auth/store";

/**
 * Top bar — matches the prototype's `.topbar` layout:
 *   [logo] [site switcher] | [view site] ········ [search] [theme] [bell] [avatar]
 *
 * Stays sticky at the top with a hairline bottom border. Heights and spacing
 * come straight from the prototype (52px row).
 */

interface TopBarProps {
	siteName: string;
	dark: boolean;
	onToggleDark: () => void;
	onOpenSearch: () => void;
}

export const TopBar = ({ siteName, dark, onToggleDark, onOpenSearch }: TopBarProps) => {
	const user = useAuthStore((s) => s.user);
	const initials = user?.name
		? user.name
				.split(/\s+/)
				.map((w) => w[0])
				.filter(Boolean)
				.slice(0, 2)
				.join("")
				.toUpperCase()
		: "?";

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
				<button
					type="button"
					className="flex cursor-pointer items-center gap-1 rounded-md p-1 pr-2 transition-colors hover:bg-hover"
					title="Switch site"
				>
					<span className="text-[14px] font-semibold tracking-[-0.01em]">{siteName}</span>
					<ChevronsUpDown size={13} className="text-text-3" />
				</button>
			</div>

			<div className="h-[22px] w-px bg-border" />

			<button
				type="button"
				className="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 py-1 text-[12.5px] text-text-2 transition-colors hover:border-border-strong hover:bg-hover"
			>
				<ExternalLink size={13} />
				<span>View site</span>
			</button>

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
				title="Notifications"
				className="relative grid h-[30px] w-[30px] cursor-pointer place-items-center rounded-md bg-transparent text-text-2 transition-colors hover:bg-hover hover:text-text"
			>
				<Bell size={15} />
				<span className="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-danger" />
			</button>

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
		</header>
	);
};
