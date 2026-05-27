import { api } from "@/api/client";

import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Callouts catalog endpoints. The CalloutsField (and later the Developer
 * section's callout designer) consumes these to enumerate available callout
 * types and read each type's `resources` schema, which drives the per-row
 * sub-field rendering.
 *
 * A "resource" in callout JSON-DB storage is structurally equivalent to a
 * `ModuleFormField` (column / type / settings), so we re-use that interface
 * rather than coining a parallel type.
 */

export interface CalloutSummary {
	id: string;
	name: string;
	description: string;
	level: number;
	position: number;
	display_field: string;
	display_default: string;
	resources: ModuleFormField[];
}

export interface CalloutGroup {
	id: string;
	name: string;
	callouts?: string[];
}

export const calloutsApi = {
	list: () => api.get<CalloutSummary[]>("/callouts"),

	get: (id: string) => api.get<CalloutSummary>(`/callouts/${encodeURIComponent(id)}`),

	listGroups: () => api.get<CalloutGroup[]>("/callout-groups"),

	getGroup: (id: string) => api.get<CalloutGroup>(`/callout-groups/${encodeURIComponent(id)}`),
};
