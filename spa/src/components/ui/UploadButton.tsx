import type { ReactNode } from "react";
import { Upload } from "lucide-react";

import { useFilePicker } from "@/hooks/useFilePicker";

interface UploadButtonProps {
	/** `accept` attribute for the underlying file input (e.g. ".json", ".pem"). */
	accept?: string;
	/** Tailwind overrides for the button. */
	className?: string;
	/** Disables the control (e.g. while an upload is in flight). */
	disabled?: boolean;
	/** Override the leading icon. Defaults to an upload glyph. */
	icon?: ReactNode;
	/** Button label. */
	label: ReactNode;
	/** Called with the chosen file. The input is reset afterward so the same file can be re-picked. */
	onSelect: (file: File) => void;
}

/**
 * Thin wrapper around a hidden `<input type="file">` rendered as a styled button.
 * Used by the Configure screens to collect single credential files (service-account
 * keys, certificates) for multipart upload.
 */
export const UploadButton = ({
	onSelect,
	label,
	accept,
	disabled = false,
	icon,
	className,
}: UploadButtonProps) => {
	const { open, inputRef, onChange } = useFilePicker(onSelect);

	return (
		<>
			<button
				className={
					className ??
					"inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-60"
				}
				disabled={disabled}
				type="button"
				onClick={open}
			>
				{icon ?? <Upload size={13} />}
				{label}
			</button>

			<input
				accept={accept}
				aria-label={typeof label === "string" ? label : "Choose file"}
				className="hidden"
				ref={inputRef}
				type="file"
				onChange={onChange}
			/>
		</>
	);
};
