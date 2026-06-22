import { Checkbox } from "../../ui/Checkbox";

import type { ControlProps } from "./types";

/**
 * Checkbox setting. Stored as "on" / "" to match the value a legacy
 * settings.php checkbox posts (so the field type's draw/process php reads it
 * identically) — hence the value map around the boolean {@link Checkbox}.
 */
export const BoolControl = ({ descriptor, settings, onPatch }: ControlProps) => {
	const checked = Boolean(settings[descriptor.id]);

	return (
		<Checkbox
			align="start"
			checked={checked}
			onChange={(next) => onPatch({ [descriptor.id]: next ? "on" : "" })}
			label={
				<>
					{descriptor.label}
					{descriptor.hint && <span className="ml-1 text-text-3">{descriptor.hint}</span>}
					{descriptor.note && (
						<span className="mt-0.5 block text-[11px] text-text-3">
							{descriptor.note}
						</span>
					)}
				</>
			}
		/>
	);
};
