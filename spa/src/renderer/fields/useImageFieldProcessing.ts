import { useCallback, useMemo, useState } from "react";

import {
	IMAGE_PROCESS_PATH,
	imagesApi,
	type PendingCrop,
	type ProcessImageResult,
	type ReprocessSource,
} from "@/api/endpoints/images";
import { useLatestUpload } from "@/hooks/useLatestUpload";
import { useUploads } from "@/hooks/useUploads";
import { describeApiError } from "@/lib/errorHandling";

export interface UseImageFieldProcessingOptions {
	/** Commit a freshly processed image path (after any manual crops finalize). */
	onCommit: (file: string) => void;
	/** Surface an error message, or `null` to clear a previous one. */
	onError: (message: string | null) => void;
}

/**
 * The upload → `/images/process` → defer-until-crops-finalized pipeline shared
 * by `ImageField` and `MediaGalleryField`'s AddBar. Both run the field's own
 * crop settings over a chosen (or library-browsed) image, hold the commit until
 * any manual crops are finalized in the crop modal, and reprocess from the
 * library the same way. The two only differ in where a finished image goes
 * (`onCommit`) and how errors surface (`onError` — an inline alert vs a toast),
 * which are the two seams; the crop lifecycle stays identical here.
 */
export const useImageFieldProcessing = (
	settings: object,
	{ onCommit, onError }: UseImageFieldProcessingOptions
) => {
	const [reprocessing, setReprocessing] = useState(false);
	const [cropState, setCropState] = useState<{ file: string; crops: PendingCrop[] } | null>(null);
	const { items, enqueue } = useUploads();

	// The settings the server needs to apply the field's crops. Sent verbatim;
	// the backend reads the keys it cares about and ignores UI-only ones.
	const processSettings = useMemo(() => JSON.stringify(settings), [settings]);

	const applyResult = useCallback(
		(result: ProcessImageResult | undefined) => {
			if (!result?.file) {
				onError("The server did not return a processed image.");

				return;
			}

			onError(null);

			// Defer committing until any manual crops are finalized. If the user
			// cancels the cropper nothing was changed, so the prior state stands.
			if (result.pending_crops.length > 0) {
				setCropState({ file: result.file, crops: result.pending_crops });
			} else {
				onCommit(result.file);
			}
		},
		[onCommit, onError]
	);

	// Drain finished uploads (done → apply + maybe crop; error → surface).
	const { inFlight } = useLatestUpload(items, {
		onDone: (item) => applyResult(item.result as ProcessImageResult | undefined),
		onError: (item) => onError(item.error ?? "Upload failed."),
	});

	const enqueueFile = (file: File) => {
		enqueue([file], { path: IMAGE_PROCESS_PATH, extra: { settings: processSettings } });
	};

	const reprocess = async (source: ReprocessSource) => {
		if (reprocessing) {
			return;
		}

		setReprocessing(true);
		onError(null);

		try {
			const result = await imagesApi.reprocess(source, settings as Record<string, unknown>);
			applyResult(result);
		} catch (err) {
			onError(describeApiError(err, "Could not process the image."));
		} finally {
			setReprocessing(false);
		}
	};

	const cropModalProps = {
		open: Boolean(cropState),
		file: cropState?.file ?? "",
		crops: cropState?.crops ?? [],
		onComplete: () => {
			if (cropState) {
				onCommit(cropState.file);
			}

			setCropState(null);
		},
		onCancel: () => setCropState(null),
	};

	return { inFlight, reprocessing, enqueueFile, reprocess, cropModalProps };
};
