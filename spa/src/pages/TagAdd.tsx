import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Plus } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { AccessDenied } from "@/components/ui/AccessDenied";
import { Button } from "@/components/ui/Button";
import { SubNav } from "@/components/ui/SubNav";
import { TagInput } from "@/components/tags/TagInput";

import { tagsApi, type Tag } from "@/api/endpoints/tags";
import { isAdmin } from "@/lib/permissions";
import { toast } from "@/lib/toast";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";

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
	const queryClient = useQueryClient();
	const user = useAuthStore((s) => s.user);

	const [name, setName] = useState("");
	const [mergeTags, setMergeTags] = useState<Tag[]>([]);
	const [error, setError] = useState<string | null>(null);

	const isDirty = useDirtyTracker({ name, mergeIds: mergeTags.map((t) => t.id).join(",") });

	const normalized = normalizeTag(name);

	// Debounce the normalized name so we don't hit /tags/search on every keystroke.
	const [debounced, setDebounced] = useState("");

	useEffect(() => {
		const handle = setTimeout(() => setDebounced(normalized), 250);

		return () => clearTimeout(handle);
	}, [normalized]);

	const duplicateQuery = useQuery({
		queryKey: ["tags", "exists", debounced],
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

	const createMutation = useMutation({
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
		onSuccess: (tag) => {
			queryClient.invalidateQueries({ queryKey: ["tags"] });
			toast.success(`Tag “${tag.tag}” created`);
			navigate("/tags");
		},
		onError: () => {
			setError("Could not create tag");
			toast.error("Could not create tag");
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
		<div className="mx-auto max-w-3xl px-6 py-4">
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
				<div className="mb-3 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{error}
				</div>
			)}

			<form
				onSubmit={submit}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				<div>
					<label
						htmlFor="tag-name"
						className="mb-1 block text-[12.5px] font-medium text-text-2"
					>
						Tag name
					</label>
					<input
						id="tag-name"
						type="text"
						className={`w-full rounded-md border bg-surface px-3 py-1.5 text-[13.5px] focus:outline-none focus:ring-1 ${
							duplicate
								? "border-danger focus:ring-danger/40"
								: "border-border focus:ring-accent-ring"
						}`}
						placeholder="e.g. announcements"
						value={name}
						onChange={(e) => {
							setName(e.target.value);
							setError(null);
						}}
						aria-invalid={duplicate}
						autoFocus
					/>
					{duplicate ? (
						<p className="mt-1.5 text-[11.5px] text-danger">
							A tag named “{normalized}” already exists.
						</p>
					) : (
						<p className="mt-1.5 text-[11.5px] text-text-3">
							Only letters and numbers are kept — the name is normalized on save.
						</p>
					)}
				</div>

				<div>
					<label className="mb-1 block text-[12.5px] font-medium text-text-2">
						Tags to merge in <span className="font-normal text-text-3">(optional)</span>
					</label>
					<TagInput
						multiple
						value={mergeTags}
						onChange={setMergeTags}
						placeholder="Type to search for tags to merge…"
					/>
					<p className="mt-1.5 text-[11.5px] text-text-3">
						Relations pointing at these tags will be re-pointed to the new tag, then the
						merged tags will be deleted.
					</p>
				</div>

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Button to="/tags">Cancel</Button>
					<Button
						variant="primary"
						type="submit"
						icon={<Plus size={13} />}
						disabled={
							normalized === "" || duplicate || checking || createMutation.isPending
						}
					>
						{createMutation.isPending
							? "Creating…"
							: checking
								? "Checking…"
								: "Create tag"}
					</Button>
				</div>
			</form>

			<UnsavedChangesGuard isDirty={isDirty && !createMutation.isPending} />
		</div>
	);
};
