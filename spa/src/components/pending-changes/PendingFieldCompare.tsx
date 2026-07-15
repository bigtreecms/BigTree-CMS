import { useState } from "react";

import { DisclosureToggle } from "@/components/ui/DisclosureToggle";
import { FieldComparison } from "./FieldComparison";

/**
 * "Compare with published" affordance shown beneath a field that has pending
 * changes. Owns its own expand/collapse state and reveals a <FieldComparison />
 * panel. Rendered by both the module FormRenderer (via FieldRow) and the page
 * editor's field wrapper.
 */

interface PendingFieldCompareProps {
	/** The field's BigTree type slug, for richer comparison rendering. */
	fieldType?: string;
	isNew?: boolean;
	pending: unknown;
	/** Heading for the draft column, attributed to its owner. */
	pendingLabel?: string;
	published: unknown;
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
			<DisclosureToggle
				className="gap-1 text-[11.5px] text-text-3 hover:text-text-2"
				label={
					open ? "Hide comparison" : isNew ? "View new content" : "Compare with published"
				}
				open={open}
				size={12}
				onToggle={() => setOpen((v) => !v)}
			/>

			{open && (
				<FieldComparison
					fieldType={fieldType}
					isNew={isNew}
					pending={pending}
					pendingLabel={pendingLabel}
					published={published}
				/>
			)}
		</div>
	);
};
