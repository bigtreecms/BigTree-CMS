import type { DragEvent, ReactNode } from "react";
import { Pencil, Plus, Trash, X } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";
import { MonoText } from "@/components/ui/MonoText";
import { DragHandle } from "@/components/ui/DragHandle";
import type { UseConfirmDialogResult } from "@/hooks/useConfirmDialog";

/**
 * Presentational chrome shared by every sub-resource tab (actions, forms,
 * views, reports, embed forms): a row list with edit/delete affordances, an
 * add button, an empty state, and an editor card with a save/cancel footer.
 */

interface SubListProps {
	isLoading?: boolean;
	loadingLabel?: string;
	emptyLabel: string;
	isEmpty: boolean;
	children: ReactNode;
}

export const SubList = ({
	isLoading,
	loadingLabel = "Loading…",
	emptyLabel,
	isEmpty,
	children,
}: SubListProps) => {
	if (isLoading) {
		return (
			<div className="rounded-md border border-border bg-surface p-9 text-center text-[13px] text-text-3">
				{loadingLabel}
			</div>
		);
	}

	if (isEmpty) {
		return (
			<InlineEmpty align="center" pad="xl">
				{emptyLabel}
			</InlineEmpty>
		);
	}

	return <ul className="space-y-1.5">{children}</ul>;
};

interface SubRowProps {
	title: string;
	subtitle?: string;
	badge?: string;
	onEdit: () => void;
	onDelete: () => void;
	/** When set, the row shows a drag handle and becomes a drag-to-reorder source. */
	reorderable?: boolean;
	isDragging?: boolean;
	isDropTarget?: boolean;
	onDragStart?: (e: DragEvent) => void;
	onDragOver?: (e: DragEvent) => void;
	onDrop?: (e: DragEvent) => void;
	onDragEnd?: () => void;
}

export const SubRow = ({
	title,
	subtitle,
	badge,
	onEdit,
	onDelete,
	reorderable,
	isDragging,
	isDropTarget,
	onDragStart,
	onDragOver,
	onDrop,
	onDragEnd,
}: SubRowProps) => (
	<li
		className={`flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2 transition-colors ${
			isDragging ? "bg-accent-soft shadow-md" : ""
		} ${isDropTarget ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""}`}
		draggable={reorderable}
		onDragStart={onDragStart}
		onDragOver={onDragOver}
		onDrop={onDrop}
		onDragEnd={onDragEnd}
	>
		{reorderable && <DragHandle />}
		<button
			type="button"
			className="flex min-w-0 flex-1 items-center gap-2 text-left"
			onClick={onEdit}
		>
			<span className="truncate text-[12.5px] font-medium text-text">{title}</span>
			{badge && (
				<span className="rounded bg-surface-2 px-1.5 py-0.5 text-[10.5px] text-text-3">
					{badge}
				</span>
			)}
			{subtitle && <MonoText>{subtitle}</MonoText>}
		</button>
		<IconButton onClick={onEdit} title="Edit" label="Edit">
			<Pencil size={13} />
		</IconButton>
		<IconButton tone="danger" onClick={onDelete} title="Delete" label="Delete">
			<Trash size={13} />
		</IconButton>
	</li>
);

interface AddSubButtonProps {
	label: string;
	onClick: () => void;
}

export const AddSubButton = ({ label, onClick }: AddSubButtonProps) => (
	<Button variant="secondary" icon={<Plus size={13} />} onClick={onClick}>
		{label}
	</Button>
);

interface EditorCardProps {
	title: string;
	onClose: () => void;
	onSave: () => void;
	saving?: boolean;
	saveLabel?: string;
	children: ReactNode;
}

export const EditorCard = ({
	title,
	onClose,
	onSave,
	saving,
	saveLabel = "Save",
	children,
}: EditorCardProps) => (
	<Card>
		<div className="flex items-center justify-between border-b border-border px-4 py-2.5">
			<span className="text-[13px] font-semibold text-text">{title}</span>
			<IconButton onClick={onClose} label="Close editor">
				<X size={14} />
			</IconButton>
		</div>
		<div className="space-y-4 p-4">{children}</div>
		<div className="flex justify-end gap-2 border-t border-border px-4 py-3">
			<Button variant="secondary" onClick={onClose}>
				Cancel
			</Button>
			<Button variant="primary" onClick={onSave} disabled={saving}>
				{saving ? "Saving…" : saveLabel}
			</Button>
		</div>
	</Card>
);

interface SubDeleteDialogProps<T extends { id: string }> {
	dialog: UseConfirmDialogResult<T>;
	/** Singular resource noun; drives the title and confirm label (e.g. "form"). */
	noun: string;
	/** Human label for the row being deleted (e.g. `(f) => f.title`). */
	labelFor: (item: T) => string;
	description: string;
	onConfirm: (id: string) => void;
}

/**
 * The delete-confirmation dialog shared by every sub-resource tab: renders the
 * `dialog.item &&` guard, the danger `ConfirmDialog`, and the close/remove
 * wiring. Only the noun and copy differ between tabs.
 */
export const SubDeleteDialog = <T extends { id: string }>({
	dialog,
	noun,
	labelFor,
	description,
	onConfirm,
}: SubDeleteDialogProps<T>) => {
	if (!dialog.item) {
		return null;
	}

	return (
		<ConfirmDialog
			open={dialog.isOpen}
			onOpenChange={(v) => {
				if (!v) {
					dialog.close();
				}
			}}
			title={`Delete ${noun} "${labelFor(dialog.item)}"?`}
			description={description}
			confirmLabel={`Delete ${noun}`}
			variant="danger"
			onConfirm={() => {
				onConfirm(dialog.item!.id);
				dialog.close();
			}}
		/>
	);
};
