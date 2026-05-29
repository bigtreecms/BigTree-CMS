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

export type EmailServiceId =
	| "local"
	| "smtp"
	| "mandrill"
	| "mailgun"
	| "postmark"
	| "sendgrid";

export interface EmailConfig {
	service: EmailServiceId;
	settings: Record<string, string>;
}

export type GeocodingServiceId = "" | "google" | "bing" | "mapquest";

export interface GeocodingConfig {
	service: GeocodingServiceId;
	google_key: string;
	bing_key: string;
	mapquest_key: string;
}

export type CloudProvider = "amazon" | "rackspace" | "google";

export interface CloudProviderState {
	active: boolean;
	settings: Record<string, unknown> & { [k: `${string}_set`]: boolean | undefined };
}

export interface CloudStorageConfig {
	default_service: "local" | CloudProvider;
	default_container: string;
	providers: Record<CloudProvider, CloudProviderState>;
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
	verified: boolean;
	property_id: string;
	service_account: string;
	setup_url: string;
}

export interface ServicesIndex {
	[service: string]: { connected: boolean; identity: string } | string;
	_setup_url_base: string;
}

export interface MediaPreset {
	id: string;
	name: string;
	[key: string]: unknown;
}

export interface MediaPresetsConfig {
	presets: MediaPreset[];
}

export interface FileMetadataField {
	id: string;
	title: string;
	subtitle: string;
	type: string;
	settings: Record<string, unknown>;
}

export interface FileMetadataConfig {
	file: FileMetadataField[];
	image: FileMetadataField[];
	video: FileMetadataField[];
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
		updateDefault: (body: { service: string; container?: string }) =>
			api.put<{ default_service: string; default_container: string }>(
				"/system/configure/cloud-storage/default",
				body
			),
	},

	paymentGateway: {
		get: () => api.get<PaymentGatewayConfig>("/system/configure/payment-gateway"),
		update: (body: PaymentGatewayConfig) =>
			api.put<PaymentGatewayConfig>("/system/configure/payment-gateway", body),
	},

	analytics: {
		get: () => api.get<AnalyticsStatus>("/system/configure/analytics"),
		disconnect: () => api.delete<void>("/system/configure/analytics"),
	},

	services: {
		list: () => api.get<ServicesIndex>("/system/configure/services"),
		disconnect: (service: string) =>
			api.delete<void>(`/system/configure/services/${encodeURIComponent(service)}`),
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
};
