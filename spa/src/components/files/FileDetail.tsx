import { useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
	Copy,
	Crop as CropIcon,
	File as FileIcon,
	Film,
	Image as ImageIcon,
	Link as LinkIcon,
	RefreshCw,
	Trash,
} from "lucide-react";

import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { SlideOver } from "@/components/ui/SlideOver";
import { CropModal } from "@/components/files/CropModal";
import { FileUsageList } from "@/components/files/FileUsageList";

import {
	resourcesApi,
	type ResourceDetail,
	type ResourceMetadataField,
	type ResourcePrefixedAsset,
} from "@/api/endpoints/resources";
import { resourceFoldersApi } from "@/api/endpoints/resource-folders";
import type { ModuleFormField } from "@/api/endpoints/modules";
import { FieldRenderer } from "@/renderer/forms/FieldRenderer";
import { ApiError } from "@/types/api";
import { formatBytes } from "@/lib/bytes";
import { expandImageUrl } from "@/lib/imageUrl";
import { toast } from "@/lib/toast";

interface FileDetailProps {
	/** Resource id to load, or `null` to keep the SlideOver closed. */
	resourceId: number | null;
	onOpenChange: (open: boolean) => void;
	/** Query key for the parent folder listing — invalidated on save/delete. */
	folderQueryKey: readonly unknown[];
}

const RESOURCE_DETAIL_KEY = (id: number) => ["resources", "detail", id] as const;
const RESOURCE_USAGE_KEY = (id: number) => ["resources", "usage", id] as const;

/**
 * Slide-over detail panel for a single resource. Loads `/resources/{id}` and
 * `/resources/{id}/usage` and exposes rename, folder move, metadata
 * editing (developer-defined fields, per file kind), and delete — mirroring
 * the legacy files/edit/file.php form.
 */
