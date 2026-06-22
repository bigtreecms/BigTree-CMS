import { Loader2 } from "lucide-react";
import type { ReactNode } from "react";

type LoadingVariant = "inline" | "block" | "card";

const VARIANT_CLASS: Record<LoadingVariant, string> = {
	inline: "inline-flex items-center gap-2 text-[12.5px] text-text-3",
	block: "flex items-center justify-center gap-2 p-9 text-[13px] text-text-3",
	card: "flex items-center justify-center gap-2 rounded-xl border border-border bg-surface p-9 text-[13px] text-text-3",
};

interface LoadingProps {
	/** Text shown beside the spinner. Default "Loading…". Pass `null` for spinner only. */
	label?: ReactNode;
	/**
	 * - `inline` — compact left-aligned row for inside cards/lists (default).
	 * - `block` — centered padded region (bare, in-flow — e.g. inside a table body).
	 * - `card` — `block` wrapped in the bordered `surface` card (page-level placeholder).
	 */
	variant?: LoadingVariant;
	/** Hide the spinner and show text only. */
	hideSpinner?: boolean;
	/** Layout-only classes appended to the wrapper. */
	className?: string;
}

/**
 * The single loading indicator — a muted `Loader2` spinner plus label. Replaces
 * the split between `<EmptyState>Loading…</EmptyState>` (page-level) and the
 * ad-hoc inline `text-text-3` "Loading…" divs scattered across pages and
 * panels. `DataTable` delegates its internal loading state here too.
 */
export const Loading = ({
	label = "Loading…",
	variant = "inline",
	hideSpinner,
	className,
}: LoadingProps) => {
	const extra = className ? ` ${className}` : "";

	return (
		<div className={`${VARIANT_CLASS[variant]}${extra}`} role="status" aria-live="polite">
			{!hideSpinner && (
				<Loader2
					size={variant === "inline" ? 13 : 15}
					className="shrink-0 animate-spin text-text-4"
				/>
			)}
			{label != null && <span>{label}</span>}
		</div>
	);
};
