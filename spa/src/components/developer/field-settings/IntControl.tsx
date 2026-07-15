import { TextInput } from "@/components/ui/TextInput";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

/**
 * Numeric setting. Stored as the raw string the user typed (legacy settings.php
 * use plain text inputs and `intval()` on read), so empty stays empty rather
 * than coercing to 0.
 */
export const IntControl = ({ descriptor, settings, onPatch }: ControlProps) => (
	<ControlShell
		hint={descriptor.hint}
		label={descriptor.label}
		note={descriptor.note}
		required={descriptor.required}
	>
		<TextInput
			dense
			inputMode="numeric"
			placeholder={descriptor.placeholder}
			value={String(settings[descriptor.id] ?? "")}
			onChange={(e) => onPatch({ [descriptor.id]: e.target.value.replace(/[^0-9]/g, "") })}
		/>
	</ControlShell>
);
