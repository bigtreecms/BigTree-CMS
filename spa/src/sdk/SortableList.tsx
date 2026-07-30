import { useCallback, useRef, useState, type ReactNode } from "react";

import { DragHandle } from "@/components/ui/DragHandle";
import { useDragReorder } from "@/hooks/useDragReorder";

/** Default MIME type for {@link DragSource} ↔ {@link SortableList} external drops. */
export const BIGTREE_DRAG_TYPE = "application/x-bigtree-drag";

export interface SortableItem {
	id: string | number;
}

export interface SortableItemRenderApi {
	/** Spread onto the row (or a nested handle) to enable HTML5 drag. */
	dragHandleProps: {
		draggable: boolean;
		onDragEnd: () => void;
		onDragOver: (e: React.DragEvent) => void;
		onDragStart: (e: React.DragEvent) => void;
		onDrop: (e: React.DragEvent) => void;
	};
	/** Ready-made grip; put it at the start of the row. */
	handle: ReactNode;
	isDragging: boolean;
	isOver: boolean;
}

export interface ExternalDropInfo {
	/** Insertion index (0 = before first item, `items.length` = after last). */
	index: number;
	/** Parsed JSON payload from {@link DragSource}. */
	payload: unknown;
}

interface SortableListProps<T extends SortableItem> {
	className?: string;
	/**
	 * Accept external {@link DragSource} drops. Pass `true` for the default
	 * MIME type, or a custom type string that matches `DragSource type=…`.
	 */
	acceptExternal?: boolean | string;
	/** Gap between items in px (default 8). */
	gap?: number;
	items: T[];
	/**
	 * Called after a successful internal reorder. Parent should update state.
	 */
	onChange: (next: T[]) => void;
	/**
	 * Optional commit hook after internal reorder (e.g. persist ids).
	 */
	onCommit?: (orderedIds: Array<string | number>) => void;
	/**
	 * Insert an external payload at `index`. Required when `acceptExternal` is set.
	 */
	onExternalDrop?: (info: ExternalDropInfo) => void;
	/**
	 * Placeholder when the list is empty and accepts external drops
	 * (defaults to a simple drop target message).
	 */
	emptyDropLabel?: ReactNode;
	/** Render one row. Receives the item plus drag chrome. */
	renderItem: (item: T, api: SortableItemRenderApi) => ReactNode;
}

const typesInclude = (dt: DataTransfer, type: string): boolean => {
	const types = Array.from(dt.types || []);

	return types.includes(type) || types.includes(type.toLowerCase());
};

const readPayload = (dt: DataTransfer, type: string): unknown => {
	const raw = dt.getData(type) || dt.getData(type.toLowerCase());

	if (!raw) {
		return null;
	}

	try {
		return JSON.parse(raw);
	} catch {
		return raw;
	}
};

const DropLine = () => (
	<div
		aria-hidden
		className="relative h-0"
		style={{ marginBlock: -1 }}
	>
		<div className="absolute inset-x-1 top-1/2 z-10 h-0.5 -translate-y-1/2 rounded-full bg-accent shadow-[0_0_0_2px] shadow-accent/20" />
	</div>
);

/**
 * HTML5 drag-reorder list for custom actions. Built on the same
 * {@link useDragReorder} hook as draggable module views — no extra dependency.
 *
 * Supports **external drops** from {@link DragSource} (e.g. a field palette):
 * drag over a slot to see an insert line, drop to call `onExternalDrop`.
 *
 * @example
 * ```tsx
 * <DragSource data={{ type: "text" }}>
 *   <button type="button">Text</button>
 * </DragSource>
 *
 * <SortableList
 *   items={fields}
 *   onChange={setFields}
 *   acceptExternal
 *   onExternalDrop={({ index, payload }) => insertField(index, payload)}
 *   renderItem={(item, { handle }) => <Row>{handle}{item.label}</Row>}
 * />
 * ```
 */
