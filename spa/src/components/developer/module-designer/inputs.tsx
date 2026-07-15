import { Checkbox } from "../../ui/Checkbox";
import { Field } from "../../ui/Field";
import { JsonField } from "../../ui/JsonField";
import { SelectField } from "../../ui/SelectField";
import { TextArea } from "../../ui/TextArea";
import { TextField } from "../../ui/TextField";
import type { LabeledOption } from "@/types/labeled-option";

/**
 * Thin, designer-flavored wrappers over the shared `ui/*` form primitives. The
 * module designer packs many fields per tab, so these default to the `dense`
 * density; otherwise they delegate label/error chrome to {@link Field} and the
 * control rendering to the canonical primitives (single source of truth — no
 * fork). The bespoke {@link JsonInput} below is the one control with no shared
 * equivalent.
 */

interface TextInputProps {
	disabled?: boolean;
	error?: string;
	hint?: string;
	label: string;
	mono?: boolean;
	onChange: (next: string) => void;
	placeholder?: string;
	required?: boolean;
	value: string;
}

export const TextInput = (props: TextInputProps) => <TextField dense {...props} />;

interface SelectInputProps {
	disabled?: boolean;
	error?: string;
	hint?: string;
	label: string;
	onChange: (next: string) => void;
	options: LabeledOption[];
	value: string;
}

export const SelectInput = (props: SelectInputProps) => <SelectField dense {...props} />;

interface CheckboxInputProps {
	checked: boolean;
	disabled?: boolean;
	label: string;
	onChange: (next: boolean) => void;
}

export const CheckboxInput = ({ label, checked, onChange, disabled }: CheckboxInputProps) => (
	<Checkbox checked={checked} disabled={disabled} label={label} onChange={onChange} />
);

interface TextareaInputProps {
	hint?: string;
	label: string;
	mono?: boolean;
	onChange: (next: string) => void;
	rows?: number;
	value: string;
}

export const TextareaInput = ({
	label,
	value,
	onChange,
	rows = 4,
	hint,
	mono,
}: TextareaInputProps) => (
	<Field hint={hint} label={label}>
		<TextArea
			className="leading-relaxed"
			mono={mono}
			rows={rows}
			spellCheck={false}
			value={value}
			onChange={(e) => onChange(e.target.value)}
		/>
	</Field>
);

interface JsonInputProps {
	hint?: string;
	label: string;
	onChange: (next: Record<string, unknown>) => void;
	rows?: number;
	value: unknown;
}

/**
 * JSON object editor for config blobs we don't have a bespoke UI for yet
 * (view actions, report filters, form hooks). Commits on blur and surfaces
 * a parse error inline; honest about what's stored in the legacy JSONDB.
 */
export const JsonInput = ({ label, value, onChange, hint, rows = 5 }: JsonInputProps) => (
	<JsonField
		hint={hint}
		label={
			<>
				{label} <span className="text-text-3">(JSON)</span>
			</>
		}
		rows={rows}
		value={value}
		onChange={onChange}
	/>
);
