import { Combobox } from "@/components/ui/Combobox";
import { Field } from "@/components/ui/Field";
import { useDbColumns } from "@/hooks/useDbColumns";

interface DataColumnSelectProps {
	/** Table whose columns are offered; when empty the picker is disabled. */
	table: string;
	/** Selected column name; empty string when nothing is chosen. */
	value: string;
	onChange: (column: string) => void;
	label?: string;
	hint?: string;
	error?: string;
	required?: boolean;
	disabled?: boolean;
	placeholder?: string;
	id?: string;
	ariaLabel?: string;
	className?: string;
}

/**
 * Searchable picker for the columns of a chosen table, backed by
 * GET /db/tables/{table}/columns. Companion to {@link DataTableSelect} for the
 * developer designers — once a table is selected, column names are picked from
 * the real schema rather than typed by hand.
 *
 * Disabled until a table is chosen. A value that isn't in the current column
 * list (e.g. a parser-only "custom" column on an existing view) is still shown
 * as the selection so editing never silently drops it.
 */
export const DataColumnSelect = ({
	table,
	value,
	onChange,
	label,
	hint,
	error,
	required,
	disabled,
	placeholder = "Select a column…",
	id,
	ariaLabel,
	className,
}: DataColumnSelectProps) => {
	const columnsQ = useDbColumns(table);

	const options = columnsQ.data ?? [];
	const selected = value ? { value, label: value } : null;

	return (
		<Field
			as="div"
			label={label}
			hint={hint}
			error={error}
			required={required}
			className={className}
		>
			<Combobox<string>
				value={selected}
				onChange={(option) => onChange(option ? option.value : "")}
				options={options}
				isLoading={columnsQ.isLoading}
				placeholder={table === "" ? "Select a table first" : placeholder}
				searchPlaceholder="Search columns…"
				emptyLabel="No columns found."
				disabled={disabled || table === ""}
				id={id}
				ariaLabel={ariaLabel ?? label}
			/>
		</Field>
	);
};