export const SortableList = <T extends SortableItem>({
	items,
	onChange,
	onCommit,
	renderItem,
	gap = 8,
	className = "",
	acceptExternal = false,
	onExternalDrop,
	emptyDropLabel,
}: SortableListProps<T>) => {
	const drag = useDragReorder<T, string | number>(items, onChange, onCommit);
	const externalType =
		acceptExternal === true
			? BIGTREE_DRAG_TYPE
			: typeof acceptExternal === "string"
				? acceptExternal
				: "";

	const [insertIndex, setInsertIndex] = useState<number | null>(null);
	const listRef = useRef<HTMLDivElement | null>(null);

	const clearInsert = useCallback(() => setInsertIndex(null), []);

	const isExternal = useCallback(
		(e: React.DragEvent) => externalType !== "" && typesInclude(e.dataTransfer, externalType),
		[externalType]
	);

	const handleExternalOver = useCallback(
		(e: React.DragEvent, index: number, el: HTMLElement) => {
			if (!isExternal(e)) {
				return false;
			}

			e.preventDefault();
			e.stopPropagation();
			e.dataTransfer.dropEffect = "copy";

			const rect = el.getBoundingClientRect();
			const before = e.clientY < rect.top + rect.height / 2;
			setInsertIndex(before ? index : index + 1);

			return true;
		},
		[isExternal]
	);

	const handleExternalDrop = useCallback(
		(e: React.DragEvent, fallbackIndex: number) => {
			if (!isExternal(e) || !onExternalDrop || !externalType) {
				return false;
			}

			e.preventDefault();
			e.stopPropagation();

			const payload = readPayload(e.dataTransfer, externalType);
			const index = insertIndex ?? fallbackIndex;
			setInsertIndex(null);

			if (payload != null) {
				onExternalDrop({ index, payload });
			}

			return true;
		},
		[isExternal, onExternalDrop, externalType, insertIndex]
	);

	// Empty list — full drop target.
	if (items.length === 0) {
		if (!externalType || !onExternalDrop) {
			return <div className={className} />;
		}

		return (
			<div
				className={`rounded-lg border border-dashed border-border bg-surface-2 px-4 py-8 text-center text-[12.5px] text-text-3 transition-colors ${className}`}
				onDragLeave={(e) => {
					if (e.currentTarget === e.target) {
						clearInsert();
					}
				}}
				onDragOver={(e) => {
					if (!isExternal(e)) {
						return;
					}

					e.preventDefault();
					e.dataTransfer.dropEffect = "copy";
					setInsertIndex(0);
				}}
				onDrop={(e) => {
					handleExternalDrop(e, 0);
				}}
			>
				{emptyDropLabel ?? "Drop items here"}
			</div>
		);
	}

	return (
		<div
			ref={listRef}
			className={className}
			style={{ display: "flex", flexDirection: "column", gap }}
			onDragLeave={(e) => {
				// Leaving the list entirely clears the insert marker.
				const related = e.relatedTarget as Node | null;

				if (related && listRef.current?.contains(related)) {
					return;
				}

				clearInsert();
			}}
		>
			{items.map((item, index) => {
				const isDragging = drag.dragId === item.id;
				const isOver = drag.overId === item.id && drag.dragId !== item.id && insertIndex === null;

				const dragHandleProps = {
					draggable: true as const,
					onDragStart: (e: React.DragEvent) => {
						clearInsert();
						drag.onDragStart(e, item.id);
					},
					onDragOver: (e: React.DragEvent) => {
						if (handleExternalOver(e, index, e.currentTarget as HTMLElement)) {
							return;
						}

						drag.onDragOver(e, item.id);
					},
					onDrop: (e: React.DragEvent) => {
						if (handleExternalDrop(e, index)) {
							return;
						}

						drag.onDrop(e);
					},
					onDragEnd: () => {
						clearInsert();
						drag.onDragEnd();
					},
				};

				const handle = (
					<span className="inline-flex shrink-0">
						<DragHandle enabled />
					</span>
				);

				return (
					<div key={String(item.id)}>
						{insertIndex === index ? <DropLine /> : null}
						<div
							className={
								isOver
									? "rounded-lg ring-2 ring-accent/40 ring-offset-1 ring-offset-bg"
									: isDragging
										? "opacity-60"
										: undefined
							}
							{...dragHandleProps}
						>
							{renderItem(item, { handle, dragHandleProps, isDragging, isOver })}
						</div>
					</div>
				);
			})}

			{insertIndex === items.length ? <DropLine /> : null}

			{/* Trailing hit area so you can drop after the last item easily. */}
			{externalType && onExternalDrop ? (
				<div
					aria-hidden
					className="min-h-3 flex-1"
					onDragOver={(e) => {
						if (!isExternal(e)) {
							return;
						}

						e.preventDefault();
						e.dataTransfer.dropEffect = "copy";
						setInsertIndex(items.length);
					}}
					onDrop={(e) => {
						handleExternalDrop(e, items.length);
					}}
				/>
			) : null}
		</div>
	);
};

// ── DragSource ───────────────────────────────────────────────────────────────

interface DragSourceProps {
	children: ReactNode;
	className?: string;
	/** Payload stored as JSON (any serializable value). */
	data: unknown;
	disabled?: boolean;
	/** `copy` for palettes, `move` when relocating. Default `copy`. */
	effect?: "copy" | "move" | "copyMove";
	/**
	 * MIME type — must match `SortableList acceptExternal` when dropping onto a list.
	 * Default {@link BIGTREE_DRAG_TYPE}.
	 */
	type?: string;
}

/**
 * Makes its child a drag source for {@link SortableList} external drops.
 * Click handlers on the child still work (drag only starts after movement).
 */
export const DragSource = ({
	children,
	data,
	type = BIGTREE_DRAG_TYPE,
	effect = "copy",
	disabled = false,
	className = "",
}: DragSourceProps) => (
	<div
		className={className}
		draggable={!disabled}
		style={{ cursor: disabled ? undefined : "grab" }}
		onDragStart={(e) => {
			if (disabled) {
				e.preventDefault();

				return;
			}

			try {
				e.dataTransfer.setData(type, JSON.stringify(data));
				// Some browsers require a text/plain fallback for the drag to start.
				e.dataTransfer.setData("text/plain", typeof data === "string" ? data : JSON.stringify(data));
			} catch {
				// Ignore setData failures in restricted environments.
			}

			e.dataTransfer.effectAllowed = effect;
		}}
	>
		{children}
	</div>
);

export { DragHandle, useDragReorder };
