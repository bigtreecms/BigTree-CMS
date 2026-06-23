import { INPUT_CLASS, type FieldComponentProps } from "./types";

export const ColorField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const stringValue = typeof value === "string" ? value : value == null ? "" : String(value);
	const hex = /^#[0-9a-fA-F]{6}$/.test(stringValue) ? stringValue : "#000000";

	return (
		<div className="flex items-center gap-2">
			<input
				type="color"
				value={hex}
				disabled={disabled}
				onChange={(event) => onChange(event.target.value)}
				className="h-9 w-12 rounded-md border border-border bg-surface"
				aria-label={field.title}
			/>
			<input
				type="text"
				aria-label={`${field.title} (hex)`}
				className={`${INPUT_CLASS} flex-1 font-mono text-[12.5px]`}
				value={stringValue}
				placeholder="#000000"
				disabled={disabled}
				onChange={(event) => onChange(event.target.value)}
			/>
		</div>
	);
};
