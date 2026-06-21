import type { FieldComponentProps } from "./types";

/**
 * Boolean checkbox. BigTree legacy stores checkbox truthy as "on" and falsy
 * as "" — to keep the SPA's contract clean we emit booleans here and let the
 * API client coerce. (The server already accepts boolean true/false for new
 * routes; legacy hooks may need the "on" form, which is a separate concern.)
 */
export const CheckboxField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const checked = value === true || value === "on" || value === 1 || value === "1";

	return (
		<label className="inline-flex cursor-pointer items-center gap-2 text-[13px] text-text-2">
			<input
				type="checkbox"
				className="size-3.5 rounded border-border accent-accent disabled:cursor-not-allowed"
				checked={checked}
				disabled={disabled}
				onChange={(event) => onChange(event.target.checked)}
			/>
			<span>{field.subtitle || "Yes"}</span>
		</label>
	);
};
