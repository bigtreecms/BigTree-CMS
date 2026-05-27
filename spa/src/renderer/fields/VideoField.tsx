import { useState } from "react";
import { Search, Video as VideoIcon, X } from "lucide-react";

import { ResourcePicker } from "@/components/files/ResourcePicker";

import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

/**
 * Video field — mirrors `core/admin/field-types/video/draw.php` and the
 * server-side processor in `field-types/video/process.php`.
 *
 * Storage shape on the wire matches the legacy form payload so the existing
 * field processor handles it without changes:
 *
 *   { new:      "https://youtube.com/..." }   - a fresh URL to ingest
 *   { managed:  123                       }   - resource id from media library
 *   { service, id, ... }                      - the persisted shape, returned
 *                                               by the API and round-tripped
 *                                               untouched when unchanged.
 *
 * The field renders an existing video as an iframe preview; new-URL and
 * managed-pick selections show a pending row that resolves on save (we don't
 * try to re-implement the YouTube/Vimeo oembed lookup client-side).
 */
interface VideoValue {
	new?: string;
	managed?: number | string;
	service?: string;
	id?: string;
	image?: string;
	embed?: string;
	[key: string]: unknown;
}

const isVideoObject = (raw: unknown): raw is VideoValue => {
	return Boolean(raw) && typeof raw === "object" && !Array.isArray(raw);
};

export const VideoField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const showBrowse = !settings.disable_browse;

	const [pickerOpen, setPickerOpen] = useState(false);

	const current: VideoValue = isVideoObject(value) ? (value as VideoValue) : {};
	const hasPersisted = Boolean(current.service && current.id);
	const pendingUrl = typeof current.new === "string" ? current.new : null;
	const pendingManaged = current.managed != null ? String(current.managed) : null;

	const handleUrlChange = (next: string) => {
		const trimmed = next.trim();

		if (trimmed.length === 0) {
			onChange({});

			return;
		}

		onChange({ new: trimmed });
	};

	const handlePickFromLibrary = (resourceId: number) => {
		onChange({ managed: resourceId });
	};

	const handleClear = () => {
		onChange({});
	};

	return (
		<div className="space-y-2">
			<div className="flex flex-wrap items-center gap-2">
				<input
					type="url"
					className={`${INPUT_CLASS} flex-1`}
					placeholder="YouTube or Vimeo URL"
					value={pendingUrl ?? ""}
					disabled={disabled}
					onChange={(e) => handleUrlChange(e.target.value)}
				/>
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
			</div>

			{pendingUrl && (
				<PendingRow
					label="Pending"
					text={pendingUrl}
					onClear={handleClear}
					disabled={disabled}
				/>
			)}

			{!pendingUrl && pendingManaged && (
				<PendingRow
					label="Pending (library)"
					text={`Resource #${pendingManaged} — will be linked on save`}
					onClear={handleClear}
					disabled={disabled}
				/>
			)}

			{!pendingUrl && !pendingManaged && hasPersisted && (
				<CurrentPreview value={current} onClear={handleClear} disabled={disabled} />
			)}

			<ResourcePicker
				open={pickerOpen}
				onOpenChange={setPickerOpen}
				type="video"
				onSelect={(resource) => handlePickFromLibrary(resource.id)}
			/>
		</div>
	);
};

interface PendingRowProps {
	label: string;
	text: string;
	onClear: () => void;
	disabled?: boolean;
}

const PendingRow = ({ label, text, onClear, disabled }: PendingRowProps) => (
	<div className="flex items-center gap-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-[12.5px]">
		<VideoIcon size={14} className="text-text-3" />
		<div className="min-w-0 flex-1">
			<div className="text-[11px] uppercase tracking-[0.06em] text-text-3">{label}</div>
			<div className="truncate text-text-2" title={text}>
				{text}
			</div>
		</div>
		{!disabled && (
			<button
				type="button"
				className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
				onClick={onClear}
				aria-label="Clear"
			>
				<X size={13} />
			</button>
		)}
	</div>
);

interface CurrentPreviewProps {
	value: VideoValue;
	onClear: () => void;
	disabled?: boolean;
}

const CurrentPreview = ({ value, onClear, disabled }: CurrentPreviewProps) => {
	const service = String(value.service ?? "").toLowerCase();
	const id = String(value.id ?? "");
	const src =
		service === "youtube"
			? `https://www.youtube.com/embed/${encodeURIComponent(id)}`
			: service === "vimeo"
				? `https://player.vimeo.com/video/${encodeURIComponent(id)}`
				: null;

	return (
		<div className="flex items-start gap-3 rounded-md border border-border bg-surface-2 p-2">
			<div className="overflow-hidden rounded border border-border bg-black">
				{src ? (
					<iframe
						src={src}
						title={`${value.service} video ${id}`}
						className="block aspect-video h-32 w-56"
						allowFullScreen
					/>
				) : value.image ? (
					<img
						src={String(value.image)}
						alt=""
						className="block aspect-video h-32 w-56 object-cover"
					/>
				) : (
					<div className="grid h-32 w-56 place-items-center text-text-3">
						<VideoIcon size={22} />
					</div>
				)}
			</div>
			<div className="min-w-0 flex-1 text-[12px]">
				<div className="text-[11px] uppercase tracking-[0.06em] text-text-3">Current</div>
				<div className="truncate text-text-2">
					{value.service} · {id}
				</div>
			</div>
			{!disabled && (
				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
					onClick={onClear}
					aria-label="Remove video"
				>
					<X size={14} />
				</button>
			)}
		</div>
	);
};
