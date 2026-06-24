import { useEffect, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";

import { Button } from "@/components/ui/Button";
import { SlideOver } from "@/components/ui/SlideOver";
import { Field } from "@/components/ui/Field";
import { resourceFoldersApi, type ResourceFolderRow } from "@/api/endpoints/resource-folders";
import { toast } from "@/lib/toast";

interface FolderEditorProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	/** Parent folder id for creates; ignored for renames. 0 = home. */
	parentId: number;
	/** When set, the SlideOver renames this folder instead of creating a new one. */
	folder?: ResourceFolderRow | null;
	/** Query key to invalidate on success — typically the folder-contents key. */
	invalidateKey: readonly unknown[];
}

/**
 * Slide-over editor for creating or renaming a resource folder. Single name
 * input; the mode is determined by whether `folder` is passed.
 */
export const FolderEditor = ({
	open,
	onOpenChange,
	parentId,
	folder,
	invalidateKey,
}: FolderEditorProps) => {
	const queryClient = useQueryClient();
	const isRename = !!folder;
	const [name, setName] = useState(folder?.name ?? "");

	// Reset the input every time the SlideOver is (re)opened so stale text from
	// a previous open doesn't leak across modes.
	useEffect(() => {
		if (open) {
			setName(folder?.name ?? "");
		}
	}, [open, folder?.name]);

	const createMutation = useMutation({
		mutationFn: () => resourceFoldersApi.create({ parent: parentId, name: name.trim() }),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: invalidateKey });
			toast.success("Folder created");
			onOpenChange(false);
		},
		onError: () => {
			toast.error("Could not create folder");
		},
	});

	const renameMutation = useMutation({
		mutationFn: () => {
			if (!folder) {
				throw new Error("rename: no folder");
			}

			return resourceFoldersApi.update(folder.id, { name: name.trim() });
		},
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: invalidateKey });
			toast.success("Folder renamed");
			onOpenChange(false);
		},
		onError: () => {
			toast.error("Could not rename folder");
		},
	});

	const pending = createMutation.isPending || renameMutation.isPending;
	const trimmed = name.trim();
	const valid = trimmed.length > 0 && trimmed.length <= 255;

	const submit = () => {
		if (!valid || pending) {
			return;
		}

		if (isRename) {
			renameMutation.mutate();

			return;
		}

		createMutation.mutate();
	};

	return (
		<SlideOver
			open={open}
			onOpenChange={onOpenChange}
			title={isRename ? "Rename folder" : "New folder"}
			description={
				isRename
					? `Rename “${folder?.name}”.`
					: "Folders inherit permissions from their parent."
			}
			width="sm"
			footer={
				<div className="flex justify-end gap-2">
					<button
						type="button"
						className="rounded-md border border-border px-3 py-1.5 text-[12.5px] hover:bg-hover"
						onClick={() => onOpenChange(false)}
					>
						Cancel
					</button>
					<Button variant="primary" disabled={!valid || pending} onClick={submit}>
						{pending ? "Saving…" : isRename ? "Rename" : "Create"}
					</Button>
				</div>
			}
		>
			<Field label="Name">
				<input
					autoFocus
					className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
					value={name}
					onChange={(e) => setName(e.target.value)}
					onKeyDown={(e) => {
						if (e.key === "Enter") {
							e.preventDefault();
							submit();
						}
					}}
					placeholder="e.g. Press releases"
					maxLength={255}
				/>
			</Field>
		</SlideOver>
	);
};
