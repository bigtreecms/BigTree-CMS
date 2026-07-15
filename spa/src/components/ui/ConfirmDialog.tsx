import { Button } from "./Button";
import { Modal } from "./Modal";

interface ConfirmDialogProps {
	cancelLabel?: string;
	confirmLabel: string;
	description: string;
	onConfirm: () => void;
	onOpenChange: (open: boolean) => void;
	open: boolean;
	title: string;
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
		<Modal
			description={description}
			footer={
				<>
					<Button variant="secondary" onClick={() => onOpenChange(false)}>
						{cancelLabel}
					</Button>

					<Button
						variant={variant === "danger" ? "danger" : "primary"}
						onClick={handleConfirm}
					>
						{confirmLabel}
					</Button>
				</>
			}
			open={open}
			title={title}
			onOpenChange={onOpenChange}
		/>
	);
};
