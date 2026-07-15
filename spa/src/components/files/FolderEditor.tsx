import { useEffect, useState } from "react";
import { useToastMutation } from "@/hooks/useToastMutation";

import { Button } from "@/components/ui/Button";
import { SlideOver } from "@/components/ui/SlideOver";
import { Field } from "@/components/ui/Field";
import { TextInput } from "@/components/ui/TextInput";
import { resourceFoldersApi, type ResourceFolderRow } from "@/api/endpoints/resource-folders";

interface FolderEditorProps {
	/** When set, the SlideOver renames this folder instead of creating a new one. */
	folder?: ResourceFolderRow | null;
	/** Query key to invalidate on success — typically the folder-contents key. */
	invalidateKey: readonly unknown[];
	onOpenChange: (open: boolean) => void;
	open: boolean;
	/** Parent folder id for creates; ignored for renames. 0 = home. */
	parentId: number;
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
	const isRename = !!folder;
	const [name, setName] = useState(folder?.name ?? "");

	// Reset the input every time the SlideOver is (re)opened so stale text from
	// a previous open doesn't leak across modes.
	useEffect(() => {
		if (open) {
			setName(folder?.name ?? "");
		}
	}, [open, folder?.name]);

	const createMutation = useToastMutation({
		mutationFn: () => resourceFoldersApi.create({ parent: parentId, name: name.trim() }),
		invalidate: [[...invalidateKey]],
		successMessage: "Folder created",
		errorMessage: "Could not create folder",
		onSuccess: () => {
			onOpenChange(false);
		},
	});

	const renameMutation = useToastMutation({
		mutationFn: () => {
			if (!folder) {
				throw new Error("rename: no folder");
			}

			return resourceFoldersApi.update(folder.id, { name: name.trim() });
		},
		invalidate: [[...invalidateKey]],
		successMessage: "Folder renamed",
		errorMessage: "Could not rename folder",
		onSuccess: () => {
			onOpenChange(false);
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
			description={
				isRename
					? `Rename “${folder?.name}”.`
					: "Folders inherit permissions from their parent."
			}
			footer={
				<div className="flex justify-end gap-2">
					<Button variant="secondary" onClick={() => onOpenChange(false)}>
						Cancel
					</Button>
					<Button disabled={!valid || pending} variant="primary" onClick={submit}>
						{pending ? "Saving…" : isRename ? "Rename" : "Create"}
					</Button>
				</div>
			}
			open={open}
			title={isRename ? "Rename folder" : "New folder"}
			width="sm"
			onOpenChange={onOpenChange}
		>
			<Field label="Name">
				<TextInput
					autoFocus
					maxLength={255}
					placeholder="e.g. Press releases"
					value={name}
					onChange={(e) => setName(e.target.value)}
					onKeyDown={(e) => {
						if (e.key === "Enter") {
							e.preventDefault();
							submit();
						}
					}}
				/>
			</Field>
		</SlideOver>
	);
};
