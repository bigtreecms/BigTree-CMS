import type { ReactNode } from "react";
import { ChevronDown, ChevronRight } from "lucide-react";

interface CollapsibleRowHeaderProps {
	/** Whether the row is expanded — drives the chevron + `aria-expanded`. */
	open: boolean;
	onToggle: () => void;
	/** id of the panel this toggle reveals (for `aria-controls`). */
	controls: string;
	title: ReactNode;
	subtitle?: ReactNode;
	/** Rendered inside the button before the title (e.g. an inline icon). */
	leading?: ReactNode;
	/** Rendered inside the button after the title/subtitle (e.g. a status pill). */
	trailing?: ReactNode;
	disabled?: boolean;
	/** Stack the title over the subtitle instead of laying them out inline. */
	stacked?: boolean;
	className?: string;
}

/**
 * The shared expand/collapse toggle for a repeating field's row (Matrix,
 * Callouts, MediaGallery). Renders the chevron, a title + optional subtitle and
 * `leading`/`trailing` slots for per-field extras (a type badge, a lock pill).
 * The move/delete row actions and any leading thumbnail stay as siblings in the
 * caller — this owns only the clickable title region.
 */
export const CollapsibleRowHeader = ({
	open,
	onToggle,
	controls,
	title,
	subtitle,
	leading,
	trailing,
	disabled,
	stacked,
	className,
}: CollapsibleRowHeaderProps) => (
	<button
		type="button"
		className={`flex min-w-0 flex-1 items-center gap-2 rounded px-1.5 py-1 text-left hover:bg-hover disabled:cursor-not-allowed disabled:opacity-60${
			className ? ` ${className}` : ""
		}`}
		onClick={onToggle}
		disabled={disabled}
		aria-expanded={open}
		aria-controls={controls}
	>
		{open ? (
			<ChevronDown size={13} className="text-text-3" />
		) : (
			<ChevronRight size={13} className="text-text-3" />
		)}
		{leading}
		{stacked ? (
			<span className="min-w-0">
				<span className="block truncate text-[12.5px] text-text-2">{title}</span>
				{subtitle && (
					<span className="block truncate text-[11.5px] text-text-3">{subtitle}</span>
				)}
			</span>
		) : (
			<>
				<span className="truncate text-[12.5px] text-text-2">{title}</span>
				{subtitle && <span className="truncate text-[11.5px] text-text-3">{subtitle}</span>}
			</>
		)}
		{trailing}
	</button>
);
