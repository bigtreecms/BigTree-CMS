import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { ChevronLeft, Plus } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { AccessDenied } from "@/components/ui/AccessDenied";
import { Button } from "@/components/ui/Button";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";
import { SubNav } from "@/components/ui/SubNav";
import { TextInput } from "@/components/ui/TextInput";
import { TagInput } from "@/components/tags/TagInput";

import { tagsApi, type Tag } from "@/api/endpoints/tags";
import { isAdmin } from "@/lib/permissions";
import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { useToastMutation } from "@/hooks/useToastMutation";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { Field } from "@/components/ui/Field";

/**
 * Tag add page (`/tags/add`). Mirrors the legacy admin's tags/add form: a
 * name field plus an optional list of existing tags to merge into the one
 * being created. On submit we create the tag and, if any merge tags were
 * picked, re-point their relations onto it.
 *
 * The server de-dupes on a normalized form (lowercase, alphanumerics only),
 * so we run the same normalization client-side and check it against
 * /tags/search to surface a "tag already exists" error before submit rather
 * than silently resolving to the existing row.
 */

/** Mirror of TagService::normalize — strip non-alphanumerics, lowercase. */
const normalizeTag = (raw: string): string => raw.replace(/[^a-zA-Z0-9]/g, "").toLowerCase();

export const TagAdd = () => {
	const navigate = useNavigate();
	const user = useAuthStore((s) => s.user);

	const [name, setName] = useState("");
	const [mergeTags, setMergeTags] = useState<Tag[]>([]);
	const [error, setError] = useState<string | null>(null);

	const isDirty = useDirtyTracker({ name, mergeIds: mergeTags.map((t) => t.id).join(",") });

	const normalized = normalizeTag(name);

	// Debounce the normalized name so we don't hit /tags/search on every keystroke.
	const debounced = useDebouncedValue(normalized, 250);

	const duplicateQuery = useQuery({
		queryKey: queryKeys.tags.exists(debounced),
		queryFn: () => tagsApi.search(debounced),
		enabled: debounced.length > 0,
	});

	// search is a LIKE %q% match, so the exact normalized row (if any) is in the results.
	const duplicate =
		debounced.length > 0 &&
		debounced === normalized &&
		(duplicateQuery.data ?? []).some((t) => t.tag === normalized);

	// True while the duplicate check hasn't caught up to what's typed (debounce
	// pending or request in flight). We hold submit until it resolves so a fast
	// submit can't slip an unchecked name past the guard.
	const checking = normalized !== "" && (debounced !== normalized || duplicateQuery.isFetching);

	const createMutation = useToastMutation({
		mutationFn: async () => {
			const created = await tagsApi.create(name.trim());

			// Don't merge a tag into itself if the typed name matched an
			// existing tag that's also in the merge list.
			const from = mergeTags.map((t) => t.id).filter((id) => id !== created.id);

			if (from.length > 0) {
				return tagsApi.merge({ into: created.id, from });
			}

			return created;
		},
		invalidate: [queryKeys.tags.root()],
		errorMessage: "Could not create tag",
		onSuccess: (tag) => {
			toast.success(`Tag “${tag.tag}” created`);
			navigate("/tags");
		},
		onError: () => {
			setError("Could not create tag");
		},
	});

	if (!isAdmin(user)) {
		return <AccessDenied message="Only administrators can create tags." />;
	}

	const submit = (event: React.FormEvent) => {
		event.preventDefault();

		if (normalized === "") {
			setError("Please enter a tag name with at least one letter or number.");

			return;
		}

		if (duplicate) {
			setError(`A tag named “${normalized}” already exists.`);

			return;
		}

		setError(null);
		createMutation.mutate();
	};

	return (
		<PageContainer width="narrow">
			<Breadcrumb items={[{ label: "Tags", to: "/tags" }, { label: "Add Tag" }]} />

			<PageHead
				title="Add tag"
				sub="Create a tag, optionally merging existing tags into it."
				actions={
					<Button icon={<ChevronLeft size={13} />} to="/tags">
						Back to list
					</Button>
				}
			/>

			<SubNav<"list" | "add">
				className="mb-4"
				value="add"
				onChange={(v) => navigate(v === "add" ? "/tags/add" : "/tags")}
				items={[
					{ value: "list", label: "View Tags" },
					{ value: "add", label: "Add Tag", icon: <Plus size={13} /> },
				]}
			/>

			{error && (
				<Alert tone="danger" className="mb-3">
					{error}
				</Alert>
			)}

			<FormShell
				onSubmit={submit}
				footer={
					<FormFooter
						cancelTo="/tags"
						submitIcon={<Plus size={13} />}
						submitLabel={checking ? "Checking…" : "Create tag"}
						disabled={normalized === "" || duplicate || checking}
						loading={createMutation.isPending}
						loadingLabel="Creating…"
					/>
				}
			>
				<div className="space-y-4">
					<Field
						label="Tag name"
						error={
							duplicate ? `A tag named “${normalized}” already exists.` : undefined
						}
						hint={
							duplicate
								? undefined
								: "Only letters and numbers are kept — the name is normalized on save."
						}
					>
						<TextInput
							className={duplicate ? "border-danger focus:ring-danger/40" : undefined}
							placeholder="e.g. announcements"
							value={name}
							onChange={(e) => {
								setName(e.target.value);
								setError(null);
							}}
							aria-invalid={duplicate}
							autoFocus
						/>
					</Field>

					<div>
						<label className="mb-1 block text-[12.5px] font-medium text-text-2">
							Tags to merge in{" "}
							<span className="font-normal text-text-3">(optional)</span>
						</label>
						<TagInput
							multiple
							value={mergeTags}
							onChange={setMergeTags}
							placeholder="Type to search for tags to merge…"
						/>
						<p className="mt-1.5 text-[11.5px] text-text-3">
							Relations pointing at these tags will be re-pointed to the new tag, then
							the merged tags will be deleted.
						</p>
					</div>
				</div>
			</FormShell>

			<UnsavedChangesGuard isDirty={isDirty && !createMutation.isPending} />
		</PageContainer>
	);
};
