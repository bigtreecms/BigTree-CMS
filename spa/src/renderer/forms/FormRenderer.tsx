import { useEffect, useState, type ReactNode } from "react";

import type { ModuleForm } from "@/api/endpoints/modules";
import { ApiError } from "@/types/api";

import { FieldRenderer } from "./FieldRenderer";
import { FieldRow } from "./FieldRow";

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
}: FormRendererProps) => {
	const [values, setValues] = useState<Record<string, unknown>>(() => seedValues(form, initialValues));
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [generalError, setGeneralError] = useState<string | null>(null);
	const [submitting, setSubmitting] = useState(false);

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

		setSubmitting(true);
		setGeneralError(null);
		setFieldErrors({});

		try {
			await onSubmit(values);
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

	return (
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
