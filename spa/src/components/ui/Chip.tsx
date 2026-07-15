import type { ReactNode } from "react";

interface ChipProps {
	active: boolean;
	children: ReactNode;
	onClick: () => void;
}

export const Chip = ({ active, onClick, children }: ChipProps) => {
	return (
		<button
			className={`inline-flex cursor-pointer items-center gap-1 rounded-full border px-2 py-0.5 text-[11.5px] transition active:scale-[0.96] ${
				active
					? "border-accent-soft-2 bg-accent-soft text-accent"
					: "border-border bg-transparent text-text-3 hover:bg-hover"
			}`}
			data-active={active}
			type="button"
			onClick={onClick}
		>
			{children}
		</button>
	);
};
