import { useId, useMemo, useRef, useState } from "react";
import { useToastMutation } from "@/hooks/useToastMutation";
import {
	ChevronRight,
	Image as ImageIcon,
	Plus,
	Search,
	Upload as UploadIcon,
	Video as VideoIcon,
} from "lucide-react";

import { UPLOAD_PATH, resourcesApi, type ResourceDetail } from "@/api/endpoints/resources";
import {
	IMAGE_PROCESS_PATH,
	type PendingCrop,
	type ProcessImageResult,
} from "@/api/endpoints/images";
import { ResourcePicker } from "@/components/files/ResourcePicker";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field } from "@/components/ui/Field";
import { TextInput } from "@/components/ui/TextInput";
import { useLatestUpload } from "@/hooks/useLatestUpload";
import { useRepeaterRows, type RepeaterRow } from "@/hooks/useRepeaterRows";
import { useUploads } from "@/hooks/useUploads";
import { expandImageUrl } from "@/lib/imageUrl";
import { toast } from "@/lib/toast";

import { FieldCropModal } from "./FieldCropModal";
import { deriveRepeaterSummary, isTruthyFlag, toInt } from "./fieldHelpers";
import { RepeaterColumnFields } from "./RepeaterColumnFields";
import { RepeaterRowShell } from "./RepeaterRowShell";
import { useImageFieldProcessing } from "./useImageFieldProcessing";
import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Media gallery — repeating list of photos and videos with optional per-item
 * metadata columns. Modelled on `core/admin/field-types/media-gallery/{draw,process}.php`.
 *
 * Storage shape per item (matches the legacy persisted JSON):
 *   {
 *     type: "photo" | "video",
 *     image: "url",               // thumbnail for video, full-size for photo
 *     video?: { service, id, url, embed, ... },  // present for video items
 *     info?: { col_id: value, ... },             // per-item extra columns
 *     __internal-title?: string,
 *     __internal-subtitle?: string,
 *   }
 *
 * Key implementation note: unlike the legacy admin which submits special
 * `*photo` / `*video` / `*localvideo` keys and relies on server-side
 * processField to turn URLs into normalised video metadata, the new SPA API
 * path is JSON-in / JSON-out. We do the URL → metadata conversion *client*
 * side by hitting POST /resources/video (which creates a managed-video
 * resource on the server and returns `video_data` + a thumbnail URL we can
 * use directly).
 *
 * Local video upload (settings.enable_manual) is a two-step sequence (upload the
 * H.264 file, then a cover photo). The file goes to POST /resources/upload and the
 * cover through the same /images/process pipeline as photos (so crop settings
 * apply); the stored item mirrors the legacy shape produced by `process.php`:
 *   { type: "video", image: <cover>, video: { service: "local", url: <file> } }
 */

interface MediaColumn {
	id: string;
	title: string;
	subtitle?: string;
	type: string;
	settings?: unknown;
	display_title?: boolean | string | number;
}

interface MediaGallerySettings {
	max?: number | string;
	disable_photos?: boolean | string | number;
	disable_youtube?: boolean | string | number;
	disable_vimeo?: boolean | string | number;
	enable_manual?: boolean | string | number;
	preview_prefix?: string;
	preview_cache_suffix?: string;
	min_width?: number | string;
	min_height?: number | string;
	columns?: MediaColumn[];
}

interface VideoData {
	service?: string;
	id?: string;
	url?: string;
	embed?: string;
	[k: string]: unknown;
}

interface MediaItemData {
	type?: "photo" | "video";
	image?: string;
	video?: VideoData;
	info?: Record<string, unknown>;
	"__internal-title"?: string;
	"__internal-subtitle"?: string;
	[k: string]: unknown;
}

type MediaItem = RepeaterRow<MediaItemData>;

const buildPreviewUrl = (
	path: string | undefined,
	settings: MediaGallerySettings,
	isVideo: boolean
): string | null => {
	if (!path) {
		return null;
	}

	// Expand {wwwroot}/{staticroot} to a loadable URL. The crop `preview_prefix`
	// only applies to photos — a video's `image` is the poster thumbnail.
	const prefix = isVideo ? "" : (settings.preview_prefix ?? "");

	return expandImageUrl(path, prefix) + (settings.preview_cache_suffix ?? "");
};

