import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import {
	Edit,
	File as FileIcon,
	Film,
	Folder,
	FolderPlus,
	Image as ImageIcon,
	Trash,
	Video,
} from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { IconTile } from "@/components/ui/IconTile";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";
import { IconButton } from "@/components/ui/IconButton";
import { FileDetail } from "@/components/files/FileDetail";
import { FolderEditor } from "@/components/files/FolderEditor";
import { UploadZone } from "@/components/files/UploadZone";
import { VideoCreator } from "@/components/files/VideoCreator";

import {
	resourceFoldersApi,
	type ResourceFolderRow,
	type ResourceSummary,
} from "@/api/endpoints/resource-folders";
import { resourcesApi } from "@/api/endpoints/resources";

import { formatBytes } from "@/lib/bytes";
import { expandImageUrl } from "@/lib/imageUrl";
import { toast } from "@/lib/toast";
import { useToastMutation } from "@/hooks/useToastMutation";

/**
 * Files / Resource Manager.
 *
 *   /files            → root folder (id = 0)
 *   /files/folder/:id → that folder
 *
 * Folders and resources share one DataTable; rows are tagged with a `kind`
 * discriminator so click handling can branch (folders navigate, files open
 * a detail SlideOver). When the search box has content (debounced 200ms)
 * the list is replaced by `/resources/search` results — folders are dropped
 * from the listing in that mode to match the PHP admin.
 */

type Row =
	| { kind: "folder"; folder: ResourceFolderRow }
	| { kind: "file"; resource: ResourceSummary };

const FOLDER_CONTENTS_KEY = (id: number) => ["resource-folders", "contents", id] as const;
const RESOURCE_SEARCH_KEY = (q: string) => ["resources", "search", q] as const;

const rowKey = (row: Row): string =>
	row.kind === "folder" ? `f-${row.folder.id}` : `r-${row.resource.id}`;

interface FileThumbProps {
	resource: ResourceSummary;
}

const FileThumb = ({ resource }: FileThumbProps) => {
	if (resource.is_image && resource.file) {
		return (
			<img
				src={expandImageUrl(resource.file)}
				alt=""
				className="size-9 rounded object-cover ring-1 ring-border"
				loading="lazy"
			/>
		);
	}

	const Icon = resource.is_video ? Film : FileIcon;

	return (
		<IconTile tone="neutral" ringed>
			{resource.is_image ? <ImageIcon size={16} /> : <Icon size={16} />}
		</IconTile>
	);
};

const FolderThumb = () => (
	<IconTile ringed>
		<Folder size={16} />
	</IconTile>
);

