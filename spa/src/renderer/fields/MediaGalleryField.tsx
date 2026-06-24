import { useCallback, useEffect, useId, useMemo, useRef, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import {
	ChevronDown,
	ChevronRight,
	GripVertical,
	Image as ImageIcon,
	Plus,
	Search,
	Trash,
	Upload as UploadIcon,
	Video as VideoIcon,
} from "lucide-react";

import type { ModuleFormField } from "@/api/endpoints/modules";
import { UPLOAD_PATH, resourcesApi, type ResourceDetail } from "@/api/endpoints/resources";
import {
	IMAGE_PROCESS_PATH,
	imagesApi,
	type PendingCrop,
	type ProcessImageResult,
} from "@/api/endpoints/images";
import { ResourcePicker } from "@/components/files/ResourcePicker";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { IconButton } from "@/components/ui/IconButton";
import { Field } from "@/components/ui/Field";
import { useUploads, type UploadItem } from "@/hooks/useUploads";
import { ApiError } from "@/types/api";
import { expandImageUrl } from "@/lib/imageUrl";
import { toast } from "@/lib/toast";

import { FieldRenderer } from "@/renderer/forms/FieldRenderer";
import { FieldRow } from "@/renderer/forms/FieldRow";

import { FieldCropModal } from "./FieldCropModal";
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

interface MediaItem {
	uid: string;
	data: MediaItemData;
}

let uidCounter = 0;
const nextUid = (): string => `g${++uidCounter}-${Date.now().toString(36)}`;

const TITLE_KEY = "__internal-title";
const SUBTITLE_KEY = "__internal-subtitle";

const isRecord = (raw: unknown): raw is Record<string, unknown> =>
	Boolean(raw) && typeof raw === "object" && !Array.isArray(raw);

const seedItems = (raw: unknown): MediaItem[] => {
	if (!Array.isArray(raw)) {
		return [];
	}

	return raw
		.filter(isRecord)
		.map((data) => ({ uid: nextUid(), data: { ...data } as MediaItemData }));
};

const stripUid = (items: MediaItem[]): MediaItemData[] => items.map((it) => it.data);

/**
 * Structural equality of two item sets by their data payloads (ignoring the
 * ephemeral uids), so we can tell our own edit's echo from an external change.
 */
const itemsDataEqual = (a: MediaItem[], b: MediaItem[]): boolean => {
	if (a.length !== b.length) {
		return false;
	}

	for (let i = 0; i < a.length; i++) {
		if (JSON.stringify(a[i]?.data) !== JSON.stringify(b[i]?.data)) {
			return false;
		}
	}

	return true;
};

const toInt = (raw: unknown): number => {
	const n = typeof raw === "number" ? raw : Number(raw);

	return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0;
};

const isTruthyFlag = (raw: unknown): boolean => {
	if (typeof raw === "boolean") {
		return raw;
	}

	if (typeof raw === "number") {
		return raw !== 0;
	}

	if (typeof raw === "string") {
		const lower = raw.toLowerCase();

		return lower !== "" && lower !== "0" && lower !== "false" && lower !== "off";
	}

	return false;
};

const normalizeColumnSettings = (raw: unknown): Record<string, unknown> => {
	if (typeof raw === "string" && raw.trim().length > 0) {
		try {
			const parsed = JSON.parse(raw);

			return isRecord(parsed) ? parsed : {};
		} catch {
			return {};
		}
	}

	if (isRecord(raw)) {
		return raw;
	}

	return {};
};

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

	const [items, setItems] = useState<MediaItem[]>(() => seedItems(value));
	const [expanded, setExpanded] = useState<Set<string>>(new Set());
	const [videoPromptOpen, setVideoPromptOpen] = useState(false);
	const [localVideoOpen, setLocalVideoOpen] = useState(false);

	// Re-seed only when the incoming value genuinely differs from the items we
	// already hold (mount / record switch / reset). The echo of our own onChange
	// — and any unrelated re-render passing a structurally-equal value — is
	// ignored, so item uids (and the expand state keyed on them) survive.
	useEffect(() => {
		setItems((prev) => {
			const incoming = seedItems(value);

			return itemsDataEqual(prev, incoming) ? prev : incoming;
		});
	}, [value]);

	const commit = (next: MediaItem[]) => {
		setItems(next);
		onChange(stripUid(next));
	};

	const atLimit = max > 0 && items.length >= max;

	const addItem = (data: MediaItemData) => {
		if (atLimit) {
			return;
		}

		const fresh: MediaItem = { uid: nextUid(), data };
		commit([...items, fresh]);
		setExpanded((prev) => new Set(prev).add(fresh.uid));
	};

	const updateItemData = (uid: string, patch: Partial<MediaItemData>) => {
		commit(
			items.map((it) => (it.uid === uid ? { ...it, data: { ...it.data, ...patch } } : it))
		);
	};

	const updateItemColumn = (uid: string, columnId: string, next: unknown) => {
		commit(
			items.map((it) => {
				if (it.uid !== uid) {
					return it;
				}

				const info = { ...(it.data.info ?? {}), [columnId]: next };

				return { ...it, data: { ...it.data, info } };
			})
		);
	};

	const deleteItem = (uid: string) => {
		commit(items.filter((it) => it.uid !== uid));
		setExpanded((prev) => {
			const copy = new Set(prev);
			copy.delete(uid);

			return copy;
		});
	};

	const toggleExpanded = (uid: string) => {
		setExpanded((prev) => {
			const copy = new Set(prev);

			if (copy.has(uid)) {
				copy.delete(uid);
			} else {
				copy.add(uid);
			}

			return copy;
		});
	};

	const moveItem = (index: number, direction: "up" | "down") => {
		const swap = direction === "up" ? index - 1 : index + 1;

		if (swap < 0 || swap >= items.length) {
			return;
		}

		const next = [...items];
		const removed = next.splice(index, 1)[0];

		if (removed) {
			next.splice(swap, 0, removed);
			commit(next);
		}
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
							expanded={expanded.has(item.uid)}
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
	const summary = useMemo(() => deriveSummary(data, columns), [data, columns]);
	const titleText = summary.title || (isVideo ? `Video #${index + 1}` : `Photo #${index + 1}`);

	return (
		<li className="rounded-md border border-border bg-surface">
			<div className="flex items-stretch gap-2 p-2">
				<IconButton
					label="Move up"
					title="Move up"
					onClick={() => onMove("up")}
					disabled={disabled || index === 0}
				>
					<GripVertical size={13} />
				</IconButton>

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

				<button
					type="button"
					className="flex min-w-0 flex-1 items-center gap-1.5 rounded px-1.5 py-1 text-left hover:bg-hover"
					onClick={onToggle}
					aria-expanded={expanded}
					aria-controls={`${idPrefix}-row-${item.uid}`}
				>
					{expanded ? (
						<ChevronDown size={13} className="text-text-3" />
					) : (
						<ChevronRight size={13} className="text-text-3" />
					)}
					<span className="min-w-0">
						<span className="block truncate text-[12.5px] text-text-2">
							{titleText}
						</span>
						{(summary.subtitle || data.video?.service) && (
							<span className="block truncate text-[11px] text-text-3">
								{summary.subtitle || String(data.video?.service ?? "")}
							</span>
						)}
					</span>
				</button>

				<IconButton
					label="Delete item"
					title="Delete item"
					tone="danger"
					onClick={onDelete}
					disabled={disabled}
				>
					<Trash size={13} />
				</IconButton>
			</div>

			{expanded && (
				<div
					id={`${idPrefix}-row-${item.uid}`}
					className="border-t border-border px-3 pb-1 pt-3"
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
						columns.map((column) => {
							const subField: ModuleFormField = {
								column: column.id,
								title: column.title || column.id,
								subtitle: column.subtitle,
								type: column.type,
								settings: normalizeColumnSettings(column.settings),
							};

							return (
								<FieldRow key={column.id} field={subField}>
									<FieldRenderer
										field={subField}
										value={(data.info ?? {})[column.id]}
										onChange={(next) => onColumnChange(column.id, next)}
										disabled={disabled}
									/>
								</FieldRow>
							);
						})
					)}

					{index < total - 1 && (
						<Button
							variant="secondary"
							size="sm"
							className="mb-2"
							onClick={() => onMove("down")}
							disabled={disabled}
						>
							Move down
						</Button>
					)}
				</div>
			)}
		</li>
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
	const [reprocessing, setReprocessing] = useState(false);
	const [cropState, setCropState] = useState<{ file: string; crops: PendingCrop[] } | null>(null);
	const { items, enqueue } = useUploads();
	const lastHandled = useRef(0);

	// Apply the gallery field's own crop settings (stored under its directory,
	// not the media library) so photos get the same treatment as the image field.
	const processSettings = useMemo(() => JSON.stringify(settings), [settings]);

	const applyResult = useCallback(
		(result: ProcessImageResult | undefined) => {
			if (!result?.file) {
				toast.error("The server did not return a processed image.");

				return;
			}

			// Defer adding the gallery item until any manual crops are finalized.
			// If the user cancels the cropper, no item is added, so the photo is
			// not used.
			if (result.pending_crops.length > 0) {
				setCropState({ file: result.file, crops: result.pending_crops });
			} else {
				onPhotoUploaded(result.file);
			}
		},
		[onPhotoUploaded]
	);

	// Drain finished uploads (done → add item + maybe crop; error → toast).
	useEffect(() => {
		const newest = items
			.filter(
				(it: UploadItem) =>
					it.id > lastHandled.current && (it.status === "done" || it.status === "error")
			)
			.pop();

		if (!newest) {
			return;
		}

		lastHandled.current = newest.id;

		if (newest.status === "error") {
			toast.error(newest.error ?? "Upload failed.");

			return;
		}

		applyResult(newest.result as ProcessImageResult | undefined);
	}, [items, applyResult]);

	const inFlight = items.find(
		(it) =>
			(it.status === "pending" || it.status === "uploading") && it.id > lastHandled.current
	);
	const busy = Boolean(disabled) || Boolean(inFlight) || reprocessing;

	const handlePick = (files: FileList | null) => {
		const first = files?.[0];

		if (!first) {
			return;
		}

		enqueue([first], { path: IMAGE_PROCESS_PATH, extra: { settings: processSettings } });
	};

	const handleBrowse = async (resource: { id: number }) => {
		setReprocessing(true);

		try {
			const result = await imagesApi.reprocess(
				{ resource_id: resource.id },
				settings as Record<string, unknown>
			);
			applyResult(result);
		} catch (err) {
			toast.error(err instanceof Error ? err.message : "Could not process the image.");
		} finally {
			setReprocessing(false);
		}
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
				onSelect={(resource) => handleBrowse(resource)}
			/>

			<FieldCropModal
				open={Boolean(cropState)}
				file={cropState?.file ?? ""}
				crops={cropState?.crops ?? []}
				onComplete={() => {
					if (cropState) {
						onPhotoUploaded(cropState.file);
					}

					setCropState(null);
				}}
				onCancel={() => setCropState(null)}
			/>
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

	const createMutation = useMutation({
		mutationFn: () => resourcesApi.createVideo({ url: url.trim() }),
		onSuccess: (resource) => onCreated(resource),
		onError: (err) => {
			let message = "Could not add video";

			if (err instanceof ApiError) {
				message = err.message || message;
			}

			toast.error(message);
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
				<input
					autoFocus
					type="url"
					inputMode="url"
					className="w-full rounded-md border border-border bg-surface px-3 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
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
				<button
					type="button"
					className="rounded-md border border-border px-3 py-1 text-[12px] hover:bg-hover"
					onClick={onClose}
					disabled={createMutation.isPending}
				>
					Cancel
				</button>
				<button
					type="button"
					className="rounded-md bg-accent px-3 py-1 text-[12px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
					onClick={submit}
					disabled={!looksValid || createMutation.isPending}
				>
					{createMutation.isPending ? "Adding…" : "Add"}
				</button>
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
	const lastHandled = useRef(0);

	const [step, setStep] = useState<"video" | "cover">("video");
	const [videoUrl, setVideoUrl] = useState<string | null>(null);
	const [cropState, setCropState] = useState<{ file: string; crops: PendingCrop[] } | null>(null);

	const processSettings = useMemo(() => JSON.stringify(settings), [settings]);

	useEffect(() => {
		const newest = items
			.filter(
				(it: UploadItem) =>
					it.id > lastHandled.current && (it.status === "done" || it.status === "error")
			)
			.pop();

		if (!newest) {
			return;
		}

		lastHandled.current = newest.id;

		if (newest.status === "error") {
			toast.error(newest.error ?? "Upload failed.");

			return;
		}

		if (step === "video") {
			const result = newest.result as ResourceDetail | undefined;

			if (!result?.file) {
				toast.error("The server did not return the uploaded video.");

				return;
			}

			setVideoUrl(result.file);
			setStep("cover");

			return;
		}

		const result = newest.result as ProcessImageResult | undefined;

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
	}, [items, step, videoUrl, processSettings, onCreated, onClose]);

	const inFlight = items.find(
		(it) =>
			(it.status === "pending" || it.status === "uploading") && it.id > lastHandled.current
	);

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

interface ItemSummary {
	title: string;
	subtitle: string;
}

const deriveSummary = (data: MediaItemData, columns: MediaColumn[]): ItemSummary => {
	let title = "";
	let subtitle = "";

	for (const col of columns) {
		if (!isTruthyFlag(col.display_title)) {
			continue;
		}

		const raw = (data.info ?? {})[col.id];

		if (raw == null || raw === "") {
			continue;
		}

		const text = stringifyForTitle(raw);

		if (!title) {
			title = text;
		} else if (!subtitle) {
			subtitle = text;
			break;
		}
	}

	if (!title && typeof data[TITLE_KEY] === "string") {
		title = String(data[TITLE_KEY]);
	}

	if (!subtitle && typeof data[SUBTITLE_KEY] === "string") {
		subtitle = String(data[SUBTITLE_KEY]);
	}

	return { title, subtitle };
};

const stringifyForTitle = (raw: unknown): string => {
	if (typeof raw === "string") {
		return raw;
	}

	if (typeof raw === "number" || typeof raw === "boolean") {
		return String(raw);
	}

	if (isRecord(raw)) {
		const candidate =
			(typeof raw.title === "string" && raw.title) ||
			(typeof raw.name === "string" && raw.name) ||
			(typeof raw.id === "string" && raw.id);

		return candidate || "";
	}

	return "";
};