export const MediaGalleryField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field) as MediaGallerySettings;
	const max = toInt(settings.max);
	const columns = useMemo<MediaColumn[]>(
		() => (Array.isArray(settings.columns) ? settings.columns : []),
		[settings.columns]
	);

	const allowPhotos = !isTruthyFlag(settings.disable_photos);
	const allowYoutube = !isTruthyFlag(settings.disable_youtube);
	const allowVimeo = !isTruthyFlag(settings.disable_vimeo);
	const allowLocal = isTruthyFlag(settings.enable_manual);

	const reactId = useId();

	const [videoPromptOpen, setVideoPromptOpen] = useState(false);
	const [localVideoOpen, setLocalVideoOpen] = useState(false);

	const {
		rows: items,
		isExpanded,
		toggleExpanded,
		atLimit,
		add: addItem,
		remove: deleteItem,
		move: moveItem,
		update: updateItemData,
		updateWith,
	} = useRepeaterRows<MediaItemData>({ value, onChange, uidPrefix: "g", max });

	const updateItemColumn = (uid: string, columnId: string, next: unknown) => {
		updateWith(uid, (data) => ({
			...data,
			info: { ...(data.info ?? {}), [columnId]: next },
		}));
	};

	const handlePhotoUploaded = (url: string) => {
		addItem({ type: "photo", image: url });
	};

	const handleVideoCreated = (resource: ResourceDetail) => {
		const vd = resource.video_data as VideoData | undefined;

		if (!vd || !vd.service || !vd.id) {
			toast.error("Could not parse video metadata");

			return;
		}

		addItem({
			type: "video",
			image: resource.file,
			video: vd,
		});
	};

	const handleLocalVideoCreated = (coverUrl: string, videoUrl: string) => {
		addItem({
			type: "video",
			image: coverUrl,
			video: { service: "local", url: videoUrl },
		});
	};

	return (
		<div className="space-y-2">
			{items.length === 0 ? (
				<EmptyState size="sm" dashed>
					No items yet — use the buttons below to add media.
				</EmptyState>
			) : (
				<ul className="grid grid-cols-1 gap-1.5 md:grid-cols-2">
					{items.map((item, index) => (
						<MediaItemRow
							key={item.uid}
							item={item}
							index={index}
							total={items.length}
							columns={columns}
							previewSettings={settings}
							expanded={isExpanded(item.uid)}
							onToggle={() => toggleExpanded(item.uid)}
							onDelete={() => deleteItem(item.uid)}
							onMove={(dir) => moveItem(index, dir)}
							onPatchData={(patch) => updateItemData(item.uid, patch)}
							onColumnChange={(col, next) => updateItemColumn(item.uid, col, next)}
							disabled={disabled}
							idPrefix={reactId}
						/>
					))}
				</ul>
			)}

			<AddBar
				atLimit={atLimit}
				disabled={disabled}
				max={max}
				currentCount={items.length}
				allowPhotos={allowPhotos}
				allowYoutube={allowYoutube}
				allowVimeo={allowVimeo}
				allowLocal={allowLocal}
				settings={settings}
				onPhotoUploaded={handlePhotoUploaded}
				onAskVideo={() => setVideoPromptOpen(true)}
				onAskLocalVideo={() => setLocalVideoOpen(true)}
			/>

			{videoPromptOpen && (
				<VideoUrlPrompt
					allowYoutube={allowYoutube}
					allowVimeo={allowVimeo}
					onClose={() => setVideoPromptOpen(false)}
					onCreated={(resource) => {
						handleVideoCreated(resource);
						setVideoPromptOpen(false);
					}}
				/>
			)}

			{localVideoOpen && (
				<LocalVideoPrompt
					settings={settings}
					onClose={() => setLocalVideoOpen(false)}
					onCreated={handleLocalVideoCreated}
				/>
			)}
		</div>
	);
};

interface MediaItemRowProps {
	item: MediaItem;
	index: number;
	total: number;
	columns: MediaColumn[];
	previewSettings: MediaGallerySettings;
	expanded: boolean;
	onToggle: () => void;
	onDelete: () => void;
	onMove: (direction: "up" | "down") => void;
	onPatchData: (patch: Partial<MediaItemData>) => void;
	onColumnChange: (columnId: string, next: unknown) => void;
	disabled?: boolean;
	idPrefix: string;
}

