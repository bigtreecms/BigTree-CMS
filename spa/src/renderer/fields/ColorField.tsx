import { toStringValue } from "./fieldHelpers";
import { INPUT_CLASS, type FieldComponentProps } from "./types";

export const ColorField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const stringValue = toStringValue(value);
	const hex = /^#[0-9a-fA-F]{6}$/.test(stringValue) ? stringValue : "#000000";

	return (
		<div className="flex items-center gap-2">
			<input
				aria-label={field.title}
				className="h-9 w-12 rounded-md border border-border bg-surface"
				disabled={disabled}
				type="color"
				value={hex}
				onChange={(event) => onChange(event.target.value)}
			/>
			<input
				aria-label={`${field.title} (hex)`}
				className={`${INPUT_CLASS} flex-1 font-mono text-[12.5px]`}
				disabled={disabled}
				placeholder="#000000"
				type="text"
				value={stringValue}
				onChange={(event) => onChange(event.target.value)}
			/>
		</div>
	);
};
