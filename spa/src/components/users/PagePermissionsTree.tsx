import { Fragment, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight } from "lucide-react";

import { IconButton } from "@/components/ui/IconButton";
import { pagesApi, type PageListRow } from "@/api/endpoints/pages";
import { queryKeys } from "@/lib/queryKeys";
import type { PermissionCode, UserAlerts, UserPermissions } from "@/api/endpoints/users";

import { PermissionRadios } from "./PermissionRadios";
import { PermissionTreeHeader } from "./PermissionTreeHeader";
import { PAGE_PERMISSION_OPTIONS } from "./permissionOptions";

interface PagePermissionsTreeProps {
	value: UserPermissions["page"];
	alerts: UserAlerts;
	onChange: (next: UserPermissions["page"]) => void;
	onAlertsChange: (next: UserAlerts) => void;
	/** True when user.level >= 1 — controls are hidden and the user is treated as having full publisher access. */
	isAdminUser: boolean;
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
		const next = { ...(value ?? {}) };

		if (perm === "" || perm === "i") {
			delete next[id];
		} else {
			next[id] = perm;
		}

		onChange(next);
	};

	const setAlert = (id: string, on: boolean) => {
		const next = { ...alerts };

		if (on) {
			next[id] = "on";
		} else {
			delete next[id];
		}

		onAlertsChange(next);
	};

	return (
		<div>
			<TreeHeader isAdminUser={isAdminUser} />

			<div className="border-x border-b border-border">
				<TreeRow
					id={0}
					label="All Pages"
					depth={0}
					hasChildren
					initiallyExpanded
					value={value}
					alerts={alerts}
					setPagePerm={setPagePerm}
					setAlert={setAlert}
					isAdminUser={isAdminUser}
					hideInheritForRoot
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
					<div key={opt.value} className="text-center">
						{opt.label}
					</div>
				))}
		</PermissionTreeHeader>
	);
};

interface TreeRowProps {
	id: number;
	label: string;
	depth: number;
	hasChildren: boolean;
	initiallyExpanded?: boolean;
	value: UserPermissions["page"];
	alerts: UserAlerts;
	setPagePerm: (id: string, perm: PermissionCode) => void;
	setAlert: (id: string, on: boolean) => void;
	isAdminUser: boolean;
	/** Hide the Inherit radio (true for the synthetic root row). */
	hideInheritForRoot?: boolean;
	/** True when any ancestor has an alert subscribed — disables this row's checkbox. */
	alertInheritedFromAbove?: boolean;
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
			<div
				className="grid items-center gap-2 border-t border-border bg-surface px-3 py-1.5 text-[12.5px] first:border-t-0"
				style={{
					gridTemplateColumns: isAdminUser
						? "minmax(0,1fr) 120px"
						: "minmax(0,1fr) 120px repeat(4, 80px)",
					paddingLeft: `${12 + depth * 16}px`,
				}}
			>
				<div className="flex items-center gap-1.5 min-w-0">
					{hasChildren ? (
						<IconButton
							label={expanded ? "Collapse" : "Expand"}
							size="sm"
							onClick={() => setExpanded((e) => !e)}
							ariaExpanded={expanded}
						>
							{expanded ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
						</IconButton>
					) : (
						<span className="inline-block w-[18px]" aria-hidden="true" />
					)}

					<span className="truncate text-text">{label}</span>
				</div>

				<label className="flex items-center justify-center">
					<input
						type="checkbox"
						checked={alertOn}
						disabled={!!alertInheritedFromAbove}
						aria-label="Email alert on change"
						onChange={(e) => setAlert(idKey, e.target.checked)}
						className="size-3.5 cursor-pointer accent-accent disabled:cursor-not-allowed disabled:opacity-50"
					/>
				</label>

				{!isAdminUser && (
					<PermissionRadios
						name={`page-perm-${id}`}
						value={currentPerm}
						onChange={(next) => setPagePerm(idKey, next)}
						options={
							hideInheritForRoot
								? PAGE_PERMISSION_OPTIONS.filter((o) => o.value !== "i")
								: PAGE_PERMISSION_OPTIONS
						}
					/>
				)}

				{/* Spacer so the row still has the inherit column when it's hidden on the root. */}
				{!isAdminUser && hideInheritForRoot && <span aria-hidden="true" />}
			</div>

			{expanded && hasChildren && (
				<PageChildren
					parent={id}
					depth={depth + 1}
					value={value}
					alerts={alerts}
					setPagePerm={setPagePerm}
					setAlert={setAlert}
					isAdminUser={isAdminUser}
					alertInheritedFromAbove={childAlertsInherited}
				/>
			)}
		</div>
	);
};

interface PageChildrenProps {
	parent: number;
	depth: number;
	value: UserPermissions["page"];
	alerts: UserAlerts;
	setPagePerm: (id: string, perm: PermissionCode) => void;
	setAlert: (id: string, on: boolean) => void;
	isAdminUser: boolean;
	alertInheritedFromAbove: boolean;
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
			{rows.map((row: PageListRow) => (
				<TreeRow
					key={row.id}
					id={row.id}
					label={row.nav_title}
					depth={depth}
					hasChildren={row.has_children}
					value={value}
					alerts={alerts}
					setPagePerm={setPagePerm}
					setAlert={setAlert}
					isAdminUser={isAdminUser}
					alertInheritedFromAbove={alertInheritedFromAbove}
				/>
			))}
		</Fragment>
	);
};
