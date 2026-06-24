import type { ReactNode } from "react";

import { Field } from "@/components/ui/Field";

interface ControlShellProps {
	label?: string;
	hint?: string;
	note?: string;
	required?: boolean;
	children: ReactNode;
}

/**
 * Shared label / hint / note wrapper so every simple resource-designer control
 * renders with the same spacing as the rest of the designer. A thin adapter over
 * the shared {@link Field}: the designer uses the denser `sm` label, an inline
 * `hint` on the label line, and a `note` below the control.
 */
export const ControlShell = ({ label, hint, note, required, children }: ControlShellProps) => (
	<Field label={label} required={required} size="sm" inlineHint={hint} hint={note}>
		{children}
	</Field>
);
