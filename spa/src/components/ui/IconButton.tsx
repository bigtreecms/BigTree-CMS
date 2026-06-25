import type { MouseEventHandler, ReactNode } from "react";
import { Link } from "react-router-dom";

export type IconButtonTone = "default" | "danger" | "accent" | "success";

export type IconButtonSize = "sm" | "md";

interface IconButtonProps {
	/** The icon to render — e.g. a lucide `<Trash size={13} />`. */
	children: ReactNode;
	/** Accessible name — required, since the control is icon-only. */
	label: string;
	onClick?: MouseEventHandler<HTMLButtonElement | HTMLAnchorElement>;
	/** Internal route — renders a react-router `<Link>` for client-side nav. */
	to?: string;
	/** External/absolute URL — renders an `<a>`. */
	href?: string;
	/** Anchor target (e.g. `_blank`); only used together with `href`. */
	target?: string;
	/**
	 * Hover color: `default` (neutral text — edit/clear/expand affordances),
	 * `danger` (destructive remove/delete/reject), `accent` (a primary action),
	 * `success` (an approve/confirm action, e.g. paired with a `danger` reject).
	 */
	tone?: IconButtonTone;
	/**
	 * Hit-target density: `md` (default `p-1` — table rows, toolbars) or `sm`
	 * (`p-0.5` — dense tree expand/collapse chevrons and inline clear toggles).
	 */
	size?: IconButtonSize;
	/** Expanded state for a disclosure toggle — sets `aria-expanded` (button mode only). */
	ariaExpanded?: boolean;
	disabled?: boolean;
	/** Native tooltip; pass when the action benefits from a hover label. */
	title?: string;
	/** Native button type — defaults to `button`. Ignored in link mode. */
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
 * Pass `to` for internal navigation (client-side `<Link>`), `href` for an
 * external URL (`<a>`), or `onClick`/`type` for an action — a disabled control
 * always renders as a plain `<button>` (matching `Button`).
 *
 * For buttons with visible text, use `Button` (which also takes a leading
 * `icon`). The `before:-inset` hit-area close button in `Modal`/`SlideOver` is
 * intentionally bespoke and stays inline.
 */

const baseClassName =
	"inline-flex cursor-pointer items-center justify-center rounded text-text-3 transition hover:bg-hover disabled:cursor-default disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-3";

const sizeClassName: Record<IconButtonSize, string> = {
	md: "p-1",
	sm: "p-0.5",
};

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
	to,
	href,
	target,
	tone = "default",
	size = "md",
	ariaExpanded,
	disabled,
	title,
	type = "button",
	className: extra,
}: IconButtonProps) => {
	const className = `${baseClassName} ${sizeClassName[size]} ${toneClassName[tone]}${extra ? ` ${extra}` : ""}`;

	if (!disabled && to) {
		return (
			<Link to={to} onClick={onClick} aria-label={label} title={title} className={className}>
				{children}
			</Link>
		);
	}

	if (!disabled && href) {
		return (
			<a
				href={href}
				target={target}
				rel={target === "_blank" ? "noopener noreferrer" : undefined}
				onClick={onClick}
				aria-label={label}
				title={title}
				className={className}
			>
				{children}
			</a>
		);
	}

	return (
		<button
			type={type}
			onClick={onClick}
			disabled={disabled}
			aria-label={label}
			aria-expanded={ariaExpanded}
			title={title}
			className={className}
		>
			{children}
		</button>
	);
};
