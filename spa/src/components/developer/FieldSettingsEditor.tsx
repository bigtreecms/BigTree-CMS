import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";

import { fieldTypesApi, type FieldUseCase } from "@/api/endpoints/field-types";
import { queryKeys } from "@/lib/queryKeys";
import { Field } from "@/components/ui/Field";
import { Loading } from "@/components/ui/Loading";

import { isVisible } from "./field-settings/evaluate";
import { JsonFallbackControl } from "./field-settings/JsonFallbackControl";
import { controlRegistry } from "./field-settings/registry";

interface FieldSettingsEditorProps {
	/** Field type id (e.g. "text", "image", "list"). */
	type: string;
	/** Host use_case — drives context-specific settings (templates vs callouts …). */
	useCase: FieldUseCase;
	value: Record<string, unknown> | unknown[] | undefined;
	onChange: (next: Record<string, unknown>) => void;
	/**
	 * Suppress the internal "Field settings" heading (and its top margin) for
	 * callers that supply their own grouping heading outside this control — e.g.
	 * a bordered box whose title should sit above the box, not inside it.
	 */
	hideLabel?: boolean;
	/**
	 * Required-setting errors keyed by descriptor id (from
	 * `validateFieldSettings`). Rendered inline beneath the matching control so
	 * the validation message lands next to the offending setting.
	 */
	errors?: Record<string, string>;
}

const toObject = (value: unknown): Record<string, unknown> =>
	value && typeof value === "object" && !Array.isArray(value)
		? (value as Record<string, unknown>)
		: {};

/**
 * Per-field settings UI. Reads the field type's settings schema
 * (GET /field-types/{id}/schema) and renders a typed control per setting,
 * replacing the raw-JSON block. Falls back to JSON for custom/extension types
 * that ship no schema, and always offers an "Edit as JSON" escape hatch.
 */
export const FieldSettingsEditor = ({
	type,
	useCase,
	value,
	onChange,
	hideLabel,
	errors,
}: FieldSettingsEditorProps) => {
	const settings = useMemo(() => toObject(value), [value]);
	const [showJson, setShowJson] = useState(false);

	const schemaQ = useQuery({
		queryKey: queryKeys.fieldTypes.schema(type),
		queryFn: () => fieldTypesApi.getSchema(type),
		enabled: type !== "",
	});

	const onPatch = (patch: Record<string, unknown>) => onChange({ ...settings, ...patch });

	// When the caller renders its own heading, drop both the label and the top
	// margin that spaces it away from the field-type select above.
	const rootMargin = hideLabel ? "" : "mt-3";
	const label = hideLabel ? null : (
		<span className="mb-1 block text-[11.5px] font-medium text-text-2">Field settings</span>
	);

	if (schemaQ.isLoading) {
		return (
			<div className={rootMargin}>
				{label}
				<Loading label="Loading settings…" />
			</div>
		);
	}

	const schema = schemaQ.data;
	const descriptors = schema?.settings_schema;
	const useFallback = !schema || schema.render_fallback || !Array.isArray(descriptors);

	if (useFallback || showJson) {
		return (
			<div className={rootMargin}>
				<div className="mb-1 flex items-center justify-between">
					{label}
					{!useFallback && (
						<button
							type="button"
							className="text-[11px] text-text-3 underline hover:text-text-2"
							onClick={() => setShowJson(false)}
						>
							Use settings form
						</button>
					)}
				</div>
				<JsonFallbackControl value={settings} onChange={onChange} />
			</div>
		);
	}

	if (descriptors.length === 0) {
		return (
			<div className={rootMargin}>
				{label}
				<p className="text-[12px] text-text-3">
					This field type has no configurable settings.
				</p>
			</div>
		);
	}

	return (
		<div className={`${rootMargin} space-y-3`.trim()}>
			{label}
			{descriptors
				.filter((descriptor) => isVisible(descriptor, settings, useCase))
				.map((descriptor) => {
					const Control = controlRegistry[descriptor.control];

					if (!Control) {
						return null;
					}

					const error = errors?.[descriptor.id];

					return (
						<Field as="div" key={descriptor.id} error={error}>
							<Control
								descriptor={descriptor}
								settings={settings}
								onPatch={onPatch}
								useCase={useCase}
							/>
						</Field>
					);
				})}
			<button
				type="button"
				className="text-[11px] text-text-3 underline hover:text-text-2"
				onClick={() => setShowJson(true)}
			>
				Edit as JSON
			</button>
		</div>
	);
};
