import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { DragHandle } from "@/components/ui/DragHandle";

import { modulesApi, type ModuleSummary } from "@/api/endpoints/modules";

import { iconFor } from "@/lib/legacyIcons";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

import { useDragReorder } from "@/hooks/useDragReorder";
import { Card } from "@/components/ui/Card";
import { Loading } from "@/components/ui/Loading";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

interface ModuleGroupModulesListProps {
	groupId: string;
}

const MODULES_LIST_KEY = ["modules", "list"] as const;

/**
 * Sortable list of the modules assigned to a module group, shown on the group
 * editor. Mirrors the legacy admin's per-group sortable list: dragging a row
 * reorders the modules and persists their position via the same
 * `/modules/reorder` endpoint, passing only this group's ids (positions are
 * assigned count→1, which is fine since the list is always bucketed by group).
 */
export const ModuleGroupModulesList = ({ groupId }: ModuleGroupModulesListProps) => {
	const queryClient = useQueryClient();

	const query = useQuery({
		queryKey: MODULES_LIST_KEY,
		queryFn: () => modulesApi.list(),
	});

	const [ordered, setOrdered] = useState<ModuleSummary[]>([]);

	useEffect(() => {
		if (!query.data) {
			return;
		}

		const inGroup = query.data
			.filter((m) => m.group === groupId)
			.sort((a, b) => (b.position ?? 0) - (a.position ?? 0));

		setOrdered(inGroup);
	}, [query.data, groupId]);

	const reorderMutation = useMutation({
		mutationFn: (ids: string[]) => modulesApi.reorder(ids),
		onSuccess: () => queryClient.invalidateQueries({ queryKey: ["modules"] }),
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Reorder failed");
			queryClient.invalidateQueries({ queryKey: ["modules"] });
		},
	});

	const drag = useDragReorder<ModuleSummary, string>(ordered, setOrdered, (ids) =>
		reorderMutation.mutate(ids)
	);

	const body = () => {
		if (query.isLoading) {
			return (
				<Loading
					variant="block"
					className="rounded-md border border-border bg-surface"
					label="Loading modules…"
				/>
			);
		}

		if (ordered.length === 0) {
			return (
				<InlineEmpty align="center" pad="xl">
					No modules are assigned to this group yet. Assign a module to this group from
					the module designer.
				</InlineEmpty>
			);
		}

		return (
			<ul className="space-y-1.5">
				{ordered.map((module) => {
					const Icon = iconFor(module.icon);

					return (
						<li
							key={module.id}
							className={`flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2 transition-colors ${
								drag.dragId === module.id ? "bg-accent-soft shadow-md" : ""
							} ${
								drag.overId === module.id && drag.dragId !== module.id
									? "shadow-[inset_0_2px_0_0_var(--color-accent)]"
									: ""
							}`}
							draggable
							onDragStart={(e) => drag.onDragStart(e, module.id)}
							onDragOver={(e) => drag.onDragOver(e, module.id)}
							onDrop={drag.onDrop}
							onDragEnd={drag.onDragEnd}
						>
							<DragHandle />
							<span className="grid size-[26px] shrink-0 place-items-center rounded-md bg-accent-soft text-accent">
								<Icon size={15} />
							</span>
							<Link
								to={`/developer/modules/${encodeURIComponent(module.id)}`}
								className="min-w-0 flex-1 truncate text-[12.5px] font-medium text-text hover:text-accent"
							>
								{module.name}
							</Link>
							<span className="truncate font-mono text-[11px] text-text-3">
								{module.id}
							</span>
						</li>
					);
				})}
			</ul>
		);
	};

	return (
		<Card className="space-y-2 p-4">
			<div className="flex items-baseline justify-between">
				<h2 className="text-[13px] font-semibold text-text">Modules in this group</h2>
				<span className="text-[11px] tabular-nums text-text-3">
					{ordered.length} module{ordered.length === 1 ? "" : "s"}
				</span>
			</div>
			<p className="text-[11.5px] text-text-3">Drag to change the order modules appear in.</p>
			{body()}
		</Card>
	);
};
