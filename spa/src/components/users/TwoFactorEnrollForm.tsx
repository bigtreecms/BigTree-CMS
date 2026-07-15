import type { TwoFactorSetup } from "@/auth/endpoints";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { TextInput } from "@/components/ui/TextInput";

interface TwoFactorEnrollFormProps {
	/** Disables inputs while the verify request is in flight. */
	busy: boolean;
	cancelLabel?: string;
	code: string;
	confirmLabel?: string;
	onCancel: () => void;
	onCodeChange: (code: string) => void;
	onConfirm: () => void;
	/** The ceremony payload (secret + QR + otpauth URI) from the server. */
	setup: TwoFactorSetup;
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
				alt="Two-factor QR code"
				className="size-40 shrink-0 rounded-md border border-border bg-white p-2"
				src={setup.qr_image}
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

				<Field label="Verification code">
					<TextInput
						autoFocus
						autoComplete="one-time-code"
						className="tracking-[0.2em]"
						inputMode="numeric"
						placeholder="123456"
						value={code}
						onChange={(e) => onCodeChange(e.target.value)}
					/>
				</Field>

				<div className="flex justify-end gap-2">
					<Button disabled={busy} variant="secondary" onClick={onCancel}>
						{cancelLabel}
					</Button>
					<Button
						disabled={code.trim().length === 0}
						loading={busy}
						loadingLabel="Verifying…"
						variant="primary"
						onClick={onConfirm}
					>
						{confirmLabel}
					</Button>
				</div>
			</div>
		</div>
	</div>
);
