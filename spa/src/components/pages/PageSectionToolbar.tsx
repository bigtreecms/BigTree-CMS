import { Link } from "react-router-dom";
import { pageAddPath, pageEditPath, pagePath, pageRevisionsPath } from "@/lib/routes";
import { Copy, Edit, FileText, List as PagesIcon, Move, Plus, ShieldCheck } from "lucide-react";

/**
 * Section-level subnav that sits between the breadcrumb/title and the wizard
 * body on every page-section view (View Subpages, Add Subpage, Edit Page,
 * Revisions, Move Page, Duplicate Page). Ports `.subnav--wide` from the
 * design.
 *
 * Move / Duplicate aren't standalone routes in this SPA — Move opens a
 * dialog and Duplicate fires POST /pages/{id}/duplicate — so the parent
 * supplies callbacks for those instead of `to` Link targets.
 */
type Action = "view" | "add" | "edit" | "revisions" | "move" | "duplicate" | "access";

interface PageSectionToolbarProps {
	active: Action;
	/** Admin-only access-levels viewer; the item renders only when provided. */
	onAccessLevels?: () => void;
	onDuplicate?: () => void;
	onMove?: () => void;
	pageId: number;
	parentId: number;
}

interface ItemSpec {
	disabled?: boolean;
	icon: React.ReactNode;
	id: Action;
	label: string;
	onClick?: () => void;
	to?: string;
}

export const PageSectionToolbar = ({
	active,
	pageId,
	parentId,
	onMove,
	onDuplicate,
	onAccessLevels,
}: PageSectionToolbarProps) => {
	const items: ItemSpec[] = [
		{
			id: "view",
			label: "View Subpages",
			icon: <PagesIcon size={13} />,
			to: pagePath(pageId || parentId),
		},
		{
			id: "add",
			label: "Add Subpage",
			icon: <Plus size={13} />,
			to: pageAddPath(pageId || parentId),
		},
		{
			id: "edit",
			label: "Edit Page",
			icon: <Edit size={13} />,
			to: pageId ? pageEditPath(pageId) : undefined,
			disabled: !pageId,
		},
		{
			id: "revisions",
			label: "Revisions",
			icon: <FileText size={13} />,
			to: pageId ? pageRevisionsPath(pageId) : undefined,
			disabled: !pageId,
		},
		{
			id: "move",
			label: "Move Page",
			icon: <Move size={13} />,
			onClick: onMove,
			disabled: !pageId || !onMove,
		},
		{
			id: "duplicate",
			label: "Duplicate Page",
			icon: <Copy size={13} />,
			onClick: onDuplicate,
			disabled: !pageId || !onDuplicate,
		},
	];

	if (onAccessLevels) {
		items.push({
			id: "access",
			label: "Access Levels",
			icon: <ShieldCheck size={13} />,
			onClick: onAccessLevels,
			disabled: !pageId,
		});
	}

	return (
		<nav className="mb-4 flex items-stretch gap-0 overflow-x-auto rounded-md border border-border bg-surface p-1 text-[12.5px]">
			{items.map((it) => {
				const isActive = it.id === active;
				const className = `inline-flex shrink-0 items-center gap-1.5 rounded px-3 py-1.5 transition-colors ${
					isActive
						? "bg-accent-soft font-medium text-accent"
						: "text-text-2 hover:bg-hover hover:text-text"
				} ${it.disabled ? "cursor-not-allowed opacity-40 hover:bg-transparent hover:text-text-2" : ""}`;

				if (it.to && !it.disabled) {
					return (
						<Link
							aria-current={isActive ? "page" : undefined}
							className={className}
							key={it.id}
							to={it.to}
						>
							{it.icon}
							<span>{it.label}</span>
						</Link>
					);
				}

				return (
					<button
						aria-current={isActive ? "page" : undefined}
						className={className}
						disabled={it.disabled}
						key={it.id}
						type="button"
						onClick={it.onClick}
					>
						{it.icon}
						<span>{it.label}</span>
					</button>
				);
			})}
		</nav>
	);
};
