import { useEffect, useMemo, useState, type ReactNode } from "react";

import type { ModuleForm, ModuleFormField } from "@/api/endpoints/modules";
import { ApiError } from "@/types/api";

import { FieldRenderer } from "./FieldRenderer";
import { FieldRow } from "./FieldRow";
import { FormRenderContextProvider, type FormRenderContextValue } from "./FormContext";
import { validateRequiredFields } from "./validation";

/**
 * Generic runtime for any BigTree module form. The caller owns the network
 * (mutation) — we just collect values, surface field-level errors, and call
 * `onSubmit(values)` when the form is committed.
 *
 *   - `form` — the form config from /modules/{id}/forms
 *   - `initialValues` — values for an edit screen (omit for add)
 *   - `submitLabel` — defaults to "Save". Add screens typically pass "Create".
 *   - `secondaryAction` — optional extra footer button (e.g. "Save & Publish").
 *   - `disabled` — read-only mode (e.g. when a lock is held by another user).
 *   - `header` — optional content rendered in the FormShell header bar.
 *
 * Field-level validation errors from a 422 response are routed through
 * ApiError#fieldErrors() and rendered under the corresponding field. Surface
 * errors that aren't field-bound are returned via `onSubmit`'s rejection so
 * the caller can show a banner.
 */

export interface FormRendererProps {
	form: ModuleForm;
	initialValues?: Record<string, unknown>;
	onSubmit: (values: Record<string, unknown>) => Promise<unknown> | unknown;
	onCancel?: () => void;
	submitLabel?: string;
	secondaryAction?: ReactNode;
	disabled?: boolean;
	header?: ReactNode;
	/**
	 * Owning module id. Required by relation-style fields (one-to-many /
	 * many-to-many) so they can hit the relation-options endpoint. Optional
	 * because some non-module contexts (e.g. global settings) reuse the
	 * renderer without a module behind it.
	 */
	moduleId?: string;
	/**
	 * Numeric entry id when editing an existing row; omit for create.
	 * ManyToManyField uses this to load its initial selections from the
	 * connecting table (the entry payload doesn't carry MTM data inline).
	 */
	entryId?: number | null;
}

export const FormRenderer = ({
	form,
	initialValues,
	onSubmit,
	onCancel,
	submitLabel = "Save",
	secondaryAction,
	disabled,
	header,
	moduleId,
	entryId,
}: FormRendererProps) => {
	const [values, setValues] = useState<Record<string, unknown>>(() =>
		seedValues(form, initialValues)
	);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [generalError, setGeneralError] = useState<string | null>(null);
	const [submitting, setSubmitting] = useState(false);

	const renderContext = useMemo<FormRenderContextValue | null>(() => {
		if (!moduleId) {
			return null;
		}

		return {
			moduleId,
			formId: form.id,
			entryId: entryId ?? null,
		};
	}, [moduleId, form.id, entryId]);

	useEffect(() => {
		setValues(seedValues(form, initialValues));
		setFieldErrors({});
		setGeneralError(null);
	}, [form, initialValues]);

	const setFieldValue = (column: string, next: unknown) => {
		setValues((prev) => ({ ...prev, [column]: next }));

		if (fieldErrors[column]) {
			setFieldErrors((prev) => {
				const copy = { ...prev };
				delete copy[column];

				return copy;
			});
		}
	};

	const handleSubmit = async (event: React.FormEvent) => {
		event.preventDefault();

		if (submitting || disabled) {
			return;
		}

		const requiredErrors = validateRequiredFields(form.fields, values);

		if (Object.keys(requiredErrors).length > 0) {
			setFieldErrors(requiredErrors);
			setGeneralError("Please fill in the required fields.");

			return;
		}

		setSubmitting(true);
		setGeneralError(null);
		setFieldErrors({});

		try {
			await onSubmit(packForSubmit(form, values));
		} catch (err) {
			if (err instanceof ApiError) {
				const fe = err.fieldErrors();

				if (Object.keys(fe).length > 0) {
					setFieldErrors(fe);
				}

				setGeneralError(err.message);
			} else if (err instanceof Error) {
				setGeneralError(err.message);
			} else {
				setGeneralError("Save failed");
			}
		} finally {
			setSubmitting(false);
		}
	};

	const body = (
		<form onSubmit={handleSubmit} className="rounded-xl border border-border bg-surface">
			{header && (
				<div className="flex items-baseline justify-between border-b border-border bg-surface-2 px-4 py-3 text-[12.5px]">
					{header}
				</div>
			)}

			<div className="p-4">
				{generalError && (
					<div className="mb-4 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
						{generalError}
					</div>
				)}

				{form.fields.length === 0 ? (
					<div className="rounded-md border border-dashed border-border bg-surface-2 p-6 text-center text-[12.5px] text-text-3">
						This form has no fields configured.
					</div>
				) : (
					form.fields.map((field) => (
						<FieldRow
							key={field.column}
							field={field}
							error={fieldErrors[field.column]}
						>
							<FieldRenderer
								field={field}
								value={values[field.column]}
								onChange={(next) => setFieldValue(field.column, next)}
								disabled={disabled || submitting}
								error={fieldErrors[field.column]}
							/>
						</FieldRow>
					))
				)}
			</div>

			<div className="sticky bottom-0 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface-2 px-4 py-3">
				{onCancel && (
					<button
						type="button"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] text-text-2 hover:bg-hover"
						onClick={onCancel}
						disabled={submitting}
					>
						Cancel
					</button>
				)}
				{secondaryAction}
				<button
					type="submit"
					className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
					disabled={submitting || disabled}
				>
					{submitting ? "Saving…" : submitLabel}
				</button>
			</div>
		</form>
	);

	if (!renderContext) {
		return body;
	}

	return <FormRenderContextProvider value={renderContext}>{body}</FormRenderContextProvider>;
};

