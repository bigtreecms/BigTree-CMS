import type { ReactNode } from "react";

import type { ModuleFormField } from "@/api/endpoints/modules";

interface FieldRowProps {
	field: ModuleFormField;
	error?: string;
	children: ReactNode;
}

/**
 * Standard label + control + hint wrapper around every form field. Mirrors
 * the design's `.field` block: label on top (with optional inline subtitle
 * hint), the input control below, then an error or subtitle hint underneath.
 *
 * Field-level errors come from the API's structured 422 payload and are
 * passed in by FormRenderer.
 */
export const FieldRow = ({ field, error, children }: FieldRowProps) => {
	return (
		<div className="mb-4">
			<div className="mb-1.5 flex items-baseline gap-1.5">
				<span className="text-[12.5px] font-medium text-text-2">{field.title}</span>
				{field.subtitle && (
					<span className="text-[11.5px] text-text-3">({field.subtitle})</span>
				)}
			</div>

			{children}

			{error && <div className="mt-1 text-[11.5px] text-danger">{error}</div>}
		</div>
	);
};
