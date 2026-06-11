import { api } from "@/api/client";

/**
 * Resource-folder endpoints (`/resource-folders`). Files / resources live
 * inside a folder tree (parent=0 is "Home Folder"). The full Files manager
 * lands in Phase 3 — this module covers the read endpoints needed by both
 * the user-permissions tree (Phase 2) and the Files browser (Phase 3).
 *
 * `GET /resource-folders` returns the *contents* of a folder — its breadcrumb,
 * subfolders, child resources, and the caller's effective access level. The
 * permission tree only cares about `folders`, but we model the whole envelope
 * so Phase 3 can reuse it.
 */

export type FolderAccess = "n" | "v" | "e" | "p";

export interface ResourceFolderRow {
	id: number;
	parent: number;
	name: string;
	access: FolderAccess;
	/** Server-computed via EXISTS subquery on bigtree_resource_folders.parent. */
	has_children: boolean;
}

export interface ResourceSummary {
	id: number;
	folder: number;
	file: string;
	name: string;
	type: string;
	mimetype?: string;
	is_image: boolean;
	is_video?: boolean;
	height?: number;
	width?: number;
	size?: number;
	date?: string;
}

export interface FolderBreadcrumbEntry {
	id: number;
	name: string;
}

export interface FolderContents {
	breadcrumb: FolderBreadcrumbEntry[];
	folders: ResourceFolderRow[];
	resources: ResourceSummary[];
	access: FolderAccess;
}

/** A row from GET /resource-folders/flat — the whole tree in display order. */
export interface FlatFolderRow {
	id: number;
	parent: number;
	name: string;
	/** Nesting depth (0 = directly under Home) for select-option indentation. */
	depth: number;
	access: FolderAccess;
}

export interface CreateFolderPayload {
	parent: number;
	name: string;
}

export interface UpdateFolderPayload {
	name?: string;
	parent?: number;
}

export const resourceFoldersApi = {
	/** Full folder contents (folders + resources + breadcrumb). */
	listContents: (parent: number = 0) =>
		api.get<FolderContents>("/resource-folders", { query: { parent } }),

	/** Convenience: just the subfolder list, used by the user-permissions tree. */
	listSubfolders: (parent: number = 0) =>
		api
			.get<FolderContents>("/resource-folders", { query: { parent } })
			.then((res) => res.folders ?? []),

	/** The whole tree flattened (depth-first), permission-filtered server-side. */
	listFlat: () => api.get<FlatFolderRow[]>("/resource-folders/flat"),

	create: (body: CreateFolderPayload) => api.post<ResourceFolderRow>("/resource-folders", body),

	update: (id: number, body: UpdateFolderPayload) =>
		api.patch<ResourceFolderRow>(`/resource-folders/${id}`, body),

	delete: (id: number) => api.delete<void>(`/resource-folders/${id}`),
};