export const Files = () => {
	const params = useParams<{ id?: string }>();
	const folderId = params.id ? parseInt(params.id, 10) : 0;
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [query, setQuery] = useState("");
	const [debounced, setDebounced] = useState("");

	// Folder editor SlideOver — `null` closed, `"new"` for create, or a folder row for rename.
	const [folderEditor, setFolderEditor] = useState<"new" | ResourceFolderRow | null>(null);
	const [confirmDeleteFolder, setConfirmDeleteFolder] = useState<ResourceFolderRow | null>(null);
	// File detail SlideOver — null closed, otherwise the resource id to load.
	const [detailResourceId, setDetailResourceId] = useState<number | null>(null);
	const [videoCreatorOpen, setVideoCreatorOpen] = useState(false);

	// Reset the search box when the folder changes.
	useEffect(() => {
		setQuery("");
		setDebounced("");
	}, [folderId]);

	// Debounce the query so we don't fire a request on every keystroke.
	useEffect(() => {
		const h = setTimeout(() => setDebounced(query.trim()), 200);

		return () => clearTimeout(h);
	}, [query]);

	const isSearching = debounced.length >= 2;

	const contentsQuery = useQuery({
		queryKey: FOLDER_CONTENTS_KEY(folderId),
		queryFn: () => resourceFoldersApi.listContents(folderId),
		placeholderData: keepPreviousData,
	});

	const searchQuery = useQuery({
		queryKey: RESOURCE_SEARCH_KEY(debounced),
		queryFn: () => resourcesApi.search(debounced),
		enabled: isSearching,
		placeholderData: keepPreviousData,
	});

	const contents = contentsQuery.data;
	const access = contents?.access ?? "n";
	const canUpload = access === "p";
	const canCreateFolder = access === "p";

	const handleUploaded = useCallback(() => {
		queryClient.invalidateQueries({ queryKey: FOLDER_CONTENTS_KEY(folderId) });
		toast.success("File uploaded");
	}, [queryClient, folderId]);

	const deleteFolderMutation = useToastMutation({
		mutationFn: (folder: ResourceFolderRow) => resourceFoldersApi.delete(folder.id),
		invalidate: [FOLDER_CONTENTS_KEY(folderId)],
		errorMessage: "Could not delete folder",
		onSuccess: (_, folder) => {
			toast.success(`Deleted "${folder.name}"`);
		},
	});

	const breadcrumbItems = useMemo(() => {
		const trail = [{ label: "Files", to: "/files" }];

		if (folderId === 0) {
			trail.push({ label: "Home", to: "/files" });

			return trail;
		}

		// `breadcrumb` from the server is ancestor-first, ending with the current folder.
		const fromServer = contents?.breadcrumb ?? [];
		trail.push({ label: "Home", to: "/files" });

		for (const piece of fromServer) {
			trail.push({ label: piece.name, to: `/files/folder/${piece.id}` });
		}

		return trail;
	}, [folderId, contents?.breadcrumb]);

	const rows: Row[] = useMemo(() => {
		if (isSearching) {
			const list = searchQuery.data ?? [];

			return list.map((r): Row => ({ kind: "file", resource: r }));
		}

		if (!contents) {
			return [];
		}

		return [
			...contents.folders.map((f): Row => ({ kind: "folder", folder: f })),
			...contents.resources.map((r): Row => ({ kind: "file", resource: r })),
		];
	}, [isSearching, searchQuery.data, contents]);

	const handleRowClick = (row: Row) => {
		if (row.kind === "folder") {
			navigate(`/files/folder/${row.folder.id}`);

			return;
		}

		setDetailResourceId(row.resource.id);
	};

	const columns: DataTableColumn<Row>[] = [
		{
			key: "icon",
			header: "",
			width: "56px",
			cell: (row) =>
				row.kind === "folder" ? <FolderThumb /> : <FileThumb resource={row.resource} />,
		},
		{
			key: "name",
			header: "Name",
			width: "minmax(0,1.7fr)",
			cell: (row) => (
				<span
					className="block truncate font-medium text-text"
					title={row.kind === "folder" ? row.folder.name : row.resource.name}
				>
					{row.kind === "folder" ? row.folder.name : row.resource.name}
				</span>
			),
		},
		{
			key: "type",
			header: "Type",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) =>
				row.kind === "folder" ? (
					<span className="text-text-3">folder</span>
				) : (
					<span className="truncate text-text-3" title={row.resource.mimetype}>
						{row.resource.mimetype || row.resource.type || "—"}
					</span>
				),
		},
		{
			key: "size",
			header: "Size",
			width: "140px",
			hideOnMobile: true,
			align: "right",
			headerAlign: "right",
			cell: (row) => {
				if (row.kind === "folder") {
					return <span className="text-text-3">—</span>;
				}

				const r = row.resource;

				if (r.width && r.height) {
					return (
						<span className="tabular-nums text-text-3">
							{r.width} × {r.height}
						</span>
					);
				}

				return <span className="tabular-nums text-text-3">{formatBytes(r.size)}</span>;
			},
		},
		{
			key: "actions",
			header: "Actions",
			width: "74px",
			headerAlign: "right",
			align: "right",
			cell: (row) => {
				if (row.kind === "folder") {
					const canEdit = row.folder.access === "p";

					return (
						<div className="flex items-center justify-end gap-1">
							<IconButton
								className="disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-3"
								title="Rename folder"
								label="Rename folder"
								disabled={!canEdit}
								onClick={(e) => {
									e.stopPropagation();
									setFolderEditor(row.folder);
								}}
							>
								<Edit size={15} />
							</IconButton>
							<IconButton
								tone="danger"
								className="disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-3"
								title="Delete folder"
								label="Delete folder"
								disabled={!canEdit}
								onClick={(e) => {
									e.stopPropagation();
									setConfirmDeleteFolder(row.folder);
								}}
							>
								<Trash size={15} />
							</IconButton>
						</div>
					);
				}

				return (
					<div className="flex items-center justify-end gap-1">
						<IconButton
							title="View file"
							label="View file"
							onClick={(e) => {
								e.stopPropagation();
								setDetailResourceId(row.resource.id);
							}}
						>
							<Edit size={15} />
						</IconButton>
					</div>
				);
			},
		},
	];

	const loading =
		(isSearching && searchQuery.isFetching && !searchQuery.data) ||
		(!isSearching && contentsQuery.isLoading);

	const totalCount = isSearching ? (searchQuery.data?.length ?? 0) : rows.length;

	const sub = isSearching
		? `${totalCount} result${totalCount === 1 ? "" : "s"} for "${debounced}"`
		: contents
			? `${contents.folders.length} folder${contents.folders.length === 1 ? "" : "s"}, ${contents.resources.length} file${contents.resources.length === 1 ? "" : "s"}`
			: "Loading…";

	return (
		<PageContainer width="wide">
			<Breadcrumb items={breadcrumbItems} />

			<PageHead
				title="Files"
				sub={sub}
				actions={
					canCreateFolder && !isSearching ? (
						<>
							<Button
								icon={<Video size={13} />}
								onClick={() => setVideoCreatorOpen(true)}
							>
								Add video
							</Button>
							<Button
								icon={<FolderPlus size={13} />}
								onClick={() => setFolderEditor("new")}
							>
								New folder
							</Button>
						</>
					) : undefined
				}
			/>

			{canUpload && !isSearching && (
				<UploadZone folderId={folderId} onUploaded={handleUploaded} />
			)}

			<Toolbar
				search={
					<SearchInput
						value={query}
						onChange={setQuery}
						placeholder="Search files by name…"
					/>
				}
			/>

			<DataTable<Row>
				columns={columns}
				rows={rows}
				getRowKey={rowKey}
				isLoading={loading}
				loadingLabel={isSearching ? "Searching…" : "Loading files…"}
				emptyLabel={
					isSearching ? `No files match "${debounced}".` : "This folder is empty."
				}
				onRowClick={handleRowClick}
			/>

			<FolderEditor
				open={folderEditor !== null}
				onOpenChange={(open) => {
					if (!open) {
						setFolderEditor(null);
					}
				}}
				parentId={folderId}
				folder={folderEditor && folderEditor !== "new" ? folderEditor : null}
				invalidateKey={FOLDER_CONTENTS_KEY(folderId)}
			/>

			{confirmDeleteFolder && (
				<ConfirmDialog
					open={true}
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDeleteFolder(null);
						}
					}}
					title={`Delete "${confirmDeleteFolder.name}"?`}
					description="Subfolders and files inside this folder will be moved up one level — they won't be deleted. This action cannot be undone."
					confirmLabel="Delete folder"
					variant="danger"
					onConfirm={() => deleteFolderMutation.mutate(confirmDeleteFolder)}
				/>
			)}

			<FileDetail
				resourceId={detailResourceId}
				onOpenChange={(open) => {
					if (!open) {
						setDetailResourceId(null);
					}
				}}
				folderQueryKey={FOLDER_CONTENTS_KEY(folderId)}
			/>

			<VideoCreator
				open={videoCreatorOpen}
				onOpenChange={setVideoCreatorOpen}
				folderId={folderId}
				invalidateKey={FOLDER_CONTENTS_KEY(folderId)}
				onCreated={(resource) => setDetailResourceId(resource.id)}
			/>
		</PageContainer>
	);
};
