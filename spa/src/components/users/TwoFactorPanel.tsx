import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { ShieldCheck } from "lucide-react";

import { authApi, type TwoFactorSetup } from "@/auth/endpoints";

import { queryKeys } from "@/lib/queryKeys";
import { useToastMutation } from "@/hooks/useToastMutation";
import { TwoFactorEnrollForm } from "./TwoFactorEnrollForm";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { Badge } from "@/components/ui/Badge";
import { Field } from "@/components/ui/Field";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { TextInput } from "@/components/ui/TextInput";

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
		queryClient.invalidateQueries({ queryKey: queryKeys.users.me() });
	};

	const setupMutation = useToastMutation({
		mutationFn: () => authApi.twoFactorSetup(),
		errorMessage: "Could not start 2FA setup",
		onSuccess: (data) => {
			setSetup(data);
			setEnableCode("");
		},
	});

	const enableMutation = useToastMutation({
		mutationFn: () => authApi.enableTwoFactor(setup!.secret, enableCode.trim()),
		successMessage: "Two-factor authentication enabled",
		errorMessage: "Could not enable two-factor authentication",
		onSuccess: () => {
			invalidate();
			setSetup(null);
			setEnableCode("");
		},
	});

	const disableMutation = useToastMutation({
		mutationFn: () => authApi.disableTwoFactor(disableCode.trim()),
		successMessage: "Two-factor authentication disabled",
		errorMessage: "Could not disable two-factor authentication",
		onSuccess: () => {
			invalidate();
			setDisabling(false);
			setDisableCode("");
		},
	});

	return (
		<Card>
			<CardHeader className="flex items-center gap-2">
				<ShieldCheck className="text-text-3" size={14} />
				<SectionLabel as="h3">Two-factor authentication</SectionLabel>
			</CardHeader>

			<div className="space-y-3 p-4 text-[12.5px]">
				<div className="flex items-center justify-between gap-3">
					<Badge dot tone={enabled ? "success" : "info"}>
						{enabled ? "Enabled" : "Not enabled"}
					</Badge>

					{enabled && !disabling && (
						<Button
							className="shrink-0"
							variant="secondary"
							onClick={() => {
								setDisabling(true);
								setDisableCode("");
							}}
						>
							Disable
						</Button>
					)}

					{!enabled && !setup && (
						<Button
							className="shrink-0"
							loading={setupMutation.isPending}
							loadingLabel="Starting…"
							variant="primary"
							onClick={() => setupMutation.mutate()}
						>
							Enable
						</Button>
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
					<TwoFactorEnrollForm
						busy={enableMutation.isPending}
						code={enableCode}
						setup={setup}
						onCancel={() => {
							setSetup(null);
							setEnableCode("");
						}}
						onCodeChange={setEnableCode}
						onConfirm={() => enableMutation.mutate()}
					/>
				)}

				{enabled && disabling && (
					<div className="rounded-md border border-border bg-surface-2 p-4">
						<Field label="Enter a current code to confirm">
							<TextInput
								autoFocus
								autoComplete="one-time-code"
								className="tracking-[0.2em]"
								inputMode="numeric"
								placeholder="123456"
								value={disableCode}
								onChange={(e) => setDisableCode(e.target.value)}
							/>
						</Field>

						<div className="mt-3 flex justify-end gap-2">
							<Button
								disabled={disableMutation.isPending}
								variant="secondary"
								onClick={() => {
									setDisabling(false);
									setDisableCode("");
								}}
							>
								Cancel
							</Button>
							<Button
								disabled={disableCode.trim().length === 0}
								loading={disableMutation.isPending}
								loadingLabel="Disabling…"
								variant="danger"
								onClick={() => disableMutation.mutate()}
							>
								Disable 2FA
							</Button>
						</div>
					</div>
				)}
			</div>
		</Card>
	);
};
