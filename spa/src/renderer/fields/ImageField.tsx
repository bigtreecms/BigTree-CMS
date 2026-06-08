import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Crop, ImageIcon, Images, Search, Upload as UploadIcon, X } from "lucide-react";

import { ResourcePicker } from "@/components/files/ResourcePicker";

import {
	IMAGE_PROCESS_PATH,
	imagesApi,
	type PendingCrop,
	type ProcessImageResult,
	type ReprocessSource,
} from "@/api/endpoints/images";
import { useUploads } from "@/hooks/useUploads";
import { expandImageUrl } from "@/lib/imageUrl";

import { FieldCropModal } from "./FieldCropModal";
import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Image field — mirrors `core/admin/field-types/image/draw.php` +
 * `process.php`. Stores the chosen image's path as a string (use
 * ImageReferenceField to store a resource id instead).
 *
 * Unlike the media library, picking/uploading here runs the field's own crop
 * settings: the original is stored under the field `directory` (default
 * `files/`, NOT the media manager), thumbs / center-crops / exact crops are
 * generated automatically, and any crop that needs manual framing opens the
 * cropper before the value is considered final. Supports:
 *   - Direct upload (`POST /images/process`)
 *   - Browse the media library, re-processed through the field's crops
 *   - Recrop the current image ("Choose New Crops")
 *   - Preview existing crops ("Show Existing Crops")
 *   - Optional `min_width` / `min_height`, `preview_prefix`, `preview_cache_suffix`
 */
interface ConfiguredCrop {
	prefix?: string;
	width?: number | string;
	height?: number | string;
}

interface ImageFieldSettings {
	min_width?: number | string;
	min_height?: number | string;
	preview_prefix?: string;
	preview_cache_suffix?: string;
	disable_browse?: boolean | string | number;
	disable_remove?: boolean | string | number;
	crops?: ConfiguredCrop[];
}

