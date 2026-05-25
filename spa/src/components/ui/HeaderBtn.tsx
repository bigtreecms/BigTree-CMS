import type { ReactNode } from "react";

interface HeaderBtnProps {
	icon: ReactNode;
	primary?: boolean;
	children: ReactNode;
}

export const HeaderBtn = ({ icon, primary, children }: HeaderBtnProps) => {
	return (
		<button
			type="button"
			className={
				primary
					? "inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-accent px-2.5 py-1.5 text-[12.5px] font-medium text-accent-fg transition-colors hover:bg-accent-hover"
					: "inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 py-1.5 text-[12.5px] font-medium text-text-2 transition-colors hover:border-border-strong hover:bg-hover"
			}
		>
			{icon}
			{children}
		</button>
	);
};
