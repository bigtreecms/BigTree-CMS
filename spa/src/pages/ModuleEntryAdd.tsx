import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { PageHead } from "@/components/shell/PageHead";
import { EmptyState } from "@/components/ui/EmptyState";
import { Loading } from "@/components/ui/Loading";

import { autoModulesApi } from "@/api/endpoints/auto-modules";
import { modulesApi } from "@/api/endpoints/modules";
import { FormRenderer } from "@/renderer/forms/FormRenderer";
import { useFormPreviewUrl } from "@/renderer/forms/useFormPreviewUrl";
import { modulePath, moduleActionPath } from "@/lib/moduleActions";
import { useModuleContext, useModuleEntryLinks } from "@/pages/ModuleLayout";
import { adminPath } from "@/lib/adminBoot";
import { toast } from "@/lib/toast";
import {
	discardPreviewWindow,
	modulePreviewUrl,
	openPreviewWindow,
	showPreview,
} from "@/lib/preview";
import { queryKeys } from "@/lib/queryKeys";

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
	const { editPath } = useModuleEntryLinks();

	const formsQuery = useQuery({
		queryKey: queryKeys.modules.forms(moduleId),
		queryFn: () => modulesApi.forms(moduleId),
		enabled: moduleId !== "",
	});

	const moduleQuery = useQuery({
		queryKey: queryKeys.modules.detail(moduleId),
		queryFn: () => modulesApi.get(moduleId),
		enabled: moduleId !== "",
	});

	const form = formsQuery.data?.find((f) => f.id === formId);
	const previewUrl = useFormPreviewUrl(moduleId, form);

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
		mutationFn: ({ values, publish }: EntryCreateVariables) =>
			autoModulesApi.create(moduleId, values, { form: formId }, publish),
		onSuccess: (result, { preview }) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.moduleEntries.root(moduleId) });

			// Save & Preview: open the new entry through the view's preview URL and
			// carry on editing it (a second "Create" would add a duplicate).
			if (preview !== undefined && previewUrl) {
				const newId = "pending" in result ? `p${result.pending_id}` : result.id;

				toast.success("Draft created", { description: "The preview opened in a new tab." });
				// "Continue Editing" should land on the new draft, not this add form.
				const editRoute = editPath(newId);
				const returnTo = new URL(adminPath(editRoute), window.location.origin).href;

				void showPreview(preview, modulePreviewUrl(previewUrl, newId), returnTo);
				navigate(editRoute, { replace: true });

				return;
			}

			if (result && typeof result === "object" && "pending" in result) {
				toast.success("Draft created", {
					description: "Your entry is pending publisher approval.",
				});
			} else {
				toast.success("Entry published");
			}

			// What the save did that the form can't show: a geocoding field whose
			// lookup failed, so the entry's coordinates were stored empty. The
			// legacy admin printed the geocoder's own error on this save; the
			// entry still saved, so it is a warning rather than an error.
			const warnings = (result as { warnings?: string[] } | null)?.warnings;

			if (warnings?.length) {
				toast.warning("Saved, but check the entry", {
					description: warnings.join(" "),
				});
			}

			navigate(returnPath);
		},
	});

	return (
		<>
			<PageHead title={form ? `Add ${form.title}` : "Add entry"} />

			{formsQuery.isLoading ? (
				<Loading label="Loading form…" variant="card" />
			) : !form ? (
				<EmptyState>This module doesn't have a form configured.</EmptyState>
			) : (
				<FormRenderer
					canPreview={Boolean(previewUrl)}
					canPublish={moduleQuery.data?.access === "p"}
					form={form}
					moduleId={moduleId}
					publishLabel="Create & Publish"
					submitLabel="Create"
					onCancel={() => navigate(returnPath)}
					onSubmit={async (values, opts) => {
						// Opened before the first await so it counts as part of the click.
						const preview = opts?.preview ? openPreviewWindow() : undefined;

						try {
							await createMutation.mutateAsync({
								values,
								publish: Boolean(opts?.publish),
								preview,
							});
						} catch (err) {
							discardPreviewWindow(preview ?? null);

							throw err;
						}
					}}
				/>
			)}
		</>
	);
};

interface EntryCreateVariables {
	/** The tab a Save & Preview opens in (null if blocked); undefined for a plain save. */
	preview?: Window | null;
	publish: boolean;
	values: Record<string, unknown>;
}
