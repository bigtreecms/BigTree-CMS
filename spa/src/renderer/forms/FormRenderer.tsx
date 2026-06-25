import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";

import type { ModuleForm, ModuleFormField } from "@/api/endpoints/modules";
import type { Tag } from "@/api/endpoints/tags";
import { TagInput } from "@/components/tags/TagInput";
import { Alert } from "@/components/ui/Alert";
import { EmptyState } from "@/components/ui/EmptyState";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { ApiError } from "@/types/api";

import { FieldRowItem } from "./FieldRowItem";
import { FormRenderContextProvider, type FormRenderContextValue } from "./FormContext";
import { OpenGraphSection, type OpenGraphValue } from "./OpenGraphSection";
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
	onSubmit: (
		values: Record<string, unknown>,
		opts?: { publish?: boolean }
	) => Promise<unknown> | unknown;
	onCancel?: () => void;
	submitLabel?: string;
	/**
	 * When true, render a second primary button ("Save & Publish") that submits
	 * with `{ publish: true }`. Gate this on the caller's publisher access.
	 */
	canPublish?: boolean;
	publishLabel?: string;
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
	/**
	 * Columns whose draft value differs from the published content. Each gets a
	 * "Pending" badge and a published-vs-draft comparison toggle.
	 */
	pendingFields?: string[];
	/**
	 * Published (live) values keyed by column, for the comparison panel. Null
	 * when the entry has never been published (a brand-new draft).
	 */
	publishedValues?: Record<string, unknown> | null;
	/**
	 * Pending state of the loaded entry, if any: "updated" = a live row with a
	 * queued edit overlaid; "pending" = a never-published new draft. Drives the
	 * banner copy and per-field "New" vs "Pending" labelling.
	 */
	pendingStatus?: "updated" | "pending";
	/**
	 * Heading for the draft side of each field comparison, attributed to the
	 * pending change's owner (e.g. "Your draft" / "Draft by Jane").
	 */
	pendingLabel?: string;
	/**
	 * The entry's current tags (forms with `tagging` enabled). Submitted as
	 * `__tags__` ids; the server replaces the entry's tag set wholesale, so
	 * callers should always pass what the entry currently has.
	 */
	initialTags?: Tag[];
	/** The entry's Open Graph data (forms with `open_graph` enabled). */
	initialOpenGraph?: OpenGraphValue | null;
}

