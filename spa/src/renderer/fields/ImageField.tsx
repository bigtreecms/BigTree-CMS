import { useEffect, useState } from "react";
import { Crop, ImageIcon, Images, Search, Upload as UploadIcon, X } from "lucide-react";

import { ResourcePicker } from "@/components/files/ResourcePicker";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { ProgressBar } from "@/components/ui/ProgressBar";

import { expandImageUrl } from "@/lib/imageUrl";
import { useFilePicker } from "@/hooks/useFilePicker";

import { FieldCropModal } from "./FieldCropModal";
import { toInt } from "./fieldHelpers";
import { useImageFieldProcessing } from "./useImageFieldProcessing";
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

	const [pickerOpen, setPickerOpen] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const [showCrops, setShowCrops] = useState(false);

	const { inFlight, reprocessing, enqueueFile, reprocess, cropModalProps } =
		useImageFieldProcessing(settings, { onCommit: onChange, onError: setError });

	const busy = disabled || Boolean(inFlight) || reprocessing;

	const filePicker = useFilePicker((file) => {
		setError(null);
		enqueueFile(file);
	});

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
				<Button
					variant="secondary"
					icon={<UploadIcon size={13} />}
					onClick={filePicker.open}
					disabled={busy}
				>
					{currentPath ? "Replace image" : "Upload image"}
				</Button>

				{showBrowse && (
					<Button
						variant="secondary"
						icon={<Search size={13} />}
						onClick={() => setPickerOpen(true)}
						disabled={busy}
					>
						Browse media
					</Button>
				)}

				{currentPath && configuredCrops.length > 0 && (
					<Button
						variant="secondary"
						icon={<Crop size={13} />}
						onClick={() => reprocess({ file: currentPath, in_place: true })}
						disabled={busy}
					>
						Choose new crops
					</Button>
				)}

				{currentPath && configuredCrops.length > 0 && (
					<Button
						variant="secondary"
						icon={<Images size={13} />}
						onClick={() => setShowCrops((s) => !s)}
					>
						{showCrops ? "Hide existing crops" : "Show existing crops"}
					</Button>
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
								<ProgressBar
									value={inFlight.progress}
									label="Upload progress"
									className="w-24"
								/>
								{inFlight.progress}%
							</>
						) : (
							"Processing…"
						)}
					</span>
				)}

				<input
					ref={filePicker.inputRef}
					type="file"
					accept="image/*"
					aria-label={field.title}
					className="hidden"
					onChange={filePicker.onChange}
				/>
			</div>

			{error && (
				<Alert tone="danger" data-field-error>
					{error}
				</Alert>
			)}

			{currentPath ? (
				<div className="flex items-start gap-3 rounded-md border border-border bg-surface-2 p-2">
					<div className="overflow-hidden rounded border border-border bg-surface">
						<a href={fullUrl ?? "#"} target="_blank" rel="noopener noreferrer">
							<PreviewThumb src={previewUrl ?? ""} fallback={fullUrl ?? ""} />
						</a>
					</div>
					<div className="min-w-0 flex-1 text-[12px]">
						<SectionLabel size="sm">Current</SectionLabel>
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
						<IconButton
							label="Remove image"
							tone="danger"
							onClick={() => {
								setShowCrops(false);
								onChange("");
							}}
						>
							<X size={14} />
						</IconButton>
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
				onSelect={(resource) => reprocess({ resource_id: resource.id })}
			/>

			<FieldCropModal {...cropModalProps} />
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
			<div className="grid size-24 place-items-center text-text-3">
				<ImageIcon size={22} />
			</div>
		);
	}

	const current = stage === 0 && src ? src : fallback;

	return (
		<img
			src={current}
			alt=""
			className="block size-24 object-cover"
			onError={() => setStage((s) => (s === 0 && src && src !== fallback ? 1 : 2))}
		/>
	);
};

/**
 * Apply `preview_prefix` (and optional `preview_cache_suffix`) to the stored
 * full-size path and expand `{wwwroot}`/`{staticroot}` to a loadable URL.
 */
const buildPreviewUrl = (path: string, settings: ImageFieldSettings): string => {
	const expanded = expandImageUrl(path, settings.preview_prefix ?? "");

	return expanded + (settings.preview_cache_suffix ?? "");
};
