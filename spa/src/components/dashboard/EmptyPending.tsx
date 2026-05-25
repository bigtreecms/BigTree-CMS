interface EmptyPendingProps {
	label: string;
}

export const EmptyPending = ({ label }: EmptyPendingProps) => {
	return (
		<div className="flex h-full min-h-[110px] items-center rounded-md border border-dashed border-border bg-surface-2 p-3.5 text-[12.5px] leading-[1.55] text-text-3">
			{label}
		</div>
	);
};
