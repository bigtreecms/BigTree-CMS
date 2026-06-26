import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { LoadingText } from "@/components/ui/LoadingText";
import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";
import { FormShell } from "@/components/ui/FormShell";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { configureApi, type EmailConfig, type EmailServiceId } from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

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

export const ConfigureEmail = () => {
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: ["configure", "email"],
		queryFn: () => configureApi.email.get(),
	});

	const [draft, setDraft] = useState<EmailConfig | null>(null);
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (detailQ.data) {
			setDraft({
				service: detailQ.data.service ?? "local",
				settings: { ...(detailQ.data.settings ?? {}) },
			});
		}
	}, [detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: EmailConfig) => configureApi.email.update(next),
		onSuccess: (fresh) => {
			queryClient.setQueryData(["configure", "email"], fresh);
			toast.success("Email service updated");
			setGeneralError(null);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message
					? err.message
					: "Could not save email config";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const onChange = (key: string, value: string) => {
		if (!draft) {
			return;
		}

		setDraft({ ...draft, settings: { ...draft.settings, [key]: value } });
	};

	const onChangeService = (service: EmailServiceId) => {
		if (!draft) {
			return;
		}

		setDraft({ ...draft, service });
	};

	const onSubmit = (e: React.FormEvent) => {
		e.preventDefault();

		if (!draft) {
			return;
		}

		saveMutation.mutate(draft);
	};

	const active = useMemo(
		() => SERVICES.find((s) => s.id === draft?.service) ?? SERVICES[0]!,
		[draft]
	);

	return (
		<ConfigureLayout
			title="Email"
			sub="Picks the delivery service BigTree uses for password resets, daily digests, and EmailService::sendEmail() calls."
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

					<SelectField
						label="Service"
						value={draft.service}
						onChange={(v) => onChangeService(v as EmailServiceId)}
						options={SERVICES.map((s) => ({ value: s.id, label: s.label }))}
						hint={active.blurb}
					/>

					<div className="mt-4 space-y-3">
						{draft.service === "smtp" && (
							<>
								<Field label="Hostname">
									<TextInput
										value={draft.settings.smtp_host ?? ""}
										onChange={(e) => onChange("smtp_host", e.target.value)}
									/>
								</Field>
								<Field label="Port">
									<TextInput
										value={draft.settings.smtp_port ?? ""}
										onChange={(e) => onChange("smtp_port", e.target.value)}
										placeholder="25"
									/>
								</Field>
								<Field label="Username">
									<TextInput
										value={draft.settings.smtp_user ?? ""}
										onChange={(e) => onChange("smtp_user", e.target.value)}
									/>
								</Field>
								<Field label="Password">
									<TextInput
										type="password"
										value={draft.settings.smtp_password ?? ""}
										onChange={(e) => onChange("smtp_password", e.target.value)}
									/>
								</Field>
								<SelectField
									label="Security"
									value={draft.settings.smtp_security ?? ""}
									onChange={(v) => onChange("smtp_security", v)}
									options={[
										{ value: "", label: "Plain text" },
										{ value: "ssl", label: "SSL" },
										{ value: "tls", label: "TLS" },
									]}
								/>
							</>
						)}

						{draft.service === "mandrill" && (
							<Field label="API key">
								<TextInput
									value={draft.settings.mandrill_key ?? ""}
									onChange={(e) => onChange("mandrill_key", e.target.value)}
								/>
							</Field>
						)}

						{draft.service === "mailgun" && (
							<>
								<Field label="API key">
									<TextInput
										value={draft.settings.mailgun_key ?? ""}
										onChange={(e) => onChange("mailgun_key", e.target.value)}
									/>
								</Field>
								<Field label="Domain">
									<TextInput
										placeholder="e.g. mg.example.com"
										value={draft.settings.mailgun_domain ?? ""}
										onChange={(e) => onChange("mailgun_domain", e.target.value)}
									/>
								</Field>
							</>
						)}

						{draft.service === "postmark" && (
							<Field label="API key">
								<TextInput
									value={draft.settings.postmark_key ?? ""}
									onChange={(e) => onChange("postmark_key", e.target.value)}
								/>
							</Field>
						)}

						{draft.service === "sendgrid" && (
							<>
								<Field label="API user">
									<TextInput
										value={draft.settings.sendgrid_api_user ?? ""}
										onChange={(e) =>
											onChange("sendgrid_api_user", e.target.value)
										}
									/>
								</Field>
								<Field label="API key">
									<TextInput
										value={draft.settings.sendgrid_api_key ?? ""}
										onChange={(e) =>
											onChange("sendgrid_api_key", e.target.value)
										}
									/>
								</Field>
							</>
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
