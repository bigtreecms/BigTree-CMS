import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useOAuthRedirectResult } from "@/hooks/useOAuthRedirectResult";
import { useSeededState } from "@/hooks/useSeededState";
import { useToastMutation } from "@/hooks/useToastMutation";
import { RefreshCw, Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";
import { TextInput } from "@/components/ui/TextInput";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { UploadButton } from "@/components/ui/UploadButton";
import { Badge } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";

import { configureApi, type CloudProvider } from "@/api/endpoints/configure";

import { describeApiError } from "@/lib/errorHandling";
import { pluralize } from "@/lib/number";
import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";

const AWS_REGIONS = [
	{ value: "us-east-1", label: "US East (N. Virginia)" },
	{ value: "us-east-2", label: "US East (Ohio)" },
	{ value: "us-west-1", label: "US West (N. California)" },
	{ value: "us-west-2", label: "US West (Oregon)" },
	{ value: "eu-west-1", label: "EU (Ireland)" },
	{ value: "eu-west-2", label: "EU (London)" },
	{ value: "eu-central-1", label: "EU (Frankfurt)" },
	{ value: "ap-southeast-1", label: "Asia Pacific (Singapore)" },
	{ value: "ap-southeast-2", label: "Asia Pacific (Sydney)" },
	{ value: "ap-northeast-1", label: "Asia Pacific (Tokyo)" },
];

const RACKSPACE_REGIONS = [
	{ value: "ORD", label: "Chicago, IL (USA)" },
	{ value: "DFW", label: "Dallas/Ft. Worth, TX (USA)" },
	{ value: "HKG", label: "Hong Kong" },
	{ value: "LON", label: "London (UK)" },
	{ value: "IAD", label: "Northern Virginia (USA)" },
	{ value: "SYD", label: "Sydney (Australia)" },
];

interface ProviderDraft {
	[key: string]: string;
}

export const ConfigureCloudStorage = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: queryKeys.configure.cloudStorage(),
		queryFn: () => configureApi.cloudStorage.get(),
	});

	useOAuthRedirectResult({
		onConnected: () => toast.success("Google Cloud Storage connected"),
		onError: () => toast.error("Google Cloud connection failed."),
		invalidate: queryKeys.configure.cloudStorage(),
	});

	const [drafts, setDrafts] = useState<Record<CloudProvider, ProviderDraft>>({
		amazon: {},
		rackspace: {},
		google: {},
	});
	const [defaultService, setDefaultService] = useState<string>("local");
	const [defaultContainer, setDefaultContainer] = useState<string>("");
	const [cloudfront, setCloudfront] = useState({ distribution: "", domain: "", ssl: "" });
	const [generalError, setGeneralError] = useState<string | null>(null);
	const [recacheProgress, setRecacheProgress] = useState<number | null>(null);

	useSeededState(detailQ.data, (data) => {
		const amazon = (data.providers.amazon?.settings ?? {}) as ProviderDraft;
		const next: Record<CloudProvider, ProviderDraft> = {
			amazon: { ...amazon },
			rackspace: { ...(data.providers.rackspace?.settings as ProviderDraft) },
			google: { ...(data.providers.google?.settings as ProviderDraft) },
		};
		setDrafts(next);
		setDefaultService(data.default_service ?? "local");
		setDefaultContainer(data.default_container ?? "");
		setCloudfront({
			distribution: (amazon.cloudfront_distribution as string) ?? "",
			domain: (amazon.cloudfront_domain as string) ?? "",
			ssl: (amazon.cloudfront_ssl as string) ?? "",
		});
	});

	const saveProviderMutation = useMutation({
		mutationFn: ({ provider, body }: { provider: CloudProvider; body: ProviderDraft }) =>
			configureApi.cloudStorage.updateProvider(provider, body),
		onSuccess: (_fresh, { provider }) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.configure.cloudStorage() });
			toast.success(`${labelFor(provider)} credentials saved`);
			setGeneralError(null);
		},
		onError: (err) => {
			const msg = describeApiError(err, "Could not save credentials");
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const saveDefaultMutation = useToastMutation({
		mutationFn: () =>
			configureApi.cloudStorage.updateDefault({
				service: defaultService,
				container: defaultContainer,
				...(defaultService === "amazon"
					? {
							cloudfront_distribution: cloudfront.distribution,
							cloudfront_domain: cloudfront.domain,
							cloudfront_ssl: cloudfront.ssl,
						}
					: {}),
			}),
		invalidate: [queryKeys.configure.cloudStorage()],
		successMessage: "Default storage updated",
		errorMessage: "Could not save default",
		onSuccess: () => setGeneralError(null),
		onError: (err) => {
			setGeneralError(describeApiError(err, "Could not save default"));
		},
	});

	const googleKeyMutation = useToastMutation({
		mutationFn: (file: File) => configureApi.cloudStorage.uploadGoogleKey(file),
		invalidate: [queryKeys.configure.cloudStorage()],
		successMessage: "Private key uploaded",
		errorMessage: "Could not upload private key",
		onSuccess: () => setGeneralError(null),
		onError: (err) => {
			setGeneralError(describeApiError(err, "Could not upload private key"));
		},
	});

	const googleOAuthMutation = useMutation({
		mutationFn: () => configureApi.cloudStorage.startGoogleOAuth(),
		onSuccess: ({ launch_url }) => {
			window.location.href = launch_url;
		},
		onError: (err) => {
			const msg = describeApiError(err, "Could not start Google OAuth");
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	// Walks every page of the S3 bucket, forwarding the marker the server hands
	// back, until the recache reports complete. Mirrors the legacy amazon-cache
	// AJAX loop but keeps the paging entirely on the client.
	const recacheMutation = useMutation({
		mutationFn: async () => {
			let marker: string | undefined;
			let cached = 0;

			setRecacheProgress(0);

			for (;;) {
				const page = await configureApi.cloudStorage.recacheAmazon(marker);
				cached += page.cached;
				setRecacheProgress(cached);

				if (page.complete || !page.marker) {
					break;
				}

				marker = page.marker;
			}

			return cached;
		},
		onSuccess: (cached) => {
			toast.success(
				cached > 0
					? `Cached ${pluralize(cached, "S3 file")}`
					: "S3 file cache is up to date"
			);
			setGeneralError(null);
		},
		onError: (err) => {
			const msg = describeApiError(err, "Could not recache S3 files");
			setGeneralError(msg);
			toast.error(msg);
		},
		onSettled: () => {
			setRecacheProgress(null);
		},
	});

	const update = (provider: CloudProvider, key: string, value: string) =>
		setDrafts((prev) => ({ ...prev, [provider]: { ...prev[provider], [key]: value } }));

	return (
		<ConfigureLayout
			title="Cloud storage"
			sub="Credentials for the storage backend BigTree uploads files to, plus default-service selection and bucket / CloudFront wiring."
			query={detailQ}
		>
			{detailQ.data && (
				<>
					{generalError && <ErrorPanel message={generalError} />}

					<Card className="mb-4 p-4">
						<div className="mb-3 text-[12.5px] font-semibold text-text">
							Default storage service
						</div>
						<div className="flex flex-col gap-3 sm:flex-row sm:items-end">
							<div className="sm:flex-1">
								<SelectField
									label="Service"
									value={defaultService}
									onChange={setDefaultService}
									options={[
										{ value: "local", label: "Local storage" },
										...(detailQ.data.providers.amazon.active
											? [{ value: "amazon", label: "Amazon S3" }]
											: []),
										...(detailQ.data.providers.rackspace.active
											? [
													{
														value: "rackspace",
														label: "Rackspace Cloud Files",
													},
												]
											: []),
										...(detailQ.data.providers.google.active
											? [{ value: "google", label: "Google Cloud Storage" }]
											: []),
									]}
								/>
							</div>

							<div className="sm:flex-1">
								<Field label="Container / bucket (optional)">
									<TextInput
										placeholder="leave blank to auto-create"
										value={defaultContainer}
										onChange={(e) => setDefaultContainer(e.target.value)}
									/>
								</Field>
							</div>

							<Button
								variant="primary"
								className="w-full justify-center sm:w-auto sm:justify-start"
								icon={<Save size={13} />}
								onClick={() => saveDefaultMutation.mutate()}
								loading={saveDefaultMutation.isPending}
								loadingLabel="Saving…"
							>
								Update default
							</Button>
						</div>

						{defaultService === "amazon" && (
							<div className="mt-3 grid grid-cols-1 gap-3 border-t border-border pt-3 md:grid-cols-3">
								<Field label="CloudFront distribution (optional)">
									<TextInput
										value={cloudfront.distribution}
										onChange={(e) =>
											setCloudfront((c) => ({
												...c,
												distribution: e.target.value,
											}))
										}
									/>
								</Field>
								<Field label="CloudFront domain (optional)">
									<TextInput
										placeholder="d111111abcdef8.cloudfront.net"
										value={cloudfront.domain}
										onChange={(e) =>
											setCloudfront((c) => ({ ...c, domain: e.target.value }))
										}
									/>
								</Field>
								<SelectField
									label="Serve CloudFront over SSL"
									value={cloudfront.ssl}
									onChange={(v) => setCloudfront((c) => ({ ...c, ssl: v }))}
									options={[
										{ value: "", label: "No" },
										{ value: "on", label: "Yes" },
									]}
								/>
							</div>
						)}

						<p className="mt-2 text-[11.5px] text-text-3">
							Only providers with stored credentials are pickable. Leave the container
							blank to auto-create a unique bucket.
						</p>
					</Card>

					<ProviderCard
						title="Amazon S3"
						active={detailQ.data.providers.amazon.active}
						onSave={() =>
							saveProviderMutation.mutate({ provider: "amazon", body: drafts.amazon })
						}
						saving={saveProviderMutation.isPending}
						footnote={
							defaultService === "amazon" ? (
								<Button
									variant="secondary"
									size="sm"
									disabled={recacheMutation.isPending}
									onClick={() => recacheMutation.mutate()}
									icon={
										<RefreshCw
											size={13}
											className={
												recacheMutation.isPending
													? "animate-spin"
													: undefined
											}
										/>
									}
								>
									{recacheMutation.isPending
										? `Recaching… ${recacheProgress ?? 0} cached`
										: "Recache S3 files"}
								</Button>
							) : undefined
						}
					>
						<SelectField
							label="AWS region"
							value={(drafts.amazon.region as string) ?? "us-east-1"}
							onChange={(v) => update("amazon", "region", v)}
							options={AWS_REGIONS}
						/>

						<Field label="Access key ID">
							<TextInput
								value={(drafts.amazon.key as string) ?? ""}
								onChange={(e) => update("amazon", "key", e.target.value)}
							/>
						</Field>

						<Field label="Secret access key">
							<TextInput
								type="password"
								value={(drafts.amazon.secret as string) ?? ""}
								placeholder={
									drafts.amazon.secret_set
										? "•••••••• (stored, leave blank to keep)"
										: ""
								}
								onChange={(e) => update("amazon", "secret", e.target.value)}
								autoComplete="off"
							/>
						</Field>
					</ProviderCard>

					<ProviderCard
						title="Rackspace Cloud Files"
						active={detailQ.data.providers.rackspace.active}
						onSave={() =>
							saveProviderMutation.mutate({
								provider: "rackspace",
								body: drafts.rackspace,
							})
						}
						saving={saveProviderMutation.isPending}
					>
						<Field label="API key">
							<TextInput
								type="password"
								value={(drafts.rackspace.api_key as string) ?? ""}
								placeholder={
									drafts.rackspace.api_key_set ? "•••••••• (stored)" : ""
								}
								onChange={(e) => update("rackspace", "api_key", e.target.value)}
								autoComplete="off"
							/>
						</Field>
						<Field label="Username">
							<TextInput
								value={(drafts.rackspace.username as string) ?? ""}
								onChange={(e) => update("rackspace", "username", e.target.value)}
							/>
						</Field>
						<SelectField
							label="Region"
							value={(drafts.rackspace.region as string) ?? "ORD"}
							onChange={(v) => update("rackspace", "region", v)}
							options={RACKSPACE_REGIONS}
						/>
					</ProviderCard>

					<ProviderCard
						title="Google Cloud Storage"
						active={detailQ.data.providers.google.active}
						onSave={() =>
							saveProviderMutation.mutate({ provider: "google", body: drafts.google })
						}
						saving={saveProviderMutation.isPending}
						footnote={
							<Button
								variant="link"
								size="sm"
								disabled={googleOAuthMutation.isPending}
								onClick={() => googleOAuthMutation.mutate()}
								loading={googleOAuthMutation.isPending}
								loadingLabel="Starting…"
							>
								Complete activation (Google OAuth)
							</Button>
						}
					>
						<Field label="Project ID">
							<TextInput
								value={(drafts.google.project as string) ?? ""}
								onChange={(e) => update("google", "project", e.target.value)}
							/>
						</Field>
						<Field label="Client ID">
							<TextInput
								value={(drafts.google.key as string) ?? ""}
								onChange={(e) => update("google", "key", e.target.value)}
							/>
						</Field>
						<Field label="Client secret">
							<TextInput
								type="password"
								value={(drafts.google.client_secret as string) ?? ""}
								placeholder={
									drafts.google.client_secret_set ? "•••••••• (stored)" : ""
								}
								onChange={(e) => update("google", "client_secret", e.target.value)}
								autoComplete="off"
							/>
						</Field>
						<Field label="Certificate email (optional)">
							<TextInput
								value={(drafts.google.certificate_email as string) ?? ""}
								onChange={(e) =>
									update("google", "certificate_email", e.target.value)
								}
							/>
						</Field>
						<Field label="Private key (.json or .p12)">
							<div className="flex items-center gap-3">
								<UploadButton
									accept=".json,.p12,application/json"
									disabled={googleKeyMutation.isPending}
									onSelect={(file) => googleKeyMutation.mutate(file)}
									label={
										googleKeyMutation.isPending
											? "Uploading…"
											: "Upload private key"
									}
								/>

								<span className="text-[12px] text-text-3">
									{drafts.google.private_key_set
										? "Stored — upload again to replace."
										: "No private key uploaded yet."}
								</span>
							</div>
						</Field>
					</ProviderCard>
				</>
			)}
		</ConfigureLayout>
	);
};

interface ProviderCardProps {
	title: string;
	active: boolean;
	saving: boolean;
	onSave: () => void;
	footnote?: React.ReactNode;
	children: React.ReactNode;
}

const ProviderCard = ({ title, active, saving, onSave, footnote, children }: ProviderCardProps) => (
	<Card className="mb-3 p-4">
		<div className="mb-3 flex items-center justify-between">
			<div className="text-[12.5px] font-semibold text-text">{title}</div>
			{active && (
				<Badge size="sm" tone="accent">
					Connected
				</Badge>
			)}
		</div>

		<div className="space-y-3">{children}</div>

		<div className="mt-4 flex items-center justify-between gap-3">
			{footnote ? <p className="text-[11.5px] text-text-3">{footnote}</p> : <span />}

			<Button
				variant="secondary"
				size="sm"
				onClick={onSave}
				disabled={saving}
				loading={saving}
				loadingLabel="Saving…"
				icon={<Save size={13} />}
			>
				Save credentials
			</Button>
		</div>
	</Card>
);

const labelFor = (p: CloudProvider) =>
	({ amazon: "Amazon S3", rackspace: "Rackspace", google: "Google Cloud Storage" })[p];
