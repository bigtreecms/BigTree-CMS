import { api, crudEndpoints } from "@/api/client";

import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Templates — the schema each Page slots into for its dynamic `resources`
 * fields. Listed in the Pages picker and looked up on PageEdit to render the
 * Content tab through FieldRenderer.
 *
 * Note: template `resources` use `id` as the field key (legacy JSON storage),
 * whereas module-form fields use `column`. We surface the legacy `id` here
 * verbatim and let PageEdit map `id → column` when handing them to
 * FieldRenderer.
 */

export interface TemplateResource {
	id: string;
	settings: Record<string, unknown>;
	subtitle: string;
	title: string;
	type: string;
}

export interface TemplateSummary {
	hooks?: unknown;
	id: string;
	level: number;
	module: string;
	name: string;
	position: number;
	resources: TemplateResource[];
	routed: boolean;
}

/**
 * Adapt a TemplateResource into the ModuleFormField shape that FieldRenderer
 * expects. The only structural difference is `id` ↔ `column`.
 */
export const resourceToFormField = (r: TemplateResource): ModuleFormField => ({
	column: r.id,
	title: r.title || r.id,
	subtitle: r.subtitle,
	type: r.type,
	settings: r.settings,
});

export interface TemplateEditBody {
	hooks?: unknown;
	id?: string;
	level?: number;
	module?: string;
	name?: string;
	resources?: TemplateResource[];
	routed?: boolean;
}

export const templatesApi = {
	...crudEndpoints<TemplateSummary, TemplateEditBody>("/templates"),

	reorder: (ids: string[]) => api.post<void>("/templates/reorder", { ids }),
};
