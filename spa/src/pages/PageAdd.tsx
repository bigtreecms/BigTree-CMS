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

import { validateRequiredFields } from "@/renderer/forms/validation";

import { useAuthStore } from "@/auth/store";
import { canPublishPage, isAdmin } from "@/lib/permissions";
import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { pagePath } from "@/lib/routes";
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

			navigate(from ?? (parent > 0 ? pagePath(parent) : "/pages"));
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
		const resourceErrors = !templateDisabled
			? validateRequiredFields(
					(templateQuery.data?.resources ?? []).map(resourceToFormField),
					resourceValues
				)
			: {};

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
		...lineage.map((p) => ({ label: p.nav_title, to: pagePath(p.id) })),
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
				actions={
					<Button icon={<X size={13} />} to={parent > 0 ? pagePath(parent) : "/pages"}>
						Cancel
					</Button>
				}
				sub="Configure properties, then add content, SEO, and sharing metadata."
				title={body.nav_title?.trim() || "New subpage"}
			/>

			<PageSummaryPanel page={parentQuery.data ?? null} />

			<PageSectionToolbar active="add" pageId={parent > 0 ? parent : 0} parentId={parent} />

			{error && (
				<Alert className="mb-3" tone="danger">
					{error}
				</Alert>
			)}

			<form
				className="mb-6 overflow-hidden rounded-lg border border-border bg-surface"
				onSubmit={(e) => {
					e.preventDefault();
					handleSubmit(false);
				}}
			>
				<PageTabStrip value={activeTab} onChange={setActiveTab} />

				<div className="flex flex-col gap-[18px] p-[22px]">
					{activeTab === "properties" && (
						<PropertiesTab
							body={body}
							fieldErrors={fieldErrors}
							templateDisabled={templateDisabled}
							templates={templatesQuery.data ?? []}
							onPatch={setBodyPatch}
						/>
					)}

					{activeTab === "content" && (
						<ContentTab
							body={body}
							fieldErrors={fieldErrors}
							loading={!templateDisabled && templateQuery.isLoading}
							tags={tagObjects}
							template={templateDisabled ? undefined : templateQuery.data}
							templateDisabled={templateDisabled}
							onChange={(resources) => setBodyPatch({ resources })}
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

				<PageWizardFooter showNext activeTab={activeTab} onSelect={setActiveTab}>
					<Button
						disabled={!canCreate}
						loading={createMutation.isPending}
						loadingLabel="Saving…"
						variant={canPublish ? "secondary" : "primary"}
						onClick={() => handleSubmit(false)}
					>
						Create
					</Button>

					{canPublish && (
						<Button
							data-testid="page-create-publish"
							disabled={!canCreate}
							loading={createMutation.isPending}
							loadingLabel="Saving…"
							variant="primary"
							onClick={() => handleSubmit(true)}
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
