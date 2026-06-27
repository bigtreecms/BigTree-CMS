import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Plus, Save, Trash } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { IconButton } from "@/components/ui/IconButton";
import { TextInput } from "@/components/ui/TextInput";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Card } from "@/components/ui/Card";
import { LoadingText } from "@/components/ui/LoadingText";
import { MediaPresetEditor } from "@/components/developer/MediaPresetEditor";

import { configureApi, type MediaPreset } from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { queryKeys } from "@/lib/queryKeys";
import { useToastMutation } from "@/hooks/useToastMutation";

/**
 * Media presets — reusable image-field configurations.
 *
 * Edits the full preset recipe natively (min dimensions, preview prefix, retina,
 * crops with nested thumbnails / center sub-crops, top-level thumbnails, and
 * center crops) via the MediaPresetEditor. The advanced fields used to be
 * editable only on the legacy admin's crop modal.
 */
export const ConfigureMediaPresets = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: queryKeys.configure.mediaPresets(),
		queryFn: () => configureApi.mediaPresets.get(),
	});

	const [presets, setPresets] = useState<MediaPreset[]>([]);
	const [expanded, setExpanded] = useState<string | null>(null);
	const deleteDialog = useConfirmDialog<string>();
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (detailQ.data) {
			setPresets(detailQ.data.presets.map((p) => ({ ...p })));
		}
	}, [detailQ.data]);

	const saveMutation = useToastMutation({
		mutationFn: (next: MediaPreset[]) => configureApi.mediaPresets.update({ presets: next }),
		successMessage: "Media presets saved",
		errorMessage: "Could not save presets",
		onSuccess: (fresh) => {
			queryClient.setQueryData(queryKeys.configure.mediaPresets(), fresh);
			setPresets(fresh.presets.map((p) => ({ ...p })));
			setGeneralError(null);
		},
		onError: (err) => {
			setGeneralError(
				err instanceof ApiError && err.message ? err.message : "Could not save presets"
			);
		},
	});

	const addPreset = () => {
		const id = `tmp-${Date.now()}`;
		setPresets((prev) => [
			...prev,
			{
				id,
				name: "New preset",
				min_width: "",
				min_height: "",
				preview_prefix: "",
			} as MediaPreset,
		]);
		setExpanded(id);
	};

	const update = (id: string, patch: Partial<MediaPreset>) => {
		setPresets((prev) => prev.map((p) => (p.id === id ? { ...p, ...patch } : p)));
	};

	const remove = (id: string) => {
		const next = presets.filter((p) => p.id !== id);
		setPresets(next);
		saveMutation.mutate(next);
		deleteDialog.close();
	};

	return (
		<ConfigureLayout
			title="Media presets"
			sub="Reusable image-field configurations — minimum dimensions, crops, thumbnails. Save the dropdown of choices an editor sees on every image field."
			actions={
				<Button variant="primary" icon={<Plus size={13} />} onClick={addPreset}>
					Add preset
				</Button>
			}
		>
			{detailQ.isLoading && <LoadingText />}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{generalError && <ErrorPanel error={new Error(generalError)} />}

			{detailQ.data && presets.length === 0 && (
				<p className="rounded-xl border border-dashed border-border bg-surface p-6 text-center text-[12.5px] text-text-3">
					No presets yet. Add one above to give editors a reusable choice on image fields.
				</p>
			)}

			<div className="space-y-3">
				{presets.map((p) => {
					const isOpen = expanded === p.id;

					return (
						<Card key={p.id}>
							<div className="flex items-center gap-2 p-3">
								<IconButton
									label={isOpen ? "Collapse" : "Expand"}
									size="sm"
									onClick={() => setExpanded(isOpen ? null : p.id)}
									ariaExpanded={isOpen}
								>
									{isOpen ? (
										<ChevronDown size={15} />
									) : (
										<ChevronRight size={15} />
									)}
								</IconButton>

								<TextInput
									value={(p.name as string) ?? ""}
									placeholder="Preset name"
									onChange={(e) => update(p.id, { name: e.target.value })}
								/>

								<button
									type="button"
									onClick={() => deleteDialog.open(p.id)}
									className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-2 p-2 text-text-3 hover:bg-hover hover:text-danger"
									title="Delete preset"
									aria-label="Delete preset"
								>
									<Trash size={13} />
								</button>
							</div>

							{isOpen && (
								<div className="border-t border-border p-4">
									<MediaPresetEditor
										preset={p}
										onChange={(patch) => update(p.id, patch)}
									/>
								</div>
							)}
						</Card>
					);
				})}
			</div>

			{presets.length > 0 && (
				<div className="sticky bottom-4 mt-4 flex justify-end">
					<Button
						variant="primary"
						icon={<Save size={13} />}
						onClick={() => saveMutation.mutate(presets)}
						loading={saveMutation.isPending}
						loadingLabel="Saving…"
					>
						Save all
					</Button>
				</div>
			)}

			{deleteDialog.item && (
				<ConfirmDialog
					open={deleteDialog.isOpen}
					onOpenChange={(open) => {
						if (!open) {
							deleteDialog.close();
						}
					}}
					title="Delete this preset?"
					description="Image fields referencing it will fall back to their inline settings."
					confirmLabel="Delete"
					variant="danger"
					onConfirm={() => remove(deleteDialog.item!)}
				/>
			)}
		</ConfigureLayout>
	);
};
