import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { Select } from "@/components/ui/Select";
import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { FormShell } from "@/components/ui/FormShell";
import { LoadingText } from "@/components/ui/LoadingText";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { UploadButton } from "@/components/ui/UploadButton";

import {
	configureApi,
	type PaymentGatewayConfig,
	type PaymentGatewayId,
} from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

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
const FIELDS: Record<
	PaymentGatewayId,
	Array<{
		key: string;
		label: string;
		type?: "select";
		options?: Array<{ value: string; label: string }>;
	}>
> = {
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

const isMaskedKey = (key: string) => /secret|key|password|token|signature/i.test(key);

export const ConfigurePaymentGateway = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: ["configure", "payment-gateway"],
		queryFn: () => configureApi.paymentGateway.get(),
	});

	const [draft, setDraft] = useState<PaymentGatewayConfig | null>(null);
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (detailQ.data) {
			setDraft({
				service: detailQ.data.service,
				settings: { ...(detailQ.data.settings ?? {}) },
			});
		}
	}, [detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: PaymentGatewayConfig) => configureApi.paymentGateway.update(next),
		onSuccess: (fresh) => {
			queryClient.setQueryData(["configure", "payment-gateway"], fresh);
			setDraft({
				service: fresh.service,
				settings: { ...(fresh.settings ?? {}) },
			});
			toast.success("Payment gateway updated");
			setGeneralError(null);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message
					? err.message
					: "Could not save payment gateway";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const certMutation = useMutation({
		mutationFn: (file: File) => configureApi.paymentGateway.uploadLinkpointCertificate(file),
		onSuccess: (fresh) => {
			queryClient.setQueryData(["configure", "payment-gateway"], fresh);
			setDraft({ service: fresh.service, settings: { ...(fresh.settings ?? {}) } });
			toast.success("LinkPoint certificate uploaded");
			setGeneralError(null);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message
					? err.message
					: "Could not upload certificate";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const onSubmit = (e: React.FormEvent) => {
		e.preventDefault();

		if (!draft) {
			return;
		}

		saveMutation.mutate(draft);
	};

	const onChange = (key: string, value: string) => {
		if (!draft) {
			return;
		}

		setDraft({ ...draft, settings: { ...draft.settings, [key]: value } });
	};

	const fields = draft ? (FIELDS[draft.service] ?? []) : [];

	return (
		<ConfigureLayout
			title="Payment gateway"
			sub="Credentials for the payment provider that module forms (and BigTreePaymentGateway) charge through."
		>
			{detailQ.isLoading && <LoadingText />}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

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
					{generalError && <ErrorPanel error={new Error(generalError)} />}

					<Field label="Gateway">
						<Select
							value={draft.service}
							onChange={(e) =>
								setDraft({ ...draft, service: e.target.value as PaymentGatewayId })
							}
						>
							{GATEWAYS.map((g) => (
								<option key={g.id} value={g.id}>
									{g.label}
								</option>
							))}
						</Select>
					</Field>

					{fields.length > 0 && (
						<div className="mt-4 space-y-3">
							{fields.map((f) => {
								const masked = isMaskedKey(f.key);
								const isSet = !!draft.settings[`${f.key}-set`];
								const value = (draft.settings[f.key] as string) ?? "";

								if (f.type === "select" && f.options) {
									return (
										<Field key={f.key} label={f.label}>
											<Select
												value={value}
												onChange={(e) => onChange(f.key, e.target.value)}
											>
												{f.options.map((o) => (
													<option key={o.value} value={o.value}>
														{o.label}
													</option>
												))}
											</Select>
										</Field>
									);
								}

								return (
									<Field key={f.key} label={f.label}>
										<TextInput
											type={masked ? "password" : "text"}
											value={value}
											placeholder={
												masked && isSet
													? "•••••••• (stored, leave blank to keep)"
													: ""
											}
											onChange={(e) => onChange(f.key, e.target.value)}
											autoComplete="off"
										/>
									</Field>
								);
							})}

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
