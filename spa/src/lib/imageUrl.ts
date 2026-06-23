/**
 * Helpers for the `{wwwroot}` / `{staticroot}` placeholder paths the BigTree
 * backend stores for portable asset URLs.
 *
 * In production the SPA shares an origin with the assets, so the placeholders
 * become page-relative (`/files/...`). In dev the SPA runs on the Vite origin
 * (localhost:5173) while assets live on the BigTree backend, so we resolve the
 * placeholders to the backend's install root instead — otherwise images 404
 * against the dev server.
 */

const PLACEHOLDER_TOKEN_RE = /\{(www|static)root\}/g;

/**
 * Base URL the `{wwwroot}`/`{staticroot}` tokens expand to (without trailing
 * slash). Empty string → page-relative (production / same-origin).
 */
const ASSET_BASE = resolveAssetBase();

function resolveAssetBase(): string {
	// Production: the SPA is served from the BigTree origin, so page-relative works.
	if (import.meta.env.PROD) {
		return "";
	}

	// Explicit override wins (assets on a separate host / CDN).
	const explicit = import.meta.env.VITE_ASSET_BASE;

	if (explicit) {
		return explicit.replace(/\/+$/, "");
	}

	// Otherwise derive the BigTree install root from the API proxy target, so the
	// Vite dev server points image URLs at the real backend (incl. subpath installs).
	const target = import.meta.env.VITE_API_TARGET;

	if (target) {
		try {
			const url = new URL(target);
			const base = url.pathname.replace(/\/admin\/api\/v1\/?$/, "").replace(/\/+$/, "");

			return url.origin + base;
		} catch {
			return "";
		}
	}

	return "";
}

/**
 * Expand a stored asset path to a browser-loadable URL, optionally inserting a
 * crop/thumbnail `prefix` before the filename (mirrors `BigTree::prefixFile`).
 */
export const expandImageUrl = (raw: unknown, prefix = ""): string => {
	if (typeof raw !== "string" || !raw) {
		return "";
	}

	const expanded = raw.replace(PLACEHOLDER_TOKEN_RE, `${ASSET_BASE}/`);

	if (!prefix) {
		return expanded;
	}

	return prefixFile(expanded, prefix);
};

/**
 * Insert `prefix` immediately before the filename in a path. Mirrors
 * `BigTree::prefixFile` from the PHP admin.
 */
const prefixFile = (path: string, prefix: string): string => {
	const idx = path.lastIndexOf("/");

	if (idx < 0) {
		return prefix + path;
	}

	return path.slice(0, idx + 1) + prefix + path.slice(idx + 1);
};
