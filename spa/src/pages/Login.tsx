import { Link, Navigate, useLocation, useNavigate } from "react-router-dom";

import { ApiError } from "@/types/api";
import { authApi, type TwoFactorSetup } from "@/auth/endpoints";
import { useAuthStore } from "@/auth/store";
import { useForm } from "react-hook-form";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { Fingerprint } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { AuthCard } from "@/components/ui/AuthCard";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { TextInput } from "@/components/ui/TextInput";
import { TwoFactorEnrollForm } from "@/components/users/TwoFactorEnrollForm";
import { isWebAuthnSupported } from "@/lib/webauthn";
import { queryKeys } from "@/lib/queryKeys";
import { postLoginPath } from "@/lib/postLoginPath";

const schema = z.object({
	email: z.string().email("Enter a valid email"),
	password: z.string().min(1, "Password is required"),
	remember: z.boolean().optional(),
});

type FormValues = z.infer<typeof schema>;

interface LocationState {
	from?: string;
	/** Set by the reset-password screen after a successful reset. */
	resetSuccess?: boolean;
}

/**
 * Login screen — auth flow end-to-end: submit → set session → redirect to
 * the original path (or pause on MFA / forced 2FA enrollment).
 */
export const Login = () => {
	const navigate = useNavigate();
	const location = useLocation();
	const state = location.state as LocationState | null;
	const returnTo = state?.from ?? "/dashboard";
	const authenticated = useAuthStore((s) => !!s.accessToken);
	const user = useAuthStore((s) => s.user);
	const destination = postLoginPath(user, returnTo);

	const [mfa, setMfa] = useState<{ token: string } | null>(null);
	const [enroll, setEnroll] = useState<{ token: string; setup: TwoFactorSetup } | null>(null);
	const [enrollCode, setEnrollCode] = useState("");
	const [enrollBusy, setEnrollBusy] = useState(false);
	const [serverError, setServerError] = useState<string | null>(null);
	const [passkeyBusy, setPasskeyBusy] = useState(false);
	const passkeySupported = isWebAuthnSupported();

	// Cosmetic only — the server clamps the remember flag regardless. If the
	// policy fetch fails we show the checkbox; worst case it's a no-op.
	const policyQuery = useQuery({
		queryKey: queryKeys.auth.loginPolicy(),
		queryFn: () => authApi.loginPolicy(),
		staleTime: 5 * 60 * 1000,
		retry: false,
	});
	const rememberDisabled = policyQuery.data?.remember_disabled ?? false;

	const form = useForm<FormValues>({
		resolver: zodResolver(schema),
		defaultValues: { email: "", password: "", remember: false },
	});
	const mfaForm = useForm<{ code: string }>({ defaultValues: { code: "" } });

	if (authenticated) {
		return <Navigate replace to={destination} />;
	}

	async function onSubmit(values: FormValues) {
		setServerError(null);
		try {
			const result = await authApi.login(values.email, values.password, values.remember);
			if ("mfa_required" in result) {
				setMfa({ token: result.mfa_token });
				return;
			}
			if ("two_factor_setup_required" in result) {
				// Policy mandates 2FA and this account hasn't enrolled — fetch the
				// ceremony payload and pause the login on the enrollment step.
				const setup = await authApi.twoFactorSetupRequired(result.setup_token);
				setEnroll({ token: result.setup_token, setup });
				setEnrollCode("");
				return;
			}
			// Prefer the user payload from this login (includes migrations_pending).
			const loggedIn = "user" in result ? result.user : useAuthStore.getState().user;
			navigate(postLoginPath(loggedIn, returnTo), { replace: true });
		} catch (err) {
			handleSubmitError(err);
		}
	}

	const onConfirmEnroll = async () => {
		if (!enroll || enrollBusy) {
			return;
		}

		setServerError(null);
		setEnrollBusy(true);

		try {
			await authApi.enableTwoFactorRequired(
				enroll.token,
				enroll.setup.secret,
				enrollCode.trim()
			);
			navigate(postLoginPath(useAuthStore.getState().user, returnTo), { replace: true });
		} catch (err) {
			handleSubmitError(err);
		} finally {
			setEnrollBusy(false);
		}
	};

	const onSubmitMfa = async ({ code }: { code: string }) => {
		if (!mfa) {
			return;
		}

		setServerError(null);
		try {
			await authApi.twoFactor(mfa.token, code);
			navigate(postLoginPath(useAuthStore.getState().user, returnTo), { replace: true });
		} catch (err) {
			handleSubmitError(err);
		}
	};

	const onPasskeySignIn = async () => {
		if (passkeyBusy) {
			return;
		}

		setServerError(null);
		setPasskeyBusy(true);

		try {
			await authApi.loginWithPasskey();
			navigate(postLoginPath(useAuthStore.getState().user, returnTo), { replace: true });
		} catch (err) {
			if (err instanceof DOMException) {
				// User cancelled / timed out / no matching credential — quiet failure.
				if (err.name === "NotAllowedError" || err.name === "AbortError") {
					return;
				}

				setServerError(err.message || "Passkey sign-in failed.");
			} else if (err instanceof ApiError) {
				setServerError(err.message || "Passkey sign-in failed.");
			} else {
				setServerError("Could not reach the server.");
			}
		} finally {
			setPasskeyBusy(false);
		}
	};

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
			if (!bound) {
				setServerError(err.message);
			}
		} else {
			setServerError("Could not reach the server.");
		}
	}

	return (
		<AuthCard
			subtitle={
				enroll
					? "Your organization requires a second factor to sign in."
					: "Use your admin credentials."
			}
			title={enroll ? "Set up two-factor authentication" : "Sign in to BigTree"}
			wide={!!enroll}
		>
			{state?.resetSuccess && !serverError && (
				<Alert className="mb-3" tone="success">
					Password updated. Sign in with your new password.
				</Alert>
			)}

			{serverError && (
				<Alert className="mb-3" tone="danger">
					{serverError}
				</Alert>
			)}

			{enroll ? (
				<div className="text-[12.5px]">
					<TwoFactorEnrollForm
						busy={enrollBusy}
						cancelLabel="Back"
						code={enrollCode}
						confirmLabel="Verify & sign in"
						setup={enroll.setup}
						onCancel={() => {
							setEnroll(null);
							setEnrollCode("");
							setServerError(null);
						}}
						onCodeChange={setEnrollCode}
						onConfirm={onConfirmEnroll}
					/>
				</div>
			) : !mfa ? (
				<form
					className="space-y-3"
					data-testid="login-form"
					onSubmit={form.handleSubmit(onSubmit)}
				>
					<Field error={form.formState.errors.email?.message} label="Email">
						<TextInput
							autoFocus
							autoComplete="email"
							data-testid="login-email"
							type="email"
							{...form.register("email")}
						/>
					</Field>

					<Field error={form.formState.errors.password?.message} label="Password">
						<TextInput
							autoComplete="current-password"
							data-testid="login-password"
							type="password"
							{...form.register("password")}
						/>
					</Field>

					<Link
						className="block text-right text-[12px] text-text-3 hover:text-text-2"
						to="/login/forgot"
					>
						Forgot password?
					</Link>

					{!rememberDisabled && (
						<Checkbox
							checked={form.watch("remember") ?? false}
							label="Remember me"
							onChange={(checked) => form.setValue("remember", checked)}
						/>
					)}

					<Button
						className="mt-1 w-full justify-center"
						data-testid="login-submit"
						disabled={form.formState.isSubmitting}
						size="lg"
						type="submit"
						variant="primary"
					>
						{form.formState.isSubmitting ? "Signing in…" : "Sign in"}
					</Button>

					{passkeySupported && (
						<>
							<div className="flex items-center gap-2 py-1 text-[11px] uppercase tracking-[0.06em] text-text-3">
								<span className="h-px flex-1 bg-border" />
								or
								<span className="h-px flex-1 bg-border" />
							</div>
							<Button
								className="w-full justify-center"
								disabled={passkeyBusy || form.formState.isSubmitting}
								icon={<Fingerprint size={14} />}
								size="lg"
								variant="secondary"
								onClick={onPasskeySignIn}
							>
								{passkeyBusy
									? "Waiting for authenticator…"
									: "Sign in with a passkey"}
							</Button>
						</>
					)}
				</form>
			) : (
				<form className="space-y-3" onSubmit={mfaForm.handleSubmit(onSubmitMfa)}>
					<p className="text-[12.5px] text-text-2">
						Enter the 6-digit code from your authenticator app.
					</p>
					<input
						autoFocus
						inputMode="numeric"
						maxLength={6}
						pattern="[0-9]*"
						type="text"
						{...mfaForm.register("code", { required: true })}
						className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-center font-mono text-[14px] tracking-widest outline-none focus:border-accent"
					/>
					<Button
						className="w-full justify-center"
						disabled={mfaForm.formState.isSubmitting}
						size="lg"
						type="submit"
						variant="primary"
					>
						{mfaForm.formState.isSubmitting ? "Verifying…" : "Verify"}
					</Button>
					<button
						className="w-full text-[12px] text-text-3 hover:text-text-2"
						type="button"
						onClick={() => setMfa(null)}
					>
						Back
					</button>
				</form>
			)}
		</AuthCard>
	);
};