export const FileDetail = ({ resourceId, onOpenChange, folderQueryKey }: FileDetailProps) => {
	const queryClient = useQueryClient();
	const open = resourceId !== null;
	const [name, setName] = useState("");
	const [folder, setFolder] = useState(0);
	const [metadata, setMetadata] = useState<Record<string, unknown>>({});
	const [confirmDelete, setConfirmDelete] = useState(false);
	const [cropOpen, setCropOpen] = useState(false);
	const replaceInputRef = useRef<HTMLInputElement>(null);

	const detailQuery = useQuery({
		queryKey: resourceId ? RESOURCE_DETAIL_KEY(resourceId) : ["resources", "detail", "noop"],
		queryFn: () => resourcesApi.get(resourceId as number),
		enabled: resourceId !== null,
	});

	const usageQuery = useQuery({
		queryKey: resourceId ? RESOURCE_USAGE_KEY(resourceId) : ["resources", "usage", "noop"],
		queryFn: () => resourcesApi.usage(resourceId as number),
		enabled: resourceId !== null,
	});

	const foldersQuery = useQuery({
		queryKey: ["resource-folders", "flat"],
		queryFn: () => resourceFoldersApi.listFlat(),
		enabled: open,
	});

	const metadataFieldsQuery = useQuery({
		queryKey: ["resources", "metadata-fields"],
		queryFn: () => resourcesApi.metadataFields(),
		enabled: open,
		staleTime: 5 * 60 * 1000,
	});

	const resource = detailQuery.data;

	// Sync the editable fields with whatever the server returned (resets when
	// switching between resources or after a save).
	useEffect(() => {
		if (resource) {
			setName(resource.name);
			setFolder(resource.folder ?? 0);
			setMetadata(resource.metadata ?? {});
		}
	}, [resource]);

	// The metadata definitions that apply to this file's kind.
	const metaFields: ResourceMetadataField[] = !resource
		? []
		: resource.is_video
			? (metadataFieldsQuery.data?.video ?? [])
			: resource.is_image
				? (metadataFieldsQuery.data?.image ?? [])
				: (metadataFieldsQuery.data?.file ?? []);

	const updateMutation = useMutation({
		mutationFn: () => {
			if (!resource) {
				throw new Error("no resource loaded");
			}

			return resourcesApi.update(resource.id, { name: name.trim(), folder, metadata });
		},
		onSuccess: (updated) => {
			queryClient.invalidateQueries({ queryKey: folderQueryKey });
			queryClient.setQueryData(RESOURCE_DETAIL_KEY(updated.id), updated);
			toast.success("File saved");
			onOpenChange(false);
		},
		onError: () => {
			toast.error("Could not save changes");
		},
	});

	const replaceMutation = useMutation({
		mutationFn: (file: File) => {
			if (!resource) {
				throw new Error("no resource loaded");
			}

			return resourcesApi.replace(resource.id, file);
		},
		onSuccess: (updated) => {
			queryClient.setQueryData(RESOURCE_DETAIL_KEY(updated.id), updated);
			queryClient.invalidateQueries({ queryKey: folderQueryKey });
			toast.success("File replaced", {
				description: "The URL is unchanged — existing references keep working.",
			});
		},
		onError: (err) => {
			toast.error(
				err instanceof ApiError && err.message ? err.message : "Could not replace the file"
			);
		},
	});

	const deleteMutation = useMutation({
		mutationFn: () => {
			if (!resource) {
				throw new Error("no resource loaded");
			}

			return resourcesApi.delete(resource.id);
		},
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: folderQueryKey });
			toast.success("File deleted");
			setConfirmDelete(false);
			onOpenChange(false);
		},
		onError: () => {
			toast.error("Could not delete file");
		},
	});

	const dirty =
		!!resource &&
		name.trim().length > 0 &&
		(name.trim() !== resource.name ||
			folder !== (resource.folder ?? 0) ||
			JSON.stringify(metadata) !== JSON.stringify(resource.metadata ?? {}));
	const pending =
		updateMutation.isPending || deleteMutation.isPending || replaceMutation.isPending;

	// Min replacement size: the largest existing crop (matches the server rule).
	const cropList = Object.values(resource?.crops ?? {});
	const minWidth = cropList.reduce((m, c) => Math.max(m, c.width || 0), 0);
	const minHeight = cropList.reduce((m, c) => Math.max(m, c.height || 0), 0);

	const onPickReplacement = (event: React.ChangeEvent<HTMLInputElement>) => {
		const file = event.target.files?.[0];
		// Allow re-selecting the same file later.
		event.target.value = "";

		if (file) {
			replaceMutation.mutate(file);
		}
	};

	const copyUrl = async () => {
		if (!resource?.file) {
			return;
		}

		try {
			await navigator.clipboard.writeText(expandImageUrl(resource.file));
			toast.success("URL copied");
		} catch {
			toast.error("Could not copy URL");
		}
	};

	return (
		<>
			<SlideOver
				open={open}
				onOpenChange={onOpenChange}
				title={resource?.name ?? "File"}
				description={resource ? `Resource #${resource.id}` : undefined}
				width="lg"
				footer={
					<div className="flex justify-between gap-2">
						<button
							type="button"
							className="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-[12.5px] text-danger hover:bg-danger-bg disabled:opacity-50"
							onClick={() => setConfirmDelete(true)}
							disabled={!resource || pending}
						>
							<Trash size={13} />
							Delete
						</button>

						<div className="flex gap-2">
							<button
								type="button"
								className="rounded-md border border-border px-3 py-1.5 text-[12.5px] hover:bg-hover"
								onClick={() => onOpenChange(false)}
							>
								Close
							</button>
							<Button
								variant="primary"
								disabled={!dirty || pending}
								onClick={() => updateMutation.mutate()}
							>
								{updateMutation.isPending ? "Saving…" : "Save changes"}
							</Button>
						</div>
					</div>
				}
			>
				{detailQuery.isLoading || !resource ? (
					<div className="grid h-40 place-items-center text-[13px] text-text-3">
						Loading…
					</div>
				) : (
					<div className="space-y-5">
						<Preview resource={resource} cacheKey={detailQuery.dataUpdatedAt} />

						{!resource.is_video && (
							<div className="flex flex-wrap items-center gap-2">
								<input
									ref={replaceInputRef}
									type="file"
									className="hidden"
									accept={resource.is_image ? "image/*" : undefined}
									onChange={onPickReplacement}
								/>
								<Button
									variant="secondary"
									icon={
										<RefreshCw
											size={13}
											className={
												replaceMutation.isPending ? "animate-spin" : ""
											}
										/>
									}
									onClick={() => replaceInputRef.current?.click()}
									disabled={pending}
								>
									{replaceMutation.isPending ? "Replacing…" : "Replace file"}
								</Button>
								<span className="text-[11.5px] text-text-3">
									Keeps the URL and references.
									{resource.is_image && (minWidth > 0 || minHeight > 0)
										? ` Minimum size ${minWidth}×${minHeight}px (largest crop).`
										: ""}
								</span>
							</div>
						)}

						<label className="block">
							<span className="mb-1 block text-[12px] font-medium text-text-2">
								Name
							</span>
							<input
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								value={name}
								onChange={(e) => setName(e.target.value)}
								maxLength={255}
							/>
						</label>

						<label className="block">
							<span className="mb-1 block text-[12px] font-medium text-text-2">
								Folder
							</span>
							<select
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								value={folder}
								onChange={(e) => setFolder(Number(e.target.value))}
							>
								<option value={0}>Home</option>
								{(foldersQuery.data ?? []).map((f) => (
									<option key={f.id} value={f.id}>
										{" ".repeat((f.depth + 1) * 2)}
										{f.name}
									</option>
								))}
							</select>
						</label>

						<MetaGrid resource={resource} onCopyUrl={copyUrl} />

						{metaFields.length > 0 && (
							<section>
								<h3 className="mb-1.5 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
									Metadata
								</h3>
								<div className="flex flex-col gap-3 rounded-lg border border-border bg-surface-2 p-3">
									{metaFields.map((def) => {
										const field: ModuleFormField = {
											column: def.id,
											title: def.title,
											subtitle: def.subtitle,
											type: def.type,
											settings: def.settings ?? undefined,
										};

										return (
											<label key={def.id} className="block">
												<span className="mb-1 block text-[12px] font-medium text-text-2">
													{def.title}
													{def.subtitle && (
														<span className="ml-1 font-normal text-text-3">
															{def.subtitle}
														</span>
													)}
												</span>
												<FieldRenderer
													field={field}
													value={metadata[def.id]}
													onChange={(next) =>
														setMetadata((prev) => ({
															...prev,
															[def.id]: next,
														}))
													}
													disabled={pending}
												/>
											</label>
										);
									})}
								</div>
							</section>
						)}

						<FileUsageList
							isLoading={usageQuery.isLoading}
							usages={usageQuery.data ?? []}
						/>

						{resource.is_image && (
							<CropsSection
								crops={resource.crops ?? {}}
								onAddCrop={() => setCropOpen(true)}
							/>
						)}
					</div>
				)}
			</SlideOver>

			{resource && resource.is_image && (
				<CropModal open={cropOpen} onOpenChange={setCropOpen} resource={resource} />
			)}

			{confirmDelete && resource && (
				<ConfirmDialog
					open={true}
					onOpenChange={setConfirmDelete}
					title={`Delete “${resource.name}”?`}
					description={`This permanently removes the file and all of its crops. ${
						(usageQuery.data?.length ?? 0) > 0
							? "It's currently used by other content — those references will break."
							: "It does not appear to be in use."
					}`}
					confirmLabel="Delete file"
					variant="danger"
					onConfirm={() => deleteMutation.mutate()}
				/>
			)}
		</>
	);
};

