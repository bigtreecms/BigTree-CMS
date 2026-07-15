import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";
import type { LabeledOption } from "@/types/labeled-option";

/** A single per-service credential field descriptor. */
export interface ServiceSettingField {
	key: string;
	label: string;
	options?: LabeledOption[];
	type?: "select";
}

/** Secret-ish keys are rendered masked and get a "stored, leave blank to keep" hint. */
export const isMaskedKey = (key: string): boolean =>
	/secret|key|password|token|signature/i.test(key);

interface ServiceSettingsFieldsProps {
	fields: ServiceSettingField[];
	onChange: (key: string, value: string) => void;
	settings: Record<string, unknown>;
}

/**
 * Renders a service's credential fields from a `ServiceSettingField[]`
 * descriptor — the declarative shape `ConfigurePaymentGateway` uses. Masked keys
 * become password inputs that show a "stored" placeholder when the server
 * reports a `${key}-set` flag; `select` descriptors render a `SelectField`.
 */
export const ServiceSettingsFields = ({
	fields,
	settings,
	onChange,
}: ServiceSettingsFieldsProps) => (
	<>
		{fields.map((f) => {
			const masked = isMaskedKey(f.key);
			const isSet = !!settings[`${f.key}-set`];
			const value = (settings[f.key] as string) ?? "";

			if (f.type === "select" && f.options) {
				return (
					<SelectField
						key={f.key}
						label={f.label}
						options={f.options}
						value={value}
						onChange={(v) => onChange(f.key, v)}
					/>
				);
			}

			return (
				<Field key={f.key} label={f.label}>
					<TextInput
						autoComplete="off"
						placeholder={
							masked && isSet ? "•••••••• (stored, leave blank to keep)" : ""
						}
						type={masked ? "password" : "text"}
						value={value}
						onChange={(e) => onChange(f.key, e.target.value)}
					/>
				</Field>
			);
		})}
	</>
);
