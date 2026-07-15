import { Link, useNavigate, useParams } from "react-router-dom";
import { useForm } from "react-hook-form";
import { useState } from "react";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";

import { ApiError } from "@/types/api";
import { authApi } from "@/auth/endpoints";
import { Alert } from "@/components/ui/Alert";
import { AuthCard } from "@/components/ui/AuthCard";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { TextInput } from "@/components/ui/TextInput";

const schema = z
	.object({
		password: z.string().min(8, "At least 8 characters"),
		confirm: z.string(),
	})
	.refine((v) => v.password === v.confirm, {
		path: ["confirm"],
		message: "Passwords don't match",
	});

type FormValues = z.infer<typeof schema>;

/**
 * Reset-password screen, reached from the emailed link
 * (/login/reset/:token). Posts the token + new password; the server enforces
 * the password policy (surfaced via the weak_password error) and the token's
 * one-hour expiry (invalid_token → offer a fresh request). Success bounces to
 * the login screen with a confirmation note.
 */
export const ResetPassword = () => {
	const { token } = useParams<{ token: string }>();
	const navigate = useNavigate();
	const [serverError, setServerError] = useState<string | null>(null);
	const [tokenRejected, setTokenRejected] = useState(false);

	const form = useForm<FormValues>({
		resolver: zodResolver(schema),
		defaultValues: { password: "", confirm: "" },
	});

	const onSubmit = async ({ password }: FormValues) => {
		if (!token) {
			return;
		}

		setServerError(null);

		try {
			await authApi.resetPassword(token, password);
			navigate("/login", { replace: true, state: { resetSuccess: true } });
		} catch (err) {
			if (err instanceof ApiError) {
				if (err.code === "invalid_token") {
					setTokenRejected(true);

					return;
				}

				setServerError(
					err.code === "weak_password"
						? "That password doesn't meet the site's requirements. Try a longer password with mixed case, numbers, and symbols."
						: err.message
				);
			} else {
				setServerError("Could not reach the server. Please try again.");
			}
		}
	};

	if (!token || tokenRejected) {
		return (
			<AuthCard subtitle="This link is no longer valid." title="Reset link expired">
				<div className="space-y-3">
					<p className="text-[12.5px] text-text-2">
						Reset links expire after one hour and can only be used once. Request a new
						one to continue.
					</p>
					<Link
						className="block w-full rounded-md bg-accent px-3 py-2 text-center text-[13px] font-medium text-accent-fg hover:bg-accent-hover"
						to="/login/forgot"
					>
						Request a new link
					</Link>
				</div>
			</AuthCard>
		);
	}

	return (
		<AuthCard subtitle="Then sign in with it right away." title="Choose a new password">
			{serverError && (
				<Alert className="mb-3" tone="danger">
					{serverError}
				</Alert>
			)}

			<form className="space-y-3" onSubmit={form.handleSubmit(onSubmit)}>
				<Field error={form.formState.errors.password?.message} label="New password">
					<TextInput
						autoFocus
						autoComplete="new-password"
						type="password"
						{...form.register("password")}
					/>
				</Field>

				<Field error={form.formState.errors.confirm?.message} label="Confirm password">
					<TextInput
						autoComplete="new-password"
						type="password"
						{...form.register("confirm")}
					/>
				</Field>

				<Button
					className="mt-1 w-full justify-center"
					disabled={form.formState.isSubmitting}
					size="lg"
					type="submit"
					variant="primary"
				>
					{form.formState.isSubmitting ? "Saving…" : "Set new password"}
				</Button>
			</form>
		</AuthCard>
	);
};