const MediaItemRow = ({
	item,
	index,
	total,
	columns,
	previewSettings,
	expanded,
	onToggle,
	onDelete,
	onMove,
	onColumnChange,
	disabled,
	idPrefix,
}: MediaItemRowProps) => {
	const data = item.data;
	const isVideo = data.type === "video";
	const previewUrl = buildPreviewUrl(data.image, previewSettings, isVideo);
	const fullUrl = expandImageUrl(data.image);
	const summary = useMemo(
		() => deriveRepeaterSummary(data, columns, (d, id) => (d.info ?? {})[id]),
		[data, columns]
	);
	const titleText = summary.title || (isVideo ? `Video #${index + 1}` : `Photo #${index + 1}`);

	return (
		<RepeaterRowShell
			index={index}
			total={total}
			expanded={expanded}
			onToggle={onToggle}
			onMove={onMove}
			onDelete={onDelete}
			disabled={disabled}
			panelId={`${idPrefix}-row-${item.uid}`}
			stacked
			title={titleText}
			subtitle={
				summary.subtitle || data.video?.service
					? summary.subtitle || String(data.video?.service ?? "")
					: undefined
			}
			leading={
				<div className="overflow-hidden rounded border border-border bg-surface-2">
					{previewUrl ? (
						<img
							src={previewUrl}
							alt=""
							className="block h-16 w-20 object-cover"
							loading="lazy"
							onError={(e) => {
								// The prefixed crop may not exist yet — fall back to the
								// full image once before giving up.
								if (fullUrl && e.currentTarget.src !== fullUrl) {
									e.currentTarget.src = fullUrl;
								}
							}}
						/>
					) : (
						<div className="grid h-16 w-20 place-items-center text-text-3">
							{isVideo ? <VideoIcon size={20} /> : <ImageIcon size={20} />}
						</div>
					)}
				</div>
			}
		>
			{isVideo && data.video && (
				<div className="mb-3 text-[11.5px] text-text-3">
					{data.video.service} · {data.video.id || data.video.url}
				</div>
			)}

			{columns.length === 0 ? (
				<EmptyState size="sm" dashed>
					No extra fields configured for this gallery.
				</EmptyState>
			) : (
				<RepeaterColumnFields
					columns={columns}
					getValue={(id) => (data.info ?? {})[id]}
					onColumnChange={onColumnChange}
					disabled={disabled}
				/>
			)}
		</RepeaterRowShell>
	);
};

interface AddBarProps {
	atLimit: boolean;
	disabled?: boolean;
	max: number;
	currentCount: number;
	allowPhotos: boolean;
	allowYoutube: boolean;
	allowVimeo: boolean;
	allowLocal: boolean;
	settings: MediaGallerySettings;
	onPhotoUploaded: (url: string) => void;
	onAskVideo: () => void;
	onAskLocalVideo: () => void;
}

const AddBar = ({
	atLimit,
	disabled,
	max,
	currentCount,
	allowPhotos,
	allowYoutube,
	allowVimeo,
	allowLocal,
	settings,
	onPhotoUploaded,
	onAskVideo,
	onAskLocalVideo,
}: AddBarProps) => {
	const inputRef = useRef<HTMLInputElement>(null);
	const [pickerOpen, setPickerOpen] = useState(false);

	const { inFlight, reprocessing, enqueueFile, reprocess, cropModalProps } =
		useImageFieldProcessing(settings, {
			onCommit: onPhotoUploaded,
			onError: (message) => {
				if (message) {
					toast.error(message);
				}
			},
		});
	const busy = Boolean(disabled) || Boolean(inFlight) || reprocessing;

	const handlePick = (files: FileList | null) => {
		const first = files?.[0];

		if (!first) {
			return;
		}

		enqueueFile(first);
	};

	const showAnyVideo = allowYoutube || allowVimeo;
	const minWidth = toInt(settings.min_width);
	const minHeight = toInt(settings.min_height);

	return (
		<>
			<div className="flex flex-wrap items-center justify-between gap-2">
				<div className="flex flex-wrap gap-2">
					{allowPhotos && (
						<>
							<Button
								variant="secondary"
								icon={<UploadIcon size={13} />}
								onClick={() => inputRef.current?.click()}
								disabled={busy || atLimit}
							>
								Upload photo
							</Button>
							<Button
								variant="secondary"
								icon={<Search size={13} />}
								onClick={() => setPickerOpen(true)}
								disabled={busy || atLimit}
							>
								Browse photos
							</Button>
						</>
					)}
					{showAnyVideo && (
						<Button
							variant="secondary"
							icon={<Plus size={13} />}
							onClick={onAskVideo}
							disabled={busy || atLimit}
						>
							Add video URL
						</Button>
					)}
					{allowLocal && (
						<Button
							variant="secondary"
							icon={<VideoIcon size={13} />}
							onClick={onAskLocalVideo}
							disabled={busy || atLimit}
						>
							Add local video
						</Button>
					)}
				</div>
				<div className="flex items-center gap-3">
					{(inFlight || reprocessing) && (
						<span className="text-[11.5px] text-text-3">
							{inFlight ? `Uploading… ${inFlight.progress}%` : "Processing…"}
						</span>
					)}
					{max > 0 && (
						<span className="text-[11.5px] text-text-3 tabular-nums">
							{currentCount} / {max}
						</span>
					)}
				</div>
			</div>

			<input
				ref={inputRef}
				type="file"
				accept="image/*"
				aria-label="Add images"
				className="hidden"
				onChange={(e) => {
					handlePick(e.target.files);
					e.target.value = "";
				}}
			/>

			<ResourcePicker
				open={pickerOpen}
				onOpenChange={setPickerOpen}
				type="image"
				minWidth={minWidth}
				minHeight={minHeight}
				onSelect={(resource) => reprocess({ resource_id: resource.id })}
			/>

			<FieldCropModal {...cropModalProps} />
		</>
	);
};

