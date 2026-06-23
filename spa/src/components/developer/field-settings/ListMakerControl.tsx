import { Plus, Trash } from "lucide-react";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";
import { IconButton } from "@/components/ui/IconButton";

type Row = Record<string, string>;

/**
 * Static value/description list (legacy BigTreeListMaker). Stores an array of
 * `{ value, description }` objects — the shape the list field's draw.php reads.
 */
export const ListMakerControl = ({ descriptor, settings, onPatch }: ControlProps) => {
	const keys = descriptor.keys ?? ["value", "description"];
	const columns = descriptor.columns ?? ["Value", "Description"];
	const rows: Row[] = Array.isArray(settings[descriptor.id])
		? (settings[descriptor.id] as Row[])
		: [];

	const commit = (next: Row[]) => onPatch({ [descriptor.id]: next });

	const update = (index: number, key: string, value: string) => {
		commit(rows.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
	};

	const add = () => commit([...rows, Object.fromEntries(keys.map((k) => [k, ""]))]);

	const remove = (index: number) => commit(rows.filter((_, i) => i !== index));

	return (
		<ControlShell label={descriptor.label} hint={descriptor.hint} note={descriptor.note}>
			<div className="space-y-1.5 rounded-md border border-border bg-surface-2 p-2">
				{rows.length > 0 && (
					<div className="flex gap-2 px-1 text-[10.5px] font-medium uppercase tracking-wide text-text-3">
						{columns.map((c) => (
							<span key={c} className="flex-1">
								{c}
							</span>
						))}
						<span className="w-6" />
					</div>
				)}
				{rows.map((row, index) => (
					<div key={index} className="flex items-center gap-2">
						{keys.map((key) => (
							<input
								key={key}
								type="text"
								aria-label={key}
								className="min-w-0 flex-1 rounded border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								value={row[key] ?? ""}
								onChange={(e) => update(index, key, e.target.value)}
							/>
						))}
						<IconButton
							tone="danger"
							onClick={() => remove(index)}
							label="Remove option"
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
					Add option
				</button>
			</div>
		</ControlShell>
	);
};
