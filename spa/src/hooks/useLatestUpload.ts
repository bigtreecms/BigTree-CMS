import { useEffect, useRef } from "react";

import type { UploadItem } from "@/hooks/useUploads";

export interface UseLatestUploadOptions {
	/**
	 * Called once for each upload that finishes successfully, in id order. The
	 * finished item carries the API-decoded `result`.
	 */
	onDone: (item: UploadItem) => void;
	/**
	 * Called once for each upload that fails. When omitted, failed uploads are
	 * left alone entirely (not drained), matching fields that surface errors
	 * elsewhere (e.g. `UploadField`).
	 */
	onError?: (item: UploadItem) => void;
}

/**
 * Drains a `useUploads` queue for the caller: watches `items`, hands each newly
 * finished upload to `onDone`/`onError` exactly once (in id order), and reports
 * the still-in-flight item so the caller can show progress.
 *
 * The callbacks are read through refs, so they always see the latest closure
 * (e.g. `LocalVideoPrompt`'s step-dependent handler) without re-running the
 * drain effect. Pass the `items` array straight from `useUploads()`.
 */
export const useLatestUpload = (
	items: UploadItem[],
	{ onDone, onError }: UseLatestUploadOptions
): { inFlight: UploadItem | undefined } => {
	const lastHandled = useRef(0);

	const onDoneRef = useRef(onDone);
	const onErrorRef = useRef(onError);
	onDoneRef.current = onDone;
	onErrorRef.current = onError;

	useEffect(() => {
		const handlesError = Boolean(onErrorRef.current);
		const newest = items
			.filter(
				(it) =>
					it.id > lastHandled.current &&
					(it.status === "done" || (handlesError && it.status === "error"))
			)
			.pop();

		if (!newest) {
			return;
		}

		lastHandled.current = newest.id;

		if (newest.status === "error") {
			onErrorRef.current?.(newest);

			return;
		}

		onDoneRef.current(newest);
	}, [items]);

	const inFlight = items.find(
		(it) =>
			(it.status === "pending" || it.status === "uploading") && it.id > lastHandled.current
	);

	return { inFlight };
};
