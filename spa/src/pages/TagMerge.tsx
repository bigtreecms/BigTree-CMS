import { useMemo, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { useQueries } from "@tanstack/react-query";
import { ArrowRight, X } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { AccessDenied } from "@/components/ui/AccessDenied";
import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";
import { Loading } from "@/components/ui/Loading";
import { CardFooter, CardHeader } from "@/components/ui/Card";
import { TagInput } from "@/components/tags/TagInput";

import { tagsApi, type Tag } from "@/api/endpoints/tags";
import { isAdmin } from "@/lib/permissions";
import { queryKeys } from "@/lib/queryKeys";
import { toast } from "@/lib/toast";
import { useToastMutation } from "@/hooks/useToastMutation";

/**
 * Tag merge page. The source IDs come in via ?from=1,2,3 (set by Tags.tsx
 * when bulk-selection has 2+ rows). The user picks a target via the
 * TagInput combobox; submit POSTs to /tags/merge which re-points every
 * relation row, deletes the source rows, and recomputes the target's
 * usage_count. On success the cache is invalidated and we navigate back
 * to /tags.
 */

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
	const user = useAuthStore((s) => s.user);

	const [searchParams] = useSearchParams();
	const initialIds = useMemo(() => parseFromParam(searchParams.get("from")), [searchParams]);
	const [sourceIds, setSourceIds] = useState<number[]>(initialIds);
	const [target, setTarget] = useState<Tag | null>(null);

	const sourceQueries = useQueries({
		queries: sourceIds.map((id) => ({
			queryKey: queryKeys.tags.detail(id),
			queryFn: () => tagsApi.get(id),
		})),
	});

	const sourceTags: Tag[] = sourceQueries.map((q) => q.data).filter((t): t is Tag => !!t);
	const sourceLoading = sourceQueries.some((q) => q.isLoading);

	const mergeMutation = useToastMutation({
		mutationFn: () => {
			if (!target) {
				throw new Error("merge: no target");
			}

			return tagsApi.merge({ into: target.id, from: sourceIds });
		},
		invalidate: [["tags"]],
		errorMessage: "Could not merge tags",
		onSuccess: (resulting) => {
			toast.success(`Merged into "${resulting.tag}"`);
			navigate("/tags");
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
		<PageContainer width="narrow">
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
									<IconButton
										label={`Remove ${tag.tag}`}
										size="sm"
										tone="danger"
										onClick={() => removeSource(tag.id)}
									>
										<X size={11} />
									</IconButton>
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

				<CardFooter>
					<Button variant="secondary" onClick={() => navigate("/tags")}>
						Cancel
					</Button>
					<Button
						variant="primary"
						icon={<ArrowRight size={13} />}
						disabled={!valid}
						loading={mergeMutation.isPending}
						loadingLabel="Merging…"
						onClick={() => mergeMutation.mutate()}
					>
						Merge tags
					</Button>
				</CardFooter>
			</section>
		</PageContainer>
	);
};
