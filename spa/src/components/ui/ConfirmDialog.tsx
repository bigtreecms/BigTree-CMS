import { Button } from "./Button";
import { Modal } from "./Modal";

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
		<Modal
			open={open}
			onOpenChange={onOpenChange}
			title={title}
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
		/>
	);
};
