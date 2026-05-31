import type { SubNavItem } from "@/components/shell/SubNav";
import { iconFor } from "@/lib/legacyIcons";
import type { ModuleAction } from "@/api/endpoints/modules";

const enc = encodeURIComponent;

/**
 * Resolve a module action to the SPA route that runs it. Mirrors the legacy
 * router, which dispatches a module action by its `view` / `report` / `form`
 * target:
 *   - view   → /modules/:id/view/:viewId
 *   - report → /modules/:id/report/:reportId
 *   - form   → /modules/:id/view/_/add   (form-only actions; the renderer reads
 *              the form from the view fallback chain — in practice the admin
 *              always pairs a form with a view)
 * Returns null for actions with no runnable target (e.g. a custom action whose
 * handler lives only in legacy PHP).
 */
export const moduleActionTarget = (moduleId: string, action: ModuleAction): string | null => {
	if (action.view) {
		return `/modules/${enc(moduleId)}/view/${enc(action.view)}`;
	}

	if (action.report) {
		return `/modules/${enc(moduleId)}/report/${enc(action.report)}`;
	}

	if (action.form) {
		return `/modules/${enc(moduleId)}/view/_/add`;
	}

	return null;
};

/** Legacy stores the nav toggle as boolean `true` or the PHP string "on"/"1". */
const isInNav = (value: ModuleAction["in_nav"]): boolean => {
	return value === true || value === "on" || value === "1";
};

/**
 * Build the sub-nav items for a module: keep only actions flagged for the nav
 * and within the user's level, then map each to its runnable route + icon.
 * Preserves the order the actions endpoint returns (position DESC).
 */
export const visibleModuleActions = (
	moduleId: string,
	actions: ModuleAction[],
	userLevel: number
): SubNavItem[] => {
	const items: SubNavItem[] = [];

	for (const action of actions) {
		if (!isInNav(action.in_nav) || (action.level ?? 0) > userLevel) {
			continue;
		}

		const to = moduleActionTarget(moduleId, action);

		if (!to) {
			continue;
		}

		items.push({
			label: action.name,
			to,
			icon: iconFor(action.class),
		});
	}

	return items;
};
