import type { ReactNode } from "react";
import { GripVertical, Trash } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";

import { CollapsibleRowHeader } from "./CollapsibleRowHeader";

interface RepeaterRowShellProps {
	index: number;
	total: number;
	expanded: boolean;
	onToggle: () => void;
	onMove: (dir: "up" | "down") => void;
	onDelete: () => void;
	/** Disables the move/delete row actions (not the panel body). */
	disabled?: boolean;
	/** id shared by the toggle's `aria-controls` and the panel element. */
	panelId: string;
	title: ReactNode;
	subtitle?: ReactNode;
	/** Status pills rendered after the title (Callouts' Locked / Missing type). */
	trailing?: ReactNode;
	/** Stack title over subtitle (MediaGallery's taller row). */
	stacked?: boolean;
	/** Disables the expand toggle itself — e.g. a callout whose type is missing. */
	headerDisabled?: boolean;
	/** Leading cell before the title region (MediaGallery's thumbnail). */
	leading?: ReactNode;
	/** Overrides the `<li>` wrapper classes (Matrix's callout-style variant). */
	className?: string;
	/** Panel body rendered when expanded. */
	children: ReactNode;
}

const DEFAULT_WRAPPER = "rounded-md border border-border bg-surface";

/**
 * The shared per-row chrome for the repeating content fields (Matrix, Callouts,
 * MediaGallery): the `<li>` wrapper, a move-up handle, the collapsible title
 * header, a delete action, and — when expanded — the panel body plus a trailing
 * "Move down". The panel body, title derivation, and any leading thumbnail /
 * status pills stay in the caller (threaded through `children` / `leading` /
 * `trailing`), so each field keeps whatever is genuinely its own.
 */
export const RepeaterRowShell = ({
	index,
	total,
	expanded,
	onToggle,
	onMove,
	onDelete,
	disabled,
	panelId,
	title,
	subtitle,
	trailing,
	stacked,
	headerDisabled,
	leading,
	className,
	children,
}: RepeaterRowShellProps) => (
	<li className={className ?? DEFAULT_WRAPPER}>
		<div
			className={
				leading ? "flex items-stretch gap-2 p-2" : "flex items-center gap-2 px-2 py-1.5"
			}
		>
			<IconButton
				label="Move up"
				title="Move up"
				onClick={() => onMove("up")}
				disabled={disabled || index === 0}
			>
				<GripVertical size={13} />
			</IconButton>

			{leading}

			<CollapsibleRowHeader
				open={expanded}
				onToggle={onToggle}
				controls={panelId}
				title={title}
				subtitle={subtitle}
				trailing={trailing}
				stacked={stacked}
				disabled={headerDisabled}
			/>

			<IconButton
				label="Delete item"
				title="Delete item"
				tone="danger"
				onClick={onDelete}
				disabled={disabled}
			>
				<Trash size={13} />
			</IconButton>
		</div>

		{expanded && (
			<div id={panelId} className="border-t border-border px-3 pb-1 pt-3">
				{children}

				{index < total - 1 && (
					<Button
						variant="secondary"
						size="sm"
						className="mb-2"
						onClick={() => onMove("down")}
						disabled={disabled}
					>
						Move down
					</Button>
				)}
			</div>
		)}
	</li>
);
