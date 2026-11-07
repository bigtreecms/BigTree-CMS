import { createContext, useContext } from "react";

import { IMAGE_PROCESS_PATH, imagesApi, type PendingCrop } from "@/api/endpoints/images";
import { UPLOAD_PATH } from "@/api/endpoints/resources";

/** A crop selection in source pixels. */
export interface CropRect {
	height: number;
	width: number;
	x: number;
	y: number;
}

/** What the server hands back for a stored upload. */
export interface StoredUpload {
	file: string;
	/** Present when the target issues claim tokens (public embed forms). */
	token?: string;
}

/**
 * Where image and file fields send their uploads, and what else they may offer.
 * The admin default posts to the authenticated `/images/process` and
 * `/resources/upload` routes with the media library available. A public embed
 * form swaps in its own hash-scoped routes, turns the library off (an anonymous
 * visitor can't browse it), and collects the claim token each upload returns so
 * the submission can prove the file came from this form.
 */
export interface FieldUploadTarget {
	/** Finalize one manual crop drawn in the cropper. */
	crop: (crop: PendingCrop, file: string, rect: CropRect) => Promise<unknown>;
	/** Extra multipart fields sent with every upload for `column` (or path). */
	extraFor: (column: string) => Record<string, string>;
	/** Multipart path for an upload field's file. */
	filePath: string;
	/** Multipart path for an image field's file. */
	imagePath: string;
	/** Whether browsing / re-processing from the media library is available. */
	libraryAccess: boolean;
	/** Called with each stored upload, keyed by the field's column (or path). */
	onStored?: (column: string, upload: StoredUpload) => void;
}

export const adminUploadTarget: FieldUploadTarget = {
	imagePath: IMAGE_PROCESS_PATH,
	filePath: UPLOAD_PATH,
	libraryAccess: true,
	extraFor: () => ({}),
	crop: (crop, file, rect) =>
		imagesApi.crop({
			file,
			x: rect.x,
			y: rect.y,
			width: rect.width,
			height: rect.height,
			target_width: crop.width,
			target_height: crop.height,
			prefix: crop.prefix,
			name: crop.name,
			directory: crop.directory,
			retina: crop.retina,
			grayscale: crop.grayscale,
			thumbs: crop.thumbs,
			center_crops: crop.center_crops,
		}),
};

/**
 * The target for the sub-fields of a matrix at `column`: their uploads are
 * named by a dotted field path (`gallery.photo`, or `outer.inner.photo` for a
 * matrix inside a matrix) so a target that cares which field a file is for —
 * a public embed form — can find the sub-field's settings and bind the claim
 * token to it.
 */
export const scopeUploadTarget = (target: FieldUploadTarget, column: string): FieldUploadTarget => {
	const onStored = target.onStored;

	return {
		...target,
		extraFor: (sub) => target.extraFor(`${column}.${sub}`),
		onStored: onStored ? (sub, upload) => onStored(`${column}.${sub}`, upload) : undefined,
	};
};

const FieldUploadContext = createContext<FieldUploadTarget>(adminUploadTarget);

export const FieldUploadProvider = FieldUploadContext.Provider;

export const useFieldUploadTarget = (): FieldUploadTarget => useContext(FieldUploadContext);
