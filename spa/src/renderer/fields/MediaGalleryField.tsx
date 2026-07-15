import { useId, useMemo, useState } from "react";
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
import { useFilePicker } from "@/hooks/useFilePicker";
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
	display_title?: boolean | string | number;
	id: string;
	settings?: unknown;
	subtitle?: string;
	title: string;
	type: string;
}

interface MediaGallerySettings {
	columns?: MediaColumn[];
	disable_photos?: boolean | string | number;
	disable_vimeo?: boolean | string | number;
	disable_youtube?: boolean | string | number;
	enable_manual?: boolean | string | number;
	max?: number | string;
	min_height?: number | string;
	min_width?: number | string;
	preview_cache_suffix?: string;
	preview_prefix?: string;
}

interface VideoData {
	[k: string]: unknown;
	embed?: string;
	id?: string;
	service?: string;
	url?: string;
}

interface MediaItemData {
	[k: string]: unknown;
	"__internal-subtitle"?: string;
	"__internal-title"?: string;
	image?: string;
	info?: Record<string, unknown>;
	type?: "photo" | "video";
	video?: VideoData;
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
				<EmptyState dashed size="sm">
					No items yet — use the buttons below to add media.
				</EmptyState>
			) : (
				<ul className="grid grid-cols-1 gap-1.5 md:grid-cols-2">
					{items.map((item, index) => (
						<MediaItemRow
							columns={columns}
							disabled={disabled}
							expanded={isExpanded(item.uid)}
							idPrefix={reactId}
							index={index}
							item={item}
							key={item.uid}
							previewSettings={settings}
							total={items.length}
							onColumnChange={(col, next) => updateItemColumn(item.uid, col, next)}
							onDelete={() => deleteItem(item.uid)}
							onMove={(dir) => moveItem(index, dir)}
							onPatchData={(patch) => updateItemData(item.uid, patch)}
							onToggle={() => toggleExpanded(item.uid)}
						/>
					))}
				</ul>
			)}

			<AddBar
				allowLocal={allowLocal}
				allowPhotos={allowPhotos}
				allowVimeo={allowVimeo}
				allowYoutube={allowYoutube}
				atLimit={atLimit}
				currentCount={items.length}
				disabled={disabled}
				max={max}
				settings={settings}
				onAskLocalVideo={() => setLocalVideoOpen(true)}
				onAskVideo={() => setVideoPromptOpen(true)}
				onPhotoUploaded={handlePhotoUploaded}
			/>

			{videoPromptOpen && (
				<VideoUrlPrompt
					allowVimeo={allowVimeo}
					allowYoutube={allowYoutube}
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
	columns: MediaColumn[];
	disabled?: boolean;
	expanded: boolean;
	idPrefix: string;
	index: number;
	item: MediaItem;
	onColumnChange: (columnId: string, next: unknown) => void;
	onDelete: () => void;
	onMove: (direction: "up" | "down") => void;
	onPatchData: (patch: Partial<MediaItemData>) => void;
	onToggle: () => void;
	previewSettings: MediaGallerySettings;
	total: number;
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
			stacked
			disabled={disabled}
			expanded={expanded}
			index={index}
			leading={
				<div className="overflow-hidden rounded border border-border bg-surface-2">
					{previewUrl ? (
						<img
							alt=""
							className="block h-16 w-20 object-cover"
							loading="lazy"
							src={previewUrl}
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
			panelId={`${idPrefix}-row-${item.uid}`}
			subtitle={
				summary.subtitle || data.video?.service
					? summary.subtitle || String(data.video?.service ?? "")
					: undefined
			}
			title={titleText}
			total={total}
			onDelete={onDelete}
			onMove={onMove}
			onToggle={onToggle}
		>
			{isVideo && data.video && (
				<div className="mb-3 text-[11.5px] text-text-3">
					{data.video.service} · {data.video.id || data.video.url}
				</div>
			)}

			{columns.length === 0 ? (
				<EmptyState dashed size="sm">
					No extra fields configured for this gallery.
				</EmptyState>
			) : (
				<RepeaterColumnFields
					columns={columns}
					disabled={disabled}
					getValue={(id) => (data.info ?? {})[id]}
					onColumnChange={onColumnChange}
				/>
			)}
		</RepeaterRowShell>
	);
};

interface AddBarProps {
	allowLocal: boolean;
	allowPhotos: boolean;
	allowVimeo: boolean;
	allowYoutube: boolean;
	atLimit: boolean;
	currentCount: number;
	disabled?: boolean;
	max: number;
	onAskLocalVideo: () => void;
	onAskVideo: () => void;
	onPhotoUploaded: (url: string) => void;
	settings: MediaGallerySettings;
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

	const filePicker = useFilePicker((file) => {
		enqueueFile(file);
	});

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
								disabled={busy || atLimit}
								icon={<UploadIcon size={13} />}
								variant="secondary"
								onClick={filePicker.open}
							>
								Upload photo
							</Button>
							<Button
								disabled={busy || atLimit}
								icon={<Search size={13} />}
								variant="secondary"
								onClick={() => setPickerOpen(true)}
							>
								Browse photos
							</Button>
						</>
					)}
					{showAnyVideo && (
						<Button
							disabled={busy || atLimit}
							icon={<Plus size={13} />}
							variant="secondary"
							onClick={onAskVideo}
						>
							Add video URL
						</Button>
					)}
					{allowLocal && (
						<Button
							disabled={busy || atLimit}
							icon={<VideoIcon size={13} />}
							variant="secondary"
							onClick={onAskLocalVideo}
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
				accept="image/*"
				aria-label="Add images"
				className="hidden"
				ref={filePicker.inputRef}
				type="file"
				onChange={filePicker.onChange}
			/>

			<ResourcePicker
				minHeight={minHeight}
				minWidth={minWidth}
				open={pickerOpen}
				type="image"
				onOpenChange={setPickerOpen}
				onSelect={(resource) => reprocess({ resource_id: resource.id })}
			/>

			<FieldCropModal {...cropModalProps} />
		</>
	);
};

interface VideoUrlPromptProps {
	allowVimeo: boolean;
	allowYoutube: boolean;
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
					autoFocus
					dense
					inputMode="url"
					placeholder="https://youtube.com/watch?v=… or https://vimeo.com/…"
					type="url"
					value={url}
					onChange={(e) => setUrl(e.target.value)}
					onKeyDown={(e) => {
						if (e.key === "Enter") {
							e.preventDefault();
							submit();
						}
					}}
				/>
			</Field>
			<div className="mt-2 flex justify-end gap-2">
				<Button disabled={createMutation.isPending} variant="secondary" onClick={onClose}>
					Cancel
				</Button>
				<Button
					disabled={!looksValid}
					loading={createMutation.isPending}
					loadingLabel="Adding…"
					variant="primary"
					onClick={submit}
				>
					Add
				</Button>
			</div>
		</div>
	);
};

interface LocalVideoPromptProps {
	onClose: () => void;
	onCreated: (coverUrl: string, videoUrl: string) => void;
	settings: MediaGallerySettings;
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

	const videoPicker = useFilePicker((file) => {
		enqueue([file], { path: UPLOAD_PATH });
	});

	const coverPicker = useFilePicker((file) => {
		enqueue([file], { path: IMAGE_PROCESS_PATH, extra: { settings: processSettings } });
	});

	const minWidth = toInt(settings.min_width);
	const minHeight = toInt(settings.min_height);
	const coverHint = minWidth > 0 && minHeight > 0 ? ` (min ${minWidth}×${minHeight})` : "";

	return (
		<div className="rounded-md border border-border bg-surface-2 p-3">
			<div className="mb-2 flex items-center gap-2 text-[12px] font-medium text-text-2">
				<span className={step === "video" ? "text-text" : "text-text-3"}>
					1. Video file
				</span>
				<ChevronRight className="text-text-3" size={12} />
				<span className={step === "cover" ? "text-text" : "text-text-3"}>
					2. Cover photo
				</span>
			</div>

			{step === "video" ? (
				<>
					<p className="mb-2 text-[11.5px] text-text-3">Upload an H.264 video file.</p>
					<Button
						disabled={Boolean(inFlight)}
						icon={<UploadIcon size={13} />}
						variant="secondary"
						onClick={videoPicker.open}
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
						disabled={Boolean(inFlight)}
						icon={<UploadIcon size={13} />}
						variant="secondary"
						onClick={coverPicker.open}
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
					className="ml-auto rounded-md border border-border px-3 py-1 text-[12px] hover:bg-hover disabled:opacity-50"
					disabled={Boolean(inFlight)}
					type="button"
					onClick={onClose}
				>
					Cancel
				</button>
			</div>

			<input
				accept="video/*"
				aria-label="Choose video file"
				className="hidden"
				ref={videoPicker.inputRef}
				type="file"
				onChange={videoPicker.onChange}
			/>
			<input
				accept="image/*"
				aria-label="Choose cover image"
				className="hidden"
				ref={coverPicker.inputRef}
				type="file"
				onChange={coverPicker.onChange}
			/>

			<FieldCropModal
				crops={cropState?.crops ?? []}
				file={cropState?.file ?? ""}
				open={Boolean(cropState)}
				onCancel={() => {
					setCropState(null);
					onClose();
				}}
				onComplete={() => {
					if (cropState) {
						onCreated(cropState.file, videoUrl as string);
					}

					setCropState(null);
					onClose();
				}}
			/>
		</div>
	);
};
