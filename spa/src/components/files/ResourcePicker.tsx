import { useEffect, useMemo, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import {
	ChevronRight,
	File as FileIcon,
	Film,
	Folder,
	Image as ImageIcon,
	Search,
	X,
} from "lucide-react";

import { SlideOver } from "@/components/ui/SlideOver";
import { Loading } from "@/components/ui/Loading";

import { resourceFoldersApi, type ResourceSummary } from "@/api/endpoints/resource-folders";
import { resourcesApi } from "@/api/endpoints/resources";

import { expandImageUrl } from "@/lib/imageUrl";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";

export type ResourcePickerType = "image" | "file" | "video";

interface ResourcePickerProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	type: ResourcePickerType;
	/** Optional dimension floor — applied client-side for image pickers. */
	minWidth?: number;
	minHeight?: number;
	onSelect: (resource: ResourceSummary) => void;
}

const FOLDER_CONTENTS_KEY = (id: number) => ["resource-folders", "contents", id] as const;
const RESOURCE_SEARCH_KEY = (q: string, type: ResourcePickerType) =>
	["resources", "search", q, type] as const;

/**
 * Slide-over picker for choosing an existing resource from the media library.
 * Shared by the Image / Upload / Video / *-reference field renderers — the
 * field-specific bits (preview, store-as-id vs path, etc.) all live in the
 * field component; this picker just yields the chosen ResourceSummary.
 *
 * Type filtering: the API returns every file in a folder regardless of kind,
 * so we filter client-side. The image picker also enforces optional min-w/h
 * (mirrors `min_width` / `min_height` from the legacy image field settings).
 */
export const ResourcePicker = ({
	open,
	onOpenChange,
	type,
	minWidth = 0,
	minHeight = 0,
	onSelect,
}: ResourcePickerProps) => {
	const [folderId, setFolderId] = useState(0);
	const [query, setQuery] = useState("");
	const [debounced, setDebounced] = useState("");

	useEffect(() => {
		if (open) {
			setFolderId(0);
			setQuery("");
			setDebounced("");
		}
	}, [open]);

	useEffect(() => {
		const handle = setTimeout(() => setDebounced(query.trim()), 200);

		return () => clearTimeout(handle);
	}, [query]);

	const isSearching = debounced.length >= 2;

	const contentsQuery = useQuery({
		queryKey: FOLDER_CONTENTS_KEY(folderId),
		queryFn: () => resourceFoldersApi.listContents(folderId),
		enabled: open && !isSearching,
		placeholderData: keepPreviousData,
	});

	const searchQuery = useQuery({
		queryKey: RESOURCE_SEARCH_KEY(debounced, type),
		queryFn: () => resourcesApi.search(debounced),
		enabled: open && isSearching,
		placeholderData: keepPreviousData,
	});

	const contents = contentsQuery.data;

	const resources: ResourceSummary[] = useMemo(() => {
		const raw = isSearching ? (searchQuery.data ?? []) : (contents?.resources ?? []);

		return raw
			.filter((r) => matchesType(r, type))
			.filter((r) => matchesDims(r, minWidth, minHeight));
	}, [isSearching, searchQuery.data, contents?.resources, type, minWidth, minHeight]);

	const folders = isSearching ? [] : (contents?.folders ?? []);
	const breadcrumb = contents?.breadcrumb ?? [];

	const handlePick = (resource: ResourceSummary) => {
		onSelect(resource);
		onOpenChange(false);
	};

	return (
		<SlideOver
			open={open}
			onOpenChange={onOpenChange}
			title={pickerTitle(type)}
			description={
				isSearching
					? `Searching for “${debounced}”`
					: breadcrumb.length === 0
						? "Home folder"
						: breadcrumb.map((b) => b.name).join(" / ")
			}
			width="lg"
		>
			<div className="space-y-3">
				<div className="relative">
					<Search
						size={14}
						className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
					/>
					<input
						className="w-full rounded-md border border-border bg-surface py-1.5 px-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
						placeholder="Search the media library…"
						value={query}
						onChange={(e) => setQuery(e.target.value)}
					/>
					{query && (
						<IconButton
							className="absolute right-2 top-1/2 -translate-y-1/2"
							onClick={() => setQuery("")}
							label="Clear search"
						>
							<X size={14} />
						</IconButton>
					)}
				</div>

				{!isSearching && (
					<FolderBreadcrumb breadcrumb={breadcrumb} onNavigate={setFolderId} />
				)}

				{contentsQuery.isLoading && !isSearching ? (
					<Loading variant="block" className="h-32" />
				) : searchQuery.isFetching && isSearching && !searchQuery.data ? (
					<div className="grid h-32 place-items-center text-[13px] text-text-3">
						Searching…
					</div>
				) : (
					<>
						{folders.length > 0 && (
							<ul className="overflow-hidden rounded-md border border-border">
								{folders.map((folder) => (
									<li key={folder.id}>
										<button
											type="button"
											className="flex w-full items-center gap-2 px-3 py-2 text-left text-[13px] hover:bg-hover"
											onClick={() => setFolderId(folder.id)}
										>
											<Folder size={14} className="text-accent" />
											<span className="truncate">{folder.name}</span>
											<ChevronRight
												size={14}
												className="ml-auto text-text-3"
											/>
										</button>
									</li>
								))}
							</ul>
						)}

						{resources.length === 0 ? (
							<InlineEmpty align="center" pad="xl">
								{isSearching
									? "No matching files."
									: emptyMessage(type, minWidth, minHeight)}
							</InlineEmpty>
						) : (
							<ul className="grid grid-cols-3 gap-2">
								{resources.map((resource) => (
									<li key={resource.id}>
										<ResourceTile
											resource={resource}
											type={type}
											onPick={() => handlePick(resource)}
										/>
									</li>
								))}
							</ul>
						)}
					</>
				)}
			</div>
		</SlideOver>
	);
};

