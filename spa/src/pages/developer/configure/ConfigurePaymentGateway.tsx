import { Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";
import { FormShell } from "@/components/ui/FormShell";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { UploadButton } from "@/components/ui/UploadButton";

import {
	configureApi,
	type PaymentGatewayConfig,
	type PaymentGatewayId,
} from "@/api/endpoints/configure";

import { describeApiError } from "@/lib/errorHandling";
import { queryKeys } from "@/lib/queryKeys";
import { useToastMutation } from "@/hooks/useToastMutation";
import { useServiceSettingsDraft } from "./useServiceSettingsDraft";
import { ServiceSettingsFields, type ServiceSettingField } from "./ServiceSettingsFields";

const GATEWAYS: Array<{ id: PaymentGatewayId; label: string }> = [
	{ id: "", label: "Disabled" },
	{ id: "authorize.net", label: "Authorize.Net" },
	{ id: "paypal", label: "PayPal Payments Pro" },
	{ id: "paypal-rest", label: "PayPal REST API" },
	{ id: "payflow", label: "PayPal Payflow Gateway" },
	{ id: "linkpoint", label: "First Data / LinkPoint" },
];

// Per-gateway field map. The legacy admin uses these exact key names so the
// stored shape stays compatible with BigTreePaymentGateway consumers.
const FIELDS: Record<PaymentGatewayId, ServiceSettingField[]> = {
	"": [],
	"authorize.net": [
		{ key: "authorize-api-login", label: "API login" },
		{ key: "authorize-transaction-key", label: "Transaction key" },
		{
			key: "authorize-environment",
			label: "Environment",
			type: "select",
			options: [
				{ value: "live", label: "Live" },
				{ value: "test", label: "Test" },
			],
		},
	],
	paypal: [
		{ key: "paypal-username", label: "API user" },
		{ key: "paypal-password", label: "API password" },
		{ key: "paypal-signature", label: "API signature" },
		{
			key: "paypal-environment",
			label: "Environment",
			type: "select",
			options: [
				{ value: "live", label: "Live" },
				{ value: "test", label: "Test" },
			],
		},
	],
	"paypal-rest": [
		{ key: "paypal-rest-client-id", label: "Client ID" },
		{ key: "paypal-rest-client-secret", label: "Client secret" },
		{
			key: "paypal-rest-environment",
			label: "Environment",
			type: "select",
			options: [
				{ value: "live", label: "Live" },
				{ value: "test", label: "Test" },
			],
		},
	],
	payflow: [
		{ key: "payflow-partner", label: "Partner (typically PayPal)" },
		{ key: "payflow-vendor", label: "Vendor" },
		{ key: "payflow-username", label: "Username" },
		{ key: "payflow-password", label: "Password" },
		{
			key: "payflow-environment",
			label: "Environment",
			type: "select",
			options: [
				{ value: "live", label: "Live" },
				{ value: "test", label: "Test" },
			],
		},
	],
	linkpoint: [
		{ key: "linkpoint-store", label: "Store ID" },
		{
			key: "linkpoint-environment",
			label: "Environment",
			type: "select",
			options: [
				{ value: "live", label: "Live" },
				{ value: "test", label: "Test" },
			],
		},
	],
};

export const ConfigurePaymentGateway = () => {
	const {
		detailQ,
		draft,
		setDraft,
		generalError,
		setGeneralError,
		saveMutation,
		writeCache,
		onChange,
		onSubmit,
	} = useServiceSettingsDraft({
		queryKey: queryKeys.configure.paymentGateway(),
		queryFn: () => configureApi.paymentGateway.get(),
		seed: (data: PaymentGatewayConfig) => ({
			service: data.service,
			settings: { ...(data.settings ?? {}) },
		}),
		save: (next: PaymentGatewayConfig) => configureApi.paymentGateway.update(next),
		successMessage: "Payment gateway updated",
		errorMessage: "Could not save payment gateway",
	});

	// Certificate upload writes a fresh config back through the same re-seed.
	const certMutation = useToastMutation({
		mutationFn: (file: File) => configureApi.paymentGateway.uploadLinkpointCertificate(file),
		successMessage: "LinkPoint certificate uploaded",
		errorMessage: "Could not upload certificate",
		onSuccess: (fresh) => {
			writeCache(fresh);
			setGeneralError(null);
		},
		onError: (err) => {
			setGeneralError(describeApiError(err, "Could not upload certificate"));
		},
	});

	const fields = draft ? (FIELDS[draft.service] ?? []) : [];

	return (
		<ConfigureLayout
			title="Payment gateway"
			sub="Credentials for the payment provider that module forms (and BigTreePaymentGateway) charge through."
			query={detailQ}
		>
			{draft && (
				<FormShell
					onSubmit={onSubmit}
					footer={
						<Button
							variant="primary"
							type="submit"
							icon={<Save size={13} />}
							loading={saveMutation.isPending}
							loadingLabel="Saving…"
						>
							Save
						</Button>
					}
				>
					{generalError && <ErrorPanel message={generalError} />}

					<SelectField
						label="Gateway"
						value={draft.service}
						onChange={(v) => setDraft({ ...draft, service: v as PaymentGatewayId })}
						options={GATEWAYS.map((g) => ({ value: g.id, label: g.label }))}
					/>

					{fields.length > 0 && (
						<div className="mt-4 space-y-3">
							<ServiceSettingsFields
								fields={fields}
								settings={draft.settings}
								onChange={onChange}
							/>

							{draft.service === "linkpoint" && (
								<Field label="Certificate (.pem)">
									<div className="flex items-center gap-3">
										<UploadButton
											accept=".pem,.crt,application/x-pem-file"
											disabled={certMutation.isPending}
											onSelect={(file) => certMutation.mutate(file)}
											label={
												certMutation.isPending
													? "Uploading…"
													: "Upload certificate"
											}
										/>

										<span className="text-[12px] text-text-3">
											{draft.settings["linkpoint-certificate"]
												? `Stored: ${draft.settings["linkpoint-certificate"]}`
												: "No certificate uploaded yet."}
										</span>
									</div>
								</Field>
							)}
						</div>
					)}
				</FormShell>
			)}
		</ConfigureLayout>
	);
};
