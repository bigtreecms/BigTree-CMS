import type { ReactNode } from "react";

import { EmptyState } from "./EmptyState";

interface LoadingTextProps {
	/**
	 * Wrap the text in the dashed `surface-2` box used for in-field async
	 * placeholders (the compact `EmptyState size="sm"` container). Bare muted
	 * text otherwise.
	 */
	boxed?: boolean;
	children?: ReactNode;
	/** Layout-only classes appended to the wrapper. */
	className?: string;
	/** The loading message. Defaults to "Loading…". `children` overrides it. */
	label?: ReactNode;
	/** `md` (default) is `text-[12.5px]`; `sm` is `text-[11.5px]`. */
	size?: "sm" | "md";
}

/**
 * The spinner-less muted "Loading…" text for an async region that hasn't
 * resolved yet — the inline counterpart to `<Loading>` (which carries a
 * spinner and is the right choice for big centered placeholders). Pass `boxed`
 * for the dashed in-field placeholder treatment.
 */
export const LoadingText = ({
	label,
	size = "md",
	boxed,
	className,
	children,
}: LoadingTextProps) => {
	const content = children ?? label ?? "Loading…";

	if (boxed) {
		return (
			<EmptyState dashed className={className} role="status" size="sm">
				{content}
			</EmptyState>
		);
	}

	const textSize = size === "sm" ? "text-[11.5px]" : "text-[12.5px]";
	const extra = className ? ` ${className}` : "";

	return (
		<span className={`text-text-3 ${textSize}${extra}`} role="status">
			{content}
		</span>
	);
};
