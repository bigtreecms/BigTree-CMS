import { Fragment, useState } from "react";
import { useQuery } from "@tanstack/react-query";

import { resourceFoldersApi, type ResourceFolderRow } from "@/api/endpoints/resource-folders";
import { queryKeys } from "@/lib/queryKeys";
import { applyPermission } from "@/lib/permissions";
import type { PermissionCode, UserPermissions } from "@/api/endpoints/users";

import { PermissionRadios } from "./PermissionRadios";
import { PermissionRow } from "./PermissionRow";
import { PermissionTreeHeader } from "./PermissionTreeHeader";
import { TreeExpander, TreeLoadingRow } from "./PermissionTreeParts";
import { RESOURCE_PERMISSION_OPTIONS } from "./permissionOptions";

interface ResourcePermissionsTreeProps {
	onChange: (next: UserPermissions["resources"]) => void;
	value: UserPermissions["resources"];
}

/**
 * Per-resource-folder permission tree. Root is the synthetic "Home Folder"
 * (id=0). Children are lazy-loaded via `GET /resource-folders?parent=X`.
 * Each row exposes Creator / Consumer / No Access / Inherit radios.
 */
export const ResourcePermissionsTree = ({ value, onChange }: ResourcePermissionsTreeProps) => {
	const setPerm = (id: string, perm: PermissionCode) => {
		onChange(applyPermission(value, id, perm));
	};

	return (
		<div>
			<PermissionTreeHeader columns="minmax(0,1fr) repeat(4, 80px)">
				<div>Folder</div>
				{RESOURCE_PERMISSION_OPTIONS.map((opt) => (
					<div className="text-center" key={opt.value}>
						{opt.label}
					</div>
				))}
			</PermissionTreeHeader>

			<div className="border-x border-b border-border">
				<FolderRow
					hideInheritForRoot
					initiallyExpanded
					depth={0}
					id={0}
					name="Home Folder"
					setPerm={setPerm}
					value={value}
				/>
			</div>
		</div>
	);
};

interface FolderRowProps {
	depth: number;
	/**
	 * Server-computed flag from `GET /resource-folders`. `false` collapses the
	 * row to a leaf-aligned spacer; `undefined` (synthetic root) keeps the
	 * expander since we never know the root's children up-front.
	 */
	hasChildren?: boolean;
	hideInheritForRoot?: boolean;
	id: number;
	initiallyExpanded?: boolean;
	name: string;
	setPerm: (id: string, perm: PermissionCode) => void;
	value: UserPermissions["resources"];
}

const FolderRow = ({
	id,
	name,
	depth,
	initiallyExpanded,
	value,
	setPerm,
	hideInheritForRoot,
	hasChildren,
}: FolderRowProps) => {
	const [expanded, setExpanded] = useState(!!initiallyExpanded);
	const idKey = String(id);
	const current = value?.[idKey] ?? "";

	const showExpander = hasChildren !== false;

	return (
		<div>
			<PermissionRow columns="minmax(0,1fr) repeat(4, 80px)" depth={depth}>
				<div className="flex items-center gap-1.5 min-w-0">
					<TreeExpander
						expanded={expanded}
						hasChildren={showExpander}
						onToggle={() => setExpanded((e) => !e)}
					/>

					<span className="truncate text-text">{name}</span>
				</div>

				<PermissionRadios
					name={`folder-perm-${id}`}
					options={
						hideInheritForRoot
							? RESOURCE_PERMISSION_OPTIONS.filter((o) => o.value !== "i")
							: RESOURCE_PERMISSION_OPTIONS
					}
					value={current}
					onChange={(next) => setPerm(idKey, next)}
				/>

				{hideInheritForRoot && <span aria-hidden="true" />}
			</PermissionRow>

			{showExpander && expanded && (
				<FolderChildren depth={depth + 1} parent={id} setPerm={setPerm} value={value} />
			)}
		</div>
	);
};

interface FolderChildrenProps {
	depth: number;
	parent: number;
	setPerm: (id: string, perm: PermissionCode) => void;
	value: UserPermissions["resources"];
}

const FolderChildren = ({ parent, depth, value, setPerm }: FolderChildrenProps) => {
	const { data, isLoading } = useQuery({
		queryKey: queryKeys.resourceFolders.subfolders(parent),
		queryFn: () => resourceFoldersApi.listSubfolders(parent),
	});

	if (isLoading) {
		return <TreeLoadingRow depth={depth} />;
	}

	const rows = data ?? [];

	if (rows.length === 0) {
		return null;
	}

	return (
		<Fragment>
			{rows.map((row: ResourceFolderRow) => (
				<FolderRow
					depth={depth}
					hasChildren={row.has_children}
					id={row.id}
					key={row.id}
					name={row.name}
					setPerm={setPerm}
					value={value}
				/>
			))}
		</Fragment>
	);
};
