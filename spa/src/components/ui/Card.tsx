import type { HTMLAttributes, ReactNode } from "react";

/** Canonical surface: a bordered, rounded panel on the `surface` token. */
const CARD_CLASS = "rounded-xl border border-border bg-surface";

const PADDING = {
	sm: "p-4",
	md: "p-5",
	lg: "p-6",
} as const;

interface CardProps extends HTMLAttributes<HTMLElement> {
	/** Rendered tag, when the card is semantically a form / section / article rather than a div. */
	as?: "div" | "section" | "article" | "form";
	children?: ReactNode;
	/** Inner padding. Omit for a flush card (e.g. one wrapping a {@link CardHeader} + table). */
	padding?: keyof typeof PADDING;
}

/**
 * The standard content panel used across the admin — `rounded-xl border
 * border-border bg-surface`, token-driven so light/dark both work. This is the
 * single source of truth for that surface; pair with {@link CardHeader} for a
 * titled card. Append layout-only tweaks (margins, `overflow-hidden`, grid
 * spans) via `className`.
 */
export const Card = ({ padding, as: Tag = "div", className, children, ...rest }: CardProps) => {
	const pad = padding ? ` ${PADDING[padding]}` : "";
	const extra = className ? ` ${className}` : "";

	return (
		<Tag className={`${CARD_CLASS}${pad}${extra}`} {...rest}>
			{children}
		</Tag>
	);
};

interface CardHeaderProps extends Omit<HTMLAttributes<HTMLElement>, "title"> {
	/** Custom header content. Overrides `title`/`description` for bespoke layouts. */
	children?: ReactNode;
	/** Optional sub-line under the title (only used alongside `title`). */
	description?: ReactNode;
	/** Standard title rendered as a semibold heading. Ignored when `children` is given. */
	title?: ReactNode;
}

/**
 * The `border-b … bg-surface-2` bar that sits flush at the top of a flush
 * {@link Card}. Pass `title`/`description` for the common heading layout, or
 * `children` for a bespoke header (icons, action buttons, custom typography).
 * On a `Card` without `overflow-hidden`, add `rounded-t-xl` via `className`.
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
		<header className={`border-b border-border bg-surface-2 px-4 py-3${extra}`} {...rest}>
			{children ?? (
				<>
					<h2 className="text-[13px] font-semibold text-text">{title}</h2>
					{description ? <p className="text-[11px] text-text-3">{description}</p> : null}
				</>
			)}
		</header>
	);
};

const JUSTIFY = {
	end: "justify-end",
	start: "justify-start",
} as const;

interface CardFooterProps extends HTMLAttributes<HTMLDivElement> {
	children: ReactNode;
	/** `start` for bars that place their own spacer between left and right groups. */
	justify?: keyof typeof JUSTIFY;
	/** Pin the bar to the bottom of the viewport while the form above scrolls. */
	sticky?: boolean;
}

/**
 * The `border-t … bg-surface-2` action bar that sits flush at the bottom of a
 * flush {@link Card} — the mirror of {@link CardHeader}. Right-aligns its
 * buttons; pass `sticky` to pin it to the viewport, and `rounded-b-xl` via
 * `className` on a `Card` without `overflow-hidden`.
 */
export const CardFooter = ({
	sticky,
	justify = "end",
	className,
	children,
	...rest
}: CardFooterProps) => {
	const stick = sticky ? " sticky bottom-0" : "";
	const extra = className ? ` ${className}` : "";

	return (
		<div
			className={`flex ${JUSTIFY[justify]} gap-2 border-t border-border bg-surface-2 px-4 py-3${stick}${extra}`}
			{...rest}
		>
			{children}
		</div>
	);
};
