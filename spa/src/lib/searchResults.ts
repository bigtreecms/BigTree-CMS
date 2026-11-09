import type { SearchModuleEntryGroup, SearchResultGroups } from "@/api/endpoints/search";
import { decodeHtmlEntitiesDom } from "@/lib/html";
import { moduleEntryEditPath } from "@/lib/routes";

/**
 * Shared handling of GET /search payloads, for the quick-search overlay and the
 * full results page.
 */

export const GROUP_ORDER = ["pages", "modules", "entries", "settings", "tags", "users"] as const;
/** Result groups only Administrators may navigate to (Settings / Tags / Users are level 1). */
export const ADMIN_ONLY_GROUPS = new Set<string>(["settings", "tags", "users"]);
export const GROUP_LABELS: Record<string, string> = {
	pages: "Pages",
	modules: "Modules",
	entries: "Module entries",
	settings: "Settings",
	tags: "Tags",
	users: "Users",
};

/**
 * Coerce the AI/classic payload into a safe { group: array } map.
 * Empty PHP arrays encode as JSON `[]`, which is truthy but not keyed — that used
 * to crash `for…of` / `.map` when the SPA treated it as a result object.
 */
export const normalizeGroups = (raw: unknown): SearchResultGroups | undefined => {
	if (raw == null) {
		return undefined;
	}

	// JSON `[]` from an empty PHP list
	if (Array.isArray(raw)) {
		return {};
	}

	if (typeof raw !== "object") {
		return {};
	}

	const src = raw as Record<string, unknown>;
	const out: SearchResultGroups = {};

	for (const key of GROUP_ORDER) {
		const val = src[key];

		if (Array.isArray(val)) {
			out[key] = val as never;
		}
	}

	return out;
};

export const asList = (value: unknown): unknown[] => (Array.isArray(value) ? value : []);

/**
 * Titles from the search/view cache often pass through htmlspecialchars (and
 * sometimes twice). Decode so text nodes show "Tom & Jerry", not "Tom &amp; Jerry".
 */
export const displayText = (value: unknown): string => {
	if (value == null) {
		return "";
	}

	const raw = String(value);

	if (raw.trim() === "") {
		return "";
	}

	return decodeHtmlEntitiesDom(raw, 5);
};

/** Best human label from a module-view cache row (column2 is often the title). */
export const entryItemLabel = (item: Record<string, unknown>): string => {
	for (const key of ["column2", "column1", "title", "name", "id"]) {
		const v = item[key];
		const label = displayText(v);

		if (label !== "") {
			return label;
		}
	}

	const first = Object.values(item).find((v) => displayText(v) !== "");

	return first != null ? displayText(first) : "Entry";
};

/**
 * Flatten entry groups into one row per item so each result can deep-link to
 * `/modules/{route}/edit/{id}` instead of dumping the user on /modules.
 */
export const flattenEntryRows = (
	groups: SearchResultGroups | undefined
): Array<{
	module: SearchModuleEntryGroup["module"];
	item: Record<string, unknown>;
	entryId: string | number;
	path: string;
}> => {
	const out: Array<{
		module: SearchModuleEntryGroup["module"];
		item: Record<string, unknown>;
		entryId: string | number;
		path: string;
	}> = [];

	for (const group of asList(groups?.entries) as SearchModuleEntryGroup[]) {
		const route = group?.module?.route;

		if (!route) {
			continue;
		}

		for (const item of asList(group.items) as Record<string, unknown>[]) {
			const entryId = (item.id as string | number | undefined) ?? "";

			if (entryId === "" || entryId == null) {
				continue;
			}

			out.push({
				module: group.module,
				item,
				entryId,
				path: moduleEntryEditPath(route, "edit", entryId),
			});
		}
	}

	return out;
};
