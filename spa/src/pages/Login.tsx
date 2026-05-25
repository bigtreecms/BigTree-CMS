import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Navigate, useLocation, useNavigate } from "react-router-dom";
import { authApi } from "@/auth/endpoints";
import { useAuthStore } from "@/auth/store";
import { ApiError } from "@/types/api";

const schema = z.object({
	email: z.string().email("Enter a valid email"),
	password: z.string().min(1, "Password is required"),
	remember: z.boolean().optional(),
});

type FormValues = z.infer<typeof schema>;

interface LocationState {
	from?: string;
}

/**
 * Login screen — minimal first cut, will get the prototype's full styling
 * pass once we extract a reusable Input + Button + Card. For now it exercises
 * the auth flow end-to-end: submit → set session → redirect to original path.
 */
export function Login() {
	const navigate = useNavigate();
	const location = useLocation();
	const state = location.state as LocationState | null;
	const returnTo = state?.from ?? "/dashboard";
	const authenticated = useAuthStore((s) => !!s.accessToken);

	const [mfa, setMfa] = useState<{ token: string } | null>(null);
	const [serverError, setServerError] = useState<string | null>(null);

	const form = useForm<FormValues>({
		resolver: zodResolver(schema),
		defaultValues: { email: "", password: "", remember: false },
	});
	const mfaForm = useForm<{ code: string }>({ defaultValues: { code: "" } });

	if (authenticated) return <Navigate to={returnTo} replace />;

	async function onSubmit(values: FormValues) {
		setServerError(null);
		try {
			const result = await authApi.login(values.email, values.password, values.remember);
			if ("mfa_required" in result) {
				setMfa({ token: result.mfa_token });
				return;
			}
			navigate(returnTo, { replace: true });
		} catch (err) {
			handleSubmitError(err);
		}
	}

	async function onSubmitMfa({ code }: { code: string }) {
		if (!mfa) return;
		setServerError(null);
		try {
			await authApi.twoFactor(mfa.token, code);
			navigate(returnTo, { replace: true });
		} catch (err) {
			handleSubmitError(err);
		}
	}

	function handleSubmitError(err: unknown) {
		if (err instanceof ApiError) {
			const fieldErrors = err.fieldErrors();
			let bound = false;
			for (const [field, message] of Object.entries(fieldErrors)) {
				if (field === "email" || field === "password") {
					form.setError(field, { message });
					bound = true;
				}
			}
			if (!bound) setServerError(err.message);
		} else {
			setServerError("Could not reach the server.");
		}
	}

	return (
		<div className="grid min-h-screen place-items-center bg-bg px-4">
			<div className="w-full max-w-[360px] rounded-lg border border-border bg-surface p-6 shadow-md">
				<div className="mb-5 flex items-center gap-2.5">
					<div className="grid h-8 w-8 place-items-center rounded-md bg-accent text-accent-fg">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
							<path d="M12 2 4 12h4v8h8v-8h4L12 2Z" />
						</svg>
					</div>
					<div>
						<h1 className="text-[15px] font-semibold tracking-[-0.01em]">Sign in to BigTree</h1>
						<p className="text-[12px] text-text-3">Use your admin credentials.</p>
					</div>
				</div>

				{serverError && (
					<div className="mb-3 rounded-md border border-danger/30 bg-danger-bg px-3 py-2 text-[12.5px] text-danger">
						{serverError}
					</div>
				)}

				{!mfa ? (
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

						<Field label="Password" error={form.formState.errors.password?.message}>
							<input
								type="password"
								autoComplete="current-password"
								{...form.register("password")}
								className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-[13px] outline-none transition-colors focus:border-accent"
							/>
						</Field>

						<label className="flex cursor-pointer items-center gap-2 text-[12.5px] text-text-2">
							<input type="checkbox" {...form.register("remember")} />
							Remember me
						</label>

						<button
							type="submit"
							disabled={form.formState.isSubmitting}
							className="mt-1 w-full rounded-md bg-accent px-3 py-2 text-[13px] font-medium text-accent-fg transition-colors hover:bg-accent-hover disabled:opacity-60"
						>
							{form.formState.isSubmitting ? "Signing in…" : "Sign in"}
						</button>
					</form>
				) : (
					<form onSubmit={mfaForm.handleSubmit(onSubmitMfa)} className="space-y-3">
						<p className="text-[12.5px] text-text-2">
							Enter the 6-digit code from your authenticator app.
						</p>
						<input
							type="text"
							inputMode="numeric"
							pattern="[0-9]*"
							autoFocus
							maxLength={6}
							{...mfaForm.register("code", { required: true })}
							className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-center font-mono text-[14px] tracking-widest outline-none focus:border-accent"
						/>
						<button
							type="submit"
							disabled={mfaForm.formState.isSubmitting}
							className="w-full rounded-md bg-accent px-3 py-2 text-[13px] font-medium text-accent-fg transition-colors hover:bg-accent-hover disabled:opacity-60"
						>
							{mfaForm.formState.isSubmitting ? "Verifying…" : "Verify"}
						</button>
						<button
							type="button"
							onClick={() => setMfa(null)}
							className="w-full text-[12px] text-text-3 hover:text-text-2"
						>
							Back
						</button>
					</form>
				)}
			</div>
		</div>
	);
}

function Field({
	label,
	error,
	children,
}: {
	label: string;
	error?: string;
	children: React.ReactNode;
}) {
	return (
		<label className="block">
			<span className="mb-1 block text-[12px] font-medium text-text-2">{label}</span>
			{children}
			{error && <span className="mt-1 block text-[11.5px] text-danger">{error}</span>}
		</label>
	);
}
