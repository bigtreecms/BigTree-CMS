import { ChevronDown, ChevronRight } from "lucide-react";

import { IconButton } from "@/components/ui/IconButton";

interface TreeExpanderProps {
	expanded: boolean;
	/** When false the expander collapses to a leaf-aligned spacer. */
	hasChildren: boolean;
	onToggle: () => void;
}

/**
 * Shared expander cell for the lazy permission trees (`PagePermissionsTree`,
 * `ResourcePermissionsTree`): a chevron toggle when the row has children, else
 * an 18px spacer that keeps leaf rows aligned with their expandable siblings.
 */
export const TreeExpander = ({ expanded, hasChildren, onToggle }: TreeExpanderProps) =>
	hasChildren ? (
		<IconButton
			label={expanded ? "Collapse" : "Expand"}
			size="sm"
			onClick={onToggle}
			ariaExpanded={expanded}
		>
			{expanded ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
		</IconButton>
	) : (
		<span className="inline-block w-[18px]" aria-hidden="true" />
	);

interface TreeLoadingRowProps {
	depth: number;
}

/** Shared "Loading…" placeholder row shown while a tree level's children fetch. */
export const TreeLoadingRow = ({ depth }: TreeLoadingRowProps) => (
	<div
		className="border-t border-border bg-surface px-3 py-2 text-[12px] text-text-3"
		style={{ paddingLeft: `${12 + depth * 16}px` }}
	>
		Loading…
	</div>
);
