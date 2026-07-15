import { memo, useCallback } from "react";

import type { ModuleFormField } from "@/api/endpoints/modules";

import { FieldRenderer } from "./FieldRenderer";
import { FieldRow } from "./FieldRow";

interface FieldRowItemProps {
	disabled?: boolean;
	error?: string;
	field: ModuleFormField;
	/** True for a never-published draft (every field is "new", no baseline). */
	isNew?: boolean;
	/** True when this field's draft value differs from the published content. */
	pending?: boolean;
	/** Heading for the draft column in the comparison, attributed to its owner. */
	pendingLabel?: string;
	/** The published value, used by the comparison panel (omit for new drafts). */
	publishedValue?: unknown;
	/**
	 * Stable, column-agnostic setter from FormRenderer. Must be referentially
	 * stable (memoized) for this component's React.memo to be effective.
	 */
	setFieldValue: (column: string, next: unknown) => void;
	/** This field's current draft value. */
	value: unknown;
}

/**
 * Owns the FieldRow + FieldRenderer for a single form field, plus a stable
 * per-column `handleChange`. Wrapped in React.memo so that a value change in
 * one field re-renders only that field's item — the keystroke fan-out across
 * every field that the plain map-with-inline-onChange caused is eliminated.
 *
 * For the memo to bite, every prop must be referentially stable when unchanged:
 * `field` (the form config is stable), `setFieldValue` (memoized in
 * FormRenderer), and primitives (`value`, `error`, flags, labels). The stable
 * `setFieldValue` lets `handleChange` be useCallback-stable per column.
 */
const FieldRowItemComponent = ({
	field,
	value,
	setFieldValue,
	error,
	disabled,
	pending,
	isNew,
	publishedValue,
	pendingLabel,
}: FieldRowItemProps) => {
	const handleChange = useCallback(
		(next: unknown) => setFieldValue(field.column, next),
		[field.column, setFieldValue]
	);

	return (
		<FieldRow
			currentValue={value}
			error={error}
			field={field}
			isNew={isNew}
			pending={pending}
			pendingLabel={pendingLabel}
			publishedValue={publishedValue}
		>
			<FieldRenderer
				disabled={disabled}
				error={error}
				field={field}
				value={value}
				onChange={handleChange}
			/>
		</FieldRow>
	);
};

export const FieldRowItem = memo(FieldRowItemComponent);
