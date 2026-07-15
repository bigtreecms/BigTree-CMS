import { TextInput } from "@/components/ui/TextInput";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

export const StringControl = ({ descriptor, settings, onPatch }: ControlProps) => (
	<ControlShell
		hint={descriptor.hint}
		label={descriptor.label}
		note={descriptor.note}
		required={descriptor.required}
	>
		<TextInput
			dense
			placeholder={descriptor.placeholder}
			value={String(settings[descriptor.id] ?? "")}
			onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
		/>
	</ControlShell>
);
