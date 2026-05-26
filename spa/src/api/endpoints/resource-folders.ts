import { api } from "@/api/client";

/**
 * Resource-folder endpoints (`/resource-folders`). Files / resources live
 * inside a folder tree (parent=0 is "Home Folder"). The full Files manager
 * lands in Phase 3 — this module covers the read endpoints needed by the
 * user-permissions tree.
 */

export interface ResourceFolderSummary {
	id: number;
	parent: number;
	name: string;
	/** Whether this folder has any direct children — used to drive expand caret rendering. */
	has_children?: boolean;
	updated_at?: string;
}

export const resourceFoldersApi = {
	list: (parent: number = 0) =>
		api.get<ResourceFolderSummary[]>("/resource-folders", { query: { parent } }),
};
