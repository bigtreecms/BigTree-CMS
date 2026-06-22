import { useMemo, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { useMutation, useQueries, useQueryClient } from "@tanstack/react-query";
import { ArrowRight, X } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { AccessDenied } from "@/components/ui/AccessDenied";
import { Button } from "@/components/ui/Button";
import { Loading } from "@/components/ui/Loading";
import { CardHeader } from "@/components/ui/Card";
import { TagInput } from "@/components/tags/TagInput";

import { tagsApi, type Tag } from "@/api/endpoints/tags";
import { isAdmin } from "@/lib/permissions";
import { toast } from "@/lib/toast";

/**
 * Tag merge page. The source IDs come in via ?from=1,2,3 (set by Tags.tsx
 * when bulk-selection has 2+ rows). The user picks a target via the
 * TagInput combobox; submit POSTs to /tags/merge which re-points every
 * relation row, deletes the source rows, and recomputes the target's
 * usage_count. On success the cache is invalidated and we navigate back
 * to /tags.
 */

const TAG_DETAIL_KEY = (id: number) => ["tags", "detail", id] as const;

const parseFromParam = (raw: string | null): number[] => {
	if (!raw) {
		return [];
	}

	return raw
		.split(",")
		.map((part) => parseInt(part.trim(), 10))
		.filter((n) => Number.isFinite(n) && n > 0);
};

export const TagMerge = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();
	const user = useAuthStore((s) => s.user);

	const [searchParams] = useSearchParams();
	const initialIds = useMemo(() => parseFromParam(searchParams.get("from")), [searchParams]);
	const [sourceIds, setSourceIds] = useState<number[]>(initialIds);
	const [target, setTarget] = useState<Tag | null>(null);

	const sourceQueries = useQueries({
		queries: sourceIds.map((id) => ({
			queryKey: TAG_DETAIL_KEY(id),
			queryFn: () => tagsApi.get(id),
		})),
	});

	const sourceTags: Tag[] = sourceQueries.map((q) => q.data).filter((t): t is Tag => !!t);
	const sourceLoading = sourceQueries.some((q) => q.isLoading);

	const mergeMutation = useMutation({
		mutationFn: () => {
			if (!target) {
				throw new Error("merge: no target");
			}

			return tagsApi.merge({ into: target.id, from: sourceIds });
		},
		onSuccess: (resulting) => {
			queryClient.invalidateQueries({ queryKey: ["tags"] });
			toast.success(`Merged into “${resulting.tag}”`);
			navigate("/tags");
		},
		onError: () => {
			toast.error("Could not merge tags");
		},
	});

	if (!isAdmin(user)) {
		return <AccessDenied message="Only administrators can merge tags." />;
	}

	const removeSource = (id: number) => {
		setSourceIds((prev) => prev.filter((existing) => existing !== id));
	};

	const valid = target !== null && sourceIds.length > 0 && !sourceIds.includes(target.id);

	return (
		<div className="mx-auto max-w-3xl px-6 py-4">
			<Breadcrumb items={[{ label: "Tags", to: "/tags" }, { label: "Merge tags" }]} />

			<PageHead
				title="Merge tags"
				sub="Pick a target tag — every relation pointing at the source tags will be re-pointed to it, then the source tags will be deleted."
			/>

			<section className="mb-5 overflow-hidden rounded-lg border border-border bg-surface">
				<CardHeader className="text-[12.5px] font-semibold">Source tags</CardHeader>

				<div className="px-4 py-3">
					{sourceIds.length === 0 ? (
						<p className="text-[13px] text-text-3">
							No source tags selected.{" "}
							<button
								type="button"
								className="text-accent hover:underline"
								onClick={() => navigate("/tags")}
							>
								Pick tags to merge
							</button>
						</p>
					) : sourceLoading ? (
						<Loading />
					) : (
						<ul className="flex flex-wrap gap-2">
							{sourceTags.map((tag) => (
								<li
									key={tag.id}
									className="inline-flex items-center gap-2 rounded-md border border-border bg-surface-2 px-2 py-1 text-[12.5px]"
								>
									<span className="font-medium">{tag.tag}</span>
									<span className="tabular-nums text-text-3">
										{tag.usage_count}
									</span>
									<button
										type="button"
										className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-danger"
										aria-label={`Remove ${tag.tag}`}
										onClick={() => removeSource(tag.id)}
									>
										<X size={11} />
									</button>
								</li>
							))}
						</ul>
					)}
				</div>
			</section>

			<section className="overflow-hidden rounded-lg border border-border bg-surface">
				<CardHeader className="text-[12.5px] font-semibold">Target tag</CardHeader>

				<div className="px-4 py-3">
					<TagInput
						value={target}
						onChange={setTarget}
						placeholder="Type to search or create a tag…"
						excludeIds={sourceIds}
					/>
					<p className="mt-1.5 text-[11.5px] text-text-3">
						Picking a new name will create it before the merge runs.
					</p>
				</div>

				<div className="flex justify-end gap-2 border-t border-border bg-surface-2 px-4 py-3">
					<button
						type="button"
						className="rounded-md border border-border px-4 py-1.5 text-[12.5px] hover:bg-hover"
						onClick={() => navigate("/tags")}
					>
						Cancel
					</button>
					<Button
						variant="primary"
						icon={<ArrowRight size={13} />}
						disabled={!valid || mergeMutation.isPending}
						onClick={() => mergeMutation.mutate()}
					>
						{mergeMutation.isPending ? "Merging…" : "Merge tags"}
					</Button>
				</div>
			</section>
		</div>
	);
};