interface PreviewProps {
	resource: ResourceDetail;
	/** Cache-buster appended to the image URL so replacements show immediately. */
	cacheKey?: number;
}

const Preview = ({ resource, cacheKey }: PreviewProps) => {
	if (resource.is_image && resource.file) {
		return (
			<div className="overflow-hidden rounded-lg border border-border bg-surface-2">
				<img
					src={expandImageUrl(resource.file) + (cacheKey ? `?${cacheKey}` : "")}
					alt={resource.name}
					className="block max-h-[260px] w-full object-contain"
				/>
			</div>
		);
	}

	const Icon = resource.is_video ? Film : resource.is_image ? ImageIcon : FileIcon;

	return (
		<div className="grid h-32 place-items-center rounded-lg border border-border bg-surface-2 text-text-3">
			<Icon size={42} />
		</div>
	);
};

interface MetaGridProps {
	resource: ResourceDetail;
	onCopyUrl: () => void;
}

const MetaGrid = ({ resource, onCopyUrl }: MetaGridProps) => {
	const fileUrl = expandImageUrl(resource.file);

	const rows: Array<[string, React.ReactNode]> = [
		["Type", resource.mimetype || resource.type || "—"],
		["Size", formatBytes(resource.size)],
	];

	if (resource.width && resource.height) {
		rows.push(["Dimensions", `${resource.width} × ${resource.height} px`]);
	}

	if (resource.date) {
		rows.push(["Uploaded", resource.date]);
	}

	if (resource.location) {
		rows.push(["Storage", resource.location]);
	}

	return (
		<dl className="grid grid-cols-[110px_minmax(0,1fr)] gap-x-3 gap-y-1.5 rounded-lg border border-border bg-surface-2 p-3 text-[12.5px]">
			{rows.map(([label, value]) => (
				<div key={label} className="contents">
					<dt className="text-text-3">{label}</dt>
					<dd className="truncate text-text-2">{value}</dd>
				</div>
			))}

			<dt className="text-text-3">URL</dt>
			<dd className="flex min-w-0 items-center gap-1.5">
				<a
					href={fileUrl}
					target="_blank"
					rel="noopener noreferrer"
					className="inline-flex items-center gap-1 truncate text-accent hover:underline"
				>
					<LinkIcon size={12} />
					<span className="truncate font-mono text-[11.5px]">{fileUrl}</span>
				</a>
				<button
					type="button"
					className="ml-auto shrink-0 rounded p-1 text-text-3 hover:bg-hover hover:text-text"
					title="Copy URL"
					onClick={onCopyUrl}
				>
					<Copy size={12} />
				</button>
			</dd>
		</dl>
	);
};

