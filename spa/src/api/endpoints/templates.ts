import { api } from "@/api/client";

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
	type: string;
	title: string;
	subtitle: string;
	settings: Record<string, unknown>;
}

export interface TemplateSummary {
	id: string;
	name: string;
	module: string;
	level: number;
	routed: boolean;
	position: number;
	resources: TemplateResource[];
	hooks?: unknown;
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

export const templatesApi = {
	list: () => api.get<TemplateSummary[]>("/templates"),
	get: (id: string) => api.get<TemplateSummary>(`/templates/${encodeURIComponent(id)}`),
};
