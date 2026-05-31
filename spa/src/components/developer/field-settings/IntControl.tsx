import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

/**
 * Numeric setting. Stored as the raw string the user typed (legacy settings.php
 * use plain text inputs and `intval()` on read), so empty stays empty rather
 * than coercing to 0.
 */
export const IntControl = ({ descriptor, settings, onPatch }: ControlProps) => (
	<ControlShell
		label={descriptor.label}
		hint={descriptor.hint}
		note={descriptor.note}
		required={descriptor.required}
	>
		<input
			type="text"
			inputMode="numeric"
			className="w-full rounded-md border border-border bg-surface px-3 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
			value={String(settings[descriptor.id] ?? "")}
			placeholder={descriptor.placeholder}
			onChange={(e) => onPatch({ [descriptor.id]: e.target.value.replace(/[^0-9]/g, "") })}
		/>
	</ControlShell>
);
