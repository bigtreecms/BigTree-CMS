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

/**
 * Single entry inside the `crops` or `thumbs` map. The server-side column is
 * a JSON map of `prefix → { width, height, ... }` to match the legacy admin's
 * shape; `presentResource()` augments each entry with a derived `file` URL
 * (`prefixFile(originalFile, prefix)`) so the SPA can render uniformly without
 * knowing the prefix-insertion mechanics.
 *
 * The optional fields are populated for user-added crops (from the crop
 * endpoint) but absent for thumbnails generated at upload time.
 */
export interface ResourcePrefixedAsset {
	prefix: string;
	width: number;
	height: number;
	file: string;
	name?: string;
	directory?: string;
	created_at?: string;
}

/** Extra fields returned by `GET /resources/{id}` (vs the listing summary). */
export interface ResourceDetail extends ResourceSummary {
	location: string;
	md5: string;
	metadata: Record<string, unknown>;
	/** prefix → asset map. Empty when there are no derived crops. */
	crops: Record<string, ResourcePrefixedAsset>;
	thumbs: Record<string, ResourcePrefixedAsset>;
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

export interface CreateVideoPayload {
	url: string;
	folder?: number;
}

export const UPLOAD_PATH = "/resources/upload";

/** A developer-configured metadata field definition (per file kind). */
export interface ResourceMetadataField {
	id: string;
	title: string;
	subtitle: string;
	type: string;
	settings: Record<string, unknown> | null;
}

/** Metadata definitions split by file kind, from GET /resources/metadata-fields. */
export interface ResourceMetadataConfig {
	file: ResourceMetadataField[];
	image: ResourceMetadataField[];
	video: ResourceMetadataField[];
}

export const resourcesApi = {
	get: (id: number) => api.get<ResourceDetail>(`/resources/${id}`),

	/** Developer-defined metadata fields, readable by any editor. */
	metadataFields: () => api.get<ResourceMetadataConfig>("/resources/metadata-fields"),

	update: (id: number, body: UpdateResourcePayload) =>
		api.patch<ResourceDetail>(`/resources/${id}`, body),

	/**
	 * Swap the stored bytes while keeping the URL + allocations. Images are
	 * re-run through the default media preset (crops/thumbs regenerate) and
	 * must be at least as large as the largest existing crop.
	 */
	replace: (id: number, file: File) => {
		const form = new FormData();
		form.append("file", file);

		return api.post<ResourceDetail>(`/resources/${id}/replace`, form);
	},

	delete: (id: number) => api.delete<void>(`/resources/${id}`),

	search: (q: string) => api.get<ResourceSummary[]>("/resources/search", { query: { q } }),

	/**
	 * Create a managed-video resource from a YouTube or Vimeo URL. Server-side
	 * pulls oembed metadata + thumbnail and inserts a is_video=on row.
	 */
	createVideo: (body: CreateVideoPayload) => api.post<ResourceDetail>("/resources/video", body),

	crop: (id: number, body: CropPayload) => api.post<CropResult>(`/resources/${id}/crop`, body),

	allocations: (id: number) => api.get<ResourceAllocation[]>(`/resources/${id}/allocations`),

	allocate: (id: number, table: string, entry: string) =>
		api.post<ResourceAllocation>(`/resources/${id}/allocations`, { table, entry }),

	deallocate: (id: number, table: string, entry: string) =>
		api.delete<void>(`/resources/${id}/allocations`, { table, entry }),
};
