import { crudEndpoints } from "@/api/client";

import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Feeds — XML / JSON / RSS endpoints generated from a module's table. CRUD
 * lives in the Developer section; consumers fetch through the public-facing
 * /feeds/{route} URL which isn't part of this API surface.
 */

export interface FeedSummary {
	description: string;
	fields: ModuleFormField[];
	id: string;
	name: string;
	settings: Record<string, unknown> | unknown[];
	table: string;
	type: string;
}

export interface FeedEditBody {
	description?: string;
	fields?: ModuleFormField[];
	id?: string;
	name?: string;
	settings?: Record<string, unknown> | unknown[];
	table?: string;
	type?: string;
}

export const feedsApi = { ...crudEndpoints<FeedSummary, FeedEditBody>("/feeds") };
