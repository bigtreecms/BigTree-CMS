import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocation, useNavigate, useParams } from "react-router-dom";
import { X } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";

import { PageSummaryPanel } from "@/components/pages/PageSummaryPanel";
import { PageSectionToolbar } from "@/components/pages/PageSectionToolbar";

import { isPendingResult, pagesApi, type PageEditBody } from "@/api/endpoints/pages";
import { resourceToFormField, templatesApi } from "@/api/endpoints/templates";
import type { Tag } from "@/api/endpoints/tags";

import { isFieldRequired, isFieldValueEmpty } from "@/renderer/forms/validation";

import { useAuthStore } from "@/auth/store";
import { canPublishPage, isAdmin } from "@/lib/permissions";
import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";

import { PageTabStrip, type PageTabValue } from "@/components/pages/PageTabStrip";
import { PageWizardFooter } from "@/components/pages/PageWizardFooter";

import { ContentTab, PropertiesTab, SeoTab, SharingTab } from "./PageEdit";

/**
 * Add subpage screen — same four-tab structure as PageEdit, but powered by a
 * fresh PageEditBody and a wizard-style footer (Back / Next Step / Create /
 * Create & Publish). Mirrors the `add-subpage-screen.jsx` reference design.
 *
 * "Create" queues a NEW pending change (draft); "Create & Publish" (publishers
 * only) writes the page live. The button choice maps to `publish` on the body.
 */

const seedBody = (parent: number): PageEditBody => ({
	parent,
	nav_title: "",
	title: "",
	route: "",
	in_nav: true,
	template: "",
	external: "",
	new_window: false,
	meta_keywords: "",
	meta_description: "",
	seo_invisible: false,
	publish_at: null,
	expire_at: null,
	max_age: 0,
	trunk: false,
	resources: {},
});