export const ImageField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field) as ImageFieldSettings;
	const minWidth = toInt(settings.min_width);
	const minHeight = toInt(settings.min_height);

	const inputRef = useRef<HTMLInputElement>(null);
	const lastHandled = useRef(0);
	const [pickerOpen, setPickerOpen] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const [reprocessing, setReprocessing] = useState(false);
	const [showCrops, setShowCrops] = useState(false);
	const [cropState, setCropState] = useState<{ file: string; crops: PendingCrop[] } | null>(null);
	const { items, enqueue } = useUploads();

	// The settings the server needs to apply the field's crops. Sent verbatim;
	// the backend reads the keys it cares about (crops/thumbs/center_crops/
	// directory/min_*/preset/retina) and ignores UI-only ones.
	const processSettings = useMemo(() => JSON.stringify(settings), [settings]);

	const applyResult = useCallback(
		(result: ProcessImageResult | undefined) => {
			if (!result?.file) {
				setError("The server did not return a processed image.");

				return;
			}

			setError(null);

			// Defer committing the new image until any manual crops are finalized.
			// If the user cancels the cropper, nothing was changed, so the
			// previously selected image (or empty state) is left intact.
			if (result.pending_crops.length > 0) {
				setCropState({ file: result.file, crops: result.pending_crops });
			} else {
				onChange(result.file);
			}
		},
		[onChange]
	);

	// Drain finished uploads (done → set value + maybe open cropper; error → surface).
	useEffect(() => {
		const newest = items
			.filter(
				(it) =>
					it.id > lastHandled.current && (it.status === "done" || it.status === "error")
			)
			.pop();

		if (!newest) {
			return;
		}

		lastHandled.current = newest.id;

		if (newest.status === "error") {
			setError(newest.error ?? "Upload failed.");

			return;
		}

		applyResult(newest.result as ProcessImageResult | undefined);
	}, [items, applyResult]);

	const inFlight = items.find(
		(it) =>
			(it.status === "pending" || it.status === "uploading") && it.id > lastHandled.current
	);

	const busy = disabled || Boolean(inFlight) || reprocessing;

	const handlePick = (files: FileList | null) => {
		const first = files?.[0];

		if (!first) {
			return;
		}

		setError(null);
		enqueue([first], { path: IMAGE_PROCESS_PATH, extra: { settings: processSettings } });
	};

	const runReprocess = async (source: ReprocessSource) => {
		if (busy) {
			return;
		}

		setReprocessing(true);
		setError(null);

		try {
			const result = await imagesApi.reprocess(source, settings as Record<string, unknown>);
			applyResult(result);
		} catch (err) {
			setError(err instanceof Error ? err.message : "Could not process the image.");
		} finally {
			setReprocessing(false);
		}
	};

	const currentPath = typeof value === "string" && value.length > 0 ? value : null;
	// Loadable URL for the full image, and the (possibly prefixed) preview thumb.
	// The thumb may not exist until crops are drawn, so the preview falls back
	// to the full image.
	const fullUrl = currentPath ? expandImageUrl(currentPath) : null;
	const previewUrl = currentPath ? buildPreviewUrl(currentPath, settings) : null;
	const showBrowse = !settings.disable_browse;
	const showRemove = !settings.disable_remove;
	const configuredCrops = (Array.isArray(settings.crops) ? settings.crops : []).filter(
		(c) => typeof c.prefix === "string" && c.prefix.length > 0
	);

	return (
		<div className="space-y-2">
			<div className="flex flex-wrap items-center gap-2">
				<button
					type="button"
					className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
					onClick={() => inputRef.current?.click()}
					disabled={busy}
				>
					<UploadIcon size={13} />
					{currentPath ? "Replace image" : "Upload image"}
				</button>

				{showBrowse && (
					<button
						type="button"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
						onClick={() => setPickerOpen(true)}
						disabled={busy}
					>
						<Search size={13} />
						Browse media
					</button>
				)}

				{currentPath && configuredCrops.length > 0 && (
					<button
						type="button"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
						onClick={() => runReprocess({ file: currentPath, in_place: true })}
						disabled={busy}
					>
						<Crop size={13} />
						Choose new crops
					</button>
				)}

				{currentPath && configuredCrops.length > 0 && (
					<button
						type="button"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
						onClick={() => setShowCrops((s) => !s)}
					>
						<Images size={13} />
						{showCrops ? "Hide existing crops" : "Show existing crops"}
					</button>
				)}

				{(minWidth > 0 || minHeight > 0) && (
					<span className="text-[11.5px] text-text-3">
						min {minWidth || "—"} × {minHeight || "—"}
					</span>
				)}

				{(inFlight || reprocessing) && (
					<span className="ml-auto inline-flex items-center gap-2 text-[12px] text-text-3">
						{inFlight ? (
							<>
								<UploadProgress percent={inFlight.progress} />
								{inFlight.progress}%
							</>
						) : (
							"Processing…"
						)}
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

			{error && (
				<div
					data-field-error
					className="rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12px] text-danger"
				>
					{error}
				</div>
			)}

			{currentPath ? (
				<div className="flex items-start gap-3 rounded-md border border-border bg-surface-2 p-2">
					<div className="overflow-hidden rounded border border-border bg-surface">
						<a href={fullUrl ?? "#"} target="_blank" rel="noopener noreferrer">
							<PreviewThumb src={previewUrl ?? ""} fallback={fullUrl ?? ""} />
						</a>
					</div>
					<div className="min-w-0 flex-1 text-[12px]">
						<div className="text-[11px] uppercase tracking-[0.06em] text-text-3">
							Current
						</div>
						<a
							href={fullUrl ?? "#"}
							target="_blank"
							rel="noopener noreferrer"
							className="block truncate text-accent hover:underline"
							title={fullUrl ?? undefined}
						>
							{fullUrl}
						</a>
					</div>
					{showRemove && !disabled && (
						<button
							type="button"
							className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
							onClick={() => {
								setShowCrops(false);
								onChange("");
							}}
							aria-label="Remove image"
						>
							<X size={14} />
						</button>
					)}
				</div>
			) : null}

			{currentPath && showCrops && configuredCrops.length > 0 && (
				<div className="flex flex-wrap gap-2 rounded-md border border-border bg-surface-2 p-2">
					{configuredCrops.map((c) => (
						<a
							key={c.prefix}
							href={expandImageUrl(currentPath, c.prefix)}
							target="_blank"
							rel="noopener noreferrer"
							className="block"
							title={`${c.prefix} (${c.width ?? "—"}×${c.height ?? "—"})`}
						>
							<img
								src={expandImageUrl(currentPath, c.prefix)}
								alt={c.prefix}
								className="block max-h-20 w-auto max-w-[220px] rounded border border-border"
							/>
						</a>
					))}
				</div>
			)}

			<ResourcePicker
				open={pickerOpen}
				onOpenChange={setPickerOpen}
				type="image"
				minWidth={minWidth}
				minHeight={minHeight}
				onSelect={(resource) => runReprocess({ resource_id: resource.id })}
			/>

			<FieldCropModal
				open={Boolean(cropState)}
				file={cropState?.file ?? ""}
				crops={cropState?.crops ?? []}
				onComplete={() => {
					if (cropState) {
						onChange(cropState.file);
					}

					setCropState(null);
				}}
				onCancel={() => setCropState(null)}
			/>
		</div>
	);
};

interface PreviewThumbProps {
	/** Preferred source (e.g. the `preview_prefix` thumbnail). */
	src: string;
	/** Fallback when `src` fails to load (the full-size image). */
	fallback: string;
}

/**
 * Thumbnail that degrades gracefully: tries the prefixed preview, falls back to
 * the full image if that 404s (the thumb may not exist until crops are drawn),
 * and shows a placeholder icon if both fail. Resets when the sources change.
 */
const PreviewThumb = ({ src, fallback }: PreviewThumbProps) => {
	const [stage, setStage] = useState<0 | 1 | 2>(0);

	useEffect(() => {
		setStage(0);
	}, [src, fallback]);

	if (stage === 2 || (!src && !fallback)) {
		return (
			<div className="grid h-24 w-24 place-items-center text-text-3">
				<ImageIcon size={22} />
			</div>
		);
	}

	const current = stage === 0 && src ? src : fallback;

	return (
		<img
			src={current}
			alt=""
			className="block h-24 w-24 object-cover"
			onError={() => setStage((s) => (s === 0 && src && src !== fallback ? 1 : 2))}
		/>
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
 * full-size path and expand `{wwwroot}`/`{staticroot}` to a loadable URL.
 */
const buildPreviewUrl = (path: string, settings: ImageFieldSettings): string => {
	const expanded = expandImageUrl(path, settings.preview_prefix ?? "");

	return expanded + (settings.preview_cache_suffix ?? "");
};
