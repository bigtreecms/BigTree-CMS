import { api } from "@/api/client";

/**
 * Developer → Debug section.
 *
 *   Wraps the /system/* routes that aren't part of the Configure group:
 *   version/build info, the login security policy, ban management, the
 *   cache flush, and the on-demand database backup lifecycle.
 */

/** Image preset from Configure → Media Settings; consumed by the image_options control. */
export interface MediaPreset {
	id: string;
	name: string;
	[key: string]: unknown;
}

export interface SystemVersion {
	version: string;
	revision: number;
	php: string;
}

/**
 * GET /system/status — the developer "Site Status" audit. Status strings
 * follow the legacy convention: "bad" (critical), "ok" (warning), "good" (ok).
 */
export type StatusLevel = "bad" | "ok" | "good";

export interface SiteStatusWarning {
	parameter: string;
	rec: string;
	status: StatusLevel;
	/** Present only on "Bad Admin Links" rows so the SPA can link to the page editor. */
	page_id?: number;
	nav_title?: string;
}

export interface SiteStatusParameter {
	parameter: string;
	rec: string;
	status: StatusLevel;
	/** Present on rows that report a measured value (upload size, memory limit). */
	value?: string;
}

export interface SiteStatus {
	warnings: SiteStatusWarning[];
	parameters: SiteStatusParameter[];
}

/**
 * The login security policy is a free-form bag persisted under the
 * `bigtree-internal-security-policy` internal setting. PATCH does a recursive
 * merge server-side, so a partial body only touches the keys it carries.
 */
export interface SecurityFailRule {
	count: number | string;
	time: number | string;
	ban: number | string;
}

export interface SecurityPasswordPolicy {
	invitations: string;
	length: number | string;
	mixedcase: string;
	numbers: string;
	nonalphanumeric: string;
}

export interface SecurityPolicy {
	user_fails: SecurityFailRule;
	ip_fails: SecurityFailRule;
	password: SecurityPasswordPolicy;
	two_factor: string;
	remember_disabled: string;
	logout_all: string;
	suspect_geo_check: string;
	include_daily_bans: string;
	allowed_ips: string;
	banned_ips: string;
	[key: string]: unknown;
}

export interface Backup {
	backup_id: string;
	size_bytes: number;
	created_at: string;
	expires_at: string;
	age_seconds?: number;
	download_url: string;
	filename?: string;
	elapsed_ms?: number;
}

/**
 * Core upgrade pipeline. The SPA drives these in order — check, download,
 * install, then loop migrate over the returned queue. Major releases come back
 * with `installable: false` (they must be done by hand).
 */
export type UpgradeMethod = "Local" | "FTP" | "SFTP";

export interface UpgradeAvailable {
	type: "revision" | "minor" | "major";
	version: string;
	release_date: string | null;
	note: string;
	installable: boolean;
}

export interface UpgradeCheck {
	current_version: string;
	current_revision: number;
	method: UpgradeMethod | null;
	config_ignored: boolean;
	updates: UpgradeAvailable[];
}

export interface UpgradeDownload {
	ok: boolean;
	method: UpgradeMethod;
	version: string;
	size_bytes: number;
	needs_credentials: boolean;
}

export interface UpgradeInstall {
	ok: boolean;
	method: UpgradeMethod;
	next?: "migrate";
	needs_credentials?: boolean;
	needs_ftp_root?: boolean;
	bad_root?: string;
}

export interface UpgradeMigrations {
	current_revision: number;
	target_revision: number | null;
	queue: string[];
}

/** Verbatim legacy migration-script contract. */
export interface UpgradeMigrationResult {
	complete?: boolean;
	response?: string;
	pages?: number;
	error?: string;
}

export interface UpgradeInstallBody {
	ftp_username?: string;
	ftp_password?: string;
	ftp_root?: string;
}

export const systemApi = {
	version: () => api.get<SystemVersion>("/system/version"),

	status: () => api.get<SiteStatus>("/system/status"),

	upgrade: {
		check: () => api.get<UpgradeCheck>("/system/upgrade/check"),
		download: (type: "revision" | "minor") =>
			api.post<UpgradeDownload>("/system/upgrade/download", { type }),
		install: (body: UpgradeInstallBody = {}) =>
			api.post<UpgradeInstall>("/system/upgrade/install", body),
		migrations: () => api.get<UpgradeMigrations>("/system/upgrade/migrations"),
		migrate: (script: string, page?: number, total_pages?: number) =>
			api.post<UpgradeMigrationResult>("/system/upgrade/migrate", {
				script,
				...(page ? { page } : {}),
				...(total_pages ? { total_pages } : {}),
			}),
	},

	clearCache: () => api.post<void>("/system/cache/clear"),

	mediaPresets: () => api.get<{ presets: MediaPreset[] }>("/system/configure/media-presets"),

	securityPolicy: {
		get: () => api.get<Partial<SecurityPolicy>>("/system/security-policy"),
		update: (body: Partial<SecurityPolicy>) =>
			api.patch<Partial<SecurityPolicy>>("/system/security-policy", body),
	},

	bans: {
		unbanIP: (ip: string) => api.post<void>("/system/bans/unban-ip", { ip }),
		unbanUser: (user_id: number) => api.post<void>("/system/bans/unban-user", { user_id }),
	},

	backups: {
		list: () => api.get<Backup[]>("/system/backup"),
		create: () => api.post<Backup>("/system/backup"),
		remove: (id: string) => api.delete<void>(`/system/backup/${encodeURIComponent(id)}`),
	},
};
