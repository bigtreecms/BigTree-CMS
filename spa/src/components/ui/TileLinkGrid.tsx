import type { ReactNode } from "react";
import { Link } from "react-router-dom";

import { IconTile } from "@/components/ui/IconTile";

export interface TileLink {
	/** Route the tile navigates to. Doubles as the React key. */
	to: string;
	/** Leading icon node — the caller sizes it (e.g. `<Layers size={16} />`). */
	icon: ReactNode;
	title: string;
	description: ReactNode;
}

interface TileLinkGridProps {
	items: TileLink[];
	/** Layout-only classes for the grid wrapper (margins, column overrides). */
	className?: string;
}

/**
 * The responsive grid of icon-tile navigation cards used by the developer index
 * pages (Developer, Configure, Debug). Each tile is a {@link Link} pairing an
 * {@link IconTile} with a title + description, and the whole card lights up on
 * hover. The single source of truth for that grid — the three index pages used
 * to hand-roll it byte-identically.
 *
 * Related but deliberately distinct: the module designer's two-up chooser tiles
 * are `<button>`s on different hover tokens, and are not built on this.
 */
export const TileLinkGrid = ({ items, className }: TileLinkGridProps) => {
	const extra = className ? ` ${className}` : "";

	return (
		<div className={`grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3${extra}`}>
			{items.map((item) => (
				<Link
					key={item.to}
					to={item.to}
					className="group flex items-start gap-3 rounded-xl border border-border bg-surface p-4 transition-colors hover:border-accent-ring hover:bg-surface-2"
				>
					<IconTile className="shrink-0">{item.icon}</IconTile>
					<div className="min-w-0">
						<div className="text-[13.5px] font-semibold text-text group-hover:text-accent">
							{item.title}
						</div>
						<div className="mt-0.5 text-[12px] text-text-3">{item.description}</div>
					</div>
				</Link>
			))}
		</div>
	);
};
