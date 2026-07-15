import { Link } from "react-router-dom";
import { useForm } from "react-hook-form";
import { useState } from "react";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";

import { authApi } from "@/auth/endpoints";
import { Alert } from "@/components/ui/Alert";
import { AuthCard } from "@/components/ui/AuthCard";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { TextInput } from "@/components/ui/TextInput";

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
			subtitle="We'll email you a link to choose a new one."
			title="Reset your password"
		>
			{serverError && (
				<Alert className="mb-3" tone="danger">
					{serverError}
				</Alert>
			)}

			{sent ? (
				<div className="space-y-3">
					<p className="text-[12.5px] text-text-2">
						If that email is on file, a reset link is on its way. The link expires in
						one hour.
					</p>
					<Link
						className="block w-full rounded-md border border-border bg-surface px-3 py-2 text-center text-[13px] hover:bg-hover"
						to="/login"
					>
						Back to sign in
					</Link>
				</div>
			) : (
				<form className="space-y-3" onSubmit={form.handleSubmit(onSubmit)}>
					<Field error={form.formState.errors.email?.message} label="Email">
						<TextInput
							autoFocus
							autoComplete="email"
							type="email"
							{...form.register("email")}
						/>
					</Field>

					<Button
						className="mt-1 w-full justify-center"
						disabled={form.formState.isSubmitting}
						size="lg"
						type="submit"
						variant="primary"
					>
						{form.formState.isSubmitting ? "Sending…" : "Email me a reset link"}
					</Button>

					<Link
						className="block text-center text-[12px] text-text-3 hover:text-text-2"
						to="/login"
					>
						Back to sign in
					</Link>
				</form>
			)}
		</AuthCard>
	);
};
