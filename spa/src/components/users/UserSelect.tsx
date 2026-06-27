import { useEffect, useState } from "react";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import { keepPreviousData, useQuery } from "@tanstack/react-query";

import { Combobox, type ComboboxOption } from "@/components/ui/Combobox";
import { usersApi, type UserListItem } from "@/api/endpoints/users";
import { queryKeys } from "@/lib/queryKeys";

interface UserSelectProps {
	/** Selected user id, or null when nothing is chosen. */
	value: number | null;
	onChange: (id: number | null) => void;
	placeholder?: string;
	disabled?: boolean;
	id?: string;
	ariaLabel?: string;
	className?: string;
}

const toOption = (
	user: UserListItem | { id: number; name: string; email: string }
): ComboboxOption<number> => ({
	value: user.id,
	label: user.name || user.email || `User #${user.id}`,
	sublabel: user.email || undefined,
});

/**
 * Searchable user picker, reusable across the SPA. Wraps the generic Combobox
 * with an async search against `/users?q=` (debounced). The selected option is
 * cached locally so the trigger keeps showing the right name as the search
 * results change underneath it; when mounted with a `value` we don't already
 * have an option for (e.g. a deep link), we resolve it via `/users/{id}`.
 */
export const UserSelect = ({
	value,
	onChange,
	placeholder = "Any user",
	disabled,
	id,
	ariaLabel,
	className,
}: UserSelectProps) => {
	const [query, setQuery] = useState("");
	const debounced = useDebouncedValue(query.trim(), 200);
	const [selected, setSelected] = useState<ComboboxOption<number> | null>(null);

	const listQ = useQuery({
		queryKey: queryKeys.userSelect.search(debounced),
		queryFn: () => usersApi.list({ q: debounced || undefined, per_page: 25 }),
		placeholderData: keepPreviousData,
	});

	// Resolve the label for a pre-set value we don't have an option for yet
	// (deep links, restored filters). Skipped once we hold the matching option.
	const resolveQ = useQuery({
		queryKey: queryKeys.userSelect.resolve(value),
		queryFn: () => usersApi.get(value as number),
		enabled: value !== null && selected?.value !== value,
	});

	useEffect(() => {
		if (value === null) {
			setSelected(null);

			return;
		}

		if (resolveQ.data && resolveQ.data.id === value) {
			setSelected(toOption(resolveQ.data));
		}
	}, [value, resolveQ.data]);

	const options = (listQ.data?.items ?? []).map(toOption);

	return (
		<Combobox<number>
			value={selected}
			onChange={(option) => {
				setSelected(option);
				onChange(option ? option.value : null);
			}}
			options={options}
			onSearchChange={setQuery}
			isLoading={listQ.isFetching && !listQ.data}
			placeholder={placeholder}
			searchPlaceholder="Search by name or email…"
			emptyLabel="No users found."
			disabled={disabled}
			id={id}
			ariaLabel={ariaLabel}
			className={className}
		/>
	);
};
