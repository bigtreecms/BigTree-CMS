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
			<select
				className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
				value={value}
				onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
			>
				{(descriptor.options ?? []).map((o) => (
					<option key={o.value} value={o.value}>
						{o.label}
					</option>
				))}
			</select>
		</ControlShell>
	);
};
