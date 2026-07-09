import { Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { RowReorderControls } from "@/components/ui/RowReorderControls";
import { Select } from "@/components/ui/Select";
import { TextInput } from "@/components/ui/TextInput";
import type { SettingControl, SettingDescriptor } from "@/api/endpoints/field-types";
import { SchemaFieldLabel } from "@/components/developer/SchemaFieldLabel";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { useListEditor } from "@/hooks/useListEditor";

interface SettingsSchemaBuilderProps {
	value: SettingDescriptor[];
	onChange: (next: SettingDescriptor[]) => void;
}

/** Setting controls a module author can build (the simple, value-bearing set). */
const SETTING_CONTROLS: Array<{ value: SettingControl; label: string }> = [
	{ value: "string", label: "Text" },
	{ value: "int", label: "Number" },
	{ value: "textarea", label: "Textarea" },
	{ value: "bool", label: "Checkbox" },
	{ value: "enum", label: "Select" },
];

type EnumOption = { value: string; label: string };

const optionsOf = (descriptor: SettingDescriptor): EnumOption[] =>
	Array.isArray(descriptor.options) ? (descriptor.options as EnumOption[]) : [];

/**
 * Builds a module field type's settings schema (SettingDescriptor[]) — the
 * configuration shown when the field is placed on a form. Mirrors the
 * InputSchemaBuilder pattern; the result is stored in settings.js and read back
 * by the module as host.field.settings.
 */
export const SettingsSchemaBuilder = ({ value, onChange }: SettingsSchemaBuilderProps) => {
	const { update: patch, remove, move } = useListEditor<SettingDescriptor>(value, onChange);

	const add = () => onChange([...value, { id: "", control: "string", label: "" }]);

	return (
		<div className="space-y-2">
			{value.length === 0 && (
				<InlineEmpty pad="md">
					No settings. Add one to let editors configure this field — read them in your
					code as <code>host.field.settings</code>.
				</InlineEmpty>
			)}

			{value.map((descriptor, index) => {
				const control = descriptor.control;

				return (
					<div key={index} className="rounded-md border border-border bg-surface-2 p-3">
						<div className="grid grid-cols-1 gap-3 md:grid-cols-3">
							<SchemaFieldLabel label="Key">
								<TextInput
									value={descriptor.id}
									onChange={(e) => patch(index, { id: e.target.value })}
									placeholder="e.g. placeholder"
								/>
							</SchemaFieldLabel>
							<SchemaFieldLabel label="Control">
								<Select
									value={control}
									onChange={(e) =>
										patch(index, { control: e.target.value as SettingControl })
									}
								>
									{SETTING_CONTROLS.map((c) => (
										<option key={c.value} value={c.value}>
											{c.label}
										</option>
									))}
								</Select>
							</SchemaFieldLabel>
							<SchemaFieldLabel label="Label">
								<TextInput
									value={descriptor.label ?? ""}
									onChange={(e) => patch(index, { label: e.target.value })}
								/>
							</SchemaFieldLabel>
						</div>

						<div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
							<SchemaFieldLabel label="Hint (optional)">
								<TextInput
									value={descriptor.hint ?? ""}
									onChange={(e) => patch(index, { hint: e.target.value })}
								/>
							</SchemaFieldLabel>

							<SchemaFieldLabel label="Default (optional)">
								{control === "bool" ? (
									<Checkbox
										className="h-[38px]"
										label="Checked by default"
										checked={!!descriptor.default}
										onChange={(next) => patch(index, { default: next })}
									/>
								) : (
									<TextInput
										type={control === "int" ? "number" : "text"}
										value={
											descriptor.default === undefined ||
											descriptor.default === null
												? ""
												: String(descriptor.default)
										}
										onChange={(e) =>
											patch(index, {
												default:
													control === "int"
														? e.target.value === ""
															? undefined
															: Number(e.target.value)
														: e.target.value,
											})
										}
									/>
								)}
							</SchemaFieldLabel>

							<div className="flex items-end justify-between gap-1 pb-1">
								<Checkbox
									label="Required"
									checked={!!descriptor.required}
									onChange={(next) => patch(index, { required: next })}
								/>
								<RowReorderControls
									onMoveUp={() => move(index, index - 1)}
									onMoveDown={() => move(index, index + 1)}
									onRemove={() => remove(index)}
									isFirst={index === 0}
									isLast={index === value.length - 1}
									itemLabel="setting"
								/>
							</div>
						</div>

						{control === "enum" && (
							<div className="mt-3 space-y-2 border-t border-border pt-3">
								<div className="text-[11px] font-medium text-text-3">Options</div>
								{optionsOf(descriptor).map((option, optionIndex) => (
									<div key={optionIndex} className="flex items-center gap-2">
										<TextInput
											value={option.value}
											placeholder="value"
											onChange={(e) => {
												const next = [...optionsOf(descriptor)];
												next[optionIndex] = {
													...option,
													value: e.target.value,
												};
												patch(index, { options: next });
											}}
										/>
										<TextInput
											value={option.label}
											placeholder="label"
											onChange={(e) => {
												const next = [...optionsOf(descriptor)];
												next[optionIndex] = {
													...option,
													label: e.target.value,
												};
												patch(index, { options: next });
											}}
										/>
										<button
											type="button"
											onClick={() =>
												patch(index, {
													options: optionsOf(descriptor).filter(
														(_, i) => i !== optionIndex
													),
												})
											}
											className="rounded-md border border-border bg-surface p-1.5 text-danger hover:bg-danger/5"
											title="Remove option"
										>
											<Trash2 size={14} />
										</button>
									</div>
								))}
								<button
									type="button"
									onClick={() =>
										patch(index, {
											options: [
												...optionsOf(descriptor),
												{ value: "", label: "" },
											],
										})
									}
									className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-1 text-[11.5px] text-text-2 hover:bg-hover"
								>
									<Plus size={13} />
									Add option
								</button>
							</div>
						)}
					</div>
				);
			})}

			<Button variant="secondary" icon={<Plus size={14} />} onClick={add}>
				Add setting
			</Button>
		</div>
	);
};
