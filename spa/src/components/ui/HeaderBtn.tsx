import type { MouseEventHandler, ReactNode } from "react";
import { Link } from "react-router-dom";

interface HeaderBtnProps {
	icon?: ReactNode;
	primary?: boolean;
	/** Bordered surface button tinted for a destructive action (e.g. Delete). */
	danger?: boolean;
	children: ReactNode;
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
}

/**
 * The standard top-right action button used in `PageHead`'s `actions` slot
 * across the admin. `primary` paints it in the accent color; otherwise it's a
 * bordered surface button. Pass `to` for internal navigation (client-side
 * `<Link>`), `href` for an external URL (`<a>`), or `onClick` for an action —
 * a disabled control always renders as a plain `<button>`.
 */

const baseClassName =
	"inline-flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[12.5px] font-medium transition-colors disabled:cursor-default disabled:opacity-50";

const variantClassName = (primary?: boolean, danger?: boolean) => {
	if (primary) {
		return `${baseClassName} bg-accent text-accent-fg hover:bg-accent-hover`;
	}

	if (danger) {
		return `${baseClassName} border border-border bg-surface text-danger hover:border-danger/40 hover:bg-danger/5`;
	}

	return `${baseClassName} border border-border bg-surface text-text-2 hover:border-border-strong hover:bg-hover`;
};

export const HeaderBtn = ({
	icon,
	primary,
	danger,
	children,
	onClick,
	to,
	href,
	target,
	disabled,
	type = "button",
	form,
	title,
}: HeaderBtnProps) => {
	const className = variantClassName(primary, danger);

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
