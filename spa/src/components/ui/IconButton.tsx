import type { MouseEventHandler, ReactNode } from "react";

export type IconButtonTone = "default" | "danger" | "accent" | "success";

interface IconButtonProps {
	/** The icon to render — e.g. a lucide `<Trash size={13} />`. */
	children: ReactNode;
	/** Accessible name — required, since the button is icon-only. */
	label: string;
	onClick?: MouseEventHandler<HTMLButtonElement>;
	/**
	 * Hover color: `default` (neutral text — edit/clear/expand affordances),
	 * `danger` (destructive remove/delete/reject), `accent` (a primary action),
	 * `success` (an approve/confirm action, e.g. paired with a `danger` reject).
	 */
	tone?: IconButtonTone;
	disabled?: boolean;
	/** Native tooltip; pass when the action benefits from a hover label. */
	title?: string;
	/** Native button type — defaults to `button`. */
	type?: "button" | "submit";
	/** Extra classes appended after the base/tone classes (layout only — e.g. absolute positioning, a border variant). */
	className?: string;
}

/**
 * The shared icon-only button primitive — the small square `text-text-3`
 * affordance that sits in table rows, toolbars, and dense editor lists (remove
 * a row, clear an input, expand a node). Use this instead of hand-rolling a
 * `<button className="rounded p-1 …">`; it keeps the hit target, hover, and
 * disabled treatment consistent and token-driven. `label` is required so the
 * icon-only control always has an accessible name.
 *
 * For buttons with visible text, use `Button` (which also takes a leading
 * `icon`). The `before:-inset` hit-area close button in `Modal`/`SlideOver` is
 * intentionally bespoke and stays inline.
 */

const baseClassName =
	"inline-flex cursor-pointer items-center justify-center rounded p-1 text-text-3 transition hover:bg-hover disabled:cursor-default disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-3";

const toneClassName: Record<IconButtonTone, string> = {
	default: "hover:text-text",
	danger: "hover:text-danger",
	accent: "hover:text-accent",
	success: "hover:text-success",
};

export const IconButton = ({
	children,
	label,
	onClick,
	tone = "default",
	disabled,
	title,
	type = "button",
	className: extra,
}: IconButtonProps) => (
	<button
		type={type}
		onClick={onClick}
		disabled={disabled}
		aria-label={label}
		title={title}
		className={`${baseClassName} ${toneClassName[tone]}${extra ? ` ${extra}` : ""}`}
	>
		{children}
	</button>
);
