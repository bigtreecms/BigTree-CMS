import { api } from "@/api/client";

/**
 * Developer → Configure section.
 *
 *   Backed by /system/configure/{area}. Internal settings (bigtree-internal-*)
 *   aren't reachable through the public /settings endpoint by design, so the
 *   server exposes them here behind a developer-only gate.
 *
 *   Secret fields (API keys, secret access keys, OAuth tokens) come back
 *   masked — the server appends a `<key>_set` boolean so the UI can show
 *   "stored" without leaking the value. Sending an empty string on those
 *   masked keys means "leave it alone"; the server preserves the existing
 *   value.
 */

/** Wrap a single file in a FormData payload under the field name the API expects. */
const toFormData = (file: File): FormData => {
	const form = new FormData();
	form.append("file", file);

	return form;
};

export type EmailServiceId = "local" | "smtp" | "mandrill" | "mailgun" | "postmark" | "sendgrid";

export interface EmailConfig {
	service: EmailServiceId;
	settings: Record<string, string>;
}

export type GeocodingServiceId = "" | "google" | "bing" | "mapquest";

export interface GeocodingConfig {
	bing_key: string;
	google_key: string;
	mapquest_key: string;
	service: GeocodingServiceId;
}

export type CloudProvider = "amazon" | "rackspace" | "google";

export interface CloudProviderState {
	active: boolean;
	settings: Record<string, unknown> & { [k: `${string}_set`]: boolean | undefined };
}

export interface CloudStorageConfig {
	default_container: string;
	default_service: "local" | CloudProvider;
	providers: Record<CloudProvider, CloudProviderState>;
}

/** One page of the paged S3 recache. Loop while `complete` is false. */
export interface AmazonRecacheResponse {
	cached: number;
	complete: boolean;
	marker: string | null;
	processed: number;
}

export type PaymentGatewayId =
	| ""
	| "authorize.net"
	| "paypal"
	| "paypal-rest"
	| "payflow"
	| "linkpoint";

export interface PaymentGatewayConfig {
	service: PaymentGatewayId;
	settings: Record<string, unknown>;
}

export interface AnalyticsStatus {
	property_id: string;
	service_account: string;
	verified: boolean;
}

export interface ServiceState {
	connected: boolean;
	has_secret: boolean;
	identity: string;
	/** Stored client key/app id (not secret). */
	key: string;
	scope: string;
	test_environment: boolean;
	/** Whether this provider exposes an editable scope field. */
	uses_scope: boolean;
}

export type ServicesIndex = Record<string, ServiceState>;

export interface ServiceCredentials {
	key: string;
	scope?: string;
	secret: string;
	test_environment?: boolean;
}

export interface MediaPreset {
	[key: string]: unknown;
	id: string;
	name: string;
}

export interface MediaPresetsConfig {
	presets: MediaPreset[];
}

export interface FileMetadataField {
	id: string;
	settings: Record<string, unknown>;
	subtitle: string;
	title: string;
	type: string;
}

export interface FileMetadataConfig {
	file: FileMetadataField[];
	image: FileMetadataField[];
	video: FileMetadataField[];
}

export type AiServiceId = "" | "xai" | "openai" | "anthropic";

export interface AiModelOption {
	id: string;
	label: string;
}

export interface AiConfig {
	/** Always empty from the server; send a new key or "" to leave stored. */
	api_key: string;
	api_key_set: boolean;
	configured: boolean;
	/** Always empty from the server; OpenAI key used only for embeddings. */
	embedding_api_key: string;
	embedding_api_key_set: boolean;
	embedding_dimensions: number;
	/** True when chat is not OpenAI — a dedicated embedding API key is required. */
	embedding_key_required: boolean;
	embedding_model: string;
	/** Allowlisted OpenAI embedding models keyed by chat service id. */
	embedding_models: Record<string, AiModelOption[]>;
	/** True when bigtree_ai_embeddings table exists. */
	embeddings_ready: boolean;
	/** True when MySQL 9+ / MariaDB 11.7+ VECTOR is available. */
	embeddings_supported: boolean;
	features: {
		search: boolean;
		chat: boolean;
		embeddings: boolean;
	};
	/** Tokens the final, no-tools answer may generate; 0 = follow the provider default. */
	final_max_tokens: number;
	final_max_tokens_default: number;
	/** Tokens one tool-calling round may generate; 0 = follow the provider default. */
	max_tokens: number;
	max_tokens_default: number;
	model: string;
	/** Allowlisted chat models keyed by service id. */
	models: Record<string, AiModelOption[]>;
	service: AiServiceId;
	/** Upper bound accepted for either token budget. */
	token_max: number;
	/** Lower bound accepted for either token budget (blank/0 still means "default"). */
	token_min: number;
}

export interface AiEmbeddingsReindexResult {
	complete: boolean;
	indexed: number;
	page: number;
	pages: number;
	response: string;
}

