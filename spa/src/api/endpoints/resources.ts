import { api } from "@/api/client";

import type { ResourceSummary } from "@/api/endpoints/resource-folders";

/**
 * Resource (media-library file) endpoints. Pairs with `resource-folders.ts`
 * which handles the folder tree + folder contents listing.
 *
 * Uploads (`POST /resources/upload`) go through `useUploads` rather than this
 * module — XHR is required for progress events, so it doesn't share the fetch
 * pipeline. The path is still exposed here as `UPLOAD_PATH` so call sites have
 * one canonical reference.
 */

/** Extra fields returned by `GET /resources/{id}` (vs the listing summary). */
export interface ResourceCrop {
	name: string;
	prefix: string;
	directory: string;
	width: number;
	height: number;
	file: string;
	created_at: string;
}

export interface ResourceThumb {
	prefix?: string;
	directory?: string;
	width?: number;
	height?: number;
	file?: string;
}

export interface ResourceDetail extends ResourceSummary {
	location: string;
	md5: string;
	metadata: Record<string, unknown>;
	crops: ResourceCrop[];
	thumbs: ResourceThumb[];
	video_data: Record<string, unknown>;
}

export interface ResourceAllocation {
	table: string;
	entry: string;
	updated_at: string;
}

export interface UpdateResourcePayload {
	name?: string;
	folder?: number;
	metadata?: Record<string, unknown>;
}

export interface CropPayload {
	x: number;
	y: number;
	width: number;
	height: number;
	target_width?: number;
	target_height?: number;
	prefix?: string;
	directory?: string;
}

export interface CropResult {
	file: string;
	width: number;
	height: number;
	prefix: string;
}

export const UPLOAD_PATH = "/resources/upload";

export const resourcesApi = {
	get: (id: number) => api.get<ResourceDetail>(`/resources/${id}`),

	update: (id: number, body: UpdateResourcePayload) =>
		api.patch<ResourceDetail>(`/resources/${id}`, body),

	delete: (id: number) => api.delete<void>(`/resources/${id}`),

	search: (q: string) => api.get<ResourceSummary[]>("/resources/search", { query: { q } }),

	crop: (id: number, body: CropPayload) => api.post<CropResult>(`/resources/${id}/crop`, body),

	allocations: (id: number) => api.get<ResourceAllocation[]>(`/resources/${id}/allocations`),

	allocate: (id: number, table: string, entry: string) =>
		api.post<ResourceAllocation>(`/resources/${id}/allocations`, { table, entry }),

	deallocate: (id: number, table: string, entry: string) =>
		api.delete<void>(`/resources/${id}/allocations`, { table, entry }),
};
