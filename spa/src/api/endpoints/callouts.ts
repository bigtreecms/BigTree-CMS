import { api } from "@/api/client";

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

export const calloutsApi = {
	list: () => api.get<CalloutSummary[]>("/callouts"),

	get: (id: string) => api.get<CalloutSummary>(`/callouts/${encodeURIComponent(id)}`),

	create: (body: CalloutEditBody) => api.post<CalloutSummary>("/callouts", body),

	update: (id: string, body: CalloutEditBody) =>
		api.patch<CalloutSummary>(`/callouts/${encodeURIComponent(id)}`, body),

	delete: (id: string) => api.delete<void>(`/callouts/${encodeURIComponent(id)}`),

	reorder: (ids: string[]) => api.post<void>("/callouts/reorder", { ids }),

	listGroups: () => api.get<CalloutGroup[]>("/callout-groups"),

	getGroup: (id: string) => api.get<CalloutGroup>(`/callout-groups/${encodeURIComponent(id)}`),

	createGroup: (body: CalloutGroupEditBody) => api.post<CalloutGroup>("/callout-groups", body),

	updateGroup: (id: string, body: CalloutGroupEditBody) =>
		api.patch<CalloutGroup>(`/callout-groups/${encodeURIComponent(id)}`, body),

	deleteGroup: (id: string) => api.delete<void>(`/callout-groups/${encodeURIComponent(id)}`),
};