interface FolderBreadcrumbProps {
	breadcrumb: { id: number; name: string }[];
	onNavigate: (id: number) => void;
}

const FolderBreadcrumb = ({ breadcrumb, onNavigate }: FolderBreadcrumbProps) => {
	return (
		<nav className="flex flex-wrap items-center gap-1 text-[12px] text-text-3">
			<button
				type="button"
				className="rounded px-1.5 py-0.5 hover:bg-hover hover:text-text"
				onClick={() => onNavigate(0)}
			>
				Home
			</button>
			{breadcrumb.map((piece, index) => (
				<span key={piece.id} className="flex items-center gap-1">
					<ChevronRight size={12} />
					<button
						type="button"
						className="rounded px-1.5 py-0.5 hover:bg-hover hover:text-text disabled:hover:bg-transparent"
						onClick={() => onNavigate(piece.id)}
						disabled={index === breadcrumb.length - 1}
					>
						{piece.name}
					</button>
				</span>
			))}
		</nav>
	);
};

interface ResourceTileProps {
	resource: ResourceSummary;
	type: ResourcePickerType;
	onPick: () => void;
}

const ResourceTile = ({ resource, type, onPick }: ResourceTileProps) => {
	return (
		<button
			type="button"
			className="group block w-full overflow-hidden rounded-md border border-border bg-surface text-left hover:border-accent-ring"
			onClick={onPick}
			title={resource.name}
		>
			<div className="aspect-square w-full bg-surface-2">
				{resource.is_image && resource.file ? (
					<img
						src={expandImageUrl(resource.file)}
						alt=""
						className="size-full object-cover"
						loading="lazy"
					/>
				) : (
					<div className="grid size-full place-items-center text-text-3">
						{iconForType(type, resource)}
					</div>
				)}
			</div>
			<div className="border-t border-border px-2 py-1.5">
				<div className="truncate text-[11.5px] text-text-2">{resource.name}</div>
				{resource.width && resource.height ? (
					<div className="text-[10.5px] tabular-nums text-text-3">
						{resource.width} × {resource.height}
					</div>
				) : null}
			</div>
		</button>
	);
};

const pickerTitle = (type: ResourcePickerType): string => {
	switch (type) {
		case "image":
			return "Pick an image";

		case "video":
			return "Pick a video";

		case "file":
		default:
			return "Pick a file";
	}
};

const matchesType = (resource: ResourceSummary, type: ResourcePickerType): boolean => {
	if (type === "image") {
		return Boolean(resource.is_image);
	}

	if (type === "video") {
		return Boolean(resource.is_video);
	}

	// "file" — anything that isn't a managed video. Images are valid files too,
	// matching the legacy resource browser's behavior.
	return !resource.is_video;
};

const matchesDims = (resource: ResourceSummary, minWidth: number, minHeight: number): boolean => {
	if (minWidth <= 0 && minHeight <= 0) {
		return true;
	}

	if (!resource.is_image) {
		return false;
	}

	const w = resource.width ?? 0;
	const h = resource.height ?? 0;

	return w >= minWidth && h >= minHeight;
};

const emptyMessage = (type: ResourcePickerType, minWidth: number, minHeight: number): string => {
	if (type === "image" && (minWidth > 0 || minHeight > 0)) {
		return `No images in this folder meet the minimum size (${minWidth} × ${minHeight}).`;
	}

	if (type === "image") {
		return "No images in this folder.";
	}

	if (type === "video") {
		return "No videos in this folder.";
	}

	return "No files in this folder.";
};

const iconForType = (type: ResourcePickerType, resource: ResourceSummary) => {
	if (type === "video" || resource.is_video) {
		return <Film size={20} />;
	}

	if (type === "image" || resource.is_image) {
		return <ImageIcon size={20} />;
	}

	return <FileIcon size={20} />;
};
