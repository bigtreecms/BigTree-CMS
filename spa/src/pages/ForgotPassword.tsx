import { Link } from "react-router-dom";
import { useForm } from "react-hook-form";
import { useState } from "react";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";

import { authApi } from "@/auth/endpoints";
import { AuthCard } from "@/components/ui/AuthCard";
import { Field } from "@/components/ui/Field";

const schema = z.object({
	email: z.string().email("Enter a valid email"),
});

type FormValues = z.infer<typeof schema>;

/**
 * Forgot-password request screen. Always lands on the same confirmation
 * regardless of whether the email exists — the server is deliberately
 * non-committal (204 either way) so this screen can't be used to probe
 * which accounts are real.
 */
export const ForgotPassword = () => {
	const [sent, setSent] = useState(false);
	const [serverError, setServerError] = useState<string | null>(null);

	const form = useForm<FormValues>({
		resolver: zodResolver(schema),
		defaultValues: { email: "" },
	});

	const onSubmit = async ({ email }: FormValues) => {
		setServerError(null);

		try {
			await authApi.forgotPassword(email);
			setSent(true);
		} catch {
			setServerError("Could not reach the server. Please try again.");
		}
	};

	return (
		<AuthCard
			title="Reset your password"
			subtitle="We'll email you a link to choose a new one."
		>
			{serverError && (
				<div className="mb-3 rounded-md border border-danger/30 bg-danger-bg px-3 py-2 text-[12.5px] text-danger">
					{serverError}
				</div>
			)}

			{sent ? (
				<div className="space-y-3">
					<p className="text-[12.5px] text-text-2">
						If that email is on file, a reset link is on its way. The link expires in
						one hour.
					</p>
					<Link
						to="/login"
						className="block w-full rounded-md border border-border bg-surface px-3 py-2 text-center text-[13px] hover:bg-hover"
					>
						Back to sign in
					</Link>
				</div>
			) : (
				<form onSubmit={form.handleSubmit(onSubmit)} className="space-y-3">
					<Field label="Email" error={form.formState.errors.email?.message}>
						<input
							type="email"
							autoComplete="email"
							autoFocus
							{...form.register("email")}
							className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-[13px] outline-none transition-colors focus:border-accent"
						/>
					</Field>

					<button
						type="submit"
						disabled={form.formState.isSubmitting}
						className="mt-1 w-full rounded-md bg-accent px-3 py-2 text-[13px] font-medium text-accent-fg transition-colors hover:bg-accent-hover disabled:opacity-60"
					>
						{form.formState.isSubmitting ? "Sending…" : "Email me a reset link"}
					</button>

					<Link
						to="/login"
						className="block text-center text-[12px] text-text-3 hover:text-text-2"
					>
						Back to sign in
					</Link>
				</form>
			)}
		</AuthCard>
	);
};
