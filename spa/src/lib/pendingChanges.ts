import type { PendingChange } from "@/api/endpoints/dashboard";

/** A bucket of pending changes sharing a module/table category. */
export interface PendingChangeGroup {
	key: string;
	label: string;
	table: string;
	module: string | null;
	changes: PendingChange[];
}

/**
 * Group pending changes by their owning module (or table, for non-module
 * content like pages) so we can show one section per category. Mirrors the
 * legacy `dashboard/pending-changes` split of pages vs. per-module groups.
 */
export const groupPendingByCategory = (pending: PendingChange[]): PendingChangeGroup[] => {
	const map = new Map<string, PendingChangeGroup>();

	for (const p of pending) {
		const key = p.module ? `module:${p.module}` : `table:${p.table}`;
		const cur = map.get(key);

		if (cur) {
			cur.changes.push(p);
		} else {
			map.set(key, {
				key,
				label: humanizeTable(p.table),
				table: p.table,
				module: p.module,
				changes: [p],
			});
		}
	}

	return [...map.values()].sort((a, b) => b.changes.length - a.changes.length);
};

/** Turn a raw table name (e.g. `bigtree_pages`) into a display label. */
export const humanizeTable = (table: string): string => {
	if (table === "bigtree_pages") {
		return "Pages";
	}

	const base = table.startsWith("bigtree_") ? table.slice("bigtree_".length) : table;

	return base.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
};

/** Whether a change targets a page (page approvals route through the detail view). */
export const isPageChange = (change: PendingChange): boolean => change.table === "bigtree_pages";
