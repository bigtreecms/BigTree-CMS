import { useQueryClient } from "@tanstack/react-query";

import type { DataTableColumn } from "@/components/ui/DataTable";
import { NameIdCell } from "@/components/ui/NameIdCell";
import { Badge } from "@/components/ui/Badge";

import { DeveloperListPage } from "@/components/developer/DeveloperListPage";

import { templatesApi, type TemplateSummary } from "@/api/endpoints/templates";

import { queryKeys } from "@/lib/queryKeys";
import { useToastMutation } from "@/hooks/useToastMutation";

const LEVEL_LABEL = ["Editor", "Admin", "Developer"];

const columns: DataTableColumn<TemplateSummary>[] = [
	{
		key: "name",
		header: "Name",
		width: "minmax(0,1.5fr)",
		cell: (row) => <NameIdCell id={row.id} name={row.name} />,
	},
	{
		key: "module",
		header: "Module",
		width: "minmax(0,1fr)",
		hideOnMobile: true,
		cell: (row) =>
			row.module ? (
				<span className="font-mono text-[11.5px] text-text-3">{row.module}</span>
			) : (
				<span className="text-text-3">—</span>
			),
	},
	{
		key: "level",
		header: "Level",
		width: "120px",
		hideOnMobile: true,
		cell: (row) => (
			<span className="text-[12px] text-text-3">{LEVEL_LABEL[row.level] ?? row.level}</span>
		),
	},
	{
		key: "routed",
		header: "Routed",
		width: "90px",
		hideOnMobile: true,
		cell: (row) =>
			row.routed ? (
				<Badge size="sm" tone="info">
					Routed
				</Badge>
			) : (
				<span className="text-text-3">—</span>
			),
	},
];

export const Templates = () => {
	const queryClient = useQueryClient();

	const reorderMutation = useToastMutation({
		mutationFn: (ids: string[]) => templatesApi.reorder(ids),
		errorMessage: "Could not save the new order",
		onError: () => queryClient.invalidateQueries({ queryKey: queryKeys.templates.root() }),
	});

	const handleReorder = (orderedKeys: Array<string | number>) => {
		const ids = orderedKeys.map(String);

		// Optimistic: reorder the cached list immediately, then persist.
		queryClient.setQueryData<TemplateSummary[]>(queryKeys.templates.list(), (prev) => {
			if (!prev) {
				return prev;
			}

			const byId = new Map(prev.map((t) => [t.id, t]));

			return ids.map((id) => byId.get(id)).filter((t): t is TemplateSummary => !!t);
		});

		reorderMutation.mutate(ids);
	};

	return (
		<DeveloperListPage<TemplateSummary>
			addLabel="Add template"
			columns={columns}
			confirmDescription="Pages using this template will lose their content schema. This cannot be undone."
			confirmLabel="Delete template"
			countNoun="template"
			deleteButtonLabel="Delete template"
			deleteSuccessMessage="Template deleted"
			emptyLabel="No templates yet."
			getRowKey={(row) => row.id}
			invalidateKey={queryKeys.templates.root()}
			list={() => templatesApi.list()}
			loadingLabel="Loading templates…"
			queryKey={queryKeys.templates.list()}
			remove={(id) => templatesApi.delete(id)}
			route="/developer/templates"
			rowLabel={(row) => row.name}
			title="Templates"
			onReorder={handleReorder}
		/>
	);
};
