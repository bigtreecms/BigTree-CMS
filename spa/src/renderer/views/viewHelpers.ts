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
	const builtins: BuiltinViewActionFlags = { edit: false, delete: false };
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

export const formatCellValue = (value: unknown): string => {
	if (value === null || value === undefined) {
		return "";
	}

	if (typeof value === "string" || typeof value === "number" || typeof value === "boolean") {
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
