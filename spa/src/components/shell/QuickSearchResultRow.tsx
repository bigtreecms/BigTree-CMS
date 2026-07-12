import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

import { NameIdCell } from "@/components/ui/NameIdCell";

interface QuickSearchResultRowProps {
	icon: LucideIcon;
	idx: number;
	isActive: boolean;
	title: ReactNode;
	subtitle: ReactNode;
	/** Optional trailing badge (e.g. Page's "archived" pill). */
	badge?: ReactNode;
	onSelect: () => void;
}

/**
 * One keyboard-navigable result row inside the ⌘K QuickSearch palette.
 * Keeps `data-search-idx` on the button so the palette's scroll-into-view
 * and highlight logic continue to work.
 */
export const QuickSearchResultRow = ({
	icon: Icon,
	idx,
	isActive,
	title,
	subtitle,
	badge,
	onSelect,
}: QuickSearchResultRowProps) => (
	<button
		type="button"
		data-search-idx={idx}
		onClick={onSelect}
		className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition-colors ${
			isActive ? "bg-hover" : "hover:bg-hover"
		}`}
	>
		<Icon size={15} className="shrink-0 text-text-3" />
		<div className="min-w-0 flex-1">
			<NameIdCell name={title} subtitle={subtitle} />
		</div>
		{badge}
	</button>
);
