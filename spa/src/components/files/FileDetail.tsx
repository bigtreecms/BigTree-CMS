import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
	Copy,
	File as FileIcon,
	Film,
	Image as ImageIcon,
	Link as LinkIcon,
	Trash,
} from "lucide-react";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { SlideOver } from "@/components/ui/SlideOver";

import {
	resourcesApi,
	type ResourceAllocation,
	type ResourceCrop,
	type ResourceDetail,
} from "@/api/endpoints/resources";
import { formatBytes } from "@/lib/bytes";
import { toast } from "@/lib/toast";

interface FileDetailProps {
	/** Resource id to load, or `null` to keep the SlideOver closed. */
	resourceId: number | null;
	onOpenChange: (open: boolean) => void;
	/** Query key for the parent folder listing — invalidated on save/delete. */
	folderQueryKey: readonly unknown[];
}

const RESOURCE_DETAIL_KEY = (id: number) => ["resources", "detail", id] as const;
const RESOURCE_ALLOCATIONS_KEY = (id: number) => ["resources", "allocations", id] as const;

/**
 * Slide-over detail panel for a single resource. Loads `/resources/{id}` and
 * `/resources/{id}/allocations` and exposes rename + delete. Folder-move and
 * metadata editing are deferred until the ResourcePicker (task 8) lands so
 * the folder tree can be reused.
 */
export const FileDetail = ({ resourceId, onOpenChange, folderQueryKey }: FileDetailProps) => {
	const queryClient = useQueryClient();
	const open = resourceId !== null;
	const [name, setName] = useState("");
	const [confirmDelete, setConfirmDelete] = useState(false);

	const detailQuery = useQuery({
		queryKey: resourceId ? RESOURCE_DETAIL_KEY(resourceId) : ["resources", "detail", "noop"],
		queryFn: () => resourcesApi.get(resourceId as number),
		enabled: resourceId !== null,
	});

	const allocationsQuery = useQuery({
		queryKey: resourceId
			? RESOURCE_ALLOCATIONS_KEY(resourceId)
			: ["resources", "allocations", "noop"],
		queryFn: () => resourcesApi.allocations(resourceId as number),
		enabled: resourceId !== null,
	});

	const resource = detailQuery.data;

	// Sync the editable name field with whatever the server returned (resets
	// when switching between resources or after a save).
	useEffect(() => {
		if (resource) {
			setName(resource.name);
		}
	}, [resource]);

	const updateMutation = useMutation({
		mutationFn: () => {
			if (!resource) {
				throw new Error("no resource loaded");
			}

			return resourcesApi.update(resource.id, { name: name.trim() });
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

	const dirty = !!resource && name.trim() !== resource.name && name.trim().length > 0;
	const pending = updateMutation.isPending || deleteMutation.isPending;

	const copyUrl = async () => {
		if (!resource?.file) {
			return;
		}

		try {
			await navigator.clipboard.writeText(resource.file);
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
							<button
								type="button"
								disabled={!dirty || pending}
								className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
								onClick={() => updateMutation.mutate()}
							>
								{updateMutation.isPending ? "Saving…" : "Save changes"}
							</button>
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
						<Preview resource={resource} />

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

						<MetaGrid resource={resource} onCopyUrl={copyUrl} />

						<AllocationsList
							isLoading={allocationsQuery.isLoading}
							allocations={allocationsQuery.data ?? []}
						/>

						{resource.is_image && resource.crops.length > 0 && (
							<CropsList crops={resource.crops} />
						)}
					</div>
				)}
			</SlideOver>

			{confirmDelete && resource && (
				<ConfirmDialog
					open={true}
					onOpenChange={setConfirmDelete}
					title={`Delete “${resource.name}”?`}
					description={`This permanently removes the file and all of its crops. ${
						(allocationsQuery.data?.length ?? 0) > 0
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
}

const Preview = ({ resource }: PreviewProps) => {
	if (resource.is_image && resource.file) {
		return (
			<div className="overflow-hidden rounded-lg border border-border bg-surface-2">
				<img
					src={resource.file}
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
					href={resource.file}
					target="_blank"
					rel="noopener noreferrer"
					className="inline-flex items-center gap-1 truncate text-accent hover:underline"
				>
					<LinkIcon size={12} />
					<span className="truncate font-mono text-[11.5px]">{resource.file}</span>
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

interface AllocationsListProps {
	isLoading: boolean;
	allocations: ResourceAllocation[];
}

const AllocationsList = ({ isLoading, allocations }: AllocationsListProps) => {
	return (
		<section>
			<h3 className="mb-1.5 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
				Used by
			</h3>

			{isLoading ? (
				<div className="text-[12.5px] text-text-3">Loading…</div>
			) : allocations.length === 0 ? (
				<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-2 text-[12.5px] text-text-3">
					Not currently referenced anywhere.
				</div>
			) : (
				<ul className="divide-y divide-border rounded-md border border-border bg-surface">
					{allocations.map((a, i) => (
						<li
							key={`${a.table}-${a.entry}-${i}`}
							className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_140px] gap-2 px-3 py-1.5 text-[12px]"
						>
							<span className="truncate font-mono text-text-2">{a.table}</span>
							<span className="truncate font-mono text-text-2">{a.entry}</span>
							<span className="text-right tabular-nums text-text-3">
								{a.updated_at}
							</span>
						</li>
					))}
				</ul>
			)}
		</section>
	);
};

interface CropsListProps {
	crops: ResourceCrop[];
}

const CropsList = ({ crops }: CropsListProps) => {
	return (
		<section>
			<h3 className="mb-1.5 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
				Crops
			</h3>

			<ul className="grid grid-cols-2 gap-2">
				{crops.map((c, i) => (
					<li
						key={`${c.file}-${i}`}
						className="overflow-hidden rounded-md border border-border bg-surface"
					>
						<img
							src={c.file}
							alt=""
							className="block aspect-video w-full object-cover"
						/>
						<div className="px-2 py-1.5 text-[11px]">
							<div className="truncate text-text-2" title={c.name}>
								{c.name}
							</div>
							<div className="text-text-3 tabular-nums">
								{c.width} × {c.height}
							</div>
						</div>
					</li>
				))}
			</ul>
		</section>
	);
};
