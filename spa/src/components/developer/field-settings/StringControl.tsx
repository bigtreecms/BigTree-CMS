import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

export const StringControl = ({ descriptor, settings, onPatch }: ControlProps) => (
	<ControlShell
		label={descriptor.label}
		hint={descriptor.hint}
		note={descriptor.note}
		required={descriptor.required}
	>
		<input
			type="text"
			className="w-full rounded-md border border-border bg-surface px-3 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
			value={String(settings[descriptor.id] ?? "")}
			placeholder={descriptor.placeholder}
			onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
		/>
	</ControlShell>
);
