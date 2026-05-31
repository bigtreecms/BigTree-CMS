import { useQuery } from "@tanstack/react-query";

import { dbApi } from "@/api/endpoints/db";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

/** Database table picker (list "db" source, one-to-many, many-to-many). */
export const TableSelectControl = ({ descriptor, settings, onPatch }: ControlProps) => {
	const tablesQ = useQuery({ queryKey: ["db", "tables"], queryFn: () => dbApi.tables() });
	const value = String(settings[descriptor.id] ?? "");
	const options = tablesQ.data ?? [];
	const missing = value !== "" && !options.some((o) => o.value === value);

	return (
		<ControlShell label={descriptor.label} hint={descriptor.hint} note={descriptor.note}>
			<select
				className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:opacity-50"
				value={value}
				disabled={tablesQ.isLoading}
				onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
			>
				<option value="" />
				{missing && <option value={value}>{value}</option>}
				{options.map((o) => (
					<option key={o.value} value={o.value}>
						{o.label}
					</option>
				))}
			</select>
		</ControlShell>
	);
};
