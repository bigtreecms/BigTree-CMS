import type { ControlProps } from "./types";

/**
 * Checkbox setting. Stored as "on" / "" to match the value a legacy
 * settings.php checkbox posts, so the field type's draw/process php reads it
 * identically.
 */
export const BoolControl = ({ descriptor, settings, onPatch }: ControlProps) => {
	const checked = Boolean(settings[descriptor.id]);

	return (
		<label className="flex items-start gap-2 text-[12px] text-text-2">
			<input
				type="checkbox"
				className="mt-0.5 size-4 accent-accent"
				checked={checked}
				onChange={(e) => onPatch({ [descriptor.id]: e.target.checked ? "on" : "" })}
			/>
			<span>
				{descriptor.label}
				{descriptor.hint && <span className="ml-1 text-text-3">{descriptor.hint}</span>}
				{descriptor.note && (
					<span className="mt-0.5 block text-[11px] text-text-3">{descriptor.note}</span>
				)}
			</span>
		</label>
	);
};
