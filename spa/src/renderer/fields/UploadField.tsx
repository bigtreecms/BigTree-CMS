import { useRef } from "react";
import { File as FileIcon, Upload as UploadIcon, X } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";
import { ProgressBar } from "@/components/ui/ProgressBar";
import { UPLOAD_PATH, type ResourceDetail } from "@/api/endpoints/resources";
import { useLatestUpload } from "@/hooks/useLatestUpload";
import { useUploads } from "@/hooks/useUploads";

import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Generic file upload — matches `core/admin/field-types/upload/draw.php`.
 * Stores the uploaded file's URL/path as a string. Browse-from-media-library
 * intentionally absent here: the legacy upload field is upload-only too
 * (see the simpler `<input type="file">` in draw.php vs the image field's
 * dual upload/browse layout).
 *
 * Settings consumed:
 *   - valid_extensions: comma-separated `accept` value forwarded to the input
 *   - disable_remove:   if truthy, hides the Remove link on the Current row
 */
interface UploadFieldSettings {
	valid_extensions?: string;
	disable_remove?: boolean | string | number;
}

export const UploadField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field) as UploadFieldSettings;
	const inputRef = useRef<HTMLInputElement>(null);
	const { items, enqueue } = useUploads();

	// Watch the upload queue for our newest completed item and surface its URL.
	const { inFlight } = useLatestUpload(items, {
		onDone: (item) => {
			const result = item.result as ResourceDetail | undefined;

			if (result?.file) {
				onChange(result.file);
			}
		},
	});

	const handlePick = (files: FileList | null) => {
		const first = files?.[0];

		if (!first) {
			return;
		}

		enqueue([first], { path: UPLOAD_PATH });
	};

	const currentPath = typeof value === "string" && value.length > 0 ? value : null;
	const showRemove = !settings.disable_remove;

	return (
		<div className="space-y-2">
			<div className="flex flex-wrap items-center gap-2">
				<Button
					variant="secondary"
					icon={<UploadIcon size={13} />}
					onClick={() => inputRef.current?.click()}
					disabled={disabled || Boolean(inFlight)}
				>
					{currentPath ? "Replace file" : "Choose file"}
				</Button>
				{inFlight && (
					<span className="inline-flex items-center gap-2 text-[12px] text-text-3">
						<ProgressBar
							value={inFlight.progress}
							label="Upload progress"
							className="w-24"
						/>
						{inFlight.progress}%
					</span>
				)}
				<input
					ref={inputRef}
					type="file"
					aria-label={field.title}
					className="hidden"
					accept={settings.valid_extensions || undefined}
					onChange={(e) => {
						handlePick(e.target.files);
						e.target.value = "";
					}}
				/>
			</div>

			{currentPath && (
				<div className="flex items-center gap-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-[12.5px]">
					<FileIcon size={14} className="text-text-3" />
					<a
						href={currentPath}
						target="_blank"
						rel="noopener noreferrer"
						className="min-w-0 truncate text-accent hover:underline"
						title={currentPath}
					>
						{filenameFromPath(currentPath)}
					</a>
					{showRemove && !disabled && (
						<IconButton
							label="Remove file"
							tone="danger"
							className="ml-auto"
							onClick={() => onChange("")}
						>
							<X size={13} />
						</IconButton>
					)}
				</div>
			)}
		</div>
	);
};

const filenameFromPath = (path: string): string => {
	const withoutQuery = path.split("?")[0] ?? path;
	const trimmed = withoutQuery.split("#")[0] ?? withoutQuery;
	const idx = trimmed.lastIndexOf("/");

	return idx >= 0 ? trimmed.slice(idx + 1) : trimmed;
};
