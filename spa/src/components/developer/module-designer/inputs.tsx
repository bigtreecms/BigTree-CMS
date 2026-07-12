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
	label: string;
	value: string;
	onChange: (next: string) => void;
	hint?: string;
	error?: string;
	disabled?: boolean;
	required?: boolean;
	placeholder?: string;
	mono?: boolean;
}

export const TextInput = (props: TextInputProps) => <TextField dense {...props} />;

interface SelectInputProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	options: LabeledOption[];
	hint?: string;
	error?: string;
	disabled?: boolean;
}

export const SelectInput = (props: SelectInputProps) => <SelectField dense {...props} />;

interface CheckboxInputProps {
	label: string;
	checked: boolean;
	onChange: (next: boolean) => void;
	disabled?: boolean;
}

export const CheckboxInput = ({ label, checked, onChange, disabled }: CheckboxInputProps) => (
	<Checkbox label={label} checked={checked} onChange={onChange} disabled={disabled} />
);

interface TextareaInputProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	rows?: number;
	hint?: string;
	mono?: boolean;
}

export const TextareaInput = ({
	label,
	value,
	onChange,
	rows = 4,
	hint,
	mono,
}: TextareaInputProps) => (
	<Field label={label} hint={hint}>
		<TextArea
			mono={mono}
			rows={rows}
			value={value}
			onChange={(e) => onChange(e.target.value)}
			spellCheck={false}
			className="leading-relaxed"
		/>
	</Field>
);

interface JsonInputProps {
	label: string;
	value: unknown;
	onChange: (next: Record<string, unknown>) => void;
	hint?: string;
	rows?: number;
}

/**
 * JSON object editor for config blobs we don't have a bespoke UI for yet
 * (view actions, report filters, form hooks). Commits on blur and surfaces
 * a parse error inline; honest about what's stored in the legacy JSONDB.
 */
export const JsonInput = ({ label, value, onChange, hint, rows = 5 }: JsonInputProps) => (
	<JsonField
		label={
			<>
				{label} <span className="text-text-3">(JSON)</span>
			</>
		}
		value={value}
		onChange={onChange}
		hint={hint}
		rows={rows}
	/>
);
