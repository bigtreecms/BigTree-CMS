import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";

import { Combobox, type ComboboxOption } from "@/components/ui/Combobox";
import { auditApi } from "@/api/endpoints/audit";
import { queryKeys } from "@/lib/queryKeys";

interface TableSelectProps {
	ariaLabel?: string;
	className?: string;
	disabled?: boolean;
	id?: string;
	onChange: (table: string | null) => void;
	placeholder?: string;
	/** Selected table name, or null when nothing is chosen. */
	value: string | null;
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
		queryKey: queryKeys.audit.tables(),
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
			ariaLabel={ariaLabel}
			className={className}
			disabled={disabled}
			emptyLabel="No tables found."
			id={id}
			isLoading={tablesQ.isLoading}
			options={options}
			placeholder={placeholder}
			searchPlaceholder="Search tables…"
			value={selected}
			onChange={(option) => onChange(option ? option.value : null)}
		/>
	);
};
