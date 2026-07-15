import { api } from "@/api/client";

import type { ModuleFormField } from "./modules";

/**
 * Public embed-form endpoints. These are unauth — backed by a per-form hash so
 * the form can be embedded on any third-party page.
 *
 *   GET  /embed-forms/{hash}              — config for the renderer
 *   POST /embed-forms/{hash}/submit       — submit values, returns next-state
 *
 * The SPA's /embed/:hash route uses these to render a standalone embeddable
 * form outside the normal admin Shell.
 */

export interface EmbedFormConfig {
	css: string;
	default_pending: boolean;
	fields: ModuleFormField[];
	id: string;
	module: string;
	redirect_url: string;
	table: string;
	thank_you_message: string;
	title: string;
}

export interface EmbedFormSubmitRequest {
	values: Record<string, unknown>;
}

export interface EmbedFormSubmitResponse {
	id: number | string;
	redirect_url: string;
	status: "published" | "pending";
	thank_you_message: string;
}

export const embedFormsApi = {
	get: (hash: string) => api.get<EmbedFormConfig>(`/embed-forms/${encodeURIComponent(hash)}`),

	submit: (hash: string, body: EmbedFormSubmitRequest) =>
		api.post<EmbedFormSubmitResponse>(`/embed-forms/${encodeURIComponent(hash)}/submit`, body),
};
