import { useState } from "react";
import { ChevronDown, ChevronRight } from "lucide-react";

import { FieldComparison } from "./FieldComparison";

/**
 * "Compare with published" affordance shown beneath a field that has pending
 * changes. Owns its own expand/collapse state and reveals a <FieldComparison />
 * panel. Rendered by both the module FormRenderer (via FieldRow) and the page
 * editor's field wrapper.
 */

interface PendingFieldCompareProps {
	published: unknown;
	pending: unknown;
	isNew?: boolean;
	/** Heading for the draft column, attributed to its owner. */
	pendingLabel?: string;
	/** The field's BigTree type slug, for richer comparison rendering. */
	fieldType?: string;
}

export const PendingFieldCompare = ({
	published,
	pending,
	isNew,
	pendingLabel,
	fieldType,
}: PendingFieldCompareProps) => {
	const [open, setOpen] = useState(false);

	return (
		<div className="mt-1">
			<button
				type="button"
				className="inline-flex items-center gap-1 text-[11.5px] text-text-3 hover:text-text-2"
				onClick={() => setOpen((v) => !v)}
				aria-expanded={open}
			>
				{open ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
				{open ? "Hide comparison" : isNew ? "View new content" : "Compare with published"}
			</button>

			{open && (
				<FieldComparison
					published={published}
					pending={pending}
					isNew={isNew}
					pendingLabel={pendingLabel}
					fieldType={fieldType}
				/>
			)}
		</div>
	);
};
