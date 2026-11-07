import { api } from "@/api/client";

import type { ModuleFormField } from "./modules";

/**
 * Public embed-form endpoints. These are unauth — backed by a per-form hash so
 * the form can be embedded on any third-party page.
 *
 *   GET  /embed-forms/{hash}              — config + bot-check challenge
 *   POST /embed-forms/{hash}/images       — image field upload (multipart)
 *   POST /embed-forms/{hash}/images/crop  — finalize one manual crop
 *   POST /embed-forms/{hash}/files        — upload field upload (multipart)
 *   POST /embed-forms/{hash}/submit       — submit values, returns next-state
 *
 * Uploads return a claim token per stored file; the submission sends them back
 * in `upload_tokens` so the server only accepts files this form stored.
 *
 * The SPA's /embed/:hash route uses these to render a standalone embeddable
 * form outside the normal admin Shell.
 */

export interface EmbedFormConfig {
	/** Signed, time-stamped token the submission must echo back. */
	challenge: string;
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
	challenge: string;
	/** The hidden trap field; anything but empty rejects the submission. */
	honeypot: string;
	/** Claim tokens from the upload responses, one per stored file still in use. */
	upload_tokens: string[];
	values: Record<string, unknown>;
}

export interface EmbedCropRequest {
	height: number;
	token: string;
	width: number;
	x: number;
	y: number;
}

export interface EmbedFormSubmitResponse {
	id: number | string;
	redirect_url: string;
	status: "published" | "pending";
	thank_you_message: string;
}

const base = (hash: string) => `/embed-forms/${encodeURIComponent(hash)}`;

export const embedFormsApi = {
	get: (hash: string) => api.get<EmbedFormConfig>(base(hash)),

	submit: (hash: string, body: EmbedFormSubmitRequest) =>
		api.post<EmbedFormSubmitResponse>(`${base(hash)}/submit`, body),

	crop: (hash: string, body: EmbedCropRequest) =>
		api.post<unknown>(`${base(hash)}/images/crop`, body),

	/** Multipart path (for `useUploads`) for an image field's file. */
	imagePath: (hash: string) => `${base(hash)}/images`,

	/** Multipart path (for `useUploads`) for an upload field's file. */
	filePath: (hash: string) => `${base(hash)}/files`,
};
