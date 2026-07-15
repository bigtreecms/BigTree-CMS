import { Link } from "react-router-dom";
import { ChevronRight } from "lucide-react";

export interface BreadcrumbItem {
	label: string;
	to?: string;
}

/**
 * Breadcrumb trail above the page title. The last item is rendered as plain
 * text (current location); earlier items are links if `to` is provided. Matches
 * the prototype's `.crumbs` styling — small, dense, chevron-separated.
 */
interface BreadcrumbProps {
	items: BreadcrumbItem[];
}

export const Breadcrumb = ({ items }: BreadcrumbProps) => {
	return (
		<div className="flex items-center gap-1.5 text-[12px] text-text-3">
			{items.map((it, i) => {
				const isLast = i === items.length - 1;

				return (
					<span className="flex items-center gap-1.5" key={it.to ?? it.label}>
						{i > 0 && <ChevronRight className="text-text-4" size={11} />}
						{isLast ? (
							<span className="text-text-2">{it.label}</span>
						) : it.to ? (
							<Link className="hover:text-text-2" to={it.to}>
								{it.label}
							</Link>
						) : (
							<span>{it.label}</span>
						)}
					</span>
				);
			})}
		</div>
	);
};
