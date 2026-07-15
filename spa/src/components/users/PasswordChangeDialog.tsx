import { useEffect, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { Eye, EyeOff } from "lucide-react";

import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { IconButton } from "@/components/ui/IconButton";
import { Modal } from "@/components/ui/Modal";
import { TextInput } from "@/components/ui/TextInput";
import { usersApi } from "@/api/endpoints/users";
import { describeApiError } from "@/lib/errorHandling";
import { toast } from "@/lib/toast";

interface PasswordChangeDialogProps {
	onOpenChange: (open: boolean) => void;
	open: boolean;
	/** When true, asks for the user's current password before accepting the new one. */
	requireCurrent: boolean;
	userId: number;
}

/**
 * POST /users/{id}/password. When the caller is changing their own password
 * the server requires `current_password`; when an admin changes someone else's
 * it's ignored. The dialog gates that field based on `requireCurrent`.
 */
export const PasswordChangeDialog = ({
	open,
	onOpenChange,
	userId,
	requireCurrent,
}: PasswordChangeDialogProps) => {
	const [current, setCurrent] = useState("");
	const [next, setNext] = useState("");
	const [confirm, setConfirm] = useState("");
	const [showNext, setShowNext] = useState(false);
	const [error, setError] = useState<string | null>(null);

	useEffect(() => {
		if (!open) {
			setCurrent("");
			setNext("");
			setConfirm("");
			setShowNext(false);
			setError(null);
		}
	}, [open]);

	const mutation = useMutation({
		mutationFn: () =>
			usersApi.password(userId, {
				new_password: next,
				current_password: requireCurrent ? current : undefined,
			}),
		onSuccess: () => {
			toast.success("Password changed");
			onOpenChange(false);
		},
		onError: (err: unknown) => {
			setError(describeApiError(err, "Password change failed"));
		},
	});

	const submit = (event: React.FormEvent) => {
		event.preventDefault();
		setError(null);

		if (next.length < 8) {
			setError("Password must be at least 8 characters.");

			return;
		}

		if (next !== confirm) {
			setError("New password and confirmation don't match.");

			return;
		}

		if (requireCurrent && !current) {
			setError("Enter your current password.");

			return;
		}

		mutation.mutate();
	};

	return (
		<Modal
			description={
				requireCurrent
					? "Enter your current password, then choose a new one. You'll stay signed in on this device."
					: "Choose a new password for this user. They'll need to sign in again."
			}
			open={open}
			title="Change password"
			onOpenChange={onOpenChange}
		>
			<form className="space-y-3" onSubmit={submit}>
				{requireCurrent && (
					<Field label="Current password">
						<TextInput
							autoComplete="current-password"
							type="password"
							value={current}
							onChange={(e) => setCurrent(e.target.value)}
						/>
					</Field>
				)}

				<Field
					hint="Minimum 8 characters. Site security policy may require more."
					label="New password"
				>
					<div className="relative">
						<TextInput
							autoComplete="new-password"
							className="pr-9"
							type={showNext ? "text" : "password"}
							value={next}
							onChange={(e) => setNext(e.target.value)}
						/>
						<IconButton
							className="absolute right-2 top-1/2 -translate-y-1/2"
							label={showNext ? "Hide password" : "Show password"}
							onClick={() => setShowNext((v) => !v)}
						>
							{showNext ? <EyeOff size={14} /> : <Eye size={14} />}
						</IconButton>
					</div>
				</Field>

				<Field label="Confirm new password">
					<TextInput
						autoComplete="new-password"
						type={showNext ? "text" : "password"}
						value={confirm}
						onChange={(e) => setConfirm(e.target.value)}
					/>
				</Field>

				{error && <Alert tone="danger">{error}</Alert>}

				<div className="mt-4 flex justify-end gap-2">
					<Button variant="secondary" onClick={() => onOpenChange(false)}>
						Cancel
					</Button>
					<Button
						loading={mutation.isPending}
						loadingLabel="Saving…"
						type="submit"
						variant="primary"
					>
						Change password
					</Button>
				</div>
			</form>
		</Modal>
	);
};
