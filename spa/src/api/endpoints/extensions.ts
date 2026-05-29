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

export const extensionsApi = {
	list: (sort = "name") => api.get<Extension[]>("/extensions", { query: { sort } }),
	get: (id: string) => api.get<Extension>(`/extensions/${encodeURIComponent(id)}`),
	remove: (id: string) => api.delete<void>(`/extensions/${encodeURIComponent(id)}`),
};
