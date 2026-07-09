import type { ElementType, ReactNode } from "react";

import { MonoText } from "./MonoText";

interface NameIdCellProps {
	/** Bold primary line — the resource's display name. */
	name: ReactNode;
	/** Muted monospace second line — the resource's ID / route / hash. */
	id: ReactNode;
	/** Element for the ID line. Defaults to `div` (the table-cell default). */
	as?: ElementType;
}

/**
 * The two-line "name over mono-id" table cell used across the developer list
 * pages (callouts, feeds, templates, module groups, …). The bold `truncate`
 * name sits above a {@link MonoText} ID, both inside a `min-w-0` wrapper so the
 * cell can shrink and ellipsize. Single source of truth for that block.
 */
export const NameIdCell = ({ name, id, as = "div" }: NameIdCellProps) => (
	<div className="min-w-0">
		<div className="truncate font-medium text-text">{name}</div>
		<MonoText as={as}>{id}</MonoText>
	</div>
);
