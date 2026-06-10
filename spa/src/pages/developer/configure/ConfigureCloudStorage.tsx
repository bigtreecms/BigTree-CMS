import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Field } from "@/components/ui/Field";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { UploadButton } from "@/components/ui/UploadButton";

import { configureApi, type CloudProvider } from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const inputClass =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent-ring";

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
	const [searchParams, setSearchParams] = useSearchParams();
	const detailQ = useQuery({
		queryKey: ["configure", "cloud-storage"],
		queryFn: () => configureApi.cloudStorage.get(),
	});

	// Surface the OAuth broker's redirect result.
	useEffect(() => {
		const connected = searchParams.get("connected");
		const error = searchParams.get("error");

		if (connected) {
			toast.success("Google Cloud Storage connected");
		} else if (error) {
			toast.error("Google Cloud connection failed.");
		}

		if (connected || error) {
			searchParams.delete("connected");
			searchParams.delete("error");
			setSearchParams(searchParams, { replace: true });
			queryClient.invalidateQueries({ queryKey: ["configure", "cloud-storage"] });
		}
	}, [searchParams, setSearchParams, queryClient]);

	const [drafts, setDrafts] = useState<Record<CloudProvider, ProviderDraft>>({
		amazon: {},
		rackspace: {},
		google: {},
	});
	const [defaultService, setDefaultService] = useState<string>("local");
	const [defaultContainer, setDefaultContainer] = useState<string>("");
	const [cloudfront, setCloudfront] = useState({ distribution: "", domain: "", ssl: "" });
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (detailQ.data) {
			const amazon = (detailQ.data.providers.amazon?.settings ?? {}) as ProviderDraft;
			const next: Record<CloudProvider, ProviderDraft> = {
				amazon: { ...amazon },
				rackspace: { ...(detailQ.data.providers.rackspace?.settings as ProviderDraft) },
				google: { ...(detailQ.data.providers.google?.settings as ProviderDraft) },
			};
			setDrafts(next);
			setDefaultService(detailQ.data.default_service ?? "local");
			setDefaultContainer(detailQ.data.default_container ?? "");
			setCloudfront({
				distribution: (amazon.cloudfront_distribution as string) ?? "",
				domain: (amazon.cloudfront_domain as string) ?? "",
				ssl: (amazon.cloudfront_ssl as string) ?? "",
			});
		}
	}, [detailQ.data]);

	const saveProviderMutation = useMutation({
		mutationFn: ({ provider, body }: { provider: CloudProvider; body: ProviderDraft }) =>
			configureApi.cloudStorage.updateProvider(provider, body),
		onSuccess: (_fresh, { provider }) => {
			queryClient.invalidateQueries({ queryKey: ["configure", "cloud-storage"] });
			toast.success(`${labelFor(provider)} credentials saved`);
			setGeneralError(null);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message ? err.message : "Could not save credentials";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const saveDefaultMutation = useMutation({
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
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["configure", "cloud-storage"] });
			toast.success("Default storage updated");
			setGeneralError(null);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message ? err.message : "Could not save default";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const googleKeyMutation = useMutation({
		mutationFn: (file: File) => configureApi.cloudStorage.uploadGoogleKey(file),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["configure", "cloud-storage"] });
			toast.success("Private key uploaded");
			setGeneralError(null);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message
					? err.message
					: "Could not upload private key";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const googleOAuthMutation = useMutation({
		mutationFn: () => configureApi.cloudStorage.startGoogleOAuth(),
		onSuccess: ({ launch_url }) => {
			window.location.href = launch_url;
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message
					? err.message
					: "Could not start Google OAuth";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const update = (provider: CloudProvider, key: string, value: string) =>
		setDrafts((prev) => ({ ...prev, [provider]: { ...prev[provider], [key]: value } }));

	return (
		<ConfigureLayout
			title="Cloud storage"
			sub="Credentials for the storage backend BigTree uploads files to, plus default-service selection and bucket / CloudFront wiring."
		>
			{detailQ.isLoading && <p className="text-[12.5px] text-text-3">Loading…</p>}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{detailQ.data && (
				<>
					{generalError && <ErrorPanel error={new Error(generalError)} />}

					<div className="mb-4 rounded-xl border border-border bg-surface p-4">
						<div className="mb-3 text-[12.5px] font-semibold text-text">
							Default storage service
						</div>
						<div className="flex flex-col gap-3 sm:flex-row sm:items-end">
							<div className="sm:flex-1">
								<Field label="Service">
									<select
										className={inputClass}
										value={defaultService}
										onChange={(e) => setDefaultService(e.target.value)}
									>
										<option value="local">Local storage</option>
										{detailQ.data.providers.amazon.active && (
											<option value="amazon">Amazon S3</option>
										)}
										{detailQ.data.providers.rackspace.active && (
											<option value="rackspace">Rackspace Cloud Files</option>
										)}
										{detailQ.data.providers.google.active && (
											<option value="google">Google Cloud Storage</option>
										)}
									</select>
								</Field>
							</div>

							<div className="sm:flex-1">
								<Field label="Container / bucket (optional)">
									<input
										className={inputClass}
										placeholder="leave blank to auto-create"
										value={defaultContainer}
										onChange={(e) => setDefaultContainer(e.target.value)}
									/>
								</Field>
							</div>

							<button
								type="button"
								onClick={() => saveDefaultMutation.mutate()}
								disabled={saveDefaultMutation.isPending}
								className="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-accent px-3 py-2 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-60 sm:w-auto sm:justify-start sm:py-1.5"
							>
								<Save size={13} />
								{saveDefaultMutation.isPending ? "Saving…" : "Update default"}
							</button>
						</div>

						{defaultService === "amazon" && (
							<div className="mt-3 grid grid-cols-1 gap-3 border-t border-border pt-3 md:grid-cols-3">
								<Field label="CloudFront distribution (optional)">
									<input
										className={inputClass}
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
									<input
										className={inputClass}
										placeholder="d111111abcdef8.cloudfront.net"
										value={cloudfront.domain}
										onChange={(e) =>
											setCloudfront((c) => ({ ...c, domain: e.target.value }))
										}
									/>
								</Field>
								<Field label="Serve CloudFront over SSL">
									<select
										className={inputClass}
										value={cloudfront.ssl}
										onChange={(e) =>
											setCloudfront((c) => ({ ...c, ssl: e.target.value }))
										}
									>
										<option value="">No</option>
										<option value="on">Yes</option>
									</select>
								</Field>
							</div>
						)}

						<p className="mt-2 text-[11.5px] text-text-3">
							Only providers with stored credentials are pickable. Leave the container
							blank to auto-create a unique bucket.
						</p>
					</div>

					<ProviderCard
						title="Amazon S3"
						active={detailQ.data.providers.amazon.active}
						onSave={() =>
							saveProviderMutation.mutate({ provider: "amazon", body: drafts.amazon })
						}
						saving={saveProviderMutation.isPending}
					>
						<Field label="AWS region">
							<select
								className={inputClass}
								value={(drafts.amazon.region as string) ?? "us-east-1"}
								onChange={(e) => update("amazon", "region", e.target.value)}
							>
								{AWS_REGIONS.map((r) => (
									<option key={r.value} value={r.value}>
										{r.label}
									</option>
								))}
							</select>
						</Field>

						<Field label="Access key ID">
							<input
								className={inputClass}
								value={(drafts.amazon.key as string) ?? ""}
								onChange={(e) => update("amazon", "key", e.target.value)}
							/>
						</Field>

						<Field label="Secret access key">
							<input
								type="password"
								className={inputClass}
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
							<input
								type="password"
								className={inputClass}
								value={(drafts.rackspace.api_key as string) ?? ""}
								placeholder={
									drafts.rackspace.api_key_set ? "•••••••• (stored)" : ""
								}
								onChange={(e) => update("rackspace", "api_key", e.target.value)}
								autoComplete="off"
							/>
						</Field>
						<Field label="Username">
							<input
								className={inputClass}
								value={(drafts.rackspace.username as string) ?? ""}
								onChange={(e) => update("rackspace", "username", e.target.value)}
							/>
						</Field>
						<Field label="Region">
							<select
								className={inputClass}
								value={(drafts.rackspace.region as string) ?? "ORD"}
								onChange={(e) => update("rackspace", "region", e.target.value)}
							>
								{RACKSPACE_REGIONS.map((r) => (
									<option key={r.value} value={r.value}>
										{r.label}
									</option>
								))}
							</select>
						</Field>
					</ProviderCard>

					<ProviderCard
						title="Google Cloud Storage"
						active={detailQ.data.providers.google.active}
						onSave={() =>
							saveProviderMutation.mutate({ provider: "google", body: drafts.google })
						}
						saving={saveProviderMutation.isPending}
						footnote={
							<button
								type="button"
								disabled={googleOAuthMutation.isPending}
								onClick={() => googleOAuthMutation.mutate()}
								className="inline-flex items-center gap-1.5 rounded-md border border-accent/40 px-3 py-1.5 text-[12.5px] font-medium text-accent hover:bg-accent/10 disabled:opacity-60"
							>
								{googleOAuthMutation.isPending
									? "Starting…"
									: "Complete activation (Google OAuth)"}
							</button>
						}
					>
						<Field label="Project ID">
							<input
								className={inputClass}
								value={(drafts.google.project as string) ?? ""}
								onChange={(e) => update("google", "project", e.target.value)}
							/>
						</Field>
						<Field label="Client ID">
							<input
								className={inputClass}
								value={(drafts.google.key as string) ?? ""}
								onChange={(e) => update("google", "key", e.target.value)}
							/>
						</Field>
						<Field label="Client secret">
							<input
								type="password"
								className={inputClass}
								value={(drafts.google.client_secret as string) ?? ""}
								placeholder={
									drafts.google.client_secret_set ? "•••••••• (stored)" : ""
								}
								onChange={(e) => update("google", "client_secret", e.target.value)}
								autoComplete="off"
							/>
						</Field>
						<Field label="Certificate email (optional)">
							<input
								className={inputClass}
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
	<div className="mb-3 rounded-xl border border-border bg-surface p-4">
		<div className="mb-3 flex items-center justify-between">
			<div className="text-[12.5px] font-semibold text-text">{title}</div>
			{active && (
				<span className="rounded bg-accent-soft px-1.5 py-0.5 text-[11px] font-medium text-accent">
					Connected
				</span>
			)}
		</div>

		<div className="space-y-3">{children}</div>

		<div className="mt-4 flex items-center justify-between gap-3">
			{footnote ? <p className="text-[11.5px] text-text-3">{footnote}</p> : <span />}

			<button
				type="button"
				onClick={onSave}
				disabled={saving}
				className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-2 px-3 py-1.5 text-[12.5px] font-medium text-text hover:bg-hover disabled:opacity-60"
			>
				<Save size={13} />
				{saving ? "Saving…" : "Save credentials"}
			</button>
		</div>
	</div>
);

const labelFor = (p: CloudProvider) =>
	({ amazon: "Amazon S3", rackspace: "Rackspace", google: "Google Cloud Storage" })[p];
