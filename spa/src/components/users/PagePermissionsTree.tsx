import { Fragment, useState } from "react";
import { useQuery } from "@tanstack/react-query";

import { pagesApi, type PageListRow } from "@/api/endpoints/pages";
import { queryKeys } from "@/lib/queryKeys";
import { applyPermission, toggleFlag } from "@/lib/permissions";
import type { PermissionCode, UserAlerts, UserPermissions } from "@/api/endpoints/users";

import { PermissionRadios } from "./PermissionRadios";
import { PermissionRow } from "./PermissionRow";
import { PermissionTreeHeader } from "./PermissionTreeHeader";
import { TreeExpander, TreeLoadingRow } from "./PermissionTreeParts";
import { PAGE_PERMISSION_OPTIONS } from "./permissionOptions";

interface PagePermissionsTreeProps {
	alerts: UserAlerts;
	/** True when user.level >= 1 — controls are hidden and the user is treated as having full publisher access. */
	isAdminUser: boolean;
	onAlertsChange: (next: UserAlerts) => void;
	onChange: (next: UserPermissions["page"]) => void;
	value: UserPermissions["page"];
}

/**
 * Per-page permission tree. Top row is the synthetic "All Pages" (id=0); the
 * rest are loaded lazily one parent at a time via `GET /pages?parent=X`. The
 * PHP admin grabs the entire tree up-front via a custom ajax endpoint we
 * don't have — per-level fetching is functionally equivalent and avoids
 * blocking the screen on enormous sites.
 */
export const PagePermissionsTree = ({
	value,
	alerts,
	onChange,
	onAlertsChange,
	isAdminUser,
}: PagePermissionsTreeProps) => {
	const setPagePerm = (id: string, perm: PermissionCode) => {
		onChange(applyPermission(value, id, perm));
	};

	const setAlert = (id: string, on: boolean) => {
		onAlertsChange(toggleFlag(alerts, id, on));
	};

	return (
		<div>
			<TreeHeader isAdminUser={isAdminUser} />

			<div className="border-x border-b border-border">
				<TreeRow
					hasChildren
					hideInheritForRoot
					initiallyExpanded
					alerts={alerts}
					depth={0}
					id={0}
					isAdminUser={isAdminUser}
					label="All Pages"
					setAlert={setAlert}
					setPagePerm={setPagePerm}
					value={value}
				/>
			</div>
		</div>
	);
};

interface TreeHeaderProps {
	isAdminUser: boolean;
}

const TreeHeader = ({ isAdminUser }: TreeHeaderProps) => {
	return (
		<PermissionTreeHeader
			columns={isAdminUser ? "minmax(0,1fr) 120px" : "minmax(0,1fr) 120px repeat(4, 80px)"}
		>
			<div>Page</div>
			<div className="text-center">Content alerts</div>

			{!isAdminUser &&
				PAGE_PERMISSION_OPTIONS.map((opt) => (
					<div className="text-center" key={opt.value}>
						{opt.label}
					</div>
				))}
		</PermissionTreeHeader>
	);
};

interface TreeRowProps {
	/** True when any ancestor has an alert subscribed — disables this row's checkbox. */
	alertInheritedFromAbove?: boolean;
	alerts: UserAlerts;
	depth: number;
	hasChildren: boolean;
	/** Hide the Inherit radio (true for the synthetic root row). */
	hideInheritForRoot?: boolean;
	id: number;
	initiallyExpanded?: boolean;
	isAdminUser: boolean;
	label: string;
	setAlert: (id: string, on: boolean) => void;
	setPagePerm: (id: string, perm: PermissionCode) => void;
	value: UserPermissions["page"];
}

const TreeRow = ({
	id,
	label,
	depth,
	hasChildren,
	initiallyExpanded,
	value,
	alerts,
	setPagePerm,
	setAlert,
	isAdminUser,
	hideInheritForRoot,
	alertInheritedFromAbove,
}: TreeRowProps) => {
	const [expanded, setExpanded] = useState(!!initiallyExpanded);

	const idKey = String(id);
	const currentPerm = value?.[idKey] ?? "";
	const alertOn = !!alerts?.[idKey] || !!alertInheritedFromAbove;

	const childAlertsInherited = alertInheritedFromAbove || alerts?.[idKey] === "on";

	return (
		<div>
			<PermissionRow
				columns={
					isAdminUser ? "minmax(0,1fr) 120px" : "minmax(0,1fr) 120px repeat(4, 80px)"
				}
				depth={depth}
			>
				<div className="flex items-center gap-1.5 min-w-0">
					<TreeExpander
						expanded={expanded}
						hasChildren={hasChildren}
						onToggle={() => setExpanded((e) => !e)}
					/>

					<span className="truncate text-text">{label}</span>
				</div>

				<label className="flex items-center justify-center">
					<input
						aria-label="Email alert on change"
						checked={alertOn}
						className="size-3.5 cursor-pointer accent-accent disabled:cursor-not-allowed disabled:opacity-50"
						disabled={!!alertInheritedFromAbove}
						type="checkbox"
						onChange={(e) => setAlert(idKey, e.target.checked)}
					/>
				</label>

				{!isAdminUser && (
					<PermissionRadios
						name={`page-perm-${id}`}
						options={
							hideInheritForRoot
								? PAGE_PERMISSION_OPTIONS.filter((o) => o.value !== "i")
								: PAGE_PERMISSION_OPTIONS
						}
						value={currentPerm}
						onChange={(next) => setPagePerm(idKey, next)}
					/>
				)}

				{/* Spacer so the row still has the inherit column when it's hidden on the root. */}
				{!isAdminUser && hideInheritForRoot && <span aria-hidden="true" />}
			</PermissionRow>

			{expanded && hasChildren && (
				<PageChildren
					alertInheritedFromAbove={childAlertsInherited}
					alerts={alerts}
					depth={depth + 1}
					isAdminUser={isAdminUser}
					parent={id}
					setAlert={setAlert}
					setPagePerm={setPagePerm}
					value={value}
				/>
			)}
		</div>
	);
};

interface PageChildrenProps {
	alertInheritedFromAbove: boolean;
	alerts: UserAlerts;
	depth: number;
	isAdminUser: boolean;
	parent: number;
	setAlert: (id: string, on: boolean) => void;
	setPagePerm: (id: string, perm: PermissionCode) => void;
	value: UserPermissions["page"];
}

const PageChildren = ({
	parent,
	depth,
	value,
	alerts,
	setPagePerm,
	setAlert,
	isAdminUser,
	alertInheritedFromAbove,
}: PageChildrenProps) => {
	const { data, isLoading } = useQuery({
		queryKey: queryKeys.pages.list(parent),
		queryFn: () => pagesApi.list(parent, false),
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
			{rows.map((row: PageListRow) => (
				<TreeRow
					alertInheritedFromAbove={alertInheritedFromAbove}
					alerts={alerts}
					depth={depth}
					hasChildren={row.has_children}
					id={row.id}
					isAdminUser={isAdminUser}
					key={row.id}
					label={row.nav_title}
					setAlert={setAlert}
					setPagePerm={setPagePerm}
					value={value}
				/>
			))}
		</Fragment>
	);
};
