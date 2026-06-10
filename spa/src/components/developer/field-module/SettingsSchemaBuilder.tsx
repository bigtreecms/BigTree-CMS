import { ChevronDown, ChevronUp, Plus, Trash2 } from "lucide-react";

import type { SettingControl, SettingDescriptor } from "@/api/endpoints/field-types";
import { INPUT_CLASS } from "@/renderer/fields/types";

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

const Labeled = ({ label, children }: { label: string; children: React.ReactNode }) => (
	<label className="block">
		<span className="mb-1 block text-[11px] font-medium text-text-3">{label}</span>
		{children}
	</label>
);

/**
 * Builds a module field type's settings schema (SettingDescriptor[]) — the
 * configuration shown when the field is placed on a form. Mirrors the
 * InputSchemaBuilder pattern; the result is stored in settings.js and read back
 * by the module as host.field.settings.
 */
export const SettingsSchemaBuilder = ({ value, onChange }: SettingsSchemaBuilderProps) => {
	const patch = (index: number, next: Partial<SettingDescriptor>) =>
		onChange(value.map((d, i) => (i === index ? { ...d, ...next } : d)));

	const remove = (index: number) => onChange(value.filter((_, i) => i !== index));

	const move = (index: number, dir: -1 | 1) => {
		const target = index + dir;

		if (target < 0 || target >= value.length) {
			return;
		}

		const copy = [...value];
		const moved = copy[index]!;
		copy[index] = copy[target]!;
		copy[target] = moved;
		onChange(copy);
	};

	const add = () => onChange([...value, { id: "", control: "string", label: "" }]);

	const optionsOf = (descriptor: SettingDescriptor): EnumOption[] =>
		Array.isArray(descriptor.options) ? (descriptor.options as EnumOption[]) : [];

	return (
		<div className="space-y-2">
			{value.length === 0 && (
				<p className="rounded-md border border-dashed border-border bg-surface-2 p-3 text-[12px] text-text-3">
					No settings. Add one to let editors configure this field — read them in your
					code as <code>host.field.settings</code>.
				</p>
			)}

			{value.map((descriptor, index) => {
				const control = descriptor.control;

				return (
					<div key={index} className="rounded-md border border-border bg-surface-2 p-3">
						<div className="grid grid-cols-1 gap-3 md:grid-cols-3">
							<Labeled label="Key">
								<input
									className={INPUT_CLASS}
									value={descriptor.id}
									onChange={(e) => patch(index, { id: e.target.value })}
									placeholder="e.g. placeholder"
								/>
							</Labeled>
							<Labeled label="Control">
								<select
									className={INPUT_CLASS}
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
								</select>
							</Labeled>
							<Labeled label="Label">
								<input
									className={INPUT_CLASS}
									value={descriptor.label ?? ""}
									onChange={(e) => patch(index, { label: e.target.value })}
								/>
							</Labeled>
						</div>

						<div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
							<Labeled label="Hint (optional)">
								<input
									className={INPUT_CLASS}
									value={descriptor.hint ?? ""}
									onChange={(e) => patch(index, { hint: e.target.value })}
								/>
							</Labeled>

							<Labeled label="Default (optional)">
								{control === "bool" ? (
									<label className="flex h-[38px] items-center gap-2 text-[12.5px] text-text-2">
										<input
											type="checkbox"
											className="h-4 w-4 accent-accent"
											checked={!!descriptor.default}
											onChange={(e) =>
												patch(index, { default: e.target.checked })
											}
										/>
										Checked by default
									</label>
								) : (
									<input
										className={INPUT_CLASS}
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
							</Labeled>

							<div className="flex items-end justify-between gap-1 pb-1">
								<label className="flex items-center gap-2 text-[12.5px] text-text-2">
									<input
										type="checkbox"
										className="h-4 w-4 accent-accent"
										checked={!!descriptor.required}
										onChange={(e) =>
											patch(index, { required: e.target.checked })
										}
									/>
									Required
								</label>
								<div className="flex items-end gap-1">
									<button
										type="button"
										onClick={() => move(index, -1)}
										disabled={index === 0}
										className="rounded-md border border-border bg-surface p-1.5 text-text-2 disabled:opacity-40 hover:bg-hover"
										title="Move up"
									>
										<ChevronUp size={14} />
									</button>
									<button
										type="button"
										onClick={() => move(index, 1)}
										disabled={index === value.length - 1}
										className="rounded-md border border-border bg-surface p-1.5 text-text-2 disabled:opacity-40 hover:bg-hover"
										title="Move down"
									>
										<ChevronDown size={14} />
									</button>
									<button
										type="button"
										onClick={() => remove(index)}
										className="rounded-md border border-border bg-surface p-1.5 text-danger hover:bg-danger/5"
										title="Remove"
									>
										<Trash2 size={14} />
									</button>
								</div>
							</div>
						</div>

						{control === "enum" && (
							<div className="mt-3 space-y-2 border-t border-border pt-3">
								<div className="text-[11px] font-medium text-text-3">Options</div>
								{optionsOf(descriptor).map((option, optionIndex) => (
									<div key={optionIndex} className="flex items-center gap-2">
										<input
											className={INPUT_CLASS}
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
										<input
											className={INPUT_CLASS}
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

			<button
				type="button"
				onClick={add}
				className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] text-text-2 hover:bg-hover"
			>
				<Plus size={14} />
				Add setting
			</button>
		</div>
	);
};
