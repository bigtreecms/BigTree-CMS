import { useCallback, useRef, type ChangeEvent, type RefObject } from "react";

export interface UseFilePickerResult {
	/** Open the native file picker. */
	open: () => void;
	/** Attach to a hidden `<input type="file" />`. */
	inputRef: RefObject<HTMLInputElement>;
	/** Wire to the input's `onChange`. Resets the input after pick. */
	onChange: (e: ChangeEvent<HTMLInputElement>) => void;
}

/**
 * Hidden-file-input picker mechanism shared by ImageField, UploadField,
 * MediaGalleryField, and UploadButton. Owns the ref, click-to-open, and the
 * post-pick `value = ""` reset so the same file can be re-selected.
 *
 * Callers keep their own button/progress UI and pass `accept`/`aria-label` on
 * the rendered input.
 */
export const useFilePicker = (
	onPick: (file: File) => void,
	/** When true, call `onPick` once per selected file (gallery multi-add). */
	multiple = false
): UseFilePickerResult => {
	// Cast: React 19's useRef(null) types as `T | null`; JSX ref expects `T`.
	const inputRef = useRef<HTMLInputElement>(null) as RefObject<HTMLInputElement>;

	const open = useCallback(() => {
		inputRef.current?.click();
	}, []);

	const onChange = useCallback(
		(e: ChangeEvent<HTMLInputElement>) => {
			const files = e.target.files;

			if (files && files.length > 0) {
				if (multiple) {
					Array.from(files).forEach((file) => onPick(file));
				} else {
					const file = files[0];

					if (file) {
						onPick(file);
					}
				}
			}

			e.target.value = "";
		},
		[multiple, onPick]
	);

	return { open, inputRef, onChange };
};
