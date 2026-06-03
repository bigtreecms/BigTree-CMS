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
import { resourcesApi, type ResourceDetail } from "@/api/endpoints/resources";
import {
	IMAGE_PROCESS_PATH,
	imagesApi,
	type PendingCrop,
	type ProcessImageResult,
} from "@/api/endpoints/images";
import { ResourcePicker } from "@/components/files/ResourcePicker";
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
 * Local video upload (settings.enable_manual) is recognised but currently
 * stubbed — full implementation needs a sequence-of-uploads UX (video then
 * cover) we don't yet have. Other Add-* flows are fully wired.
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

	const ownChange = useMemo(() => ({ current: false }), []);

	useEffect(() => {
		if (ownChange.current) {
			ownChange.current = false;

			return;
		}

		setItems(seedItems(value));
	}, [value, ownChange]);

	const commit = (next: MediaItem[]) => {
		setItems(next);
		ownChange.current = true;
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

	return (
		<div className="space-y-2">
			{items.length === 0 ? (
				<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-4 text-center text-[12.5px] text-text-3">
					No items yet — use the buttons below to add media.
				</div>
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
				<button
					type="button"
					className="flex flex-col items-center justify-center rounded p-1 text-text-3 hover:bg-hover hover:text-text disabled:cursor-not-allowed disabled:opacity-30"
					onClick={() => onMove("up")}
					disabled={disabled || index === 0}
					title="Move up"
					aria-label="Move up"
				>
					<GripVertical size={13} />
				</button>

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

				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger disabled:opacity-40"
					onClick={onDelete}
					disabled={disabled}
					title="Delete item"
					aria-label="Delete item"
				>
					<Trash size={13} />
				</button>
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
						<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-3 text-[12px] text-text-3">
							No extra fields configured for this gallery.
						</div>
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
						<button
							type="button"
							className="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-2 py-1 text-[11.5px] text-text-3 hover:bg-hover disabled:opacity-40"
							onClick={() => onMove("down")}
							disabled={disabled}
						>
							Move down
						</button>
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

			onPhotoUploaded(result.file);

			if (result.pending_crops.length > 0) {
				setCropState({ file: result.file, crops: result.pending_crops });
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
	const buttonClass =
		"inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50";
	const minWidth = toInt(settings.min_width);
	const minHeight = toInt(settings.min_height);

	return (
		<>
			<div className="flex flex-wrap items-center justify-between gap-2">
				<div className="flex flex-wrap gap-2">
					{allowPhotos && (
						<>
							<button
								type="button"
								className={buttonClass}
								onClick={() => inputRef.current?.click()}
								disabled={busy || atLimit}
							>
								<UploadIcon size={13} />
								Upload photo
							</button>
							<button
								type="button"
								className={buttonClass}
								onClick={() => setPickerOpen(true)}
								disabled={busy || atLimit}
							>
								<Search size={13} />
								Browse photos
							</button>
						</>
					)}
					{showAnyVideo && (
						<button
							type="button"
							className={buttonClass}
							onClick={onAskVideo}
							disabled={busy || atLimit}
						>
							<Plus size={13} />
							Add video URL
						</button>
					)}
					{allowLocal && (
						<button
							type="button"
							className={buttonClass}
							disabled
							title="Local video upload — coming in a follow-up."
						>
							<VideoIcon size={13} />
							Add local video
						</button>
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
				onComplete={() => setCropState(null)}
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
			<label className="block">
				<span className="mb-1 block text-[12px] font-medium text-text-2">{hint} URL</span>
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
			</label>
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
