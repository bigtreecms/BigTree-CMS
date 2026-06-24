import type { HTMLAttributes, ReactNode } from "react";

interface EmptyStateProps extends HTMLAttributes<HTMLDivElement> {
	/**
	 * Use the dashed "drop zone / nothing here yet" treatment on a `surface-2`
	 * background instead of the default solid card. Mirrors the two visual
	 * variants already in use for empty lists.
	 */
	dashed?: boolean;
	/**
	 * `md` (default) is the large list-level placeholder (`rounded-xl p-9
	 * text-[13px]`); `sm` is the compact field-level placeholder (`rounded-md
	 * p-3 text-[12px]`) used inside form fields for "no items / nothing
	 * selected" notices.
	 */
	size?: "sm" | "md";
	children: ReactNode;
}

/**
 * The centered placeholder shown in place of an empty list/section — the
 * `p-9 text-center text-[13px] text-text-3` card that appeared ~27× inline.
 * Solid by default; pass `dashed` for the "nothing here yet" treatment and
 * `size="sm"` for the compact field-level variant.
 */
export const EmptyState = ({
	dashed,
	size = "md",
	className,
	children,
	...rest
}: EmptyStateProps) => {
	const surface = dashed
		? "border-dashed border-border bg-surface-2"
		: "border-border bg-surface";
	const sizing = size === "sm" ? "rounded-md p-3 text-[12px]" : "rounded-xl p-9 text-[13px]";
	const extra = className ? ` ${className}` : "";

	return (
		<div className={`border ${surface} ${sizing} text-center text-text-3${extra}`} {...rest}>
			{children}
		</div>
	);
};
