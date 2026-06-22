import { useQuery } from "@tanstack/react-query";

import { dbApi } from "@/api/endpoints/db";

import { Select } from "../../ui/Select";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

/**
 * Column picker that depends on a sibling table setting (`depends_on`). Handles
 * both `db_column` (plain column) and `db_column_sort` (ASC/DESC variants).
 * Disabled with a prompt until the table is chosen, matching the legacy
 * "Please select Table" placeholder.
 */
export const ColumnSelectControl = ({ descriptor, settings, onPatch }: ControlProps) => {
	const table = String(settings[descriptor.depends_on ?? ""] ?? "");
	const sort = descriptor.control === "db_column_sort";

	const columnsQ = useQuery({
		queryKey: ["db", "columns", table, sort],
		queryFn: () => dbApi.columns(table, sort),
		enabled: table !== "",
	});

	const value = String(settings[descriptor.id] ?? "");

	if (table === "") {
		return (
			<ControlShell label={descriptor.label} hint={descriptor.hint} note={descriptor.note}>
				<input
					type="text"
					disabled
					value="Please select a table first"
					className="w-full cursor-not-allowed rounded-md border border-border bg-surface-2 px-3 py-1.5 text-[13px] text-text-3"
				/>
			</ControlShell>
		);
	}

	const options = columnsQ.data ?? [];
	const missing = value !== "" && !options.some((o) => o.value === value);

	return (
		<ControlShell label={descriptor.label} hint={descriptor.hint} note={descriptor.note}>
			<Select
				dense
				value={value}
				disabled={columnsQ.isLoading}
				onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
			>
				<option value="" />
				{missing && <option value={value}>{value}</option>}
				{options.map((o) => (
					<option key={o.value} value={o.value}>
						{o.label}
					</option>
				))}
			</Select>
		</ControlShell>
	);
};
