import { ChevronDown, ChevronUp, Trash2 } from "lucide-react";

import { IconButton } from "@/components/ui/IconButton";

interface RowReorderControlsProps {
	onMoveUp: () => void;
	onMoveDown: () => void;
	onRemove: () => void;
	/** Disables move-up — the row is already first. */
	isFirst: boolean;
	/** Disables move-down — the row is already last. */
	isLast: boolean;
	/**
	 * Noun woven into the accessible labels ("Move sub-field up", "Remove
	 * setting"). Defaults to "row".
	 */
	itemLabel?: string;
}

/**
 * The move-up / move-down / remove icon cluster shared by the reorderable list
 * editors (the schema builders, and any future ordered-row editor). Built on
 * {@link IconButton} so the hit target, hover, and disabled treatment stay
 * consistent; remove is `tone="danger"`.
 */
export const RowReorderControls = ({
	onMoveUp,
	onMoveDown,
	onRemove,
	isFirst,
	isLast,
	itemLabel = "row",
}: RowReorderControlsProps) => (
	<div className="flex items-center gap-1">
		<IconButton
			label={`Move ${itemLabel} up`}
			title="Move up"
			onClick={onMoveUp}
			disabled={isFirst}
		>
			<ChevronUp size={14} />
		</IconButton>
		<IconButton
			label={`Move ${itemLabel} down`}
			title="Move down"
			onClick={onMoveDown}
			disabled={isLast}
		>
			<ChevronDown size={14} />
		</IconButton>
		<IconButton label={`Remove ${itemLabel}`} title="Remove" tone="danger" onClick={onRemove}>
			<Trash2 size={14} />
		</IconButton>
	</div>
);
