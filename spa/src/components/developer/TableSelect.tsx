import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";

import { Combobox, type ComboboxOption } from "@/components/ui/Combobox";
import { auditApi } from "@/api/endpoints/audit";

interface TableSelectProps {
	/** Selected table name, or null when nothing is chosen. */
	value: string | null;
	onChange: (table: string | null) => void;
	placeholder?: string;
	disabled?: boolean;
	id?: string;
	ariaLabel?: string;
	className?: string;
}

/**
 * Searchable select of every database table, used by the audit-trail filter.
 * The table list is small and fully known up front, so we load it once and let
 * the Combobox filter locally rather than round-tripping each keystroke.
 */
export const TableSelect = ({
	value,
	onChange,
	placeholder = "Any table",
	disabled,
	id,
	ariaLabel,
	className,
}: TableSelectProps) => {
	const tablesQ = useQuery({
		queryKey: ["audit-tables"],
		queryFn: () => auditApi.tables(),
		staleTime: 5 * 60 * 1000,
	});

	const options = useMemo<ComboboxOption<string>[]>(
		() => (tablesQ.data ?? []).map((name) => ({ value: name, label: name })),
		[tablesQ.data]
	);

	const selected = value ? { value, label: value } : null;

	return (
		<Combobox<string>
			value={selected}
			onChange={(option) => onChange(option ? option.value : null)}
			options={options}
			isLoading={tablesQ.isLoading}
			placeholder={placeholder}
			searchPlaceholder="Search tables…"
			emptyLabel="No tables found."
			disabled={disabled}
			id={id}
			ariaLabel={ariaLabel}
			className={className}
		/>
	);
};
