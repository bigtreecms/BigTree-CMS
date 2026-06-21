import type { HTMLAttributes, ReactNode } from "react";

interface EmptyStateProps extends HTMLAttributes<HTMLDivElement> {
	/**
	 * Use the dashed "drop zone / nothing here yet" treatment on a `surface-2`
	 * background instead of the default solid card. Mirrors the two visual
	 * variants already in use for empty lists.
	 */
	dashed?: boolean;
	children: ReactNode;
}

/**
 * The centered placeholder shown in place of an empty list/section — the
 * `p-9 text-center text-[13px] text-text-3` card that appeared ~27× inline.
 * Solid by default; pass `dashed` for the "nothing here yet" treatment.
 */
export const EmptyState = ({ dashed, className, children, ...rest }: EmptyStateProps) => {
	const surface = dashed
		? "border-dashed border-border bg-surface-2"
		: "border-border bg-surface";
	const extra = className ? ` ${className}` : "";

	return (
		<div
			className={`rounded-xl border ${surface} p-9 text-center text-[13px] text-text-3${extra}`}
			{...rest}
		>
			{children}
		</div>
	);
};
