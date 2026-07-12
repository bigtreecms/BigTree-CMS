import type { ElementType, ReactNode } from "react";

import { MonoText } from "./MonoText";

interface NameIdCellProps {
	/** Bold primary line — the resource's display name. */
	name: ReactNode;
	/**
	 * Muted monospace second line (ID / route / hash). Mutually exclusive with
	 * `subtitle` — provide exactly one.
	 */
	id?: ReactNode;
	/**
	 * Muted non-mono second line (description / path / meta). Mutually exclusive
	 * with `id` — provide exactly one.
	 */
	subtitle?: ReactNode;
	/** Element for the mono ID line. Defaults to `div` (the table-cell default). */
	as?: ElementType;
	/** Override the primary line's classes (e.g. unread `font-semibold`, muted `text-text-2`). */
	primaryClassName?: string;
	/** `title` attribute on the primary line (full text on hover). */
	nameTitle?: string;
	/** `title` attribute on the subtitle line. */
	subtitleTitle?: string;
}

/**
 * The two-line "name over secondary" table cell used across list pages.
 * Second line is either a {@link MonoText} ID (`id`) or a muted subtitle
 * (`subtitle`). Single source of truth for that block.
 */
export const NameIdCell = ({
	name,
	id,
	subtitle,
	as = "div",
	primaryClassName,
	nameTitle,
	subtitleTitle,
}: NameIdCellProps) => {
	const primary = primaryClassName ?? "truncate font-medium text-text";

	return (
		<div className="min-w-0">
			<div className={primary} title={nameTitle}>
				{name}
			</div>
			{id != null ? (
				<MonoText as={as}>{id}</MonoText>
			) : subtitle != null ? (
				<div className="truncate text-[11px] text-text-3" title={subtitleTitle}>
					{subtitle}
				</div>
			) : null}
		</div>
	);
};
