import { useQuery } from "@tanstack/react-query";

import { Combobox } from "@/components/ui/Combobox";
import { RequiredMarker } from "@/components/ui/Field";
import { dbApi } from "@/api/endpoints/db";

interface DataTableSelectProps {
	/** Selected table name; empty string when nothing is chosen. */
	value: string;
	onChange: (table: string) => void;
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
	const tablesQ = useQuery({
		queryKey: ["db", "tables"],
		queryFn: () => dbApi.tables(),
		staleTime: 5 * 60 * 1000,
	});

	const options = tablesQ.data ?? [];
	const selected = value ? { value, label: value } : null;

	return (
		<div className={className}>
			{label && (
				<span className="mb-1 block text-[12px] font-medium text-text-2">
					{label}
					{required && <RequiredMarker />}
				</span>
			)}
			<Combobox<string>
				value={selected}
				onChange={(option) => onChange(option ? option.value : "")}
				options={options}
				isLoading={tablesQ.isLoading}
				placeholder={placeholder}
				searchPlaceholder="Search tables…"
				emptyLabel="No tables found."
				disabled={disabled}
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
