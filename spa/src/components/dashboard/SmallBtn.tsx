import type { ReactNode } from "react";

interface SmallBtnProps {
	children: ReactNode;
	onClick?: () => void;
}

/** Compact secondary button used in card headers. */
export const SmallBtn = ({ children, onClick }: SmallBtnProps) => {
	return (
		<button
			type="button"
			onClick={onClick}
			className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 py-1 text-[12px] font-medium text-text-2 transition-colors hover:border-border-strong hover:bg-hover"
		>
			{children}
		</button>
	);
};
