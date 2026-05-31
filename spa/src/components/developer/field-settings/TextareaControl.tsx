import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

export const TextareaControl = ({ descriptor, settings, onPatch }: ControlProps) => (
	<ControlShell
		label={descriptor.label}
		hint={descriptor.hint}
		note={descriptor.note}
		required={descriptor.required}
	>
		<textarea
			rows={3}
			className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] leading-relaxed focus:outline-none focus:ring-1 focus:ring-accent-ring"
			value={String(settings[descriptor.id] ?? "")}
			placeholder={descriptor.placeholder}
			onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
		/>
	</ControlShell>
);
