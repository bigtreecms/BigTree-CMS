import { api } from "@/api/client";

/**
 * Module shapes returned by ModuleService::present and ::listGroups. Enough
 * fields here to drive the module landing, designer, and per-user permission
 * tree. The full action/form/view config is fetched separately via the
 * `/modules/{id}/...` sub-resources when needed.
 */

export interface ModuleGbpConfig {
	enabled?: boolean;
	other_table?: string;
	title_field?: string;
	name?: string;
	item_parser?: string;
}

export interface ModuleSummary {
	id: number;
	name: string;
	group: number | null;
	group_name: string;
	class: string;
	table: string;
	gbp: ModuleGbpConfig;
	icon: string;
	route: string;
	position: number;
	graphql: boolean;
	graphql_type: string;
}

export interface ModuleGroup {
	id: number;
	name: string;
	route?: string;
	position?: number;
}

export const modulesApi = {
	list: () => api.get<ModuleSummary[]>("/modules"),

	get: (id: number) => api.get<ModuleSummary>(`/modules/${id}`),

	listGroups: () => api.get<ModuleGroup[]>("/module-groups"),
};
