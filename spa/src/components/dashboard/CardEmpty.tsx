import type { LucideIcon } from "lucide-react";

interface CardEmptyProps {
	icon: LucideIcon;
	label: string;
}

export const CardEmpty = ({ icon: Icon, label }: CardEmptyProps) => {
	return (
		<div className="flex items-center gap-2.5 rounded-md border border-dashed border-border bg-surface-2 px-3.5 py-[18px] text-[13px] text-text-3">
			<Icon size={20} className="text-text-4" />
			<span>{label}</span>
		</div>
	);
};