export const FormRenderer = ({
	form,
	initialValues,
	onSubmit,
	onCancel,
	submitLabel = "Save",
	canPublish = false,
	publishLabel = "Save & Publish",
	secondaryAction,
	disabled,
	header,
	moduleId,
	entryId,
	pendingFields,
	publishedValues,
	pendingStatus,
	pendingLabel,
	initialTags,
	initialOpenGraph,
}: FormRendererProps) => {
	const [values, setValues] = useState<Record<string, unknown>>(() =>
		seedValues(form, initialValues)
	);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [generalError, setGeneralError] = useState<string | null>(null);
	const [submitting, setSubmitting] = useState(false);

	// Tag browser / Open Graph sections render only when the form definition
	// opts in (mirrors the legacy form's `tagging` / `open_graph` flags).
	const showTagging = Boolean(form.tagging);
	const showOpenGraph = Boolean(form.open_graph);
	const [tags, setTags] = useState<Tag[]>(initialTags ?? []);
	const [openGraph, setOpenGraph] = useState<OpenGraphValue>(initialOpenGraph ?? {});

	useScrollToFirstError(fieldErrors);

	// Baseline the form started from, so we can tell when the user has made
	// edits. Reseeded alongside `values` whenever the form/entry changes.
	const baselineRef = useRef<Record<string, unknown>>(values);
	const tagsBaselineRef = useRef<Tag[]>(initialTags ?? []);
	const ogBaselineRef = useRef<OpenGraphValue>(initialOpenGraph ?? {});

	// Dirty while the user has unsaved edits — but never while we're mid-submit
	// (a successful save navigates away and must not be intercepted) or in a
	// read-only/disabled view where no edits are possible.
	const isDirty =
		!disabled &&
		!submitting &&
		(!valuesAreEqual(values, baselineRef.current) ||
			JSON.stringify(tags.map((t) => t.id)) !==
				JSON.stringify(tagsBaselineRef.current.map((t) => t.id)) ||
			JSON.stringify(openGraph) !== JSON.stringify(ogBaselineRef.current));

	const pendingSet = useMemo(() => new Set(pendingFields ?? []), [pendingFields]);
	const isNewDraft = pendingStatus === "pending";
	// Count only changed columns that are actually rendered on this form, so the
	// banner doesn't include internal/non-form columns from the change blob.
	const pendingCount = useMemo(
		() => form.fields.filter((field) => pendingSet.has(field.column)).length,
		[form.fields, pendingSet]
	);

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
		const seeded = seedValues(form, initialValues);
		setValues(seeded);
		baselineRef.current = seeded;
		setTags(initialTags ?? []);
		tagsBaselineRef.current = initialTags ?? [];
		setOpenGraph(initialOpenGraph ?? {});
		ogBaselineRef.current = initialOpenGraph ?? {};
		setFieldErrors({});
		setGeneralError(null);
		// Tags + OG arrive in the same payload as `initialValues`, so they're
		// read here without being deps — callers pass fresh array identities per
		// render and including them would reseed (wiping in-progress edits) on
		// every keystroke.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [form, initialValues]);

	// Stable across renders so the memoized FieldRowItem / FieldRenderer don't
	// re-render every field on each keystroke. The error-clear is done inside the
	// functional updater (rather than reading `fieldErrors`) so this callback has
	// no value deps: when there's no error for the column the previous map is
	// returned unchanged, so unrelated fields keep their identity.
	const setFieldValue = useCallback((column: string, next: unknown) => {
		setValues((prev) => ({ ...prev, [column]: next }));

		setFieldErrors((prev) => {
			if (!prev[column]) {
				return prev;
			}

			const copy = { ...prev };
			delete copy[column];

			return copy;
		});
	}, []);

	const handleSubmit = async (event: React.FormEvent, publish = false) => {
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
			// Copy — packForSubmit returns the state object itself for simple forms.
			const payload = { ...packForSubmit(form, values) };

			// The server replaces the entry's tag set / OG row wholesale on every
			// save (legacy semantics), so always send the current state when the
			// form has the feature enabled.
			if (showTagging) {
				payload["__tags__"] = tags.map((t) => t.id);
			}

			if (showOpenGraph) {
				payload["__open_graph__"] = openGraph;
			}

			await onSubmit(payload, { publish });
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
					<Alert tone="danger" className="mb-4">
						{generalError}
					</Alert>
				)}

				{pendingStatus && (
					<div className="mb-4 rounded-md border border-warn/40 bg-warn/5 px-3 py-2 text-[12.5px] text-text-2">
						{isNewDraft
							? "This is an unpublished draft and isn’t live yet. "
							: "You’re editing unpublished changes — the live entry still shows the previously published content. "}
						{!isNewDraft &&
							pendingCount > 0 &&
							`${pendingCount} field${pendingCount === 1 ? "" : "s"} changed — `}
						{canPublish
							? "“Save” keeps it pending; “Save & Publish” makes it live."
							: "“Save” updates the draft for a publisher to review."}
					</div>
				)}

				{form.fields.length === 0 ? (
					<EmptyState dashed size="sm">
						This form has no fields configured.
					</EmptyState>
				) : (
					form.fields.map((field) => (
						<FieldRowItem
							key={field.column}
							field={field}
							value={values[field.column]}
							setFieldValue={setFieldValue}
							error={fieldErrors[field.column]}
							disabled={disabled || submitting}
							pending={pendingSet.has(field.column)}
							isNew={isNewDraft}
							publishedValue={publishedValues?.[field.column]}
							pendingLabel={pendingLabel}
						/>
					))
				)}

				{showTagging && (
					<div className="mt-5 border-t border-border pt-4">
						<span className="mb-1.5 block text-[12px] font-medium text-text-2">
							Tags
						</span>
						<TagInput
							multiple
							value={tags}
							onChange={setTags}
							disabled={disabled || submitting}
							placeholder="Search for or add tags…"
						/>
					</div>
				)}

				{showOpenGraph && (
					<OpenGraphSection
						value={openGraph}
						onChange={setOpenGraph}
						disabled={disabled || submitting}
					/>
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
					className={
						canPublish
							? "inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] font-medium text-text-2 disabled:opacity-60 hover:bg-hover"
							: "inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
					}
					disabled={submitting || disabled}
				>
					{submitting ? "Saving…" : submitLabel}
				</button>
				{canPublish && (
					<button
						type="button"
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
						onClick={(e) => handleSubmit(e, true)}
						disabled={submitting || disabled}
					>
						{publishLabel}
					</button>
				)}
			</div>
		</form>
	);

	const leaveGuard = <UnsavedChangesGuard isDirty={isDirty} />;

	if (!renderContext) {
		return (
			<>
				{body}
				{leaveGuard}
			</>
		);
	}

	return (
		<FormRenderContextProvider value={renderContext}>
			{body}
			{leaveGuard}
		</FormRenderContextProvider>
	);
};

/**
 * Shallow value-equality for the form's flat value map. Keys are seeded from
 * `form.fields` in a stable order and only ever replaced (never reordered), so
 * a per-key JSON comparison is enough to detect edits — including nested
 * arrays/objects like relation id lists.
 */
const valuesAreEqual = (a: Record<string, unknown>, b: Record<string, unknown>): boolean => {
	const aKeys = Object.keys(a);
	const bKeys = Object.keys(b);

	if (aKeys.length !== bKeys.length) {
		return false;
	}

	for (const key of aKeys) {
		if (!Object.prototype.hasOwnProperty.call(b, key)) {
			return false;
		}

		if (JSON.stringify(a[key]) !== JSON.stringify(b[key])) {
			return false;
		}
	}

	return true;
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
