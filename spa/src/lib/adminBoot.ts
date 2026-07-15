/**
 * Runtime admin path configuration for the SPA.
 *
 * In production (after the PHP cutover), `core/admin/router.php` injects
 * `window.__BIGTREE_ADMIN__` into `index.html` before the bundle runs. That
 * supplies the install-specific basename and API base (including subdirectory
 * installs like `/remaster/admin`).
 *
 * In production PHP injects the install-specific boot object. If injection is
 * missing, fall back to `/admin`. Dev always uses Vite at origin root with the
 * `/admin/api/v1` proxy.
 *
 * All API URL construction must go through {@link apiBase} — do not hardcode
 * `/admin/api/v1` in fetch/XHR callers.
 */

export interface BigTreeAdminBoot {
	/** REST API prefix including version, no trailing slash (e.g. "/admin/api/v1"). */
	apiBase: string;
	/** Asset base with trailing slash; aligns with Vite `BASE_URL` once placeholder rewrite lands. */
	assetBase?: string;
	/** React Router basename, no trailing slash (e.g. "/admin"). Empty string in dev. */
	basename: string;
	/** Optional public site root URL from PHP config. */
	wwwRoot?: string;
}

declare global {
	interface Window {
		__BIGTREE_ADMIN__?: BigTreeAdminBoot;
	}
}

/** Dev defaults: Vite at `/`, API via the configured proxy prefix. */
const DEV_BOOT: BigTreeAdminBoot = {
	basename: "",
	apiBase: "/admin/api/v1",
	assetBase: "/",
};

/** Production defaults when PHP has not injected window.__BIGTREE_ADMIN__. */
const PROD_FALLBACK_BOOT: BigTreeAdminBoot = {
	basename: "/admin",
	apiBase: "/admin/api/v1",
	assetBase: "/admin/",
};

/**
 * Pure resolver used by {@link getAdminBoot} and unit tests.
 * Prefers an injected boot object in production; never uses injection in dev.
 */
export const resolveAdminBoot = (
	mode: { dev: boolean },
	injected: BigTreeAdminBoot | null | undefined
): BigTreeAdminBoot => {
	if (mode.dev) {
		return DEV_BOOT;
	}

	if (injected?.apiBase && injected.basename !== undefined) {
		return {
			basename: injected.basename.replace(/\/+$/, ""),
			apiBase: injected.apiBase.replace(/\/+$/, ""),
			wwwRoot: injected.wwwRoot,
			assetBase: injected.assetBase,
		};
	}

	return PROD_FALLBACK_BOOT;
};

/** Current boot config for this page load. */
export const getAdminBoot = (): BigTreeAdminBoot => {
	const injected = typeof window !== "undefined" ? window.__BIGTREE_ADMIN__ : undefined;

	return resolveAdminBoot({ dev: import.meta.env.DEV }, injected);
};

/**
 * REST API path prefix (no trailing slash). Use for every admin API fetch/XHR.
 * Call per request (or after boot script has run) so injection is respected.
 */
export const apiBase = (): string => getAdminBoot().apiBase;

/**
 * React Router `basename` for {@link createBrowserRouter}.
 * Empty/dev basenames normalize to `"/"` (library mode root).
 */
export const routerBasename = (): string => {
	const { basename } = getAdminBoot();

	return basename === "" ? "/" : basename;
};

/**
 * Join an admin-relative path onto a basename (both path-only, no origin).
 * e.g. `joinAdminPath("/admin", "/dashboard")` → `/admin/dashboard`.
 */
export const joinAdminPath = (basename: string, path: string): string => {
	const normalized = path.startsWith("/") ? path : `/${path}`;

	if (!basename) {
		return normalized;
	}

	return `${basename}${normalized}`.replace(/\/{2,}/g, "/");
};

/**
 * Absolute-from-origin path under the admin SPA root (leading slash).
 * e.g. `adminPath("/dashboard")` → `/admin/dashboard` in production.
 */
export const adminPath = (path: string): string => {
	return joinAdminPath(getAdminBoot().basename, path);
};
