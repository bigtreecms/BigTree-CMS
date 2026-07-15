import { api } from "@/api/client";
import type { MediaPreset } from "./configure";

/**
 * Developer → Debug section.
 *
 *   Wraps the /system/* routes that aren't part of the Configure group:
 *   version/build info, the login security policy, ban management, the
 *   cache flush, and the on-demand database backup lifecycle.
 */

/**
 * Image preset from Configure → Media Settings; consumed by the image_options
 * control. Canonically defined in `./configure`; re-exported here for the
 * `/system/configure/media-presets` accessor below and legacy import sites.
 */
export type { MediaPreset };

export interface SystemVersion {
	php: string;
	revision: number;
	version: string;
}

/** GET /system/site — site identity + the roots the SPA links out to. */
export interface SiteInfo {
	/** Admin UI root URL (SPA is served at this path). */
	admin_root: string;
	nav_title: string;
	www_root: string;
}

export const siteApi = {
	get: () => api.get<SiteInfo>("/system/site"),
};

/**
 * GET /system/status — the developer "Site Status" audit. Status strings
 * follow the legacy convention: "bad" (critical), "ok" (warning), "good" (ok).
 */
export type StatusLevel = "bad" | "ok" | "good";

export interface SiteStatusWarning {
	nav_title?: string;
	/** Present only on "Bad Admin Links" rows so the SPA can link to the page editor. */
	page_id?: number;
	parameter: string;
	rec: string;
	status: StatusLevel;
}

export interface SiteStatusParameter {
	parameter: string;
	rec: string;
	status: StatusLevel;
	/** Present on rows that report a measured value (upload size, memory limit). */
	value?: string;
}

export interface SiteStatus {
	parameters: SiteStatusParameter[];
	warnings: SiteStatusWarning[];
}

/**
 * The login security policy is a free-form bag persisted under the
 * `bigtree-internal-security-policy` internal setting. PATCH does a recursive
 * merge server-side, so a partial body only touches the keys it carries.
 */
export interface SecurityFailRule {
	ban: number | string;
	count: number | string;
	time: number | string;
}

export interface SecurityPasswordPolicy {
	invitations: string;
	length: number | string;
	mixedcase: string;
	nonalphanumeric: string;
	numbers: string;
}

export interface SecurityPolicy {
	[key: string]: unknown;
	allowed_ips: string;
	banned_ips: string;
	include_daily_bans: string;
	ip_fails: SecurityFailRule;
	logout_all: string;
	password: SecurityPasswordPolicy;
	remember_disabled: string;
	suspect_geo_check: string;
	two_factor: string;
	user_fails: SecurityFailRule;
}

export interface Backup {
	age_seconds?: number;
	backup_id: string;
	created_at: string;
	download_url: string;
	elapsed_ms?: number;
	expires_at: string;
	filename?: string;
	size_bytes: number;
}

/**
 * Core upgrade pipeline. The SPA drives these in order — check, download,
 * install, then loop migrate over the returned queue. Major releases come back
 * with `installable: false` (they must be done by hand).
 */
export type UpgradeMethod = "Local" | "FTP" | "SFTP";

export interface UpgradeAvailable {
	installable: boolean;
	note: string;
	release_date: string | null;
	type: "revision" | "minor" | "major";
	version: string;
}

export interface UpgradeCheck {
	config_ignored: boolean;
	/** Core code target revision (version.php). */
	core_revision?: number;
	/** Applied DB revision (bigtree-internal-revision). */
	current_revision: number;
	current_version: string;
	method: UpgradeMethod | null;
	migration_queue?: string[];
	migrations_pending?: boolean;
	updates: UpgradeAvailable[];
}

export interface UpgradeDownload {
	method: UpgradeMethod;
	needs_credentials: boolean;
	ok: boolean;
	size_bytes: number;
	version: string;
}

export interface UpgradeInstall {
	bad_root?: string;
	method: UpgradeMethod;
	needs_credentials?: boolean;
	needs_ftp_root?: boolean;
	next?: "migrate";
	ok: boolean;
}

export interface UpgradeMigrations {
	current_revision: number;
	/** True when queue is non-empty. */
	pending?: boolean;
	queue: string[];
	target_revision: number | null;
}

/** Verbatim legacy migration-script contract. */
export interface UpgradeMigrationResult {
	complete?: boolean;
	error?: string;
	pages?: number;
	response?: string;
}

export interface UpgradeInstallBody {
	ftp_password?: string;
	ftp_root?: string;
	ftp_username?: string;
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
