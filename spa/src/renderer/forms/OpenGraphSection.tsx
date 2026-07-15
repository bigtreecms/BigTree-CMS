import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";
import { TextInput } from "@/components/ui/TextInput";
import { SectionLabel } from "@/components/ui/SectionLabel";

/** The Open Graph value carried by pages and module entries. */
export interface OpenGraphValue {
	description?: string;
	image?: string;
	title?: string;
	type?: string;
}

interface OpenGraphSectionProps {
	disabled?: boolean;
	onChange: (next: OpenGraphValue) => void;
	value: OpenGraphValue;
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
					inlineHint="(defaults to the entry's title if left empty)"
					label="Title"
					size="sm"
				>
					<TextInput
						dense
						aria-label="Open Graph title"
						disabled={disabled}
						value={value.title ?? ""}
						onChange={(e) => patch({ title: e.target.value })}
					/>
				</Field>

				<Field label="Description" size="sm">
					<TextInput
						dense
						aria-label="Open Graph description"
						disabled={disabled}
						value={value.description ?? ""}
						onChange={(e) => patch({ description: e.target.value })}
					/>
				</Field>

				<div className="grid grid-cols-1 gap-[14px] md:grid-cols-2 md:gap-x-[22px]">
					<SelectField
						dense
						disabled={disabled}
						label="Type"
						options={[
							{ value: "", label: "—" },
							{ value: "website", label: "website" },
							{ value: "article", label: "article" },
							{ value: "profile", label: "profile" },
							{ value: "video.movie", label: "video.movie" },
						]}
						size="sm"
						value={value.type ?? ""}
						onChange={(v) => patch({ type: v })}
					/>
					<Field inlineHint="(min 1200×630)" label="Image" size="sm">
						<TextInput
							dense
							aria-label="Open Graph image URL"
							disabled={disabled}
							placeholder="https://"
							value={value.image ?? ""}
							onChange={(e) => patch({ image: e.target.value })}
						/>
					</Field>
				</div>
			</div>
		</div>
	);
};
