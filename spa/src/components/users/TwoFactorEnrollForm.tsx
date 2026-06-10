import type { TwoFactorSetup } from "@/auth/endpoints";

interface TwoFactorEnrollFormProps {
	/** The ceremony payload (secret + QR + otpauth URI) from the server. */
	setup: TwoFactorSetup;
	code: string;
	onCodeChange: (code: string) => void;
	onCancel: () => void;
	onConfirm: () => void;
	/** Disables inputs while the verify request is in flight. */
	busy: boolean;
	confirmLabel?: string;
	cancelLabel?: string;
}

/**
 * The TOTP enrollment ceremony body: scannable QR, manual-entry key, and the
 * verification-code input with cancel/confirm actions. Shared between the
 * Profile → Security panel (self-service enrollment) and the login screen's
 * forced-enrollment step (security policy mandates 2FA). State lives in the
 * parent — this only renders the controlled form.
 */
export const TwoFactorEnrollForm = ({
	setup,
	code,
	onCodeChange,
	onCancel,
	onConfirm,
	busy,
	confirmLabel = "Verify & enable",
	cancelLabel = "Cancel",
}: TwoFactorEnrollFormProps) => (
	<div className="rounded-md border border-border bg-surface-2 p-4">
		<p className="mb-3 text-text-2">
			Scan this QR code with your authenticator app, or enter the key manually, then type the
			6-digit code it shows to confirm.
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
						value={code}
						onChange={(e) => onCodeChange(e.target.value)}
						placeholder="123456"
						autoFocus
						className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] tracking-[0.2em] focus:outline-none focus:ring-1 focus:ring-accent-ring"
					/>
				</label>

				<div className="flex justify-end gap-2">
					<button
						type="button"
						onClick={onCancel}
						disabled={busy}
						className="rounded-md border border-border bg-surface px-3 py-1.5 hover:bg-hover"
					>
						{cancelLabel}
					</button>
					<button
						type="button"
						onClick={onConfirm}
						disabled={busy || code.trim().length === 0}
						className="rounded-md bg-accent px-3 py-1.5 font-medium text-accent-fg disabled:opacity-50 hover:bg-accent-hover"
					>
						{busy ? "Verifying…" : confirmLabel}
					</button>
				</div>
			</div>
		</div>
	</div>
);
