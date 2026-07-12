import { useMemo } from "react";
import { Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";
import { FormShell } from "@/components/ui/FormShell";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { configureApi, type EmailConfig, type EmailServiceId } from "@/api/endpoints/configure";

import { queryKeys } from "@/lib/queryKeys";
import { ServiceSettingsFields, type ServiceSettingField } from "./ServiceSettingsFields";
import { useServiceSettingsDraft } from "./useServiceSettingsDraft";

const SERVICES: Array<{ id: EmailServiceId; label: string; blurb: string }> = [
	{
		id: "local",
		label: "Local server",
		blurb: "PHP's native mail() — fine for low volume, often gets marked as spam.",
	},
	{ id: "smtp", label: "SMTP", blurb: "PHPMailer over a standard SMTP relay." },
	{ id: "mandrill", label: "Mandrill", blurb: "Transactional email by the makers of MailChimp." },
	{ id: "mailgun", label: "Mailgun", blurb: "Transactional email by Rackspace." },
	{ id: "postmark", label: "Postmark", blurb: "Transactional email by the makers of Beanstalk." },
	{ id: "sendgrid", label: "SendGrid", blurb: "Transactional email delivery and management." },
];

/** Per-service credential descriptors — rendered via {@link ServiceSettingsFields}. */
const SERVICE_FIELDS: Record<EmailServiceId, ServiceSettingField[]> = {
	local: [],
	smtp: [
		{ key: "smtp_host", label: "Hostname" },
		{ key: "smtp_port", label: "Port" },
		{ key: "smtp_user", label: "Username" },
		{ key: "smtp_password", label: "Password" },
		{
			key: "smtp_security",
			label: "Security",
			type: "select",
			options: [
				{ value: "", label: "Plain text" },
				{ value: "ssl", label: "SSL" },
				{ value: "tls", label: "TLS" },
			],
		},
	],
	mandrill: [{ key: "mandrill_key", label: "API key" }],
	mailgun: [
		{ key: "mailgun_key", label: "API key" },
		{ key: "mailgun_domain", label: "Domain" },
	],
	postmark: [{ key: "postmark_key", label: "API key" }],
	sendgrid: [
		{ key: "sendgrid_api_user", label: "API user" },
		{ key: "sendgrid_api_key", label: "API key" },
	],
};

export const ConfigureEmail = () => {
	const { detailQ, draft, setDraft, generalError, saveMutation, onChange, onSubmit } =
		useServiceSettingsDraft({
			queryKey: queryKeys.configure.email(),
			queryFn: () => configureApi.email.get(),
			seed: (data: EmailConfig) => ({
				service: data.service ?? "local",
				settings: { ...(data.settings ?? {}) },
			}),
			save: (next: EmailConfig) => configureApi.email.update(next),
			successMessage: "Email service updated",
			errorMessage: "Could not save email config",
		});

	const onChangeService = (service: EmailServiceId) => {
		if (!draft) {
			return;
		}

		setDraft({ ...draft, service });
	};

	const active = useMemo(
		() => SERVICES.find((s) => s.id === draft?.service) ?? SERVICES[0]!,
		[draft]
	);

	const serviceFields = draft ? (SERVICE_FIELDS[draft.service] ?? []) : [];

	return (
		<ConfigureLayout
			title="Email"
			sub="Picks the delivery service BigTree uses for password resets, daily digests, and EmailService::sendEmail() calls."
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
						label="Service"
						value={draft.service}
						onChange={(v) => onChangeService(v as EmailServiceId)}
						options={SERVICES.map((s) => ({ value: s.id, label: s.label }))}
						hint={active.blurb}
					/>

					<div className="mt-4 space-y-3">
						{serviceFields.length > 0 && (
							<ServiceSettingsFields
								fields={serviceFields}
								settings={draft.settings}
								onChange={onChange}
							/>
						)}

						<Field label='BigTree "From" address'>
							<TextInput
								placeholder="no-reply@example.com"
								value={draft.settings.bigtree_from ?? ""}
								onChange={(e) => onChange("bigtree_from", e.target.value)}
							/>
							<p className="mt-1 text-[11.5px] text-text-3">
								Used for daily digests and password-reset emails.
							</p>
						</Field>
					</div>
				</FormShell>
			)}
		</ConfigureLayout>
	);
};
