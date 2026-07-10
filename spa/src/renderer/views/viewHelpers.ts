import { Archive, ArrowRight, Check, Download, Eye, Star, type LucideIcon } from "lucide-react";

import type { DataTableSort } from "@/components/ui/DataTable";
import type { ModuleView, ModuleViewFieldConfig } from "@/api/endpoints/modules";
import { pluralize } from "@/lib/number";
import { decodeHtmlEntitiesDom } from "@/lib/html";

/**
 * Shared utilities used by every view-type subcomponent. Pulled out of
 * SearchableView so DraggableView / GroupedView / ImagesView don't each
 * reinvent the column-config and action-parsing logic.
 */

export interface CustomViewAction {
	key: string;
	name: string;
	route: string;
	className?: string;
}

export interface BuiltinViewActionFlags {
	edit: boolean;
	delete: boolean;
	archive: boolean;
	approve: boolean;
	feature: boolean;
}

export interface ParsedViewActions {
	builtins: BuiltinViewActionFlags;
	custom: CustomViewAction[];
}

/**
 * Parse the view's `actions` dict. Built-in toggles store the literal "on";
 * custom actions store a JSON-encoded string with { name, class, route }.
 */
export const parseViewActions = (
	actions: Record<string, string> | undefined
): ParsedViewActions => {
	const builtins: BuiltinViewActionFlags = {
		edit: false,
		delete: false,
		archive: false,
		approve: false,
		feature: false,
	};
	const custom: CustomViewAction[] = [];

	if (!actions) {
		return { builtins, custom };
	}

	for (const [key, raw] of Object.entries(actions)) {
		if (raw === "on") {
			if (key === "edit") {
				builtins.edit = true;
			} else if (key === "delete") {
				builtins.delete = true;
			} else if (key === "archive") {
				builtins.archive = true;
			} else if (key === "approve") {
				builtins.approve = true;
			} else if (key === "feature") {
				builtins.feature = true;
			}

			continue;
		}

		if (typeof raw === "string" && raw.startsWith("{")) {
			try {
				const parsed = JSON.parse(raw) as {
					name?: string;
					route?: string;
					class?: string;
				};

				if (parsed.route) {
					custom.push({
						key,
						name: parsed.name ?? key,
						route: parsed.route,
						className: parsed.class,
					});
				}
			} catch {
				// Malformed action JSON — ignore.
			}
		}
	}

	return { builtins, custom };
};

/**
 * Entry status — derived from the cached `status` column the view-cache writes
 * for every row (see BigTreeAutoModule::cacheRecord in auto-modules.php). The
 * legacy admin renders the same four states in its "Status" view column:
 *
 *   l → Published   c → Changed   p → Pending   i → Inactive
 *
 * "Changed" is a published row that has unpublished edits waiting; "Pending" is
 * a brand-new row that has never been published; "Inactive" is archived or
 * not-yet-approved.
 */
export type StatusKey = "published" | "pending" | "changed" | "inactive";

export interface ViewStatus {
	label: string;
	key: StatusKey;
}

export const statusFromRow = (row: Record<string, unknown>): ViewStatus => {
	const raw = typeof row.status === "string" ? row.status : "";

	if (raw === "p") {
		return { label: "Pending", key: "pending" };
	}

	if (raw === "c") {
		return { label: "Changed", key: "changed" };
	}

	if (raw === "i") {
		return { label: "Inactive", key: "inactive" };
	}

	return { label: "Published", key: "published" };
};

// The legacy admin tints pending/changed rows and dims their non-status content
// to 50% opacity — the version shown in the list isn't the live one. Inactive
// and published rows render normally. We honor only these two states (matching
// the legacy `li.pending` CSS rule, which `c` and `p` both map to).
export const statusIsMuted = (key: StatusKey): boolean => key === "pending" || key === "changed";

// Background tint applied to the whole row for muted statuses.
export const statusRowClass = (key: StatusKey): string => (statusIsMuted(key) ? "bg-warn-bg" : "");

// Opacity applied to a row's non-status content (field columns + actions) for
// muted statuses. The status label itself must NOT receive this class.
export const statusDimClass = (key: StatusKey): string => (statusIsMuted(key) ? "opacity-60" : "");

