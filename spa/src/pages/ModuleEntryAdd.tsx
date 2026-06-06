import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { PageHead } from "@/components/shell/PageHead";

import { autoModulesApi } from "@/api/endpoints/auto-modules";
import { modulesApi } from "@/api/endpoints/modules";
import { FormRenderer } from "@/renderer/forms/FormRenderer";
import { modulePath, moduleActionPath } from "@/lib/moduleActions";
import { useModuleContext } from "@/pages/ModuleLayout";
import { toast } from "@/lib/toast";

interface ModuleEntryAddProps {
	formId: string;
}

/**
 * Add screen, rendered by <ModuleDispatcher /> when the active action relates to
 * a form and carries no entry id. The form id comes straight from the resolved
 * action — the authoritative reference to what's being created — so it's both the
 * form we render and the table ref (`?form=`) we POST with.
 *
 * On submit, POST /modules/:id/entries; the auto-module service inserts the row
 * (or a pending change row, depending on the user's level), then we invalidate
 * the entries cache and bounce back to the form's return view (or the module).
 */
export const ModuleEntryAdd = ({ formId }: ModuleEntryAddProps) => {
	const { moduleId, module, actions } = useModuleContext();
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const formsQuery = useQuery({
		queryKey: ["modules", "forms", moduleId],
		queryFn: () => modulesApi.forms(moduleId),
		enabled: moduleId !== "",
	});

	const moduleQuery = useQuery({
		queryKey: ["modules", "detail", moduleId],
		queryFn: () => modulesApi.get(moduleId),
		enabled: moduleId !== "",
	});

	const form = formsQuery.data?.find((f) => f.id === formId);

	// Bounce back to the form's declared return view (resolved to its action
	// route) if it has one, otherwise the module landing page.
	const returnAction = form?.return_view
		? actions.find((a) => a.view === form.return_view)
		: undefined;
	const returnPath =
		module && returnAction
			? moduleActionPath(module, returnAction)
			: module
				? modulePath(module)
				: "/modules";

	const createMutation = useMutation({
		mutationFn: ({ values, publish }: { values: Record<string, unknown>; publish: boolean }) =>
			autoModulesApi.create(moduleId, values, { form: formId }, publish),
		onSuccess: (result) => {
			queryClient.invalidateQueries({ queryKey: ["module-entries", moduleId] });

			if (result && typeof result === "object" && "pending" in result) {
				toast.success("Draft created", {
					description: "Your entry is pending publisher approval.",
				});
			} else {
				toast.success("Entry published");
			}

			navigate(returnPath);
		},
	});

	return (
		<>
			<PageHead title={form ? `Add ${form.title}` : "Add entry"} />

			{formsQuery.isLoading ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading form…
				</div>
			) : !form ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					This module doesn't have a form configured.
				</div>
			) : (
				<FormRenderer
					form={form}
					moduleId={moduleId}
					onCancel={() => navigate(returnPath)}
					submitLabel="Create"
					canPublish={moduleQuery.data?.access === "p"}
					publishLabel="Create & Publish"
					onSubmit={async (values, opts) => {
						await createMutation.mutateAsync({
							values,
							publish: Boolean(opts?.publish),
						});
					}}
				/>
			)}
		</>
	);
};