interface MtmFieldSettings {
	"mtm-connecting-table"?: string;
	"mtm-my-id"?: string;
	"mtm-other-id"?: string;
}

interface MtmSubmissionEntry {
	table: string;
	"my-id": string;
	"other-id": string;
	data: Array<number | string>;
}

/**
 * Transform the flat `values` dict into the wire shape the legacy auto-module
 * processor expects: any many-to-many field gets pulled out of `values` and
 * pushed into a `__mtm__` array of `{table, my-id, other-id, data}`.
 *
 * The transform is non-destructive on `values` (it does not mutate state) and
 * only kicks in when at least one many-to-many field is present, so simple
 * forms keep round-tripping unchanged.
 */
const packForSubmit = (
	form: ModuleForm,
	values: Record<string, unknown>
): Record<string, unknown> => {
	const mtmFields = form.fields.filter((field) => field.type === "many-to-many");

	if (mtmFields.length === 0) {
		return values;
	}

	const copy: Record<string, unknown> = { ...values };
	const mtm: MtmSubmissionEntry[] = [];

	for (const field of mtmFields) {
		const settings = normalizeMtmSettings(field);

		if (!settings) {
			continue;
		}

		const ids = readIdList(copy[field.column]);
		delete copy[field.column];

		mtm.push({
			table: settings.table,
			"my-id": settings.myId,
			"other-id": settings.otherId,
			data: ids,
		});
	}

	const existing = Array.isArray(copy["__mtm__"])
		? (copy["__mtm__"] as MtmSubmissionEntry[])
		: [];

	copy["__mtm__"] = [...existing, ...mtm];

	return copy;
};

const normalizeMtmSettings = (
	field: ModuleFormField
): { table: string; myId: string; otherId: string } | null => {
	const raw = field.settings;

	if (!raw || Array.isArray(raw)) {
		return null;
	}

	const settings = raw as MtmFieldSettings;
	const table = settings["mtm-connecting-table"];
	const myId = settings["mtm-my-id"];
	const otherId = settings["mtm-other-id"];

	if (!table || !myId || !otherId) {
		return null;
	}

	return { table, myId, otherId };
};

const readIdList = (raw: unknown): Array<number | string> => {
	if (!Array.isArray(raw)) {
		return [];
	}

	return raw
		.map((entry) => {
			if (typeof entry === "number" && Number.isFinite(entry)) {
				return entry;
			}

			if (typeof entry === "string" && entry.length > 0) {
				return entry;
			}

			return null;
		})
		.filter((entry): entry is number | string => entry !== null);
};

/**
 * Build the initial-values map: prefer the entry data (edit case), fall back
 * to per-field `default` settings (legacy storage), and finally to "".
 */
const seedValues = (
	form: ModuleForm,
	initial?: Record<string, unknown>
): Record<string, unknown> => {
	const out: Record<string, unknown> = {};

	for (const field of form.fields) {
		if (initial && Object.prototype.hasOwnProperty.call(initial, field.column)) {
			out[field.column] = initial[field.column];

			continue;
		}

		const settings = field.settings;
		const def =
			settings && !Array.isArray(settings)
				? (settings as Record<string, unknown>).default
				: undefined;

		out[field.column] = def !== undefined ? def : "";
	}

	return out;
};
