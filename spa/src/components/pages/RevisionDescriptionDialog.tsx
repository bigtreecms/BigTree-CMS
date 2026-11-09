import { useEffect, useState } from "react";

import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { TextInput } from "@/components/ui/TextInput";

interface RevisionDescriptionDialogProps {
	/** The description to start from (blank when keeping an auto-saved revision). */
	initial: string;
	/** True when the revision is an auto-saved one being kept. */
	keeping: boolean;
	onOpenChange: (open: boolean) => void;
	onSave: (description: string) => void;
	open: boolean;
	saving: boolean;
	/** When the revision was taken, for the dialog's description. */
	updatedAt: string;
}

/**
 * Keep an auto-saved revision, or change a kept revision's description. Keeping
 * one moves it to "Saved revisions", where auto-revision pruning never reaches.
 */
export const RevisionDescriptionDialog = ({
	initial,
	keeping,
	onOpenChange,
	onSave,
	open,
	saving,
	updatedAt,
}: RevisionDescriptionDialogProps) => {
	const [description, setDescription] = useState(initial);

	useEffect(() => {
		if (open) {
			setDescription(initial);
		}
	}, [open, initial]);

	return (
		<Modal
			description={
				keeping
					? `Keep the version from ${updatedAt} as a saved revision, so it isn't pruned with the auto-saved ones.`
					: `Change the description of the revision from ${updatedAt}.`
			}
			footer={
				<>
					<Button onClick={() => onOpenChange(false)}>Cancel</Button>
					<Button
						loading={saving}
						loadingLabel="Saving…"
						variant="primary"
						onClick={() => onSave(description.trim())}
					>
						{keeping ? "Keep revision" : "Save description"}
					</Button>
				</>
			}
			open={open}
			size="sm"
			title={keeping ? "Keep this revision?" : "Edit description"}
			onOpenChange={onOpenChange}
		>
			<form
				onSubmit={(event) => {
					event.preventDefault();
					onSave(description.trim());
				}}
			>
				<Field inlineHint="(what's special about this version?)" label="Short description">
					<TextInput
						autoFocus
						placeholder="Optional"
						value={description}
						onChange={(event) => setDescription(event.target.value)}
					/>
				</Field>
			</form>
		</Modal>
	);
};
