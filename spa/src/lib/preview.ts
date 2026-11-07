import { authApi } from "@/auth/endpoints";
import { expandImageUrl } from "@/lib/imageUrl";

/**
 * "Save & Preview" support for page and module forms.
 *
 * Previews render on the front end (the router's `_preview/` and
 * `_preview-pending/` routes, or a module view's `preview_url`), which reads the
 * admin's PHP session, so the session bridge has to finish before the browser
 * gets there. The tab is opened synchronously in the click handler — popup
 * blockers only allow window.open during a user gesture — and pointed at the
 * preview once the save and the bridge are done.
 */

/** Open a blank tab for a preview to load into later. Null if it was blocked. */
export const openPreviewWindow = (): Window | null => window.open("about:blank", "_blank");

/**
 * Append `bigtree_preview_return` so the front-end bar shows the preview bar
 * with a "Continue Editing" link back to `returnTo`.
 */
export const withPreviewReturn = (url: string, returnTo: string): string => {
	const separator = url.includes("?") ? "&" : "?";

	return `${url}${separator}bigtree_preview_return=${encodeURIComponent(returnTo)}`;
};

/**
 * Send a tab from {@link openPreviewWindow} to `url`, after making sure the PHP
 * session exists. Falls back to the current tab when the popup was blocked.
 * The preview bar's "Continue Editing" goes to `returnTo` (default: this page).
 */
export const showPreview = async (
	win: Window | null,
	url: string,
	returnTo: string = window.location.href
): Promise<void> => {
	const target = withPreviewReturn(url, returnTo);

	try {
		await authApi.ensurePhpSession();
	} catch {
		// Without the bridge the preview route shows the live page instead; still
		// worth opening so the editor sees something rather than a blank tab.
	}

	if (win && !win.closed) {
		win.location.href = target;

		return;
	}

	window.location.assign(target);
};

/** Close a preview tab that won't be used (the save failed or had no URL). */
export const discardPreviewWindow = (win: Window | null): void => {
	if (win && !win.closed) {
		win.close();
	}
};

/**
 * A module entry's preview URL: the view's `preview_url` with the entry id
 * appended as a path segment (`p{id}` for a pending entry), as the legacy views
 * and form processor built it. Placeholder roots (`{wwwroot}`) are expanded.
 */
export const modulePreviewUrl = (previewUrl: string, id: string | number): string =>
	`${expandImageUrl(previewUrl.trim()).replace(/\/+$/, "")}/${encodeURIComponent(String(id))}/`;
