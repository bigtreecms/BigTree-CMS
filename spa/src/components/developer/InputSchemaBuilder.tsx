import { useState } from "react";
import { ChevronDown, ChevronUp, Plus, Settings2, Trash2 } from "lucide-react";

import type { InputDescriptor } from "@/api/endpoints/field-types";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Select } from "@/components/ui/Select";
import { TextInput } from "@/components/ui/TextInput";
import { FieldSettingsEditor } from "@/components/developer/FieldSettingsEditor";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

interface InputSchemaBuilderProps {
	value: InputDescriptor[];
	onChange: (next: InputDescriptor[]) => void;
}

/** Primitive field types offered as declarative sub-fields. */
const PRIMITIVE_TYPES: Array<{ value: string; label: string }> = [
	{ value: "text", label: "Text" },
	{ value: "textarea", label: "Textarea" },
	{ value: "html", label: "HTML" },
	{ value: "number", label: "Number" },
	{ value: "list", label: "Select" },
	{ value: "checkbox", label: "Checkbox" },
	{ value: "radio", label: "Radio" },
	{ value: "date", label: "Date" },
	{ value: "time", label: "Time" },
	{ value: "datetime", label: "Date & Time" },
	{ value: "color", label: "Color" },
	{ value: "link", label: "Link" },
	{ value: "upload", label: "File" },
	{ value: "image", label: "Image" },
	{ value: "video", label: "Video" },
];

const Labeled = ({ label, children }: { label: string; children: React.ReactNode }) => (
	<label className="block">
		<span className="mb-1 block text-[11px] font-medium text-text-3">{label}</span>
		{children}
	</label>
);

/**
 * Authors a declarative field type's `input_schema`: an ordered list of
 * primitive sub-fields composed into one object value. Each row sets the
 * sub-field's key (`id`), primitive `type`, label, and optional per-sub-field
 * settings (reusing the schema-driven FieldSettingsEditor). See
 * spa/.custom-field-types-design.md (Tier 1).
 */
export const InputSchemaBuilder = ({ value, onChange }: InputSchemaBuilderProps) => {
	const [openSettings, setOpenSettings] = useState<number | null>(null);

	const patch = (index: number, next: Partial<InputDescriptor>) =>
		onChange(value.map((d, i) => (i === index ? { ...d, ...next } : d)));

	const remove = (index: number) => {
		onChange(value.filter((_, i) => i !== index));

		if (openSettings === index) {
			setOpenSettings(null);
		}
	};

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

	const add = () => onChange([...value, { id: "", type: "text", title: "" }]);

	return (
		<div className="space-y-2">
			{value.length === 0 && (
				<InlineEmpty pad="md">
					No sub-fields yet. Add one to compose this field type from primitives.
				</InlineEmpty>
			)}

			{value.map((descriptor, index) => (
				<div key={index} className="rounded-md border border-border bg-surface-2 p-3">
					<div className="grid grid-cols-1 gap-3 md:grid-cols-3">
						<Labeled label="Key">
							<TextInput
								value={descriptor.id}
								onChange={(e) => patch(index, { id: e.target.value })}
								placeholder="e.g. first_name"
							/>
						</Labeled>
						<Labeled label="Type">
							<Select
								value={descriptor.type}
								onChange={(e) => patch(index, { type: e.target.value })}
							>
								{PRIMITIVE_TYPES.map((t) => (
									<option key={t.value} value={t.value}>
										{t.label}
									</option>
								))}
							</Select>
						</Labeled>
						<Labeled label="Label">
							<TextInput
								value={descriptor.title ?? ""}
								onChange={(e) => patch(index, { title: e.target.value })}
								placeholder="Shown above the field"
							/>
						</Labeled>
					</div>

					<div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
						<Labeled label="Hint (optional)">
							<TextInput
								value={descriptor.subtitle ?? ""}
								onChange={(e) => patch(index, { subtitle: e.target.value })}
							/>
						</Labeled>
						<Checkbox
							className="self-end pb-2"
							label="Required"
							checked={!!descriptor.required}
							onChange={(next) => patch(index, { required: next })}
						/>
						<div className="flex items-end justify-end gap-1 pb-1">
							<button
								type="button"
								onClick={() =>
									setOpenSettings(openSettings === index ? null : index)
								}
								className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-1 text-[11.5px] text-text-2 hover:bg-hover"
								title="Field settings"
							>
								<Settings2 size={13} />
								Settings
							</button>
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

					{openSettings === index && (
						<div className="mt-3 border-t border-border pt-3">
							<FieldSettingsEditor
								type={descriptor.type}
								useCase="modules"
								value={descriptor.settings}
								onChange={(next) => patch(index, { settings: next })}
								hideLabel
							/>
						</div>
					)}
				</div>
			))}

			<Button variant="secondary" icon={<Plus size={14} />} onClick={add}>
				Add sub-field
			</Button>
		</div>
	);
};
