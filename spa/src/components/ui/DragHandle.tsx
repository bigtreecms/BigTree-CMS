import type { HTMLAttributes } from "react";
import { GripVertical } from "lucide-react";

interface DragHandleProps extends HTMLAttributes<HTMLSpanElement> {
	/** When false, the grip dims and loses its grab cursor (reordering disabled). Default true. */
	enabled?: boolean;
	/** Native tooltip — defaults to "Drag to reorder". */
	title?: string;
	/** `GripVertical` icon size. Default 14. */
	size?: number;
	/**
	 * Whether the handle element itself carries the HTML5 drag. Most rows put
	 * `draggable`/`onDragStart` on the row and use this purely as a visual grip;
	 * pass `draggable` (+ the drag handlers via `...rest`) when the handle is the
	 * drag source.
	 */
	draggable?: boolean;
}

/**
 * The shared drag-reorder grip — the small `GripVertical` affordance that sits
 * at the start of a sortable row (list views, the resource designer, module
 * designer tabs). Pairs with `useDragReorder` for the logic; this is just the
 * consistent visual handle. Extra DOM props (`onDragStart`, `onClick`,
 * `aria-hidden`, layout `className` like `shrink-0`) pass through to the span.
 */
const ENABLED = "cursor-grab hover:bg-hover hover:text-text-2 active:cursor-grabbing";
const DISABLED = "cursor-default opacity-25";

export const DragHandle = ({
	enabled = true,
	title = "Drag to reorder",
	size = 14,
	className,
	...rest
}: DragHandleProps) => (
	<span
		className={`grid size-6 shrink-0 place-items-center rounded text-text-4 ${
			enabled ? ENABLED : DISABLED
		}${className ? ` ${className}` : ""}`}
		title={title}
		aria-hidden="true"
		{...rest}
	>
		<GripVertical size={size} />
	</span>
);
