import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useFilePicker } from "@/hooks/useFilePicker";
import { useToastMutation } from "@/hooks/useToastMutation";
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
import { DescriptionList } from "@/components/ui/DescriptionList";
import { Loading } from "@/components/ui/Loading";
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
import { formatBytes } from "@/lib/bytes";
import { expandImageUrl } from "@/lib/imageUrl";
import { queryKeys } from "@/lib/queryKeys";
import { toast } from "@/lib/toast";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useCopyToClipboard } from "@/hooks/useCopyToClipboard";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";
import { SelectField } from "@/components/ui/SelectField";
import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { SectionLabel } from "@/components/ui/SectionLabel";

interface FileDetailProps {
	/** Query key for the parent folder listing — invalidated on save/delete. */
	folderQueryKey: readonly unknown[];
	onOpenChange: (open: boolean) => void;
	/** Resource id to load, or `null` to keep the SlideOver closed. */
	resourceId: number | null;
}

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
	const deleteDialog = useConfirmDialog<true>();
	const copyToClipboard = useCopyToClipboard();
	const [cropOpen, setCropOpen] = useState(false);

	const detailQuery = useQuery({
		queryKey: resourceId
			? queryKeys.resources.detail(resourceId)
			: ["resources", "detail", "noop"],
		queryFn: () => resourcesApi.get(resourceId as number),
		enabled: resourceId !== null,
	});

	const usageQuery = useQuery({
		queryKey: resourceId
			? queryKeys.resources.usage(resourceId)
			: ["resources", "usage", "noop"],
		queryFn: () => resourcesApi.usage(resourceId as number),
		enabled: resourceId !== null,
	});

	const foldersQuery = useQuery({
		queryKey: queryKeys.resourceFolders.flat(),
		queryFn: () => resourceFoldersApi.listFlat(),
		enabled: open,
	});

	const metadataFieldsQuery = useQuery({
		queryKey: queryKeys.resources.metadataFields(),
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

	const updateMutation = useToastMutation({
		mutationFn: () => {
			if (!resource) {
				throw new Error("no resource loaded");
			}

			return resourcesApi.update(resource.id, { name: name.trim(), folder, metadata });
		},
		invalidate: [folderQueryKey as import("@tanstack/react-query").QueryKey],
		successMessage: "File saved",
		errorMessage: "Could not save changes",
		onSuccess: (updated) => {
			queryClient.setQueryData(queryKeys.resources.detail(updated.id), updated);
			onOpenChange(false);
		},
	});

	const replaceMutation = useToastMutation({
		mutationFn: (file: File) => {
			if (!resource) {
				throw new Error("no resource loaded");
			}

			return resourcesApi.replace(resource.id, file);
		},
		errorMessage: "Could not replace the file",
		onSuccess: (updated) => {
			queryClient.setQueryData(queryKeys.resources.detail(updated.id), updated);
			queryClient.invalidateQueries({ queryKey: folderQueryKey });
			toast.success("File replaced", {
				description: "The URL is unchanged — existing references keep working.",
			});
		},
	});

	const deleteMutation = useToastMutation({
		mutationFn: () => {
			if (!resource) {
				throw new Error("no resource loaded");
			}

			return resourcesApi.delete(resource.id);
		},
		invalidate: [folderQueryKey as import("@tanstack/react-query").QueryKey],
		successMessage: "File deleted",
		errorMessage: "Could not delete file",
		onSuccess: () => {
			deleteDialog.close();
			onOpenChange(false);
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

	const replaceFilePicker = useFilePicker((file) => {
		replaceMutation.mutate(file);
	});

	const copyUrl = async () => {
		if (!resource?.file) {
			return;
		}

		await copyToClipboard(expandImageUrl(resource.file), "URL copied");
	};

	return (
		<>
			<SlideOver
				description={resource ? `Resource #${resource.id}` : undefined}
				footer={
					<div className="flex justify-between gap-2">
						<Button
							disabled={!resource || pending}
							icon={<Trash size={13} />}
							variant="dangerGhost"
							onClick={() => deleteDialog.open(true)}
						>
							Delete
						</Button>

						<div className="flex gap-2">
							<Button variant="secondary" onClick={() => onOpenChange(false)}>
								Close
							</Button>
							<Button
								disabled={!dirty || pending}
								loading={updateMutation.isPending}
								loadingLabel="Saving…"
								variant="primary"
								onClick={() => updateMutation.mutate()}
							>
								Save changes
							</Button>
						</div>
					</div>
				}
				open={open}
				title={resource?.name ?? "File"}
				width="lg"
				onOpenChange={onOpenChange}
			>
				{detailQuery.isLoading || !resource ? (
					<Loading className="h-40" variant="block" />
				) : (
					<div className="space-y-5">
						<Preview cacheKey={detailQuery.dataUpdatedAt} resource={resource} />

						{!resource.is_video && (
							<div className="flex flex-wrap items-center gap-2">
								<input
									accept={resource.is_image ? "image/*" : undefined}
									aria-label="Replace file"
									className="hidden"
									ref={replaceFilePicker.inputRef}
									type="file"
									onChange={replaceFilePicker.onChange}
								/>
								<Button
									disabled={pending}
									icon={<RefreshCw size={13} />}
									loading={replaceMutation.isPending}
									loadingLabel="Replacing…"
									variant="secondary"
									onClick={replaceFilePicker.open}
								>
									Replace file
								</Button>
								<span className="text-[11.5px] text-text-3">
									Keeps the URL and references.
									{resource.is_image && (minWidth > 0 || minHeight > 0)
										? ` Minimum size ${minWidth}×${minHeight}px (largest crop).`
										: ""}
								</span>
							</div>
						)}

						<Field label="Name">
							<TextInput
								maxLength={255}
								value={name}
								onChange={(e) => setName(e.target.value)}
							/>
						</Field>

						<SelectField
							label="Folder"
							options={[
								{ value: "0", label: "Home" },
								...(foldersQuery.data ?? []).map((f) => ({
									value: String(f.id),
									label: " ".repeat((f.depth + 1) * 2) + f.name,
								})),
							]}
							value={String(folder)}
							onChange={(v) => setFolder(Number(v))}
						/>

						<MetaGrid resource={resource} onCopyUrl={copyUrl} />

						{metaFields.length > 0 && (
							<section>
								<SectionLabel as="h3" className="mb-1.5">
									Metadata
								</SectionLabel>
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
											<Field
												inlineHint={def.subtitle}
												key={def.id}
												label={def.title}
											>
												<FieldRenderer
													disabled={pending}
													field={field}
													value={metadata[def.id]}
													onChange={(next) =>
														setMetadata((prev) => ({
															...prev,
															[def.id]: next,
														}))
													}
												/>
											</Field>
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
				<CropModal open={cropOpen} resource={resource} onOpenChange={setCropOpen} />
			)}

			{deleteDialog.isOpen && resource && (
				<ConfirmDialog
					{...deleteDialog.dialogProps}
					confirmLabel="Delete file"
					description={`This permanently removes the file and all of its crops. ${
						(usageQuery.data?.length ?? 0) > 0
							? "It's currently used by other content — those references will break."
							: "It does not appear to be in use."
					}`}
					title={`Delete “${resource.name}”?`}
					variant="danger"
					onConfirm={() => deleteMutation.mutate()}
				/>
			)}
		</>
	);
};

interface PreviewProps {
	/** Cache-buster appended to the image URL so replacements show immediately. */
	cacheKey?: number;
	resource: ResourceDetail;
}

const Preview = ({ resource, cacheKey }: PreviewProps) => {
	if (resource.is_image && resource.file) {
		return (
			<div className="overflow-hidden rounded-lg border border-border bg-surface-2">
				<img
					alt={resource.name}
					className="block max-h-[260px] w-full object-contain"
					src={expandImageUrl(resource.file) + (cacheKey ? `?${cacheKey}` : "")}
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
	onCopyUrl: () => void;
	resource: ResourceDetail;
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
		<DescriptionList
			boxed
			items={[
				...rows.map(([label, value]) => ({
					label,
					value,
					valueClassName: "truncate",
				})),
				{
					label: "URL",
					valueClassName: "flex items-center gap-1.5",
					value: (
						<>
							<a
								className="inline-flex items-center gap-1 truncate text-accent hover:underline"
								href={fileUrl}
								rel="noopener noreferrer"
								target="_blank"
							>
								<LinkIcon size={12} />
								<span className="truncate font-mono text-[11.5px]">{fileUrl}</span>
							</a>
							<IconButton
								className="ml-auto shrink-0"
								label="Copy URL"
								title="Copy URL"
								onClick={onCopyUrl}
							>
								<Copy size={12} />
							</IconButton>
						</>
					),
				},
			]}
			labelWidth={110}
		/>
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
				<SectionLabel as="h3">Crops</SectionLabel>
				<Button
					icon={<CropIcon size={11} />}
					size="sm"
					variant="secondary"
					onClick={onAddCrop}
				>
					Add crop
				</Button>
			</div>

			{entries.length === 0 ? (
				<InlineEmpty pad="sm">No saved crops yet.</InlineEmpty>
			) : (
				<ul className="grid grid-cols-2 gap-2">
					{entries.map(([prefix, c]) => (
						<li
							className="overflow-hidden rounded-md border border-border bg-surface"
							key={prefix}
						>
							<img
								alt=""
								className="block aspect-video w-full object-cover"
								src={expandImageUrl(c.file)}
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
