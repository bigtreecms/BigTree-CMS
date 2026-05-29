import { api } from "@/api/client";

/**
 * Field types — both built-ins (read-only, served from the core JSON DB) and
 * custom user-defined types (full CRUD via JSONDB). The list endpoint serves
 * the cached registry as a single object keyed by type id; passing ?split=1
 * splits it into `{ default, custom }` for the UI's "built-in vs custom" view.
 */

export type FieldUseCase = "templates" | "modules" | "settings" | "callouts" | "feeds";

export interface FieldType {
	id: string;
	name: string;
	use_cases?: FieldUseCase[] | string[];
	self_draw?: boolean | string;
	extension?: string;
	/** Some entries carry their own draw/process/settings php paths — kept loose. */
	[key: string]: unknown;
}

export type FieldTypeRegistry = Record<string, FieldType>;

export interface FieldTypeRegistrySplit {
	default: FieldTypeRegistry;
	custom: FieldTypeRegistry;
}

export interface FieldTypeCreateBody {
	id: string;
	name?: string;
	use_cases?: string[];
	self_draw?: boolean;
}

export const fieldTypesApi = {
	list: () => api.get<FieldTypeRegistry>("/field-types"),

	listSplit: () => api.get<FieldTypeRegistrySplit>("/field-types", { query: { split: true } }),

	get: (id: string) => api.get<FieldType>(`/field-types/${encodeURIComponent(id)}`),

	create: (body: FieldTypeCreateBody) => api.post<FieldType>("/field-types", body),

	update: (id: string, body: Partial<FieldTypeCreateBody>) =>
		api.patch<FieldType>(`/field-types/${encodeURIComponent(id)}`, body),

	delete: (id: string) => api.delete<void>(`/field-types/${encodeURIComponent(id)}`),
};
