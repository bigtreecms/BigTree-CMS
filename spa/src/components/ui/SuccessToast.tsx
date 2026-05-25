import { Check, X } from "lucide-react";

interface SuccessToastProps {
	title: string;
	description?: string;
	actionLabel?: string;
	onAction?: () => void;
	onClose: () => void;
}

export const SuccessToast = ({
	title,
	description,
	actionLabel,
	onAction,
	onClose,
}: SuccessToastProps) => {
	return (
		<div className="flex w-80 items-start gap-3 overflow-hidden rounded-xl border border-border bg-surface shadow-lg">
			{/* Green left accent bar */}
			<div className="w-1 self-stretch bg-success" />

			<div className="flex flex-1 items-start gap-3 py-3 pr-2">
				{/* Check icon in soft circle */}
				<div className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success-bg">
					<Check size={15} className="text-success" />
				</div>

				<div className="min-w-0 flex-1 text-[13px]">
					<div className="font-semibold tracking-[-0.01em] text-text">{title}</div>
					{description && <div className="mt-0.5 text-text-2">{description}</div>}
					{actionLabel && onAction && (
						<button
							type="button"
							onClick={onAction}
							className="mt-1 text-success hover:underline"
						>
							{actionLabel}
						</button>
					)}
				</div>
			</div>

			{/* Close button */}
			<button
				type="button"
				onClick={onClose}
				className="mt-2 mr-2 text-text-3 hover:text-text"
				aria-label="Close notification"
			>
				<X size={16} />
			</button>
		</div>
	);
};
