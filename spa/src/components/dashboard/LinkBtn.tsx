import type { ReactNode } from "react";

interface LinkBtnProps {
	children: ReactNode;
	onClick?: () => void;
}

/** Small inline accent-colored link button — matches prototype's `.link`. */
export const LinkBtn = ({ children, onClick }: LinkBtnProps) => {
	return (
		<button
			type="button"
			onClick={onClick}
			className="-mx-1.5 -my-1 inline-flex items-center gap-0.5 rounded-[5px] bg-transparent px-1.5 py-1 text-[12px] font-medium text-accent transition-colors hover:bg-accent-soft"
		>
			{children}
		</button>
	);
};
