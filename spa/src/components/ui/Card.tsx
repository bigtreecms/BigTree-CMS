import type { HTMLAttributes, ReactNode } from "react";

/** Canonical surface: a bordered, rounded panel on the `surface` token. */
const CARD_CLASS = "rounded-xl border border-border bg-surface";

const PADDING = {
	sm: "p-4",
	md: "p-5",
	lg: "p-6",
} as const;

interface CardProps extends HTMLAttributes<HTMLDivElement> {
	/** Inner padding. Omit for a flush card (e.g. one wrapping a {@link CardHeader} + table). */
	padding?: keyof typeof PADDING;
	children: ReactNode;
}

/**
 * The standard content panel used across the admin — `rounded-xl border
 * border-border bg-surface`, token-driven so light/dark both work. This is the
 * single source of truth for that surface; pair with {@link CardHeader} for a
 * titled card. Append layout-only tweaks (margins, `overflow-hidden`, grid
 * spans) via `className`.
 */
export const Card = ({ padding, className, children, ...rest }: CardProps) => {
	const pad = padding ? ` ${PADDING[padding]}` : "";
	const extra = className ? ` ${className}` : "";

	return (
		<div className={`${CARD_CLASS}${pad}${extra}`} {...rest}>
			{children}
		</div>
	);
};

interface CardHeaderProps extends Omit<HTMLAttributes<HTMLElement>, "title"> {
	/** Standard title rendered as a semibold heading. Ignored when `children` is given. */
	title?: ReactNode;
	/** Optional sub-line under the title (only used alongside `title`). */
	description?: ReactNode;
	/** Custom header content. Overrides `title`/`description` for bespoke layouts. */
	children?: ReactNode;
}

/**
 * The `border-b … bg-surface-2` bar that sits flush at the top of a flush
 * {@link Card}. Pass `title`/`description` for the common heading layout, or
 * `children` for a bespoke header (icons, action buttons, custom typography).
 */
export const CardHeader = ({
	title,
	description,
	className,
	children,
	...rest
}: CardHeaderProps) => {
	const extra = className ? ` ${className}` : "";

	return (
		<header className={`border-b border-border bg-surface-2 px-4 py-2.5${extra}`} {...rest}>
			{children ?? (
				<>
					<h2 className="text-[13px] font-semibold text-text">{title}</h2>
					{description ? <p className="text-[11px] text-text-3">{description}</p> : null}
				</>
			)}
		</header>
	);
};