// Whether an id refers to a persisted entry — either a positive integer (a
// published row) or a "p"-prefixed pending id (e.g. "p5", a brand-new entry that
// only exists in bigtree_pending_changes). Both can be opened in the edit form
// and deleted (deleting a pending id rejects the change). archive/approve/
// feature still require a real int id (they act on a live row).
export const isPersistedEntryId = (id: unknown): boolean => {
	if (typeof id === "number") {
		return Number.isFinite(id) && id > 0;
	}

	if (typeof id === "string") {
		return /^p\d+$/.test(id) || (/^\d+$/.test(id) && Number(id) > 0);
	}

	return false;
};

// The numeric form of an entry id, or null for pending ("p"-prefixed) ids that
// have no live row yet. Used where only a real row id makes sense (MTM relation
// lookups, archive/approve/feature).
export const numericEntryId = (id: unknown): number | null => {
	if (typeof id === "number") {
		return Number.isFinite(id) && id > 0 ? id : null;
	}

	if (typeof id === "string" && /^\d+$/.test(id) && Number(id) > 0) {
		return Number(id);
	}

	return null;
};

export const parseSortSetting = (view: ModuleView): DataTableSort | undefined => {
	const col = view.settings?.sort_column;

	if (!col) {
		return undefined;
	}

	const dir = (view.settings?.sort_direction ?? "ASC").toString().toUpperCase();

	return { key: col, dir: dir === "DESC" ? "desc" : "asc" };
};

// Map legacy admin CSS classes (e.g. `icon_view`, `icon_export`) onto Lucide
// icons. Custom view actions store the class string in `class`; without this
// the action renders as the first two letters of its name (e.g. "Re" for
// "Report"). ArrowRight is the generic fallback when nothing matches.
const customActionIcons: Record<string, LucideIcon> = {
	icon_view: Eye,
	icon_export: Download,
	icon_preview: Eye,
	icon_approve: Check,
	icon_archive: Archive,
	icon_feature: Star,
	icon_download: Download,
};

export const iconForCustomAction = (className: string | undefined): LucideIcon => {
	if (!className) {
		return ArrowRight;
	}

	for (const token of className.split(/\s+/)) {
		const match = customActionIcons[token];

		if (match) {
			return match;
		}
	}

	return ArrowRight;
};

export const formatSortParam = (sort: DataTableSort | undefined): string | undefined => {
	if (!sort) {
		return undefined;
	}

	return `${sort.key} ${sort.dir.toUpperCase()}`;
};

export const columnWidth = (field: ModuleViewFieldConfig): string => {
	const raw = field.width;

	if (raw === undefined || raw === null || raw === "" || raw === "0") {
		return "minmax(0,1fr)";
	}

	const px = typeof raw === "number" ? raw : Number.parseInt(raw, 10);

	if (!Number.isFinite(px) || px <= 0) {
		return "minmax(0,1fr)";
	}

	// Use the legacy pixel width purely as a proportional weight (the `fr` max),
	// with a `0` minimum so columns can shrink to fit narrower viewports. A hard
	// `${px}px` minimum used to force the grid wider than the container at
	// constrained desktop widths (e.g. iPad landscape ~1024px), which clipped the
	// trailing Actions column under the card's `overflow-hidden`. Keeping the `fr`
	// weight preserves the "grow to fill, in proportion" behaviour on wide
	// screens; the `0` min just lets cells truncate instead of overflowing.
	return `minmax(0, ${px}fr)`;
};

// Legacy admin double-encodes by design: BigTree::safeEncode runs once at form
// save (source table) and again when the view cache is rebuilt, so a literal
// `Tom & Jerry` lands in the cache as `Tom &amp;amp; Jerry`. We unwind with the
// shared multi-pass textarea decoder (capped at 5, well above the 2 passes the
// cache actually produces).
export const decodeHTMLEntities = (value: string): string => decodeHtmlEntitiesDom(value, 5);

export const formatCellValue = (value: unknown): string => {
	if (value === null || value === undefined) {
		return "";
	}

	if (typeof value === "string") {
		return decodeHTMLEntities(value);
	}

	if (typeof value === "number" || typeof value === "boolean") {
		return String(value);
	}

	if (Array.isArray(value)) {
		return value.length === 0 ? "" : pluralize(value.length, "item");
	}

	try {
		return JSON.stringify(value);
	} catch {
		return "";
	}
};

/**
 * The empty-list message shared by every module list view (and `DataTable`'s
 * `emptyLabel`): a "no matches" line while a search is active, otherwise the
 * "nothing here yet" line. `noun` defaults to "entries".
 */
export const viewEmptyLabel = (query: string, noun = "entries"): string =>
	query ? `No ${noun} match “${query}”.` : `No ${noun} yet.`;
