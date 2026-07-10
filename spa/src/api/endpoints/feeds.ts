import { crudEndpoints } from "@/api/client";

import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Feeds — XML / JSON / RSS endpoints generated from a module's table. CRUD
 * lives in the Developer section; consumers fetch through the public-facing
 * /feeds/{route} URL which isn't part of this API surface.
 */

export interface FeedSummary {
	id: string;
	name: string;
	description: string;
	table: string;
	type: string;
	settings: Record<string, unknown> | unknown[];
	fields: ModuleFormField[];
}

export interface FeedEditBody {
	id?: string;
	name?: string;
	description?: string;
	table?: string;
	type?: string;
	settings?: Record<string, unknown> | unknown[];
	fields?: ModuleFormField[];
}

export const feedsApi = { ...crudEndpoints<FeedSummary, FeedEditBody>("/feeds") };