export const configureApi = {
	email: {
		get: () => api.get<EmailConfig>("/system/configure/email"),
		update: (body: EmailConfig) => api.put<EmailConfig>("/system/configure/email", body),
	},

	geocoding: {
		get: () => api.get<GeocodingConfig>("/system/configure/geocoding"),
		update: (body: GeocodingConfig) =>
			api.put<GeocodingConfig>("/system/configure/geocoding", body),
	},

	cloudStorage: {
		get: () => api.get<CloudStorageConfig>("/system/configure/cloud-storage"),
		updateProvider: (provider: CloudProvider, body: Record<string, unknown>) =>
			api.put<CloudProviderState>(
				`/system/configure/cloud-storage/${encodeURIComponent(provider)}`,
				body
			),
		updateDefault: (body: {
			service: string;
			container?: string;
			cloudfront_distribution?: string;
			cloudfront_domain?: string;
			cloudfront_ssl?: string;
		}) =>
			api.put<{ default_service: string; default_container: string }>(
				"/system/configure/cloud-storage/default",
				body
			),
		/** Upload the Google service-account JSON / .p12 private key. */
		uploadGoogleKey: (file: File) =>
			api.post<CloudProviderState>(
				"/system/configure/cloud-storage/google/private-key",
				toFormData(file)
			),
		/** Begin the Google Cloud OAuth handshake; returns a URL to navigate to. */
		startGoogleOAuth: () =>
			api.post<{ launch_url: string }>(
				"/system/configure/cloud-storage/google/oauth/start",
				{}
			),
		/**
		 * Re-push one page of the local file cache against the live S3 bucket.
		 * The bucket is paged server-side: pass the `marker` returned by the
		 * previous call (omit it to start fresh) and loop until `complete`.
		 */
		recacheAmazon: (marker?: string) =>
			api.post<AmazonRecacheResponse>("/system/configure/cloud-storage/amazon/recache", {
				marker: marker ?? "",
			}),
	},

	paymentGateway: {
		get: () => api.get<PaymentGatewayConfig>("/system/configure/payment-gateway"),
		update: (body: PaymentGatewayConfig) =>
			api.put<PaymentGatewayConfig>("/system/configure/payment-gateway", body),
		/** Upload the LinkPoint .pem certificate. */
		uploadLinkpointCertificate: (file: File) =>
			api.post<PaymentGatewayConfig>(
				"/system/configure/payment-gateway/linkpoint/certificate",
				toFormData(file)
			),
	},

	analytics: {
		get: () => api.get<AnalyticsStatus>("/system/configure/analytics"),
		disconnect: () => api.delete<void>("/system/configure/analytics"),
		/** Upload the Google service-account JSON key. */
		uploadCredentials: (file: File) =>
			api.post<AnalyticsStatus>("/system/configure/analytics/credentials", toFormData(file)),
		/** Set + verify the GA4 property ID against the uploaded credentials. */
		setProperty: (property_id: string) =>
			api.put<AnalyticsStatus>("/system/configure/analytics", { property_id }),
	},

	services: {
		list: () => api.get<ServicesIndex>("/system/configure/services"),
		disconnect: (service: string) =>
			api.delete<void>(`/system/configure/services/${encodeURIComponent(service)}`),
		/** Save credentials and begin the OAuth handshake; returns a URL to navigate to. */
		startOAuth: (service: string, body: ServiceCredentials) =>
			api.post<{ launch_url: string }>(
				`/system/configure/services/${encodeURIComponent(service)}/oauth/start`,
				body
			),
	},

	mediaPresets: {
		get: () => api.get<MediaPresetsConfig>("/system/configure/media-presets"),
		update: (body: MediaPresetsConfig) =>
			api.put<MediaPresetsConfig>("/system/configure/media-presets", body),
	},

	fileMetadata: {
		get: () => api.get<FileMetadataConfig>("/system/configure/file-metadata"),
		update: (body: FileMetadataConfig) =>
			api.put<FileMetadataConfig>("/system/configure/file-metadata", body),
	},

	ai: {
		get: () => api.get<AiConfig>("/system/configure/ai"),
		update: (body: {
			service: AiServiceId;
			api_key: string;
			/** Purge the stored chat key (blank api_key otherwise means "keep"). */
			api_key_clear?: boolean;
			embedding_api_key?: string;
			/** Purge the stored embeddings key. */
			embedding_api_key_clear?: boolean;
			model: string;
			embedding_model: string;
			/** 0 or omitted = follow the provider default. */
			max_tokens?: number;
			final_max_tokens?: number;
			features: { search: boolean; chat: boolean; embeddings: boolean };
		}) => api.put<AiConfig>("/system/configure/ai", body),
		/** One page of a batched embeddings rebuild (page 0 = probe). */
		reindexEmbeddings: (page = 0) =>
			api.post<AiEmbeddingsReindexResult>("/system/configure/ai/embeddings/reindex", {
				page,
			}),
	},
};
