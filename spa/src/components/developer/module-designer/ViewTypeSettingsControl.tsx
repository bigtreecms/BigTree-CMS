import { DataColumnSelect } from "@/components/developer/DataColumnSelect";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { DataTableSelect } from "@/components/developer/DataTableSelect";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";

/**
 * Per-view-type settings — the SPA equivalent of the legacy
 * `ajax/developer/view-settings/{type}.php` gear dialogs. Each view type reads
 * different keys off the shared `settings` blob (nested → nesting_column,
 * grouped → group_field + grouping params, images → image/prefix, …), which the
 * runtime view renderers depend on. Searchable / draggable views carry no extra
 * settings.
 *
 * Settings are merged on every change so generic keys written elsewhere
 * (sort_column, per_page, filter) are never dropped.
 */
interface ViewTypeSettingsControlProps {
	onChange: (next: Record<string, unknown>) => void;
	settings: Record<string, unknown>;
	/** The view's data table; column pickers stay disabled until one is chosen. */
	table: string;
	type: string;
}

const str = (value: unknown): string => (value == null ? "" : String(value));
const truthy = (value: unknown): boolean =>
	value === true || value === "on" || value === "1" || value === 1;

const IMAGE_SORT = [
	{ value: "DESC", label: "Newest first" },
	{ value: "ASC", label: "Oldest first" },
];

const OT_SORT_DIR = [
	{ value: "ASC", label: "Ascending" },
	{ value: "DESC", label: "Descending" },
];

export const ViewTypeSettingsControl = ({
	type,
	table,
	settings,
	onChange,
}: ViewTypeSettingsControlProps) => {
	const set = (patch: Record<string, unknown>) => onChange({ ...settings, ...patch });
	const setKey = (key: string, value: unknown) => {
		if (value === "" || value === false || value == null) {
			const next = { ...settings };
			delete next[key];
			onChange(next);

			return;
		}

		set({ [key]: value });
	};

	if (type === "searchable" || type === "draggable") {
		return null;
	}

	const otherTable = str(settings.other_table);

	const draggable = (note?: string) => (
		<CheckboxInput
			checked={truthy(settings.draggable)}
			label={`Draggable${note ? ` ${note}` : ""}`}
			onChange={(v) => setKey("draggable", v)}
		/>
	);

	const groupingParams = (withSortField: boolean) => (
		<div className="space-y-4 border-t border-border pt-4">
			<SectionLabel>Grouping parameters</SectionLabel>
			<FieldGrid>
				<DataTableSelect
					hint="Optional table whose rows name the groups."
					label="Other table"
					value={otherTable}
					onChange={(v) => setKey("other_table", v)}
				/>
				<DataColumnSelect
					hint="Column on the other table used as the group label."
					label="Title field"
					table={otherTable}
					value={str(settings.title_field)}
					onChange={(v) => setKey("title_field", v)}
				/>
				{withSortField && (
					<>
						<DataColumnSelect
							label="Sort field"
							table={otherTable}
							value={str(settings.ot_sort_field)}
							onChange={(v) => setKey("ot_sort_field", v)}
						/>
						<SelectInput
							label="Sort direction"
							options={OT_SORT_DIR}
							value={str(settings.ot_sort_direction) || "ASC"}
							onChange={(v) => setKey("ot_sort_direction", v)}
						/>
					</>
				)}
			</FieldGrid>
			<TextInput
				mono
				hint="PHP: $item is the group data, set $value to the displayed name."
				label="Group name parser"
				value={str(settings.group_parser)}
				onChange={(v) => setKey("group_parser", v)}
			/>
		</div>
	);

	if (type === "nested") {
		return (
			<DataColumnSelect
				hint='The self-referencing parent column (e.g. "parent").'
				label="Nesting column"
				table={table}
				value={str(settings.nesting_column)}
				onChange={(v) => setKey("nesting_column", v)}
			/>
		);
	}

	if (type === "grouped") {
		return (
			<div className="space-y-4">
				{draggable()}
				<FieldGrid>
					<DataColumnSelect
						label="Group field"
						table={table}
						value={str(settings.group_field)}
						onChange={(v) => setKey("group_field", v)}
					/>
					<DataColumnSelect
						hint="Used when the view is not draggable."
						label="Sort inside groups"
						table={table}
						value={str(settings.sort)}
						onChange={(v) => setKey("sort", v)}
					/>
				</FieldGrid>
				{groupingParams(true)}
			</div>
		);
	}

	if (type === "images" || type === "images-grouped") {
		const grouped = type === "images-grouped";

		return (
			<div className="space-y-4">
				{draggable()}
				<FieldGrid>
					<DataColumnSelect
						label="Image field"
						table={table}
						value={str(settings.image)}
						onChange={(v) => setKey("image", v)}
					/>
					<TextInput
						hint='For thumbnails, e.g. "thumb_".'
						label="Image prefix"
						value={str(settings.prefix)}
						onChange={(v) => setKey("prefix", v)}
					/>
					{grouped && (
						<DataColumnSelect
							label="Group field"
							table={table}
							value={str(settings.group_field)}
							onChange={(v) => setKey("group_field", v)}
						/>
					)}
					<SelectInput
						label="Sort direction"
						options={IMAGE_SORT}
						value={str(settings.sort) || "DESC"}
						onChange={(v) => setKey("sort", v)}
					/>
				</FieldGrid>
				{grouped && groupingParams(false)}
			</div>
		);
	}

	return null;
};
