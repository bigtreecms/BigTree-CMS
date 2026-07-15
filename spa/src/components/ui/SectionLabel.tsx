import type { ElementType, HTMLAttributes, ReactNode } from "react";

export type SectionLabelSize = "xs" | "sm" | "md";

interface SectionLabelProps extends HTMLAttributes<HTMLElement> {
	/** Trailing content (a count, an action) pushed to the right edge of the row. */
	actions?: ReactNode;
	/**
	 * Element to render. Defaults to `div`; pass a heading (`h3`/`h4`) when the
	 * label introduces a `section` so it carries semantic weight for assistive tech.
	 */
	as?: ElementType;
	children: ReactNode;
	/** Forward `for` attribute when rendered as a `<label>`. */
	htmlFor?: string;
	/** Leading icon node; switches the box to a `flex` row so the icon and text align. */
	icon?: ReactNode;
	/** Text size: `xs` (text-[10.5px]), `sm` (text-[11px]), or `md` (text-[12px], default). */
	size?: SectionLabelSize;
}

/**
 * The small uppercase "overline" that titles a settings group, panel section, or
 * field cluster — `font-semibold uppercase tracking-[0.06em] text-text-3`. This is
 * the single source of truth for that treatment, which used to be copy-pasted (with
 * drift) into 60+ files. Token-driven so light/dark both read correctly. Pass an
 * `icon` or `actions` for the flex variant; keep margins/padding in `className`.
 */

const sizeClassName: Record<SectionLabelSize, string> = {
	xs: "text-[10.5px]",
	sm: "text-[11px]",
	md: "text-[12px]",
};

export const SectionLabel = ({
	children,
	size = "md",
	as: Tag = "div",
	icon,
	actions,
	className,
	...rest
}: SectionLabelProps) => {
	const base = `font-semibold uppercase tracking-[0.06em] text-text-3 ${sizeClassName[size]}`;
	const extra = className ? ` ${className}` : "";

	if (actions) {
		return (
			<Tag {...rest} className={`flex items-center justify-between gap-2 ${base}${extra}`}>
				<span className="flex items-center gap-1.5">
					{icon}
					{children}
				</span>
				{actions}
			</Tag>
		);
	}

	if (icon) {
		return (
			<Tag {...rest} className={`flex items-center gap-1.5 ${base}${extra}`}>
				{icon}
				{children}
			</Tag>
		);
	}

	return (
		<Tag {...rest} className={`${base}${extra}`}>
			{children}
		</Tag>
	);
};
