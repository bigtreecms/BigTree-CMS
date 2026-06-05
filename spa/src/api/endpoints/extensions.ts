import { api } from "@/api/client";

/**
 * Developer → Debug → Extensions.
 *
 *   Installed extensions live in the JSONDB. Uninstalling cascades to the
 *   resources the manifest declares (modules, templates, callouts, etc.),
 *   so the DELETE is destructive — confirm before calling.
 */

export interface Extension {
	id: string;
	name: string;
	version: string;
	manifest: Record<string, unknown>;
	installed_at: string | null;
}

/** Per-extension result of the registry version check (GET /extensions/updates). */
export interface ExtensionUpdate {
	id: string;
	name: string;
	version: string;
	update_available: boolean;
	available_version: string | null;
	compatibility: string | null;
}

/** Result of an in-place upgrade (POST /extensions/{id}/upgrade). */
export interface ExtensionUpgradeResult {
	id: string;
	version: string;
	/** Captured output of the extension's optional update.php, if any. */
	output: string;
}

/** Result of staging an uploaded package (POST /extensions/install/unpack). */
export interface ExtensionInstallPreview {
	manifest: {
		id: string;
		title: string;
		version: string;
		author: { name?: string } | null;
	};
	/** Non-blocking notes (e.g. a file/table will be overwritten). */
	warnings: string[];
	/** Blocking problems (e.g. unwritable path). Install is disabled while non-empty. */
	errors: string[];
	ready: boolean;
}

/** Result of committing the staged package (POST /extensions/install/process). */
export interface ExtensionInstallResult {
	id: string;
	/** Captured output of the extension's optional install.php, if any. */
	output: string;
}

export const extensionsApi = {
	list: (sort = "name") => api.get<Extension[]>("/extensions", { query: { sort } }),
	get: (id: string) => api.get<Extension>(`/extensions/${encodeURIComponent(id)}`),
	remove: (id: string) => api.delete<void>(`/extensions/${encodeURIComponent(id)}`),

	/** Live version check against the official registry; never writes. */
	updates: () => api.get<ExtensionUpdate[]>("/extensions/updates"),

	/** Rebuild cache/bigtree-hooks.json from installed extensions' hooks/ dirs. */
	recacheHooks: () => api.post<{ status: string }>("/extensions/recache-hooks", {}),

	/** Download the latest package from the registry and reinstall it in place. */
	upgrade: (id: string) =>
		api.post<ExtensionUpgradeResult>(`/extensions/${encodeURIComponent(id)}/upgrade`, {}),

	/** Stage an uploaded zip and report what installing it would do (no commit). */
	installUnpack: (file: File) => {
		const form = new FormData();
		form.append("file", file);

		return api.post<ExtensionInstallPreview>("/extensions/install/unpack", form);
	},

	/** Commit the previously-staged package (runs install SQL + install.php). */
	installProcess: () => api.post<ExtensionInstallResult>("/extensions/install/process", {}),

	/** License options for the build wizard's details step. */
	buildLicenses: () => api.get<ExtensionLicenseCatalog>("/extensions/build/licenses"),

	/** Infer the tables/files/field-types implied by the chosen components. */
	buildInspect: (body: ExtensionInspectBody) =>
		api.post<ExtensionBuildInspect>("/extensions/build/inspect", body),

	/** Package the extension (destructive: namespaces ids + moves files). */
	build: (body: ExtensionBuildBody) => api.post<ExtensionBuildResult>("/extensions/build", body),
};

export interface ExtensionLicenseCatalog {
	"Open Source": Record<string, string>;
	"Closed Source": Record<string, string>;
}

/** Components the wizard sends to /build/inspect (and a subset of /build). */
export interface ExtensionInspectBody {
	id?: string;
	modules?: string[];
	templates?: string[];
	callouts?: string[];
	settings?: string[];
	feeds?: string[];
	field_types?: string[];
}

export interface ExtensionBuildInspect {
	module_groups: string[];
	field_types: string[];
	tables: string[];
	files: string[];
}

export interface ExtensionBuildBody extends ExtensionInspectBody {
	title: string;
	version?: string;
	compatibility?: string;
	description?: string;
	keywords?: string[];
	author?: { name?: string; email?: string; url?: string };
	licenses?: string[];
	license?: string;
	license_name?: string;
	license_url?: string;
	module_groups?: string[];
	tables?: string[];
	files?: string[];
}

export interface ExtensionBuildResult {
	id: string;
	download_url: string;
}
