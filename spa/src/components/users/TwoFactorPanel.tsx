import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ShieldCheck } from "lucide-react";

import { authApi, type TwoFactorSetup } from "@/auth/endpoints";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

/**
 * Profile → Security TOTP manager.
 *
 *   - When disabled: "Enable" kicks off the enrollment ceremony — fetches a
 *     fresh secret + QR from the server, shows both the scannable image and the
 *     manual key, then verifies a code before the secret is stored server-side.
 *   - When enabled: "Disable" requires a valid current code (defends a hijacked
 *     session from silently dropping the second factor).
 *
 * The pending secret lives in component state during enrollment and is posted
 * back on verify — the server only persists it once a matching code proves the
 * authenticator is set up.
 */

interface TwoFactorPanelProps {
	enabled: boolean;
}

export const TwoFactorPanel = ({ enabled }: TwoFactorPanelProps) => {
	const queryClient = useQueryClient();
	const [setup, setSetup] = useState<TwoFactorSetup | null>(null);
	const [enableCode, setEnableCode] = useState("");
	const [disabling, setDisabling] = useState(false);
	const [disableCode, setDisableCode] = useState("");

	const invalidate = () => {
		queryClient.invalidateQueries({ queryKey: ["users", "me"] });
	};

	const setupMutation = useMutation({
		mutationFn: () => authApi.twoFactorSetup(),
		onSuccess: (data) => {
			setSetup(data);
			setEnableCode("");
		},
		onError: (err: unknown) => {
			toast.error(describeError(err, "Could not start 2FA setup"));
		},
	});

	const enableMutation = useMutation({
		mutationFn: () => authApi.enableTwoFactor(setup!.secret, enableCode.trim()),
		onSuccess: () => {
			invalidate();
			setSetup(null);
			setEnableCode("");
			toast.success("Two-factor authentication enabled");
		},
		onError: (err: unknown) => {
			toast.error(describeError(err, "Could not enable two-factor authentication"));
		},
	});

	const disableMutation = useMutation({
		mutationFn: () => authApi.disableTwoFactor(disableCode.trim()),
		onSuccess: () => {
			invalidate();
			setDisabling(false);
			setDisableCode("");
			toast.success("Two-factor authentication disabled");
		},
		onError: (err: unknown) => {
			toast.error(describeError(err, "Could not disable two-factor authentication"));
		},
	});

	return (
		<section className="rounded-xl border border-border bg-surface">
			<header className="flex items-center gap-2 border-b border-border bg-surface-2 px-4 py-3">
				<ShieldCheck size={14} className="text-text-3" />
				<h3 className="text-[12.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
					Two-factor authentication
				</h3>
			</header>

			<div className="space-y-3 p-4 text-[12.5px]">
				<div className="flex items-center justify-between gap-3">
					<span
						className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium ${
							enabled ? "bg-success-bg text-success" : "bg-info-bg text-info"
						}`}
					>
						<span className="h-1.5 w-1.5 rounded-full bg-current" />
						{enabled ? "Enabled" : "Not enabled"}
					</span>

					{enabled && !disabling && (
						<button
							type="button"
							onClick={() => {
								setDisabling(true);
								setDisableCode("");
							}}
							className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-text-2 hover:bg-hover hover:text-danger"
						>
							Disable
						</button>
					)}

					{!enabled && !setup && (
						<button
							type="button"
							onClick={() => setupMutation.mutate()}
							disabled={setupMutation.isPending}
							className="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
						>
							{setupMutation.isPending ? "Starting…" : "Enable"}
						</button>
					)}
				</div>

				{!enabled && !setup && (
					<p className="text-text-3">
						Add a one-time code from an authenticator app (Google Authenticator,
						1Password, Authy) as a second step after your password.
					</p>
				)}

				{enabled && !disabling && (
					<p className="text-text-3">
						You'll be prompted for a one-time code from your authenticator app after
						entering your password.
					</p>
				)}

				{!enabled && setup && (
					<div className="rounded-md border border-border bg-surface-2 p-4">
						<p className="mb-3 text-text-2">
							Scan this QR code with your authenticator app, or enter the key
							manually, then type the 6-digit code it shows to confirm.
						</p>

						<div className="flex flex-col gap-4 sm:flex-row sm:items-start">
							<img
								src={setup.qr_image}
								alt="Two-factor QR code"
								className="h-40 w-40 shrink-0 rounded-md border border-border bg-white p-2"
							/>

							<div className="min-w-0 flex-1 space-y-3">
								<div>
									<span className="mb-1 block text-[11.5px] font-medium text-text-3">
										Manual entry key
									</span>
									<code className="block break-all rounded border border-border bg-surface px-2 py-1.5 text-[12px] text-text-2">
										{setup.secret}
									</code>
								</div>

								<label className="block">
									<span className="mb-1 block text-[12px] font-medium text-text-2">
										Verification code
									</span>
									<input
										type="text"
										inputMode="numeric"
										autoComplete="one-time-code"
										value={enableCode}
										onChange={(e) => setEnableCode(e.target.value)}
										placeholder="123456"
										autoFocus
										className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] tracking-[0.2em] focus:outline-none focus:ring-1 focus:ring-accent-ring"
									/>
								</label>

								<div className="flex justify-end gap-2">
									<button
										type="button"
										onClick={() => {
											setSetup(null);
											setEnableCode("");
										}}
										disabled={enableMutation.isPending}
										className="rounded-md border border-border bg-surface px-3 py-1.5 hover:bg-hover"
									>
										Cancel
									</button>
									<button
										type="button"
										onClick={() => enableMutation.mutate()}
										disabled={
											enableMutation.isPending ||
											enableCode.trim().length === 0
										}
										className="rounded-md bg-accent px-3 py-1.5 font-medium text-accent-fg disabled:opacity-50 hover:bg-accent-hover"
									>
										{enableMutation.isPending
											? "Verifying…"
											: "Verify & enable"}
									</button>
								</div>
							</div>
						</div>
					</div>
				)}

				{enabled && disabling && (
					<div className="rounded-md border border-border bg-surface-2 p-4">
						<label className="block">
							<span className="mb-1 block text-[12px] font-medium text-text-2">
								Enter a current code to confirm
							</span>
							<input
								type="text"
								inputMode="numeric"
								autoComplete="one-time-code"
								value={disableCode}
								onChange={(e) => setDisableCode(e.target.value)}
								placeholder="123456"
								autoFocus
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] tracking-[0.2em] focus:outline-none focus:ring-1 focus:ring-accent-ring"
							/>
						</label>

						<div className="mt-3 flex justify-end gap-2">
							<button
								type="button"
								onClick={() => {
									setDisabling(false);
									setDisableCode("");
								}}
								disabled={disableMutation.isPending}
								className="rounded-md border border-border bg-surface px-3 py-1.5 hover:bg-hover"
							>
								Cancel
							</button>
							<button
								type="button"
								onClick={() => disableMutation.mutate()}
								disabled={
									disableMutation.isPending || disableCode.trim().length === 0
								}
								className="rounded-md bg-danger px-3 py-1.5 font-medium text-white disabled:opacity-50"
							>
								{disableMutation.isPending ? "Disabling…" : "Disable 2FA"}
							</button>
						</div>
					</div>
				)}
			</div>
		</section>
	);
};

const describeError = (err: unknown, fallback: string): string => {
	if (err instanceof ApiError) {
		return err.message || fallback;
	}

	if (err instanceof Error) {
		return err.message || fallback;
	}

	return fallback;
};
