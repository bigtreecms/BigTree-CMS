import type { SubNavItem } from "@/components/shell/SubNav";
import { iconFor } from "@/lib/legacyIcons";
import type { ModuleAction, ModuleSummary } from "@/api/endpoints/modules";

const enc = encodeURIComponent;

/**
 * Module routing mirrors the legacy admin: a module and its actions are addressed
 * by their human-readable `route` (URL slug), not their internal slug id. The URL
 * shape is `/modules/:moduleRoute/:actionRoute[/...commands]`, e.g.
 * `/modules/news/add` or `/modules/news/edit/5`. The action's relation
 * (custom module / view / report / form) decides what renders — resolved by the
 * ModuleDispatcher, not encoded in the URL.
 *
 * These helpers port BigTreeAdmin::getModuleByRoute / getModuleActionByRoute and
 * centralize every route-based link the SPA builds.
 */

export interface ResolvedAction {
	action: ModuleAction;
	/** Trailing path segments not part of the action route (e.g. an entry id). */
	commands: string[];
}

/**
 * Port of BigTreeAdmin::getModuleActionByRoute. Greedy longest-match: join the
 * path segments after the module route, try to match an action `route`; on a
 * miss, pop the last segment into `commands` and retry. With no segments we look
 * for the landing action (`route === ""`). Returns null when nothing matches.
 */
export const resolveActionByRoute = (
	actions: ModuleAction[],
	segments: string[]
): ResolvedAction | null => {
	const route = segments.length ? [...segments] : [""];
	const commands: string[] = [];

	while (route.length) {
		// The landing action stores its route as null/"" — normalize so the empty
		// segment matches it (legacy compares with loose ==, where null == "").
		const routeString = route.join("/");
		const action = actions.find((a) => (a.route ?? "") === routeString);

		if (action) {
			return { action, commands: commands.reverse() };
		}

		const last = route.pop();

		if (last !== undefined) {
			commands.push(last);
		}
	}

	return null;
};

/**
 * An action can run in the SPA if it draws its own UI (custom module action) or
 * relates to an auto-module form / view / report. Legacy custom-PHP actions
 * (render "server", no relation) have no native runtime and are skipped.
 */
export const isRunnableAction = (action: ModuleAction): boolean => {
	return (
		action.render === "module" ||
		Boolean(action.view) ||
		Boolean(action.report) ||
		Boolean(action.form)
	);
};

/** `/modules/:route` — a module's landing URL. */
export const modulePath = (module: Pick<ModuleSummary, "route">): string => {
	return `/modules/${enc(module.route)}`;
};

/**
 * `/modules/:route/:actionRoute[/...commands]`. The landing action has an empty
 * route, which collapses to the module path. Commands are appended in order
 * (e.g. the entry id for an edit link).
 */
export const moduleActionPath = (
	module: Pick<ModuleSummary, "route">,
	action: Pick<ModuleAction, "route">,
	...commands: Array<string | number>
): string => {
	const parts = [`/modules/${enc(module.route)}`];

	if (action.route) {
		parts.push(enc(action.route));
	}

	for (const command of commands) {
		parts.push(enc(String(command)));
	}

	return parts.join("/");
};

/**
 * Absolute URL of an action in the classic admin. Legacy custom-PHP actions
 * have no SPA runtime, so we send the user to the legacy admin to finish the
 * task there (until the action is ported to the action-module system).
 */
export const legacyActionUrl = (
	adminRoot: string,
	module: Pick<ModuleSummary, "route">,
	action: Pick<ModuleAction, "route">,
	...commands: Array<string | number>
): string => {
	const parts = [module.route, action.route ?? "", ...commands.map(String)]
		.filter((segment) => segment !== "")
		.map(enc);

	return adminRoot.replace(/\/+$/, "/") + parts.join("/") + "/";
};

/** Legacy stores the nav toggle as boolean `true` or the PHP string "on"/"1". */
const isInNav = (value: ModuleAction["in_nav"]): boolean => {
	return value === true || value === "on" || value === "1";
};

/**
 * Build the sub-nav items for a module: keep only actions flagged for the nav
 * and within the user's level, then map each to its route-based URL + icon.
 * Preserves the order the actions endpoint returns (position DESC).
 *
 * Actions that can't run in the SPA (legacy custom-PHP pages) become external
 * links into the classic admin when `adminRoot` is known; without it they're
 * omitted, matching the previous behavior.
 */
export const visibleModuleActions = (
	module: Pick<ModuleSummary, "route">,
	actions: ModuleAction[],
	userLevel: number,
	adminRoot?: string
): SubNavItem[] => {
	const items: SubNavItem[] = [];

	for (const action of actions) {
		if (!isInNav(action.in_nav) || (action.level ?? 0) > userLevel) {
			continue;
		}

		if (!isRunnableAction(action)) {
			if (adminRoot) {
				items.push({
					label: action.name,
					to: legacyActionUrl(adminRoot, module, action),
					icon: iconFor(action.class),
					external: true,
				});
			}

			continue;
		}

		items.push({
			label: action.name,
			to: moduleActionPath(module, action),
			icon: iconFor(action.class),
		});
	}

	return items;
};