interface VideoUrlPromptProps {
	allowYoutube: boolean;
	allowVimeo: boolean;
	onClose: () => void;
	onCreated: (resource: ResourceDetail) => void;
}

const URL_HOST_HINT = /(youtu\.be|youtube\.com|vimeo\.com)/i;

const VideoUrlPrompt = ({ allowYoutube, allowVimeo, onClose, onCreated }: VideoUrlPromptProps) => {
	const [url, setUrl] = useState("");

	const createMutation = useToastMutation({
		mutationFn: () => resourcesApi.createVideo({ url: url.trim() }),
		errorMessage: "Could not add video",
		onSuccess: (resource) => {
			onCreated(resource);
		},
	});

	const trimmed = url.trim();
	const looksValid = trimmed.length > 0 && URL_HOST_HINT.test(trimmed);

	const submit = () => {
		if (!looksValid || createMutation.isPending) {
			return;
		}

		createMutation.mutate();
	};

	const hint = [allowYoutube ? "YouTube" : null, allowVimeo ? "Vimeo" : null]
		.filter(Boolean)
		.join(" / ");

	return (
		<div className="rounded-md border border-border bg-surface-2 p-3">
			<Field label={`${hint} URL`}>
				<TextInput
					dense
					autoFocus
					type="url"
					inputMode="url"
					value={url}
					onChange={(e) => setUrl(e.target.value)}
					onKeyDown={(e) => {
						if (e.key === "Enter") {
							e.preventDefault();
							submit();
						}
					}}
					placeholder="https://youtube.com/watch?v=… or https://vimeo.com/…"
				/>
			</Field>
			<div className="mt-2 flex justify-end gap-2">
				<Button variant="secondary" onClick={onClose} disabled={createMutation.isPending}>
					Cancel
				</Button>
				<Button
					variant="primary"
					onClick={submit}
					disabled={!looksValid}
					loading={createMutation.isPending}
					loadingLabel="Adding…"
				>
					Add
				</Button>
			</div>
		</div>
	);
};

interface LocalVideoPromptProps {
	settings: MediaGallerySettings;
	onClose: () => void;
	onCreated: (coverUrl: string, videoUrl: string) => void;
}

/**
 * Two-step local-video flow: upload the H.264 file, then a cover photo. The
 * video goes to `/resources/upload` (raw file → URL); the cover runs through the
 * same `/images/process` pipeline as gallery photos so the field's crop settings
 * apply. Once both are in hand we hand back `(cover, video)` for the parent to
 * store, then finalize any pending cover crops in place (mirrors the photo flow
 * in `AddBar`).
 */
