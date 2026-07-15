import type { ReactNode } from "react";

interface PageContainerProps {
	children: ReactNode;
	className?: string;
	/** Content width tier. Default `wide`. */
	width?: "wide" | "medium" | "narrow" | "xwide";
}

const widthClass: Record<NonNullable<PageContainerProps["width"]>, string> = {
	wide: "max-w-screen-2xl",
	medium: "max-w-5xl",
	narrow: "max-w-3xl",
	xwide: "max-w-7xl",
};

/**
 * Centered, fixed-width page content column. Wraps every screen's body in the
 * shared `mx-auto px-6 py-4` gutter and one of four intentional content widths,
 * so the layout contract lives in one place instead of a copy-pasted magic
 * string on each page.
 */
export const PageContainer = ({ width = "wide", className, children }: PageContainerProps) => {
	return (
		<div
			className={`mx-auto ${widthClass[width]} px-6 py-4${className ? ` ${className}` : ""}`}
		>
			{children}
		</div>
	);
};
