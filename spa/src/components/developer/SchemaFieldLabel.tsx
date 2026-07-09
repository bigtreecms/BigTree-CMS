import type { ReactNode } from "react";

import { FieldLabel } from "@/components/ui/Field";

interface SchemaFieldLabelProps {
	label: string;
	children: ReactNode;
}

/**
 * The small muted label + control wrapper shared by the schema builders
 * (InputSchemaBuilder / SettingsSchemaBuilder) — a `<label>` with a `size="sm"`,
 * `tone="muted"` FieldLabel above the control. One copy so the two structurally
 * identical builders stay in lockstep.
 */
export const SchemaFieldLabel = ({ label, children }: SchemaFieldLabelProps) => (
	<label className="block">
		<FieldLabel size="sm" tone="muted">
			{label}
		</FieldLabel>
		{children}
	</label>
);
