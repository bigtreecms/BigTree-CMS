import { api } from "@/api/client";

/**
 * Image form-field processing — the API equivalent of the legacy admin's image
 * field. Stores originals under a field's own `directory` (not the media
 * library) and generates crops. See `core/inc/bigtree/services/ImageService.php`.
 *
 * The multipart upload (`POST /images/process`) goes through `useUploads`
 * (XHR is required for progress), so it isn't defined here — only the JSON
 * endpoints (`reprocess`, `crop`) plus the shared response/payload types are.
 */

/** A nested thumbnail / center-crop spec carried inside a pending crop. */
export interface CropDerivativeSpec {
	prefix: string;
	width: number;
	height: number;
	grayscale?: boolean;
}

/**
 * A crop the server couldn't generate automatically because the source is
 * larger than the target — the user must draw it. Carries everything needed to
 * finalize the crop via `POST /images/crop`.
 */
export interface PendingCrop {
	prefix: string;
	width: number;
	height: number;
	retina: boolean;
	grayscale: boolean;
	thumbs: CropDerivativeSpec[];
	center_crops: CropDerivativeSpec[];
	directory: string;
	name: string;
}

/** Response from `/images/process` and `/images/reprocess`. */
export interface ProcessImageResult {
	/** Stored original path (the value the field persists). */
	file: string;
	width: number;
	height: number;
	pending_crops: PendingCrop[];
}

/**
 * Source for a re-process: an existing media resource (always copied into the
 * field directory) or a stored file path. For a stored file, `in_place: true`
 * regenerates crops against that original without duplicating it (the recrop
 * flow); otherwise it's copied like a media pick.
 */
export type ReprocessSource = { resource_id: number } | { file: string; in_place?: boolean };

/** Body for finalizing one manual crop. */
export interface CropFinalizePayload {
	/** Stored original path the crop is taken from. */
	file: string;
	/** Crop rect in source pixels. */
	x: number;
	y: number;
	width: number;
	height: number;
	/** Output dimensions. */
	target_width: number;
	target_height: number;
	prefix: string;
	name: string;
	directory: string;
	retina: boolean;
	grayscale: boolean;
	thumbs: CropDerivativeSpec[];
	center_crops: CropDerivativeSpec[];
}

export interface CropFinalizeResult {
	file: string;
	prefix: string;
	width: number;
	height: number;
}

/** Path used by `useUploads` for the multipart upload. */
export const IMAGE_PROCESS_PATH = "/images/process";

export const imagesApi = {
	reprocess: (source: ReprocessSource, settings: Record<string, unknown>) =>
		api.post<ProcessImageResult>("/images/reprocess", { source, settings }),

	crop: (payload: CropFinalizePayload) => api.post<CropFinalizeResult>("/images/crop", payload),
};
