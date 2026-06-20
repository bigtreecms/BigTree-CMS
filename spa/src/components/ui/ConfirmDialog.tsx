import * as Dialog from "@radix-ui/react-dialog";

import { Button } from "./Button";

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
						<Button variant="secondary" onClick={() => onOpenChange(false)}>
							{cancelLabel}
						</Button>

						<Button
							variant={variant === "danger" ? "danger" : "primary"}
							onClick={handleConfirm}
						>
							{confirmLabel}
						</Button>
					</div>
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
