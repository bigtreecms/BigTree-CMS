import { Fragment, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight } from "lucide-react";

import { resourceFoldersApi, type ResourceFolderRow } from "@/api/endpoints/resource-folders";
import type { PermissionCode, UserPermissions } from "@/api/endpoints/users";

import { PermissionRadios } from "./PermissionRadios";
import { PermissionTreeHeader } from "./PermissionTreeHeader";
import { RESOURCE_PERMISSION_OPTIONS } from "./permissionOptions";

interface ResourcePermissionsTreeProps {
	value: UserPermissions["resources"];
	onChange: (next: UserPermissions["resources"]) => void;
}

/**
 * Per-resource-folder permission tree. Root is the synthetic "Home Folder"
 * (id=0). Children are lazy-loaded via `GET /resource-folders?parent=X`.
 * Each row exposes Creator / Consumer / No Access / Inherit radios.
 */
export const ResourcePermissionsTree = ({ value, onChange }: ResourcePermissionsTreeProps) => {
	const setPerm = (id: string, perm: PermissionCode) => {
		const next = { ...(value ?? {}) };

		if (perm === "" || perm === "i") {
			delete next[id];
		} else {
			next[id] = perm;
		}

		onChange(next);
	};

	return (
		<div>
			<PermissionTreeHeader columns="minmax(0,1fr) repeat(4, 80px)">
				<div>Folder</div>
				{RESOURCE_PERMISSION_OPTIONS.map((opt) => (
					<div key={opt.value} className="text-center">
						{opt.label}
					</div>
				))}
			</PermissionTreeHeader>

			<div className="border-x border-b border-border">
				<FolderRow
					id={0}
					name="Home Folder"
					depth={0}
					initiallyExpanded
					value={value}
					setPerm={setPerm}
					hideInheritForRoot
				/>
			</div>
		</div>
	);
};

interface FolderRowProps {
	id: number;
	name: string;
	depth: number;
	initiallyExpanded?: boolean;
	value: UserPermissions["resources"];
	setPerm: (id: string, perm: PermissionCode) => void;
	hideInheritForRoot?: boolean;
	/**
	 * Server-computed flag from `GET /resource-folders`. `false` collapses the
	 * row to a leaf-aligned spacer; `undefined` (synthetic root) keeps the
	 * expander since we never know the root's children up-front.
	 */
	hasChildren?: boolean;
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
			<div
				className="grid items-center gap-2 border-t border-border bg-surface px-3 py-1.5 text-[12.5px] first:border-t-0"
				style={{
					gridTemplateColumns: "minmax(0,1fr) repeat(4, 80px)",
					paddingLeft: `${12 + depth * 16}px`,
				}}
			>
				<div className="flex items-center gap-1.5 min-w-0">
					{showExpander ? (
						<button
							type="button"
							onClick={() => setExpanded((e) => !e)}
							className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text"
							aria-label={expanded ? "Collapse" : "Expand"}
						>
							{expanded ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
						</button>
					) : (
						<span className="inline-block w-[18px]" aria-hidden="true" />
					)}

					<span className="truncate text-text">{name}</span>
				</div>

				<PermissionRadios
					name={`folder-perm-${id}`}
					value={current}
					onChange={(next) => setPerm(idKey, next)}
					options={
						hideInheritForRoot
							? RESOURCE_PERMISSION_OPTIONS.filter((o) => o.value !== "i")
							: RESOURCE_PERMISSION_OPTIONS
					}
				/>

				{hideInheritForRoot && <span aria-hidden="true" />}
			</div>

			{showExpander && expanded && (
				<FolderChildren parent={id} depth={depth + 1} value={value} setPerm={setPerm} />
			)}
		</div>
	);
};

interface FolderChildrenProps {
	parent: number;
	depth: number;
	value: UserPermissions["resources"];
	setPerm: (id: string, perm: PermissionCode) => void;
}

const FolderChildren = ({ parent, depth, value, setPerm }: FolderChildrenProps) => {
	const { data, isLoading } = useQuery({
		queryKey: ["resource-folders", "subfolders", parent],
		queryFn: () => resourceFoldersApi.listSubfolders(parent),
	});

	if (isLoading) {
		return (
			<div
				className="border-t border-border bg-surface px-3 py-2 text-[12px] text-text-3"
				style={{ paddingLeft: `${12 + depth * 16}px` }}
			>
				Loading…
			</div>
		);
	}

	const rows = data ?? [];

	if (rows.length === 0) {
		return null;
	}

	return (
		<Fragment>
			{rows.map((row: ResourceFolderRow) => (
				<FolderRow
					key={row.id}
					id={row.id}
					name={row.name}
					depth={depth}
					value={value}
					setPerm={setPerm}
					hasChildren={row.has_children}
				/>
			))}
		</Fragment>
	);
};
