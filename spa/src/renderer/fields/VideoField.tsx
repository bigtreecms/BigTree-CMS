import { useState } from "react";
import { Search, Video as VideoIcon, X } from "lucide-react";

import { ResourcePicker } from "@/components/files/ResourcePicker";
import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";

import { SectionLabel } from "@/components/ui/SectionLabel";

import { isRecord } from "./fieldHelpers";
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
	[key: string]: unknown;
	embed?: string;
	id?: string;
	image?: string;
	managed?: number | string;
	new?: string;
	service?: string;
}

export const VideoField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const showBrowse = !settings.disable_browse;

	const [pickerOpen, setPickerOpen] = useState(false);

	const current: VideoValue = isRecord(value) ? (value as VideoValue) : {};
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
					aria-label="YouTube or Vimeo URL"
					className={`${INPUT_CLASS} flex-1`}
					disabled={disabled}
					placeholder="YouTube or Vimeo URL"
					type="url"
					value={pendingUrl ?? ""}
					onChange={(e) => handleUrlChange(e.target.value)}
				/>
				{showBrowse && (
					<Button
						disabled={disabled}
						icon={<Search size={13} />}
						variant="secondary"
						onClick={() => setPickerOpen(true)}
					>
						Browse media
					</Button>
				)}
			</div>

			{pendingUrl && (
				<PendingRow
					disabled={disabled}
					label="Pending"
					text={pendingUrl}
					onClear={handleClear}
				/>
			)}

			{!pendingUrl && pendingManaged && (
				<PendingRow
					disabled={disabled}
					label="Pending (library)"
					text={`Resource #${pendingManaged} — will be linked on save`}
					onClear={handleClear}
				/>
			)}

			{!pendingUrl && !pendingManaged && hasPersisted && (
				<CurrentPreview disabled={disabled} value={current} onClear={handleClear} />
			)}

			<ResourcePicker
				open={pickerOpen}
				type="video"
				onOpenChange={setPickerOpen}
				onSelect={(resource) => handlePickFromLibrary(resource.id)}
			/>
		</div>
	);
};

interface PendingRowProps {
	disabled?: boolean;
	label: string;
	onClear: () => void;
	text: string;
}

const PendingRow = ({ label, text, onClear, disabled }: PendingRowProps) => (
	<div className="flex items-center gap-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-[12.5px]">
		<VideoIcon className="text-text-3" size={14} />
		<div className="min-w-0 flex-1">
			<SectionLabel size="sm">{label}</SectionLabel>
			<div className="truncate text-text-2" title={text}>
				{text}
			</div>
		</div>
		{!disabled && (
			<IconButton label="Clear" tone="danger" onClick={onClear}>
				<X size={13} />
			</IconButton>
		)}
	</div>
);

interface CurrentPreviewProps {
	disabled?: boolean;
	onClear: () => void;
	value: VideoValue;
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
						allowFullScreen
						className="block aspect-video h-32 w-56"
						sandbox="allow-scripts allow-same-origin allow-popups allow-presentation"
						src={src}
						title={`${value.service} video ${id}`}
					/>
				) : value.image ? (
					<img
						alt=""
						className="block aspect-video h-32 w-56 object-cover"
						src={String(value.image)}
					/>
				) : (
					<div className="grid h-32 w-56 place-items-center text-text-3">
						<VideoIcon size={22} />
					</div>
				)}
			</div>
			<div className="min-w-0 flex-1 text-[12px]">
				<SectionLabel size="sm">Current</SectionLabel>
				<div className="truncate text-text-2">
					{value.service} · {id}
				</div>
			</div>
			{!disabled && (
				<IconButton label="Remove video" tone="danger" onClick={onClear}>
					<X size={14} />
				</IconButton>
			)}
		</div>
	);
};
