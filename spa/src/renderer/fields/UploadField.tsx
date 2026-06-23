import { useEffect, useRef } from "react";
import { File as FileIcon, Upload as UploadIcon, X } from "lucide-react";

import { IconButton } from "@/components/ui/IconButton";
import { UPLOAD_PATH, type ResourceDetail } from "@/api/endpoints/resources";
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
	const lastHandled = useRef<number>(0);

	// Watch the upload queue for our newest completed item and surface its URL.
	useEffect(() => {
		const done = items.filter((it) => it.status === "done" && it.id > lastHandled.current);
		const newest = done[done.length - 1];

		if (!newest) {
			return;
		}

		lastHandled.current = newest.id;

		const result = newest.result as ResourceDetail | undefined;

		if (result?.file) {
			onChange(result.file);
		}
	}, [items, onChange]);

	const inFlight = items.find(
		(it) =>
			(it.status === "pending" || it.status === "uploading") && it.id > lastHandled.current
	);

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
				<button
					type="button"
					className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
					onClick={() => inputRef.current?.click()}
					disabled={disabled || Boolean(inFlight)}
				>
					<UploadIcon size={13} />
					{currentPath ? "Replace file" : "Choose file"}
				</button>
				{inFlight && (
					<span className="inline-flex items-center gap-2 text-[12px] text-text-3">
						<UploadProgress percent={inFlight.progress} />
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

interface UploadProgressProps {
	percent: number;
}

const UploadProgress = ({ percent }: UploadProgressProps) => (
	<span className="relative inline-block h-1.5 w-24 overflow-hidden rounded-full bg-surface-2">
		<span
			className="absolute inset-y-0 left-0 bg-accent transition-[width]"
			style={{ width: `${percent}%` }}
		/>
	</span>
);

const filenameFromPath = (path: string): string => {
	const withoutQuery = path.split("?")[0] ?? path;
	const trimmed = withoutQuery.split("#")[0] ?? withoutQuery;
	const idx = trimmed.lastIndexOf("/");

	return idx >= 0 ? trimmed.slice(idx + 1) : trimmed;
};
