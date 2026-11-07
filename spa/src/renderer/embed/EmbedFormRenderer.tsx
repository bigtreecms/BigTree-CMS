import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";

import { Card } from "@/components/ui/Card";
import { Loading } from "@/components/ui/Loading";
import { embedFormsApi, type EmbedFormConfig } from "@/api/endpoints/embed-forms";
import type { ModuleForm } from "@/api/endpoints/modules";
import { normalizeColumnSettings } from "@/renderer/fields/fieldHelpers";
import { FieldUploadProvider } from "@/renderer/fields/FieldUploadContext";
import { FormRenderer } from "@/renderer/forms/FormRenderer";
import { sanitizeHtml } from "@/lib/html";
import { queryKeys } from "@/lib/queryKeys";
import { ApiError } from "@/types/api";

import { EmbedHoneypot } from "./EmbedHoneypot";
import { useEmbedUploadTarget } from "./useEmbedUploadTarget";

/**
 * Standalone embed-form runtime. Used by the public /embed/:hash route.
 *
 * Skips the admin Shell entirely — the embed form is loaded inside an iframe
 * on third-party pages, so it needs to render as a single page with optional
 * per-form CSS injected from `form.css`. The legacy admin's iframe wrapper
 * scrolls + resizes via window.parent calls; we don't replicate that yet, but
 * the entry point is here for it.
 *
 * The renderer fetches the config from the public `/embed-forms/{hash}`
 * endpoint, hands the form definition to <FormRenderer />, and on successful
 * submit either redirects (if the form has `redirect_url`) or shows the
 * configured thank-you message.
 *
 * Image and upload fields post to the form's own public upload routes through
 * a FieldUploadProvider (see useEmbedUploadTarget), and the submission carries
 * the config's bot-check challenge plus a honeypot field. Fields that pick from
 * the media library are left out: an anonymous visitor can't browse it, and the
 * server drops any value sent for them.
 */

/** Field types that only work against the signed-in media library. */
const LIBRARY_FIELD_TYPES = new Set([
	"file-reference",
	"image-reference",
	"video-reference",
	"media-gallery",
]);

export interface EmbedFormRendererProps {
	hash: string;
}

export const EmbedFormRenderer = ({ hash }: EmbedFormRendererProps) => {
	const [submitted, setSubmitted] = useState<{
		message: string;
		redirect: string;
	} | null>(null);

	const configQuery = useQuery({
		queryKey: queryKeys.embedForms.detail(hash),
		queryFn: () => embedFormsApi.get(hash),
		retry: false,
	});
	const config = configQuery.data;
	const [honeypot, setHoneypot] = useState("");
	const { target, tokensFor } = useEmbedUploadTarget(hash, config?.challenge ?? "");

	// Memoized on the field list so a refreshed challenge doesn't hand
	// FormRenderer a new form (which would reseed and wipe the visitor's input).
	const fields = config?.fields;
	const form = useMemo<ModuleForm | null>(
		() => (config ? adaptConfigToForm(config) : null),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[config?.id, config?.title, config?.table, fields]
	);

	useEffect(() => {
		const url = configQuery.data?.css;

		if (!url) {
			return;
		}

		const link = document.createElement("link");
		link.rel = "stylesheet";
		link.href = url;
		document.head.appendChild(link);

		return () => {
			document.head.removeChild(link);
		};
	}, [configQuery.data?.css]);

	useEffect(() => {
		if (submitted?.redirect) {
			window.location.assign(submitted.redirect);
		}
	}, [submitted?.redirect]);

	const submitMutation = useMutation({
		mutationFn: (values: Record<string, unknown>) =>
			embedFormsApi.submit(hash, {
				values,
				challenge: config?.challenge ?? "",
				honeypot,
				upload_tokens: tokensFor(values),
			}),
		onError: (err) => {
			// An expired or too-early challenge: fetch a fresh one so the
			// visitor's next attempt can go through.
			if (err instanceof ApiError && err.code === "spam_check_failed") {
				void configQuery.refetch();
			}
		},
		onSuccess: (response) => {
			setSubmitted({
				message: response.thank_you_message,
				redirect: response.redirect_url,
			});
		},
	});

	if (configQuery.isLoading) {
		return (
			<Loading
				className="mx-auto max-w-2xl px-6 py-12"
				label="Loading form…"
				variant="block"
			/>
		);
	}

	if (configQuery.isError || !config || !form) {
		return (
			<div className="mx-auto max-w-2xl px-6 py-12 text-center text-[13px] text-text-3">
				This form is unavailable.
			</div>
		);
	}

	if (submitted) {
		return (
			<div className="mx-auto max-w-2xl px-6 py-12">
				<Card className="p-8 text-center text-[13.5px] text-text-2">
					{submitted.redirect ? (
						"Redirecting…"
					) : submitted.message ? (
						// The thank-you message is HTML written in the form designer.
						<div
							dangerouslySetInnerHTML={{ __html: sanitizeHtml(submitted.message) }}
						/>
					) : (
						"Thanks — your submission has been received."
					)}
				</Card>
			</div>
		);
	}

	return (
		<div className="bigtree-embed-form relative mx-auto max-w-2xl p-6">
			<h1 className="mb-4 text-[18px] font-semibold text-text">{config.title}</h1>
			<EmbedHoneypot value={honeypot} onChange={setHoneypot} />
			<FieldUploadProvider value={target}>
				<FormRenderer
					form={form}
					submitLabel="Submit"
					onSubmit={(values) => submitMutation.mutateAsync(values)}
				/>
			</FieldUploadProvider>
		</div>
	);
};

const adaptConfigToForm = (config: EmbedFormConfig): ModuleForm => {
	return {
		id: config.id,
		title: config.title,
		table: config.table,
		fields: config.fields
			.filter((field) => !LIBRARY_FIELD_TYPES.has(field.type))
			.map((field) =>
				field.type === "matrix"
					? { ...field, settings: withoutLibraryColumns(field.settings) }
					: field
			),
	};
};

/**
 * A matrix's settings with library sub-fields left out, at every depth (a
 * matrix column can itself be a matrix).
 */
const withoutLibraryColumns = (raw: unknown): Record<string, unknown> => {
	const settings = normalizeColumnSettings(raw);

	if (!Array.isArray(settings.columns)) {
		return settings;
	}

	const columns = (settings.columns as Array<Record<string, unknown>>)
		.filter((column) => !LIBRARY_FIELD_TYPES.has(String(column?.type ?? "")))
		.map((column) =>
			column.type === "matrix"
				? { ...column, settings: withoutLibraryColumns(column.settings) }
				: column
		);

	return { ...settings, columns };
};
