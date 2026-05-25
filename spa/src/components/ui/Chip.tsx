import type { ReactNode } from "react";

interface ChipProps {
	active: boolean;
	onClick: () => void;
	children: ReactNode;
}

export const Chip = ({ active, onClick, children }: ChipProps) => {
	return (
		<button
			type="button"
			onClick={onClick}
			data-active={active}
			className={`inline-flex cursor-pointer items-center gap-1 rounded-full border px-2 py-0.5 text-[11.5px] transition-colors ${
				active
					? "border-accent-soft-2 bg-accent-soft text-accent"
					: "border-border bg-transparent text-text-3 hover:bg-hover"
			}`}
		>
			{children}
		</button>
	);
};
