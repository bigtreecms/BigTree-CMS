import { useRef, type ReactNode } from "react";
import { Upload } from "lucide-react";

interface UploadButtonProps {
	/** Called with the chosen file. The input is reset afterward so the same file can be re-picked. */
	onSelect: (file: File) => void;
	/** Button label. */
	label: ReactNode;
	/** `accept` attribute for the underlying file input (e.g. ".json", ".pem"). */
	accept?: string;
	/** Disables the control (e.g. while an upload is in flight). */
	disabled?: boolean;
	/** Override the leading icon. Defaults to an upload glyph. */
	icon?: ReactNode;
	/** Tailwind overrides for the button. */
	className?: string;
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
	const inputRef = useRef<HTMLInputElement>(null);

	const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		const file = e.target.files?.[0];

		if (file) {
			onSelect(file);
		}

		e.target.value = "";
	};

	return (
		<>
			<button
				type="button"
				disabled={disabled}
				onClick={() => inputRef.current?.click()}
				className={
					className ??
					"inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-60"
				}
			>
				{icon ?? <Upload size={13} />}
				{label}
			</button>

			<input
				ref={inputRef}
				type="file"
				accept={accept}
				className="hidden"
				onChange={handleChange}
			/>
		</>
	);
};
