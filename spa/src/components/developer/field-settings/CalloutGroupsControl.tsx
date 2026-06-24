import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Select } from "@/components/ui/Select";
import { calloutsApi } from "@/api/endpoints/callouts";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

/**
 * Callout group picker. Stores an array of group id strings (legacy `groups[]`).
 * If none are chosen all callouts are available.
 */
export const CalloutGroupsControl = ({ descriptor, settings, onPatch }: ControlProps) => {
	const groupsQ = useQuery({
		queryKey: ["callout-groups"],
		queryFn: () => calloutsApi.listGroups(),
	});

	const selected: string[] = Array.isArray(settings[descriptor.id])
		? (settings[descriptor.id] as string[]).map(String)
		: [];

	const [toAdd, setToAdd] = useState("");
	const groups = groupsQ.data ?? [];
	const available = groups.filter((g) => !selected.includes(String(g.id)));

	const labelFor = (id: string) => groups.find((g) => String(g.id) === id)?.name ?? id;

	const commit = (next: string[]) => onPatch({ [descriptor.id]: next });

	const add = () => {
		if (toAdd && !selected.includes(toAdd)) {
			commit([...selected, toAdd]);
			setToAdd("");
		}
	};

	const remove = (id: string) => commit(selected.filter((g) => g !== id));

	return (
		<ControlShell label={descriptor.label} hint={descriptor.hint} note={descriptor.note}>
			<div className="space-y-1.5 rounded-md border border-border bg-surface-2 p-2">
				{selected.length === 0 ? (
					<p className="px-1 text-[11.5px] text-text-3">
						No groups selected — all callouts will be available.
					</p>
				) : (
					<ul className="space-y-1">
						{selected.map((id) => (
							<li
								key={id}
								className="flex items-center justify-between rounded border border-border bg-surface px-2 py-1 text-[12.5px]"
							>
								<span>{labelFor(id)}</span>
								<button
									type="button"
									className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-danger"
									onClick={() => remove(id)}
									aria-label="Remove group"
								>
									<Trash size={12} />
								</button>
							</li>
						))}
					</ul>
				)}
				<div className="flex items-center gap-2">
					<Select
						compact
						className="min-w-0 flex-1"
						value={toAdd}
						disabled={groupsQ.isLoading || available.length === 0}
						onChange={(e) => setToAdd(e.target.value)}
					>
						<option value="">Select a group…</option>
						{available.map((g) => (
							<option key={g.id} value={String(g.id)}>
								{g.name}
							</option>
						))}
					</Select>
					<button
						type="button"
						className="inline-flex items-center gap-1 rounded border border-border bg-surface px-2 py-1 text-[12px] hover:bg-hover disabled:opacity-50"
						onClick={add}
						disabled={!toAdd}
					>
						<Plus size={12} />
						Add
					</button>
				</div>
			</div>
		</ControlShell>
	);
};
