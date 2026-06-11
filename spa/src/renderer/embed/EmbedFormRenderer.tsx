import { useEffect, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";

import { embedFormsApi, type EmbedFormConfig } from "@/api/endpoints/embed-forms";
import type { ModuleForm } from "@/api/endpoints/modules";
import { FormRenderer } from "@/renderer/forms/FormRenderer";

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
 */

export interface EmbedFormRendererProps {
	hash: string;
}

export const EmbedFormRenderer = ({ hash }: EmbedFormRendererProps) => {
	const [submitted, setSubmitted] = useState<{
		message: string;
		redirect: string;
	} | null>(null);

	const configQuery = useQuery({
		queryKey: ["embed-form", hash] as const,
		queryFn: () => embedFormsApi.get(hash),
		retry: false,
	});

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
		mutationFn: (values: Record<string, unknown>) => embedFormsApi.submit(hash, { values }),
		onSuccess: (response) => {
			setSubmitted({
				message: response.thank_you_message,
				redirect: response.redirect_url,
			});
		},
	});

	if (configQuery.isLoading) {
		return (
			<div className="mx-auto max-w-2xl px-6 py-12 text-center text-[13px] text-text-3">
				Loading form…
			</div>
		);
	}

	if (configQuery.isError || !configQuery.data) {
		return (
			<div className="mx-auto max-w-2xl px-6 py-12 text-center text-[13px] text-text-3">
				This form is unavailable.
			</div>
		);
	}

	const config = configQuery.data;

	if (submitted) {
		return (
			<div className="mx-auto max-w-2xl px-6 py-12">
				<div className="rounded-xl border border-border bg-surface p-8 text-center text-[13.5px] text-text-2">
					{submitted.redirect
						? "Redirecting…"
						: submitted.message || "Thanks — your submission has been received."}
				</div>
			</div>
		);
	}

	const form = adaptConfigToForm(config);

	return (
		<div className="bigtree-embed-form mx-auto max-w-2xl px-6 py-6">
			<h1 className="mb-4 text-[18px] font-semibold text-text">{config.title}</h1>
			<FormRenderer
				form={form}
				onSubmit={(values) => submitMutation.mutateAsync(values)}
				submitLabel="Submit"
			/>
		</div>
	);
};

const adaptConfigToForm = (config: EmbedFormConfig): ModuleForm => {
	return {
		id: config.id,
		title: config.title,
		table: config.table,
		fields: config.fields,
	};
};
