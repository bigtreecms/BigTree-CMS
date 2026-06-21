import type { MouseEventHandler, ReactNode } from "react";
import { Link } from "react-router-dom";

export type ButtonVariant = "primary" | "secondary" | "danger" | "dangerGhost" | "link";
export type ButtonSize = "sm" | "md" | "lg";

interface ButtonProps {
	children: ReactNode;
	/**
	 * `primary` = accent fill, `secondary` (default) = bordered surface,
	 * `danger` = solid destructive (confirm CTAs), `dangerGhost` = bordered
	 * destructive (subtle, e.g. a header Delete next to other actions),
	 * `link` = borderless accent text (inline/ghost — e.g. a "View" link inside
	 * a card; pair with `size="sm"` and negative-margin `className` to sit flush).
	 */
	variant?: ButtonVariant;
	/** `sm` = tight/inline, `md` (default) = standard, `lg` = prominent (e.g. full-width auth submits). */
	size?: ButtonSize;
	icon?: ReactNode;
	onClick?: MouseEventHandler<HTMLButtonElement | HTMLAnchorElement>;
	/** Internal route — renders a react-router `<Link>` for client-side nav. */
	to?: string;
	/** External/absolute URL — renders an `<a>`. */
	href?: string;
	/** Anchor target (e.g. `_blank`); only used together with `href`. */
	target?: string;
	/** Disables the control. A disabled button never renders as a link/anchor. */
	disabled?: boolean;
	/** Native button type — defaults to `button`. */
	type?: "button" | "submit";
	/** Associates a `submit` button with a form by id (button rendered outside it). */
	form?: string;
	title?: string;
	/** Extra classes appended after the variant/size classes (layout tweaks only). */
	className?: string;
}

/**
 * The shared button primitive for the admin. Every clickable action — form
 * footers, toolbars, `PageHead` actions — should route through this so size
 * and color stay consistent and token-driven. `PageHead` actions use the
 * default `md` size, same as form buttons; reserve `sm` for compact/inline
 * spots (e.g. dense toolbars).
 *
 * Pass `to` for internal navigation (client-side `<Link>`), `href` for an
 * external URL (`<a>`), or `onClick`/`type="submit"` for an action — a
 * disabled control always renders as a plain `<button>`.
 */

const baseClassName =
	"inline-flex cursor-pointer items-center gap-1.5 rounded-md font-medium transition-colors disabled:cursor-default disabled:opacity-50";

const sizeClassName: Record<ButtonSize, string> = {
	sm: "px-2.5 py-1.5 text-[12.5px]",
	md: "px-3 py-1.5 text-[12.5px]",
	lg: "px-3 py-2 text-[13px]",
};

const variantClassName: Record<ButtonVariant, string> = {
	primary: "bg-accent text-accent-fg hover:bg-accent-hover",
	secondary:
		"border border-border bg-surface text-text-2 hover:border-border-strong hover:bg-hover",
	danger: "bg-danger text-white hover:bg-danger/90",
	dangerGhost:
		"border border-border bg-surface text-danger hover:border-danger/40 hover:bg-danger/5",
	link: "bg-transparent text-accent hover:bg-accent-soft",
};

export const Button = ({
	children,
	variant = "secondary",
	size = "md",
	icon,
	onClick,
	to,
	href,
	target,
	disabled,
	type = "button",
	form,
	title,
	className: extra,
}: ButtonProps) => {
	const className = `${baseClassName} ${sizeClassName[size]} ${variantClassName[variant]}${
		extra ? ` ${extra}` : ""
	}`;

	if (!disabled && to) {
		return (
			<Link to={to} onClick={onClick} className={className} title={title}>
				{icon}
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
				className={className}
				title={title}
			>
				{icon}
				{children}
			</a>
		);
	}

	return (
		<button
			type={type}
			form={form}
			onClick={onClick}
			disabled={disabled}
			className={className}
			title={title}
		>
			{icon}
			{children}
		</button>
	);
};
