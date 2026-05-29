import type { ReactNode } from "react";
import { ArrowDown, ArrowUp, Pencil, Plus, Trash, X } from "lucide-react";

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
	onMoveUp?: () => void;
	onMoveDown?: () => void;
	canMoveUp?: boolean;
	canMoveDown?: boolean;
}

export const SubRow = ({
	title,
	subtitle,
	badge,
	onEdit,
	onDelete,
	onMoveUp,
	onMoveDown,
	canMoveUp,
	canMoveDown,
}: SubRowProps) => (
	<li className="flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
		{(onMoveUp || onMoveDown) && (
			<div className="flex flex-col">
				<button
					type="button"
					className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text disabled:opacity-30"
					onClick={onMoveUp}
					disabled={!canMoveUp}
					aria-label="Move up"
				>
					<ArrowUp size={11} />
				</button>
				<button
					type="button"
					className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text disabled:opacity-30"
					onClick={onMoveDown}
					disabled={!canMoveDown}
					aria-label="Move down"
				>
					<ArrowDown size={11} />
				</button>
			</div>
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
	<button
		type="button"
		className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
		onClick={onClick}
	>
		<Plus size={13} />
		{label}
	</button>
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
			<button
				type="button"
				className="rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] text-text-2 hover:bg-hover"
				onClick={onClose}
			>
				Cancel
			</button>
			<button
				type="button"
				className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-50 hover:bg-accent-hover"
				onClick={onSave}
				disabled={saving}
			>
				{saving ? "Saving…" : saveLabel}
			</button>
		</div>
	</div>
);
