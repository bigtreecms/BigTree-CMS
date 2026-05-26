import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

interface StaticListItem {
	key?: string;
	description?: string;
	value?: string;
	label?: string;
}

/**
 * BigTree "list" field with `list_type: "static"` — the most common form.
 * The dynamic ("db" / "parser") list types depend on server-side resolution
 * and are left as a follow-up; they currently render as a disabled select
 * with a single placeholder option.
 *
 * Static list items in the legacy storage shape look like:
 *   [{ key: "draft", description: "Draft" }, ...]
 * but we also accept `{ value, label }` for forward-compatibility.
 */
export const SelectField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const listType = (settings.list_type as string) || "static";
	const allowEmpty = settings["allow-empty"] !== "No";

	if (listType !== "static") {
		return (
			<select className={INPUT_CLASS} disabled value="">
				<option value="">— dynamic list (not yet supported) —</option>
			</select>
		);
	}

	const items = Array.isArray(settings.list) ? (settings.list as StaticListItem[]) : [];

	return (
		<select
			className={INPUT_CLASS}
			value={value == null ? "" : String(value)}
			disabled={disabled}
			onChange={(event) => onChange(event.target.value)}
		>
			{allowEmpty && <option value="">—</option>}
			{items.map((item, index) => {
				const optValue = item.key ?? item.value ?? "";
				const optLabel = item.description ?? item.label ?? optValue;

				return (
					<option key={`${optValue}-${index}`} value={optValue}>
						{optLabel}
					</option>
				);
			})}
		</select>
	);
};