interface CropsSectionProps {
	crops: Record<string, ResourcePrefixedAsset>;
	onAddCrop: () => void;
}

const CropsSection = ({ crops, onAddCrop }: CropsSectionProps) => {
	const entries = Object.entries(crops ?? {});

	return (
		<section>
			<div className="mb-1.5 flex items-center justify-between">
				<h3 className="text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
					Crops
				</h3>
				<button
					type="button"
					className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-1 text-[11.5px] hover:bg-hover"
					onClick={onAddCrop}
				>
					<CropIcon size={11} />
					Add crop
				</button>
			</div>

			{entries.length === 0 ? (
				<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-2 text-[12.5px] text-text-3">
					No saved crops yet.
				</div>
			) : (
				<ul className="grid grid-cols-2 gap-2">
					{entries.map(([prefix, c]) => (
						<li
							key={prefix}
							className="overflow-hidden rounded-md border border-border bg-surface"
						>
							<img
								src={expandImageUrl(c.file)}
								alt=""
								className="block aspect-video w-full object-cover"
							/>
							<div className="px-2 py-1.5 text-[11px]">
								<div className="truncate text-text-2" title={c.name ?? prefix}>
									{c.name ?? prefix}
								</div>
								<div className="text-text-3 tabular-nums">
									{c.width} × {c.height}
								</div>
							</div>
						</li>
					))}
				</ul>
			)}
		</section>
	);
};
