import { ChevronDown, ChevronRight } from "lucide-react";
import type { ReactNode } from "react";

interface DisclosureToggleProps {
	/** Whether the disclosure is open — drives the chevron swap + `aria-expanded`. */
	open: boolean;
	onToggle: () => void;
	/**
	 * The clickable label. Pass a string for the common case, or a fully-styled
	 * node (e.g. an `<h3>`) when the caller needs custom label typography.
	 */
	label: ReactNode;
	/** Chevron size in px (default 13). */
	size?: number;
	/**
	 * Layout classes for the button — gap, padding, width, hover, etc. The
	 * component owns only the chevron + `aria-expanded` semantics, so callers
	 * supply their own container styling (and the gap between chevron and label).
	 */
	className?: string;
	/** Trailing content rendered after the label (counts, type badges, pills). */
	children?: ReactNode;
}

/**
 * The shared chevron expand/collapse toggle. Owns the `ChevronRight`/`ChevronDown`
 * swap, the `aria-expanded` state, and the base button semantics; the host keeps
 * owning the collapsed content (this is a *toggle*, not a full disclosure/accordion
 * — content stays external). For repeating-field row headers use the richer
 * `CollapsibleRowHeader` instead.
 */
export const DisclosureToggle = ({
	open,
	onToggle,
	label,
	size = 13,
	className,
	children,
}: DisclosureToggleProps) => (
	<button
		type="button"
		onClick={onToggle}
		aria-expanded={open}
		className={`inline-flex cursor-pointer items-center text-left${className ? ` ${className}` : ""}`}
	>
		{open ? (
			<ChevronDown size={size} className="shrink-0 text-text-3" />
		) : (
			<ChevronRight size={size} className="shrink-0 text-text-3" />
		)}
		{label}
		{children}
	</button>
);
