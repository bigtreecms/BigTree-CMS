import { useEffect, useRef, useState } from "react";
import { ImageIcon, Search, Upload as UploadIcon, X } from "lucide-react";

import { ResourcePicker } from "@/components/files/ResourcePicker";

import { UPLOAD_PATH, type ResourceDetail } from "@/api/endpoints/resources";
import { useUploads } from "@/hooks/useUploads";

import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Image field — mirrors `core/admin/field-types/image/draw.php`.
 *
 * Stores the chosen image's URL/path as a string (use ImageReferenceField if
 * you want to store the resource id instead). Supports:
 *   - Direct upload (drag/drop happens at the higher level; this field is
 *     click-to-pick only — multi-file isn't meaningful for a single-image slot)
 *   - Browse the media library via ResourcePicker (image-only filter)
 *   - Optional `min_width` / `min_height` enforced on browse + surfaced as
 *     an `accept` hint on upload (the server still has the final say)
 *   - Optional `preview_prefix` for the displayed thumbnail
 *
 * Recrop / cropper integration is deferred — once the CropModal flow can be
 * fed an arbitrary resource path we can re-enable the legacy "Choose New
 * Crops" toggle from the original draw.php.
 */
interface ImageFieldSettings {
	min_width?: number | string;
	min_height?: number | string;
	preview_prefix?: string;
	preview_cache_suffix?: string;
	disable_browse?: boolean | string | number;
	disable_remove?: boolean | string | number;
}

export const ImageField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field) as ImageFieldSettings;
	const minWidth = toInt(settings.min_width);
	const minHeight = toInt(settings.min_height);

	const inputRef = useRef<HTMLInputElement>(null);
	const lastHandled = useRef(0);
	const [pickerOpen, setPickerOpen] = useState(false);
	const { items, enqueue } = useUploads();

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
	const previewUrl = currentPath ? buildPreviewUrl(currentPath, settings) : null;
	const showBrowse = !settings.disable_browse;
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
					{currentPath ? "Replace image" : "Upload image"}
				</button>

				{showBrowse && (
					<button
						type="button"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
						onClick={() => setPickerOpen(true)}
						disabled={disabled}
					>
						<Search size={13} />
						Browse media
					</button>
				)}

				{(minWidth > 0 || minHeight > 0) && (
					<span className="text-[11.5px] text-text-3">
						min {minWidth || "—"} × {minHeight || "—"}
					</span>
				)}

				{inFlight && (
					<span className="ml-auto inline-flex items-center gap-2 text-[12px] text-text-3">
						<UploadProgress percent={inFlight.progress} />
						{inFlight.progress}%
					</span>
				)}

				<input
					ref={inputRef}
					type="file"
					accept="image/*"
					className="hidden"
					onChange={(e) => {
						handlePick(e.target.files);
						e.target.value = "";
					}}
				/>
			</div>

			{currentPath ? (
				<div className="flex items-start gap-3 rounded-md border border-border bg-surface-2 p-2">
					<div className="overflow-hidden rounded border border-border bg-surface">
						{previewUrl ? (
							<a href={currentPath} target="_blank" rel="noopener noreferrer">
								<img
									src={previewUrl}
									alt=""
									className="block h-24 w-24 object-cover"
								/>
							</a>
						) : (
							<div className="grid h-24 w-24 place-items-center text-text-3">
								<ImageIcon size={22} />
							</div>
						)}
					</div>
					<div className="min-w-0 flex-1 text-[12px]">
						<div className="text-[11px] uppercase tracking-[0.06em] text-text-3">
							Current
						</div>
						<a
							href={currentPath}
							target="_blank"
							rel="noopener noreferrer"
							className="block truncate text-accent hover:underline"
							title={currentPath}
						>
							{currentPath}
						</a>
					</div>
					{showRemove && !disabled && (
						<button
							type="button"
							className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
							onClick={() => onChange("")}
							aria-label="Remove image"
						>
							<X size={14} />
						</button>
					)}
				</div>
			) : null}

			<ResourcePicker
				open={pickerOpen}
				onOpenChange={setPickerOpen}
				type="image"
				minWidth={minWidth}
				minHeight={minHeight}
				onSelect={(resource) => onChange(resource.file)}
			/>
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

const toInt = (raw: unknown): number => {
	const n = typeof raw === "number" ? raw : Number(raw);

	return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0;
};

/**
 * Apply `preview_prefix` (and optional `preview_cache_suffix`) to the stored
 * full-size path. Mirrors `BigTree::prefixFile` from the PHP admin — the
 * prefix is inserted before the filename in the URL path.
 */
const buildPreviewUrl = (path: string, settings: ImageFieldSettings): string => {
	if (!settings.preview_prefix) {
		return path + (settings.preview_cache_suffix ?? "");
	}

	const prefixed = prefixFile(path, settings.preview_prefix);

	return prefixed + (settings.preview_cache_suffix ?? "");
};

const prefixFile = (path: string, prefix: string): string => {
	const idx = path.lastIndexOf("/");

	if (idx < 0) {
		return prefix + path;
	}

	return path.slice(0, idx + 1) + prefix + path.slice(idx + 1);
};
