import { Select } from "../../ui/Select";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

export const EnumControl = ({ descriptor, settings, onPatch }: ControlProps) => {
	const value = String(settings[descriptor.id] ?? descriptor.default ?? "");

	return (
		<ControlShell
			label={descriptor.label}
			hint={descriptor.hint}
			note={descriptor.note}
			required={descriptor.required}
		>
			<Select
				dense
				value={value}
				onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
			>
				{(descriptor.options ?? []).map((o) => (
					<option key={o.value} value={o.value}>
						{o.label}
					</option>
				))}
			</Select>
		</ControlShell>
	);
};