const LocalVideoPrompt = ({ settings, onClose, onCreated }: LocalVideoPromptProps) => {
	const videoInputRef = useRef<HTMLInputElement>(null);
	const coverInputRef = useRef<HTMLInputElement>(null);
	const { items, enqueue } = useUploads();

	const [step, setStep] = useState<"video" | "cover">("video");
	const [videoUrl, setVideoUrl] = useState<string | null>(null);
	const [cropState, setCropState] = useState<{ file: string; crops: PendingCrop[] } | null>(null);

	const processSettings = useMemo(() => JSON.stringify(settings), [settings]);

	const { inFlight } = useLatestUpload(items, {
		onDone: (item) => {
			if (step === "video") {
				const result = item.result as ResourceDetail | undefined;

				if (!result?.file) {
					toast.error("The server did not return the uploaded video.");

					return;
				}

				setVideoUrl(result.file);
				setStep("cover");

				return;
			}

			const result = item.result as ProcessImageResult | undefined;

			if (!result?.file) {
				toast.error("The server did not return a processed cover image.");

				return;
			}

			// Defer adding the video item until any manual cover crops are finalized,
			// so cancelling the cropper does not create an item with an uncropped cover.
			if (result.pending_crops.length > 0) {
				setCropState({ file: result.file, crops: result.pending_crops });
			} else {
				onCreated(result.file, videoUrl as string);
				onClose();
			}
		},
		onError: (item) => toast.error(item.error ?? "Upload failed."),
	});

	const pickVideo = (files: FileList | null) => {
		const first = files?.[0];

		if (!first) {
			return;
		}

		enqueue([first], { path: UPLOAD_PATH });
	};

	const pickCover = (files: FileList | null) => {
		const first = files?.[0];

		if (!first) {
			return;
		}

		enqueue([first], { path: IMAGE_PROCESS_PATH, extra: { settings: processSettings } });
	};

	const minWidth = toInt(settings.min_width);
	const minHeight = toInt(settings.min_height);
	const coverHint = minWidth > 0 && minHeight > 0 ? ` (min ${minWidth}×${minHeight})` : "";

	return (
		<div className="rounded-md border border-border bg-surface-2 p-3">
			<div className="mb-2 flex items-center gap-2 text-[12px] font-medium text-text-2">
				<span className={step === "video" ? "text-text" : "text-text-3"}>
					1. Video file
				</span>
				<ChevronRight size={12} className="text-text-3" />
				<span className={step === "cover" ? "text-text" : "text-text-3"}>
					2. Cover photo
				</span>
			</div>

			{step === "video" ? (
				<>
					<p className="mb-2 text-[11.5px] text-text-3">Upload an H.264 video file.</p>
					<Button
						variant="secondary"
						icon={<UploadIcon size={13} />}
						onClick={() => videoInputRef.current?.click()}
						disabled={Boolean(inFlight)}
					>
						Choose video
					</Button>
				</>
			) : (
				<>
					<p className="mb-2 text-[11.5px] text-text-3">
						Now choose a cover photo{coverHint}.
					</p>
					<Button
						variant="secondary"
						icon={<UploadIcon size={13} />}
						onClick={() => coverInputRef.current?.click()}
						disabled={Boolean(inFlight)}
					>
						Choose cover photo
					</Button>
				</>
			)}

			<div className="mt-2 flex items-center gap-3">
				{inFlight && (
					<span className="text-[11.5px] text-text-3">
						Uploading… {inFlight.progress}%
					</span>
				)}
				<button
					type="button"
					className="ml-auto rounded-md border border-border px-3 py-1 text-[12px] hover:bg-hover disabled:opacity-50"
					onClick={onClose}
					disabled={Boolean(inFlight)}
				>
					Cancel
				</button>
			</div>

			<input
				ref={videoInputRef}
				type="file"
				accept="video/*"
				aria-label="Choose video file"
				className="hidden"
				onChange={(e) => {
					pickVideo(e.target.files);
					e.target.value = "";
				}}
			/>
			<input
				ref={coverInputRef}
				type="file"
				accept="image/*"
				aria-label="Choose cover image"
				className="hidden"
				onChange={(e) => {
					pickCover(e.target.files);
					e.target.value = "";
				}}
			/>

			<FieldCropModal
				open={Boolean(cropState)}
				file={cropState?.file ?? ""}
				crops={cropState?.crops ?? []}
				onComplete={() => {
					if (cropState) {
						onCreated(cropState.file, videoUrl as string);
					}

					setCropState(null);
					onClose();
				}}
				onCancel={() => {
					setCropState(null);
					onClose();
				}}
			/>
		</div>
	);
};
