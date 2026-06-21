import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { File as FileIcon, Image as ImageIcon, Search, Video as VideoIcon, X } from "lucide-react";

import { ResourcePicker, type ResourcePickerType } from "@/components/files/ResourcePicker";
import { resourcesApi, type ResourceDetail } from "@/api/endpoints/resources";

import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Shared core for the three *-reference fields — they all browse the media
 * library, store the picked resource's *id* (not its path), and render a
 * preview / link tailored to the resource kind.
 *
 * The legacy non-reference field types (image / upload / video) store the
 * resource URL and accept inline uploads; their reference cousins skip the
 * upload affordance because a reference is *always* a pointer to something
 * already in the media library.
 *
 * Settings consumed:
 *   - min_width / min_height (image variant only)
 *   - disable_remove
 */
interface ResourceReferenceFieldProps extends FieldComponentProps {
	pickerType: ResourcePickerType;
}

const RESOURCE_QUERY = (id: number) => ["resources", "detail", id] as const;

const toResourceId = (value: unknown): number | null => {
	if (typeof value === "number" && Number.isFinite(value) && value > 0) {
		return value;
	}

	if (typeof value === "string" && value.trim().length > 0) {
		const n = Number(value);

		return Number.isFinite(n) && n > 0 ? n : null;
	}

	return null;
};

export const ResourceReferenceField = ({
	field,
	value,
	onChange,
	disabled,
	pickerType,
}: ResourceReferenceFieldProps) => {
	const settings = settingsOf(field);
	const [pickerOpen, setPickerOpen] = useState(false);

	const minWidth = pickerType === "image" ? toInt(settings.min_width) : 0;
	const minHeight = pickerType === "image" ? toInt(settings.min_height) : 0;
	const showRemove = !settings.disable_remove;

	const resourceId = toResourceId(value);

	const resourceQuery = useQuery({
		queryKey: resourceId ? RESOURCE_QUERY(resourceId) : ["resources", "detail", "noop"],
		queryFn: () => resourcesApi.get(resourceId as number),
		enabled: resourceId != null,
	});

	return (
		<div className="space-y-2">
			<button
				type="button"
				className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
				onClick={() => setPickerOpen(true)}
				disabled={disabled}
			>
				<Search size={13} />
				{resourceId ? "Replace" : "Browse media"}
			</button>

			{resourceId && (
				<ReferencePreview
					pickerType={pickerType}
					resourceId={resourceId}
					resource={resourceQuery.data ?? null}
					loading={resourceQuery.isLoading}
					missing={resourceQuery.isError}
					onClear={() => onChange("")}
					showRemove={showRemove}
					disabled={disabled}
				/>
			)}

			<ResourcePicker
				open={pickerOpen}
				onOpenChange={setPickerOpen}
				type={pickerType}
				minWidth={minWidth}
				minHeight={minHeight}
				onSelect={(resource) => onChange(String(resource.id))}
			/>
		</div>
	);
};

interface ReferencePreviewProps {
	pickerType: ResourcePickerType;
	resourceId: number;
	resource: ResourceDetail | null;
	loading: boolean;
	missing: boolean;
	onClear: () => void;
	showRemove: boolean;
	disabled?: boolean;
}

const ReferencePreview = ({
	pickerType,
	resourceId,
	resource,
	loading,
	missing,
	onClear,
	showRemove,
	disabled,
}: ReferencePreviewProps) => {
	return (
		<div className="flex items-start gap-3 rounded-md border border-border bg-surface-2 p-2">
			<div className="overflow-hidden rounded border border-border bg-surface">
				<PreviewTile pickerType={pickerType} resource={resource} loading={loading} />
			</div>
			<div className="min-w-0 flex-1 text-[12px]">
				<div className="text-[11px] uppercase tracking-[0.06em] text-text-3">
					Current · #{resourceId}
				</div>

				{loading ? (
					<div className="text-text-3">Loading…</div>
				) : missing || !resource ? (
					<div className="text-danger">
						Resource not found — it may have been deleted.
					</div>
				) : (
					<a
						href={resource.file}
						target="_blank"
						rel="noopener noreferrer"
						className="block truncate text-accent hover:underline"
						title={resource.name}
					>
						{resource.name}
					</a>
				)}
			</div>
			{showRemove && !disabled && (
				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
					onClick={onClear}
					aria-label="Remove reference"
				>
					<X size={14} />
				</button>
			)}
		</div>
	);
};

interface PreviewTileProps {
	pickerType: ResourcePickerType;
	resource: ResourceDetail | null;
	loading: boolean;
}

const PreviewTile = ({ pickerType, resource, loading }: PreviewTileProps) => {
	if (loading || !resource) {
		return <PlaceholderTile pickerType={pickerType} />;
	}

	if (pickerType === "video" && resource.video_data) {
		const data = resource.video_data as { service?: string; id?: string };
		const service = String(data.service ?? "").toLowerCase();
		const id = String(data.id ?? "");

		if (service === "youtube" && id) {
			return (
				<iframe
					src={`https://www.youtube.com/embed/${encodeURIComponent(id)}`}
					title={resource.name}
					className="block aspect-video h-20 w-32"
					allowFullScreen
				/>
			);
		}

		if (service === "vimeo" && id) {
			return (
				<iframe
					src={`https://player.vimeo.com/video/${encodeURIComponent(id)}`}
					title={resource.name}
					className="block aspect-video h-20 w-32"
					allowFullScreen
				/>
			);
		}
	}

	if (resource.is_image && resource.file) {
		return (
			<img src={resource.file} alt="" className="block size-20 object-cover" loading="lazy" />
		);
	}

	return <PlaceholderTile pickerType={pickerType} />;
};

const PlaceholderTile = ({ pickerType }: { pickerType: ResourcePickerType }) => {
	const Icon = pickerType === "video" ? VideoIcon : pickerType === "image" ? ImageIcon : FileIcon;

	return (
		<div className="grid size-20 place-items-center text-text-3">
			<Icon size={22} />
		</div>
	);
};

const toInt = (raw: unknown): number => {
	const n = typeof raw === "number" ? raw : Number(raw);

	return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0;
};
