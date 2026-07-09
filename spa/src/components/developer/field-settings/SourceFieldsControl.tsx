import { Plus, Trash } from "lucide-react";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";
import { IconButton } from "@/components/ui/IconButton";
import { useListEditor } from "@/hooks/useListEditor";

/**
 * Repeatable list of source column names (route generation, geocoding address).
 * Stored as an array of column-name strings. The legacy UI uses column dropdowns
 * bound to the form's table; outside a module-form context there is no backing
 * table, so we accept the column name as free text — the stored value (the
 * column name) is identical either way.
 */
export const SourceFieldsControl = ({ descriptor, settings, onPatch }: ControlProps) => {
	const raw = settings[descriptor.id];
	const rows: string[] = Array.isArray(raw)
		? (raw as string[])
		: typeof raw === "string" && raw !== ""
			? raw.split(",").map((s) => s.trim())
			: [""];

	const commit = (next: string[]) => onPatch({ [descriptor.id]: next });

	const list = useListEditor<string>(rows, commit);
	const update = list.replace;

	const add = () => list.add("");

	// Never collapse to an empty list — the control always shows one blank row.
	const remove = (index: number) => {
		const next = rows.filter((_, i) => i !== index);
		commit(next.length > 0 ? next : [""]);
	};

	return (
		<ControlShell label={descriptor.label} hint={descriptor.hint} note={descriptor.note}>
			<div className="space-y-1.5">
				{rows.map((row, index) => (
					<div key={index} className="flex items-center gap-2">
						<input
							type="text"
							aria-label="Column name"
							className="min-w-0 flex-1 rounded border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
							placeholder="Column name"
							value={row}
							onChange={(e) => update(index, e.target.value)}
						/>
						<IconButton
							tone="danger"
							onClick={() => remove(index)}
							label="Remove source field"
						>
							<Trash size={13} />
						</IconButton>
					</div>
				))}
				<button
					type="button"
					className="inline-flex items-center gap-1 rounded border border-border bg-surface px-2 py-1 text-[12px] hover:bg-hover"
					onClick={add}
				>
					<Plus size={12} />
					Add field
				</button>
			</div>
		</ControlShell>
	);
};