export const PageAdd = () => {
	const { parentId } = useParams<{ parentId: string }>();
	const navigate = useNavigate();
	const location = useLocation();
	const queryClient = useQueryClient();
	const user = useAuthStore((s) => s.user);

	const parent = (() => {
		if (!parentId) {
			return 0;
		}

		const n = Number(parentId);

		return Number.isFinite(n) && n >= 0 ? n : 0;
	})();

	const [body, setBody] = useState<PageEditBody>(() => seedBody(parent));
	// Full Tag objects for the browser chips; body.tags carries just the ids.
	const [tagObjects, setTagObjects] = useState<Tag[]>([]);
	const [activeTab, setActiveTab] = useState<PageTabValue>("properties");
	const { error, setError, fieldErrors, setFieldErrors, onMutationError } = useFormSubmit();

	useEffect(() => {
		setBody((prev) => ({ ...prev, parent }));
	}, [parent]);

	const templatesQuery = useQuery({
		queryKey: queryKeys.templates.list(),
		queryFn: () => templatesApi.list(),
	});

	// Default the template to the first flexible (non-routed) template once the
	// list loads, falling back to the first routed template — mirroring the
	// legacy admin, which has no "None" option for a page's template.
	useEffect(() => {
		const templates = templatesQuery.data;

		if (!templates || templates.length === 0 || body.template) {
			return;
		}

		const fallback = templates.find((t) => !t.routed) ?? templates[0];

		if (fallback) {
			setBody((prev) => ({ ...prev, template: fallback.id }));
		}
	}, [templatesQuery.data, body.template]);

	const templateQuery = useQuery({
		queryKey: queryKeys.templates.detail(body.template as string),
		queryFn: () => templatesApi.get(body.template as string),
		enabled: Boolean(body.template),
	});

	const parentQuery = useQuery({
		queryKey: queryKeys.pages.detail(parent, { lineage: true }),
		queryFn: () => pagesApi.get(parent, { lineage: true }),
		enabled: parent > 0,
	});

	const createMutation = useMutation({
		mutationFn: (publish: boolean) => pagesApi.create({ ...body, publish }),
		onSuccess: (page) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.pages.lists() });

			const name = body.nav_title?.trim() || "Page";

			if (isPendingResult(page)) {
				toast.success("Draft created", {
					description: `“${name}” is pending publisher approval.`,
				});
			} else {
				toast.success("Page published", { description: `“${name}” saved.` });
			}

			// Return to the tree view we came from rather than dropping the user on
			// the new page's edit screen — matching the legacy admin, which redirects
			// a create back to the parent's view-tree.
			const from = (location.state as { from?: string } | null)?.from;

			navigate(from ?? (parent > 0 ? `/pages/${parent}` : "/pages"));
		},
		onError: (err) => onMutationError(err, "Create failed"),
	});

	const setBodyPatch = (patch: Partial<PageEditBody>) => {
		setBody((prev) => ({ ...prev, ...patch }));
	};

	const handleSubmit = (publish: boolean) => {
		if (createMutation.isPending) {
			return;
		}

		const resourceValues = (body.resources ?? {}) as Record<string, unknown>;
		const resourceErrors: Record<string, string> = {};

		if (!templateDisabled) {
			for (const resource of templateQuery.data?.resources ?? []) {
				const field = resourceToFormField(resource);

				if (
					isFieldRequired(field) &&
					isFieldValueEmpty(field, resourceValues[field.column])
				) {
					resourceErrors[field.column] = `${field.title || field.column} is required.`;
				}
			}
		}

		if (Object.keys(resourceErrors).length > 0) {
			setFieldErrors(resourceErrors);
			setError("Please fill in the required fields.");
			setActiveTab("content");

			return;
		}

		setError(null);
		setFieldErrors({});
		createMutation.mutate(publish);
	};

	// Baseline once the template has been auto-defaulted (or the template list
	// came back empty), so the automatic default selection isn't counted as a
	// user edit. `ready` derives from `body` itself, so it flips on the same
	// render the default lands — no baseline race.
	const ready =
		Boolean(body.template) ||
		(templatesQuery.isSuccess && (templatesQuery.data?.length ?? 0) === 0);
	const isDirty = useDirtyTracker(body, ready) && !createMutation.isPending;

	const lineage = parentQuery.data?.lineage ?? [];
	const templateDisabled = Boolean(body.external && body.external.trim().length > 0);

	const breadcrumbs = [
		{ label: "Pages", to: "/pages" },
		...lineage.map((p) => ({ label: p.nav_title, to: `/pages/${p.id}` })),
		{ label: "Add subpage" },
	];

	const canCreate = Boolean(body.nav_title && body.nav_title.trim().length > 0);

	// A new page's publish right derives from the parent. Top-level pages (no
	// parent row to read access from) are only creatable/publishable by admins.
	const canPublish = parent > 0 ? canPublishPage(parentQuery.data?.access) : isAdmin(user);

	return (
		<PageContainer width="wide">
			<Breadcrumb items={breadcrumbs} />

			<PageHead
				title={body.nav_title?.trim() || "New subpage"}
				sub="Configure properties, then add content, SEO, and sharing metadata."
				actions={
					<Button icon={<X size={13} />} to={`/pages/${parent}`}>
						Cancel
					</Button>
				}
			/>

			<PageSummaryPanel page={parentQuery.data ?? null} />

			<PageSectionToolbar active="add" pageId={parent > 0 ? parent : 0} parentId={parent} />

			{error && (
				<Alert tone="danger" className="mb-3">
					{error}
				</Alert>
			)}

			<form
				onSubmit={(e) => {
					e.preventDefault();
					handleSubmit(false);
				}}
				className="mb-6 overflow-hidden rounded-lg border border-border bg-surface"
			>
				<PageTabStrip value={activeTab} onChange={setActiveTab} />

				<div className="flex flex-col gap-[18px] p-[22px]">
					{activeTab === "properties" && (
						<PropertiesTab
							body={body}
							templates={templatesQuery.data ?? []}
							templateDisabled={templateDisabled}
							fieldErrors={fieldErrors}
							onPatch={setBodyPatch}
						/>
					)}

					{activeTab === "content" && (
						<ContentTab
							body={body}
							template={templateDisabled ? undefined : templateQuery.data}
							loading={!templateDisabled && templateQuery.isLoading}
							templateDisabled={templateDisabled}
							fieldErrors={fieldErrors}
							onChange={(resources) => setBodyPatch({ resources })}
							tags={tagObjects}
							onTagsChange={(next) => {
								setTagObjects(next);
								setBodyPatch({ tags: next.map((t) => t.id) });
							}}
						/>
					)}

					{activeTab === "seo" && (
						<SeoTab body={body} fieldErrors={fieldErrors} onPatch={setBodyPatch} />
					)}

					{activeTab === "sharing" && <SharingTab body={body} onPatch={setBodyPatch} />}
				</div>

				<PageWizardFooter activeTab={activeTab} onSelect={setActiveTab} showNext>
					<Button
						variant={canPublish ? "secondary" : "primary"}
						onClick={() => handleSubmit(false)}
						disabled={!canCreate}
						loading={createMutation.isPending}
						loadingLabel="Saving…"
					>
						Create
					</Button>

					{canPublish && (
						<Button
							variant="primary"
							onClick={() => handleSubmit(true)}
							disabled={!canCreate}
							loading={createMutation.isPending}
							loadingLabel="Saving…"
						>
							Create & Publish
						</Button>
					)}
				</PageWizardFooter>
			</form>

			<UnsavedChangesGuard isDirty={isDirty} />
		</PageContainer>
	);
};
