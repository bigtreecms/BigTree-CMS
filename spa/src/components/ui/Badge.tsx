import type { ReactNode } from "react";

/**
 * Static status / label pill. A Badge communicates state — it is not interactive.
 * For a toggleable filter or a removable tag use `Chip` instead.
 *
 * Tones map to the design tokens (never raw colors), so badges read consistently
 * in light + dark mode. Optionally render a leading colored `dot` (the classic
 * status-pill treatment) or a custom `icon` node (caller controls its size).
 */
export type BadgeTone = "neutral" | "accent" | "success" | "warn" | "danger" | "info";

const TONE_CLASS: Record<BadgeTone, string> = {
	neutral: "bg-surface-2 text-text-3",
	accent: "bg-accent-soft text-accent",
	success: "bg-success-bg text-success",
	warn: "bg-warn-bg text-warn",
	danger: "bg-danger-bg text-danger",
	info: "bg-info-bg text-info",
};

interface BadgeProps {
	/** Add a `border-border` outline (subtle neutral pills). */
	bordered?: boolean;
	children: ReactNode;
	/** Layout-only classes (e.g. `shrink-0`, `ml-auto`, `tabular-nums`). */
	className?: string;
	/** Leading colored dot (`bg-current`) — the at-a-glance status treatment. */
	dot?: boolean;
	/** Leading icon node; the caller sizes it (e.g. `<Key size={9} />`). */
	icon?: ReactNode;
	/**
	 * `md` (default) — pill shape (`rounded-full`, `px-2`).
	 * `sm` — tighter square-ish chip (`rounded`, `px-1.5`) for table cells and inline type labels.
	 */
	size?: "md" | "sm";
	title?: string;
	/** Token-mapped status tone. Defaults to `neutral`. */
	tone?: BadgeTone;
	/** Uppercase + slight tracking (e.g. the "Pending" field badge). */
	uppercase?: boolean;
}

export const Badge = ({
	children,
	tone = "neutral",
	dot = false,
	icon,
	bordered = false,
	uppercase = false,
	size = "md",
	className = "",
	title,
}: BadgeProps) => {
	const sizeClass =
		size === "sm" ? "rounded px-1.5 py-0.5 gap-1" : "rounded-full px-2 py-0.5 gap-1.5";

	return (
		<span
			className={`inline-flex items-center text-[11px] font-medium ${sizeClass} ${
				bordered ? "border border-border " : ""
			}${uppercase ? "uppercase tracking-[0.04em] " : ""}${TONE_CLASS[tone]} ${className}`}
			title={title}
		>
			{dot ? <span className="size-1.5 rounded-full bg-current" /> : null}
			{icon}
			{children}
		</span>
	);
};
