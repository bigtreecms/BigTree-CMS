import type { ElementType, ReactNode } from "react";

interface MonoTextProps {
	/** Element to render. Defaults to `span`; pass `div` inside table cells. */
	as?: ElementType;
	children: ReactNode;
	/**
	 * Layout-only classes the caller still owns — the `ml-1`, `w-32 shrink-0`,
	 * or `block` that varies per context. The base carries none.
	 */
	className?: string;
	title?: string;
	/** Clip overflow with an ellipsis. Default `true`. */
	truncate?: boolean;
}

/**
 * The muted monospace token used to render an ID, hash, file path, or route
 * string beneath its label — `font-mono text-[11px] text-text-3`. This is the
 * single source of truth for that treatment, which used to be copy-pasted into
 * 15+ files. Truncates by default; keep any width/margin/`block` in `className`.
 */

export const MonoText = ({
	children,
	as: Tag = "span",
	truncate = true,
	className,
	title,
}: MonoTextProps) => {
	const base = "font-mono text-[11px] text-text-3";
	const trunc = truncate ? " truncate" : "";
	const extra = className ? ` ${className}` : "";

	return (
		<Tag className={`${base}${trunc}${extra}`} title={title}>
			{children}
		</Tag>
	);
};
