import { Field } from "@/components/ui/Field";
import { Select } from "@/components/ui/Select";
import { TextInput } from "@/components/ui/TextInput";
import { SectionLabel } from "@/components/ui/SectionLabel";

/** The Open Graph value carried by pages and module entries. */
export interface OpenGraphValue {
	title?: string;
	description?: string;
	type?: string;
	image?: string;
}

interface OpenGraphSectionProps {
	value: OpenGraphValue;
	onChange: (next: OpenGraphValue) => void;
	disabled?: boolean;
}

/**
 * Open Graph metadata inputs for module forms with `open_graph` enabled —
 * the module-form counterpart of the page editor's Sharing tab (same four
 * fields, packed into `__open_graph__` on submit by FormRenderer).
 */
export const OpenGraphSection = ({ value, onChange, disabled }: OpenGraphSectionProps) => {
	const patch = (next: Partial<OpenGraphValue>) => {
		onChange({ ...value, ...next });
	};

	return (
		<div className="mt-5 border-t border-border pt-4">
			<SectionLabel as="h3" className="mb-3">
				Open Graph
			</SectionLabel>

			<div className="flex flex-col gap-[14px]">
				<Field
					label="Title"
					size="sm"
					inlineHint="(defaults to the entry's title if left empty)"
				>
					<TextInput
						dense
						aria-label="Open Graph title"
						value={value.title ?? ""}
						onChange={(e) => patch({ title: e.target.value })}
						disabled={disabled}
					/>
				</Field>

				<Field label="Description" size="sm">
					<TextInput
						dense
						aria-label="Open Graph description"
						value={value.description ?? ""}
						onChange={(e) => patch({ description: e.target.value })}
						disabled={disabled}
					/>
				</Field>

				<div className="grid grid-cols-1 gap-[14px] md:grid-cols-2 md:gap-x-[22px]">
					<Field label="Type" size="sm">
						<Select
							dense
							value={value.type ?? ""}
							onChange={(e) => patch({ type: e.target.value })}
							disabled={disabled}
						>
							<option value="">—</option>
							<option value="website">website</option>
							<option value="article">article</option>
							<option value="profile">profile</option>
							<option value="video.movie">video.movie</option>
						</Select>
					</Field>
					<Field label="Image" size="sm" inlineHint="(min 1200×630)">
						<TextInput
							dense
							aria-label="Open Graph image URL"
							value={value.image ?? ""}
							onChange={(e) => patch({ image: e.target.value })}
							placeholder="https://"
							disabled={disabled}
						/>
					</Field>
				</div>
			</div>
		</div>
	);
};
