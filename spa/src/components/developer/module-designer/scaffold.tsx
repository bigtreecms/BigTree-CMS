import type { DragEvent, ReactNode } from "react";
import { GripVertical, Pencil, Plus, Trash, X } from "lucide-react";

import { Button } from "@/components/ui/Button";

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
			<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-6 text-center text-[12.5px] text-text-3">
				{emptyLabel}
			</div>
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
		{reorderable && (
			<span
				className="grid h-6 w-6 flex-shrink-0 cursor-grab place-items-center rounded text-text-4 hover:bg-hover hover:text-text-2 active:cursor-grabbing"
				title="Drag to reorder"
				aria-hidden="true"
			>
				<GripVertical size={14} />
			</span>
		)}
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
			{subtitle && (
				<span className="truncate font-mono text-[11px] text-text-3">{subtitle}</span>
			)}
		</button>
		<button
			type="button"
			className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
			onClick={onEdit}
			title="Edit"
			aria-label="Edit"
		>
			<Pencil size={13} />
		</button>
		<button
			type="button"
			className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
			onClick={onDelete}
			title="Delete"
			aria-label="Delete"
		>
			<Trash size={13} />
		</button>
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
	<div className="rounded-xl border border-border bg-surface">
		<div className="flex items-center justify-between border-b border-border px-4 py-2.5">
			<span className="text-[13px] font-semibold text-text">{title}</span>
			<button
				type="button"
				className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
				onClick={onClose}
				aria-label="Close editor"
			>
				<X size={14} />
			</button>
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
	</div>
);
