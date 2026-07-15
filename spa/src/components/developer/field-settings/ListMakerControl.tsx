import { Plus, Trash } from "lucide-react";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";
import { IconButton } from "@/components/ui/IconButton";
import { TextInput } from "@/components/ui/TextInput";
import { useListEditor } from "@/hooks/useListEditor";

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

	const list = useListEditor<Row>(rows, commit);

	const update = (index: number, key: string, value: string) =>
		list.update(index, { [key]: value });

	const add = () => list.add(Object.fromEntries(keys.map((k) => [k, ""])));

	const remove = list.remove;

	return (
		<ControlShell hint={descriptor.hint} label={descriptor.label} note={descriptor.note}>
			<div className="space-y-1.5 rounded-md border border-border bg-surface-2 p-2">
				{rows.length > 0 && (
					<div className="flex gap-2 px-1 text-[10.5px] font-medium uppercase tracking-wide text-text-3">
						{columns.map((c) => (
							<span className="flex-1" key={c}>
								{c}
							</span>
						))}
						<span className="w-6" />
					</div>
				)}
				{rows.map((row, index) => (
					<div className="flex items-center gap-2" key={index}>
						{keys.map((key) => (
							<TextInput
								compact
								aria-label={key}
								className="min-w-0 flex-1"
								key={key}
								value={row[key] ?? ""}
								onChange={(e) => update(index, key, e.target.value)}
							/>
						))}
						<IconButton
							label="Remove option"
							tone="danger"
							onClick={() => remove(index)}
						>
							<Trash size={13} />
						</IconButton>
					</div>
				))}
				<button
					className="inline-flex items-center gap-1 rounded border border-border bg-surface px-2 py-1 text-[12px] hover:bg-hover"
					type="button"
					onClick={add}
				>
					<Plus size={12} />
					Add option
				</button>
			</div>
		</ControlShell>
	);
};
