import { useCallback, useMemo, useRef } from "react";

import { embedFormsApi } from "@/api/endpoints/embed-forms";
import type { FieldUploadTarget, StoredUpload } from "@/renderer/fields/FieldUploadContext";

/**
 * The upload target for a public embed form: image and file fields post to the
 * form's own hash-scoped routes (which read the field's settings server-side),
 * the media library is off, and each upload's claim token is kept so the
 * submission can send them back as `upload_tokens`. Fields inside a matrix are
 * named by their dotted path, so one path can hold a token per row.
 */
/** Every string anywhere in a (nested) form value. */
const collectStrings = (value: unknown, out: Set<string>): void => {
	if (typeof value === "string") {
		out.add(value);

		return;
	}

	if (value && typeof value === "object") {
		for (const item of Object.values(value)) {
			collectStrings(item, out);
		}
	}
};

export const useEmbedUploadTarget = (hash: string, challenge: string) => {
	const uploads = useRef<StoredUpload[]>([]);

	const target = useMemo<FieldUploadTarget>(
		() => ({
			imagePath: embedFormsApi.imagePath(hash),
			filePath: embedFormsApi.filePath(hash),
			libraryAccess: false,
			extraFor: (column) => ({ column, challenge }),
			onStored: (_column, upload) => {
				uploads.current.push(upload);
			},
			crop: (crop, _file, rect) => {
				if (!crop.token) {
					return Promise.reject(new Error("This crop can't be finalized."));
				}

				return embedFormsApi.crop(hash, { token: crop.token, ...rect });
			},
		}),
		[hash, challenge]
	);

	/**
	 * Claim tokens for the stored files still somewhere in the form's values. A
	 * replaced or removed file sends nothing; the server checks each token's
	 * field path against where the file actually is.
	 */
	const tokensFor = useCallback((values: Record<string, unknown>): string[] => {
		const inUse = new Set<string>();

		collectStrings(values, inUse);

		return [
			...new Set(
				uploads.current
					.filter((upload) => upload.token && inUse.has(upload.file))
					.map((upload) => upload.token as string)
			),
		];
	}, []);

	return { target, tokensFor };
};
