import { TextInput } from "@/components/ui/TextInput";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

export const StringControl = ({ descriptor, settings, onPatch }: ControlProps) => (
	<ControlShell
		label={descriptor.label}
		hint={descriptor.hint}
		note={descriptor.note}
		required={descriptor.required}
	>
		<TextInput
			dense
			value={String(settings[descriptor.id] ?? "")}
			placeholder={descriptor.placeholder}
			onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
		/>
	</ControlShell>
);
