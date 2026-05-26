import type { FieldComponentProps } from "./types";

/**
 * Hidden form field — has no visible UI but must round-trip its value through
 * form submission. The FieldRow wrapper still draws a faint disclosure so
 * developers can see hidden fields exist while debugging.
 */
export const HiddenField = ({ field, value }: FieldComponentProps) => {
	return (
		<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-1.5 text-[11.5px] text-text-3">
			<span className="font-mono">{field.column}</span>
			<span className="ml-2 italic">hidden value · {value == null ? "—" : String(value)}</span>
		</div>
	);
};
