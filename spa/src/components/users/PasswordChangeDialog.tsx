import { useEffect, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { Eye, EyeOff } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";
import { Modal } from "@/components/ui/Modal";
import { usersApi } from "@/api/endpoints/users";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

interface PasswordChangeDialogProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	userId: number;
	/** When true, asks for the user's current password before accepting the new one. */
	requireCurrent: boolean;
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
			if (err instanceof ApiError) {
				setError(err.message);

				return;
			}

			setError("Password change failed");
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
			open={open}
			onOpenChange={onOpenChange}
			title="Change password"
			description={
				requireCurrent
					? "Enter your current password, then choose a new one. You'll stay signed in on this device."
					: "Choose a new password for this user. They'll need to sign in again."
			}
		>
			<form onSubmit={submit} className="space-y-3">
				{requireCurrent && (
					<label className="block">
						<span className="mb-1 block text-[12px] font-medium text-text-2">
							Current password
						</span>
						<input
							type="password"
							autoComplete="current-password"
							value={current}
							onChange={(e) => setCurrent(e.target.value)}
							className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
						/>
					</label>
				)}

				<label className="block">
					<span className="mb-1 block text-[12px] font-medium text-text-2">
						New password
					</span>
					<div className="relative">
						<input
							type={showNext ? "text" : "password"}
							autoComplete="new-password"
							value={next}
							onChange={(e) => setNext(e.target.value)}
							className="w-full rounded-md border border-border bg-surface px-3 py-2 pr-9 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
						/>
						<IconButton
							onClick={() => setShowNext((v) => !v)}
							className="absolute right-2 top-1/2 -translate-y-1/2"
							label={showNext ? "Hide password" : "Show password"}
						>
							{showNext ? <EyeOff size={14} /> : <Eye size={14} />}
						</IconButton>
					</div>
					<p className="mt-1 text-[11.5px] text-text-3">
						Minimum 8 characters. Site security policy may require more.
					</p>
				</label>

				<label className="block">
					<span className="mb-1 block text-[12px] font-medium text-text-2">
						Confirm new password
					</span>
					<input
						type={showNext ? "text" : "password"}
						autoComplete="new-password"
						value={confirm}
						onChange={(e) => setConfirm(e.target.value)}
						className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
					/>
				</label>

				{error && (
					<div className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-[12.5px] text-danger">
						{error}
					</div>
				)}

				<div className="mt-4 flex justify-end gap-2">
					<Button variant="secondary" onClick={() => onOpenChange(false)}>
						Cancel
					</Button>
					<Button variant="primary" type="submit" disabled={mutation.isPending}>
						{mutation.isPending ? "Saving…" : "Change password"}
					</Button>
				</div>
			</form>
		</Modal>
	);
};
