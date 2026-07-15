import { TextArea } from "@/components/ui/TextArea";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

export const TextareaControl = ({ descriptor, settings, onPatch }: ControlProps) => (
	<ControlShell
		hint={descriptor.hint}
		label={descriptor.label}
		note={descriptor.note}
		required={descriptor.required}
	>
		<TextArea
			className="leading-relaxed"
			placeholder={descriptor.placeholder}
			rows={3}
			value={String(settings[descriptor.id] ?? "")}
			onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
		/>
	</ControlShell>
);
