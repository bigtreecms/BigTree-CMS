import {
	Archive,
	ArrowRight,
	Check,
	Download,
	Eye,
	Star,
	type LucideIcon,
} from "lucide-react";

import type { DataTableSort } from "@/components/ui/DataTable";
import type { ModuleView, ModuleViewFieldConfig } from "@/api/endpoints/modules";

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

	// Treat the legacy pixel width as a minimum and a proportional weight so
	// columns grow to fill the table when the configured widths total less
	// than the available space. Without the `fr` max, the grid stops at the
	// summed px widths and the trailing Actions column floats in the middle.
	return `minmax(${px}px, ${px}fr)`;
};

// Legacy admin double-encodes by design: BigTree::safeEncode runs once at form
// save (source table) and again when the view cache is rebuilt, so a literal
// `Tom & Jerry` lands in the cache as `Tom &amp;amp; Jerry`. We unwind in a
// loop until the textarea round-trip stops changing the string (capped to
// avoid pathological input). The 5-iteration cap is well above the 2 passes
// the codebase actually produces.
let decodeEl: HTMLTextAreaElement | null = null;

export const decodeHTMLEntities = (value: string): string => {
	if (!value || value.indexOf("&") === -1) {
		return value;
	}

	if (!decodeEl) {
		decodeEl = document.createElement("textarea");
	}

	let current = value;

	for (let i = 0; i < 5; i++) {
		decodeEl.innerHTML = current;
		const next = decodeEl.value;

		if (next === current) {
			break;
		}

		current = next;
	}

	return current;
};

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
		return value.length === 0 ? "" : `${value.length} item${value.length === 1 ? "" : "s"}`;
	}

	try {
		return JSON.stringify(value);
	} catch {
		return "";
	}
};
