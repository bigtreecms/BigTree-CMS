import { memo, type ReactNode } from "react";

import type { ModuleFormField } from "@/api/endpoints/modules";
import { PendingBadge } from "@/components/pending-changes/PendingBadge";
import { PendingFieldCompare } from "@/components/pending-changes/PendingFieldCompare";

import { isFieldRequired } from "./validation";

interface FieldRowProps {
	field: ModuleFormField;
	error?: string;
	children: ReactNode;
	/** True when this field's draft value differs from the published content. */
	pending?: boolean;
	/** True for a never-published draft (every field is "new", no baseline). */
	isNew?: boolean;
	/** The published value, used by the comparison panel (omit for new drafts). */
	publishedValue?: unknown;
	/** The current draft value, used by the comparison panel. */
	currentValue?: unknown;
	/** Heading for the draft column in the comparison, attributed to its owner. */
	pendingLabel?: string;
}

/**
 * Standard label + control + hint wrapper around every form field. Mirrors
 * the design's `.field` block: label on top (with optional inline subtitle
 * hint), the input control below, then an error or subtitle hint underneath.
 *
 * Field-level errors come from the API's structured 422 payload and are
 * passed in by FormRenderer.
 *
 * When `pending`, the label gets a "Pending"/"New" badge and a "Compare with
 * published" toggle is rendered beneath the control.
 */
const FieldRowComponent = ({
	field,
	error,
	children,
	pending,
	isNew,
	publishedValue,
	currentValue,
	pendingLabel,
}: FieldRowProps) => {
	return (
		<div className="mb-4">
			<div className="mb-1.5 flex items-baseline gap-1.5">
				<span className="text-[12.5px] font-medium text-text-2">
					{field.title}
					{isFieldRequired(field) && <span className="text-danger"> *</span>}
				</span>
				{field.subtitle && (
					<span className="text-[11.5px] text-text-3">({field.subtitle})</span>
				)}
				{pending && <PendingBadge label={isNew ? "New" : "Pending"} />}
			</div>

			{children}

			{pending && (
				<PendingFieldCompare
					published={publishedValue}
					pending={currentValue}
					isNew={isNew}
					pendingLabel={pendingLabel}
					fieldType={field.type}
				/>
			)}

			{error && (
				<div data-field-error className="mt-1 text-[11.5px] text-danger">
					{error}
				</div>
			)}
		</div>
	);
};

export const FieldRow = memo(FieldRowComponent);
