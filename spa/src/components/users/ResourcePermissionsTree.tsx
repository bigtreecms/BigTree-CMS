import { Fragment, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight } from "lucide-react";

import { resourceFoldersApi, type ResourceFolderSummary } from "@/api/endpoints/resource-folders";
import type { PermissionCode, UserPermissions } from "@/api/endpoints/users";

import { PermissionRadios, RESOURCE_PERMISSION_OPTIONS } from "./PermissionRadios";

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
			<div
				className="grid items-center gap-2 rounded-t-md border border-border bg-surface-2 px-3 py-2 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3"
				style={{ gridTemplateColumns: "minmax(0,1fr) repeat(4, 80px)" }}
			>
				<div>Folder</div>
				{RESOURCE_PERMISSION_OPTIONS.map((opt) => (
					<div key={opt.value} className="text-center">
						{opt.label}
					</div>
				))}
			</div>

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
}

const FolderRow = ({
	id,
	name,
	depth,
	initiallyExpanded,
	value,
	setPerm,
	hideInheritForRoot,
}: FolderRowProps) => {
	const [expanded, setExpanded] = useState(!!initiallyExpanded);
	const idKey = String(id);
	const current = value?.[idKey] ?? "";

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
					<button
						type="button"
						onClick={() => setExpanded((e) => !e)}
						className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text"
						aria-label={expanded ? "Collapse" : "Expand"}
					>
						{expanded ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
					</button>

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

			{expanded && (
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
		queryKey: ["resource-folders", "list", parent],
		queryFn: () => resourceFoldersApi.list(parent),
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
			{rows.map((row: ResourceFolderSummary) => (
				<FolderRow
					key={row.id}
					id={row.id}
					name={row.name}
					depth={depth}
					value={value}
					setPerm={setPerm}
				/>
			))}
		</Fragment>
	);
};
