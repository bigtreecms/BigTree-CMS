import { Combobox } from "@/components/ui/Combobox";
import { Field } from "@/components/ui/Field";
import { useDbTables } from "@/hooks/useDbTables";

interface DataTableSelectProps {
	ariaLabel?: string;
	className?: string;
	disabled?: boolean;
	error?: string;
	hint?: string;
	id?: string;
	label?: string;
	onChange: (table: string) => void;
	placeholder?: string;
	required?: boolean;
	/** Selected table name; empty string when nothing is chosen. */
	value: string;
}

/**
 * Searchable picker for the site's data tables, backed by GET /db/tables (which
 * lists the non-system tables a module/view/form/feed can be built on). Replaces
 * the free-text "Data table" inputs across the developer designers so a table is
 * always chosen from the real schema rather than typed by hand.
 *
 * The list is small and fully known up front, so it loads once and the Combobox
 * filters locally. A value that isn't in the current list (e.g. a legacy table
 * that's since been excluded) is still shown as the selection.
 */
export const DataTableSelect = ({
	value,
	onChange,
	label,
	hint,
	error,
	required,
	disabled,
	placeholder = "Select a table…",
	id,
	ariaLabel,
	className,
}: DataTableSelectProps) => {
	const tablesQ = useDbTables();

	const options = tablesQ.data ?? [];
	const selected = value ? { value, label: value } : null;

	return (
		<Field
			as="div"
			className={className}
			error={error}
			hint={hint}
			label={label}
			required={required}
		>
			<Combobox<string>
				ariaLabel={ariaLabel ?? label}
				disabled={disabled}
				emptyLabel="No tables found."
				id={id}
				isLoading={tablesQ.isLoading}
				options={options}
				placeholder={placeholder}
				searchPlaceholder="Search tables…"
				value={selected}
				onChange={(option) => onChange(option ? option.value : "")}
			/>
		</Field>
	);
};
