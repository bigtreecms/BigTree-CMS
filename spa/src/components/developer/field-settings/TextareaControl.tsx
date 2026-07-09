import { TextArea } from "@/components/ui/TextArea";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

export const TextareaControl = ({ descriptor, settings, onPatch }: ControlProps) => (
	<ControlShell
		label={descriptor.label}
		hint={descriptor.hint}
		note={descriptor.note}
		required={descriptor.required}
	>
		<TextArea
			rows={3}
			className="leading-relaxed"
			value={String(settings[descriptor.id] ?? "")}
			placeholder={descriptor.placeholder}
			onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
		/>
	</ControlShell>
);
