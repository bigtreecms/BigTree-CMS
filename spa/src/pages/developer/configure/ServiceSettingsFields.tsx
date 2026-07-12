import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";

/** A single per-service credential field descriptor. */
export interface ServiceSettingField {
	key: string;
	label: string;
	type?: "select";
	options?: Array<{ value: string; label: string }>;
}

/** Secret-ish keys are rendered masked and get a "stored, leave blank to keep" hint. */
export const isMaskedKey = (key: string): boolean =>
	/secret|key|password|token|signature/i.test(key);

interface ServiceSettingsFieldsProps {
	fields: ServiceSettingField[];
	settings: Record<string, unknown>;
	onChange: (key: string, value: string) => void;
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
						value={value}
						onChange={(v) => onChange(f.key, v)}
						options={f.options}
					/>
				);
			}

			return (
				<Field key={f.key} label={f.label}>
					<TextInput
						type={masked ? "password" : "text"}
						value={value}
						placeholder={
							masked && isSet ? "•••••••• (stored, leave blank to keep)" : ""
						}
						onChange={(e) => onChange(f.key, e.target.value)}
						autoComplete="off"
					/>
				</Field>
			);
		})}
	</>
);
