import type { HTMLAttributes, ReactNode } from "react";
import type { LucideIcon } from "lucide-react";

type Pad = "sm" | "md" | "lg" | "xl";
type Variant = "dashed" | "plain";

const PAD_CLASS: Record<Pad, string> = {
	sm: "px-3 py-2",
	md: "px-3 py-3",
	lg: "px-3 py-4",
	xl: "px-3 py-6",
};

interface InlineEmptyProps extends HTMLAttributes<HTMLDivElement> {
	/** Text alignment of the message. `start` (default) for notes, `center` for empty lists. */
	align?: "start" | "center";
	children: ReactNode;
	/** Fill the parent's height (`min-h-[110px] h-full`) — for dashboard columns that must align. */
	fill?: boolean;
	/** Optional leading icon (sized 20, `text-text-4`); switches to a horizontal row. */
	icon?: LucideIcon;
	/** Inner padding scale. Defaults to `lg` (the dominant empty-list size). */
	pad?: Pad;
	/**
	 * `dashed` (default) is the bordered surface-2 box. `plain` is borderless
	 * centered muted text for status notes (search empty, analytics empty, …).
	 */
	variant?: Variant;
}

/**
 * The compact dashed placeholder shown inside cards/sections — the
 * `rounded-md border border-dashed border-border bg-surface-2 text-[12.5px]
 * text-text-3` box that appeared ~34× inline. The dense sibling of
 * `EmptyState` (which is the large centered `rounded-xl p-9` card). Use it for
 * empty lists, "not configured yet" notes, and dashboard placeholders. Append
 * layout-only classes (grid spans, margins, `leading-*`) via `className`.
 *
 * Pass `variant="plain"` for the borderless centered form used by search
 * empty states and sparse analytics notes.
 */
export const InlineEmpty = ({
	icon: Icon,
	align = "start",
	pad = "lg",
	variant = "dashed",
	fill,
	className,
	children,
	...rest
}: InlineEmptyProps) => {
	const layout = Icon ? "flex items-center gap-2.5" : align === "center" ? "text-center" : "";
	const fillClass = fill ? " flex items-center min-h-[110px] h-full" : "";
	const extra = className ? ` ${className}` : "";
	const shell =
		variant === "plain"
			? "text-[12.5px] text-text-3"
			: "rounded-md border border-dashed border-border bg-surface-2 text-[12.5px] text-text-3";

	return (
		<div className={`${shell} ${PAD_CLASS[pad]} ${layout}${fillClass}${extra}`} {...rest}>
			{Icon ? <Icon className="shrink-0 text-text-4" size={20} /> : null}
			{Icon ? <span>{children}</span> : children}
		</div>
	);
};
