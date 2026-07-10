import { api, crudEndpoints } from "@/api/client";

import type { TemplateResource } from "@/api/endpoints/templates";

/**
 * Callouts catalog endpoints. The CalloutsField (and later the Developer
 * section's callout designer) consumes these to enumerate available callout
 * types and read each type's `resources` schema, which drives the per-row
 * sub-field rendering.
 *
 * A callout "resource" is keyed by `id` (legacy JSON storage), exactly like a
 * template resource — NOT by `column` like a module-form field. Consumers map
 * it to the ModuleFormField shape via `resourceToFormField` before rendering.
 */

export interface CalloutSummary {
	id: string;
	name: string;
	description: string;
	level: number;
	position: number;
	display_field: string;
	display_default: string;
	resources: TemplateResource[];
}

export interface CalloutGroup {
	id: string;
	name: string;
	callouts?: string[];
}

export interface CalloutEditBody {
	id?: string;
	name?: string;
	description?: string;
	level?: number;
	display_field?: string;
	display_default?: string;
	resources?: TemplateResource[];
}

export interface CalloutGroupEditBody {
	id?: string;
	name?: string;
	callouts?: string[];
}

/** The callout-groups catalog — the same quintet, exposed under `*Group` keys. */
const groups = crudEndpoints<CalloutGroup, CalloutGroupEditBody>("/callout-groups");

export const calloutsApi = {
	...crudEndpoints<CalloutSummary, CalloutEditBody>("/callouts"),

	reorder: (ids: string[]) => api.post<void>("/callouts/reorder", { ids }),

	listGroups: groups.list,

	getGroup: groups.get,

	createGroup: groups.create,

	updateGroup: groups.update,

	deleteGroup: groups.delete,
};
