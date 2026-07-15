import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Plus, Trash } from "lucide-react";

import { fieldTypesApi, fieldTypesForUseCase } from "@/api/endpoints/field-types";
import { queryKeys } from "@/lib/queryKeys";

import { FieldSettingsEditor } from "../FieldSettingsEditor";
import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";
import { Checkbox } from "@/components/ui/Checkbox";
import { IconButton } from "@/components/ui/IconButton";
import { Select } from "@/components/ui/Select";
import { TextInput } from "@/components/ui/TextInput";
import { useListEditor } from "@/hooks/useListEditor";

interface Column {
	display_title?: string;
	id?: string;
	settings?: Record<string, unknown>;
	subtitle?: string;
	title?: string;
	type?: string;
}

/**
 * Sub-field column editor for matrix and media-gallery. Each column is itself a
 * field ({id,title,subtitle,type,settings,display_title}) so its settings are
 * edited with a nested FieldSettingsEditor — the same recursion the legacy
 * matrix "edit column" dialog performed via load-field-settings. Columns use the
 * "callouts" field-type catalog, matching the legacy admin.
 */
export const MatrixColumnsControl = ({ descriptor, settings, onPatch }: ControlProps) => {
	const [expanded, setExpanded] = useState<Set<number>>(new Set());

	const typesQ = useQuery({
		queryKey: queryKeys.fieldTypes.list(),
		queryFn: () => fieldTypesApi.list(),
	});

	const types = useMemo(() => fieldTypesForUseCase(typesQ.data, "callouts"), [typesQ.data]);

	const columns: Column[] = Array.isArray(settings[descriptor.id])
		? (settings[descriptor.id] as Column[])
		: [];

	const commit = (next: Column[]) => onPatch({ [descriptor.id]: next });

	const { update, remove, add: addColumn } = useListEditor<Column>(columns, commit);

	const add = () => {
		addColumn({ id: "", title: "", subtitle: "", type: "text", settings: {} });
		setExpanded((prev) => new Set(prev).add(columns.length));
	};

	const toggle = (index: number) =>
		setExpanded((prev) => {
			const copy = new Set(prev);

			if (copy.has(index)) {
				copy.delete(index);
			} else {
				copy.add(index);
			}

			return copy;
		});

	return (
		<ControlShell hint={descriptor.hint} label={descriptor.label} note={descriptor.note}>
			<div className="space-y-1.5">
				{columns.map((column, index) => {
					const isOpen = expanded.has(index);

					return (
						<div className="rounded-md border border-border bg-surface" key={index}>
							<div className="flex flex-wrap items-center gap-2 p-2">
								<IconButton
									ariaExpanded={isOpen}
									label="Toggle column settings"
									size="sm"
									onClick={() => toggle(index)}
								>
									{isOpen ? (
										<ChevronDown size={13} />
									) : (
										<ChevronRight size={13} />
									)}
								</IconButton>
								<Select
									compact
									value={column.type ?? "text"}
									onChange={(e) => update(index, { type: e.target.value })}
								>
									{types.map((t) => (
										<option key={t.id} value={t.id}>
											{t.name}
										</option>
									))}
								</Select>
								<TextInput
									compact
									aria-label="Column ID"
									className="min-w-0 flex-1"
									placeholder="ID"
									value={column.id ?? ""}
									onChange={(e) => update(index, { id: e.target.value })}
								/>
								<TextInput
									compact
									aria-label="Column title"
									className="min-w-0 flex-1"
									placeholder="Title"
									value={column.title ?? ""}
									onChange={(e) => update(index, { title: e.target.value })}
								/>
								<TextInput
									compact
									aria-label="Column subtitle"
									className="min-w-0 flex-1"
									placeholder="Subtitle"
									value={column.subtitle ?? ""}
									onChange={(e) => update(index, { subtitle: e.target.value })}
								/>
								<Checkbox
									checked={Boolean(column.display_title)}
									className="whitespace-nowrap"
									label="Title"
									size="sm"
									onChange={(checked) =>
										update(index, {
											display_title: checked ? "on" : "",
										})
									}
								/>
								<IconButton
									label="Remove column"
									tone="danger"
									onClick={() => remove(index)}
								>
									<Trash size={13} />
								</IconButton>
							</div>
							{isOpen && (
								<div className="border-t border-border p-2">
									<FieldSettingsEditor
										type={column.type ?? "text"}
										useCase="callouts"
										value={column.settings}
										onChange={(s) => update(index, { settings: s })}
									/>
								</div>
							)}
						</div>
					);
				})}
				<button
					className="inline-flex items-center gap-1 rounded border border-border bg-surface px-2 py-1 text-[12px] hover:bg-hover"
					type="button"
					onClick={add}
				>
					<Plus size={12} />
					Add column
				</button>
			</div>
		</ControlShell>
	);
};
