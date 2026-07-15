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
	grayscale?: boolean;
	height: number;
	prefix: string;
	width: number;
}

/**
 * A crop the server couldn't generate automatically because the source is
 * larger than the target — the user must draw it. Carries everything needed to
 * finalize the crop via `POST /images/crop`.
 */
export interface PendingCrop {
	center_crops: CropDerivativeSpec[];
	directory: string;
	grayscale: boolean;
	height: number;
	name: string;
	prefix: string;
	retina: boolean;
	thumbs: CropDerivativeSpec[];
	width: number;
}

/** Response from `/images/process` and `/images/reprocess`. */
export interface ProcessImageResult {
	/** Stored original path (the value the field persists). */
	file: string;
	height: number;
	pending_crops: PendingCrop[];
	width: number;
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
	center_crops: CropDerivativeSpec[];
	directory: string;
	/** Stored original path the crop is taken from. */
	file: string;
	grayscale: boolean;
	height: number;
	name: string;
	prefix: string;
	retina: boolean;
	target_height: number;
	/** Output dimensions. */
	target_width: number;
	thumbs: CropDerivativeSpec[];
	width: number;
	/** Crop rect in source pixels. */
	x: number;
	y: number;
}

export interface CropFinalizeResult {
	file: string;
	height: number;
	prefix: string;
	width: number;
}

/** Path used by `useUploads` for the multipart upload. */
export const IMAGE_PROCESS_PATH = "/images/process";

export const imagesApi = {
	reprocess: (source: ReprocessSource, settings: Record<string, unknown>) =>
		api.post<ProcessImageResult>("/images/reprocess", { source, settings }),

	crop: (payload: CropFinalizePayload) => api.post<CropFinalizeResult>("/images/crop", payload),
};
