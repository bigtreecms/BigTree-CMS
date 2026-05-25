import * as Dialog from "@radix-ui/react-dialog";

interface ConfirmDialogProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	title: string;
	description: string;
	confirmLabel: string;
	cancelLabel?: string;
	onConfirm: () => void;
	variant?: "default" | "danger";
}

export const ConfirmDialog = ({
	open,
	onOpenChange,
	title,
	description,
	confirmLabel,
	cancelLabel = "Cancel",
	onConfirm,
	variant = "default",
}: ConfirmDialogProps) => {
	const handleConfirm = () => {
		onConfirm();
		onOpenChange(false);
	};

	return (
		<Dialog.Root open={open} onOpenChange={onOpenChange}>
			<Dialog.Portal>
				<Dialog.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-[1px]" />
				<Dialog.Content className="fixed left-1/2 top-1/2 z-50 w-[min(400px,90vw)] -translate-x-1/2 -translate-y-1/2 rounded-xl border border-border bg-surface p-6 shadow-lg focus:outline-none">
					<div className="text-[15px] font-semibold tracking-[-0.01em]">{title}</div>

					<div className="mt-2 text-[13px] text-text-2">{description}</div>

					<div className="mt-6 flex justify-end gap-3">
						<button
							type="button"
							onClick={() => onOpenChange(false)}
							className="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-border bg-surface px-4 py-1.5 text-[12.5px] font-medium text-text-2 transition-colors hover:border-border-strong hover:bg-hover"
						>
							{cancelLabel}
						</button>

						<button
							type="button"
							onClick={handleConfirm}
							className={
								variant === "danger"
									? "inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-danger px-4 py-1.5 text-[12.5px] font-medium text-white transition-colors hover:bg-danger/90"
									: "inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-accent px-4 py-1.5 text-[12.5px] font-medium text-accent-fg transition-colors hover:bg-accent-hover"
							}
						>
							{confirmLabel}
						</button>
					</div>
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
