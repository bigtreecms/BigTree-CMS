import type { ReactNode } from "react";
import { Link } from "react-router-dom";

interface SearchResultRowProps {
	/** Shown after the title, e.g. an "archived" badge. */
	badge?: ReactNode;
	/** A longer excerpt under the subtitle. */
	excerpt?: string;
	subtitle?: string;
	title: string;
	to: string;
}

/** One result on the search results page, linking to where it's edited. */
export const SearchResultRow = ({ badge, excerpt, subtitle, title, to }: SearchResultRowProps) => (
	<li className="border-b border-border last:border-b-0">
		<Link className="block px-4 py-2.5 transition-colors hover:bg-surface-2" to={to}>
			<div className="flex min-w-0 items-center gap-2">
				<span className="truncate text-[13px] font-medium text-text">{title}</span>
				{badge}
			</div>
			{subtitle && (
				<div className="truncate font-mono text-[11px] text-text-3">{subtitle}</div>
			)}
			{excerpt && (
				<p className="m-0 mt-1 line-clamp-2 text-[12px] leading-snug text-text-2">
					{excerpt}
				</p>
			)}
		</Link>
	</li>
);
