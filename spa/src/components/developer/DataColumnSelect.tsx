import { useQuery } from "@tanstack/react-query";

import { Combobox } from "@/components/ui/Combobox";
import { dbApi } from "@/api/endpoints/db";

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
	const columnsQ = useQuery({
		queryKey: ["db", "columns", table],
		queryFn: () => dbApi.columns(table),
		enabled: table !== "",
		staleTime: 5 * 60 * 1000,
	});

	const options = columnsQ.data ?? [];
	const selected = value ? { value, label: value } : null;

	return (
		<div className={className}>
			{label && (
				<span className="mb-1 block text-[12px] font-medium text-text-2">
					{label}
					{required && <span className="text-danger"> *</span>}
				</span>
			)}
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
			{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
			{error && (
				<span data-field-error className="mt-1 block text-[11.5px] text-danger">
					{error}
				</span>
			)}
		</div>
	);
};
