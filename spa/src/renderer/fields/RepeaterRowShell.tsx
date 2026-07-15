import type { ReactNode } from "react";
import { GripVertical, Trash } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";

import { CollapsibleRowHeader } from "./CollapsibleRowHeader";

interface RepeaterRowShellProps {
	/** Panel body rendered when expanded. */
	children: ReactNode;
	/** Overrides the `<li>` wrapper classes (Matrix's callout-style variant). */
	className?: string;
	/** Disables the move/delete row actions (not the panel body). */
	disabled?: boolean;
	expanded: boolean;
	/** Disables the expand toggle itself — e.g. a callout whose type is missing. */
	headerDisabled?: boolean;
	index: number;
	/** Leading cell before the title region (MediaGallery's thumbnail). */
	leading?: ReactNode;
	onDelete: () => void;
	onMove: (dir: "up" | "down") => void;
	onToggle: () => void;
	/** id shared by the toggle's `aria-controls` and the panel element. */
	panelId: string;
	/** Stack title over subtitle (MediaGallery's taller row). */
	stacked?: boolean;
	subtitle?: ReactNode;
	title: ReactNode;
	total: number;
	/** Status pills rendered after the title (Callouts' Locked / Missing type). */
	trailing?: ReactNode;
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
				disabled={disabled || index === 0}
				label="Move up"
				title="Move up"
				onClick={() => onMove("up")}
			>
				<GripVertical size={13} />
			</IconButton>

			{leading}

			<CollapsibleRowHeader
				controls={panelId}
				disabled={headerDisabled}
				open={expanded}
				stacked={stacked}
				subtitle={subtitle}
				title={title}
				trailing={trailing}
				onToggle={onToggle}
			/>

			<IconButton
				disabled={disabled}
				label="Delete item"
				title="Delete item"
				tone="danger"
				onClick={onDelete}
			>
				<Trash size={13} />
			</IconButton>
		</div>

		{expanded && (
			<div className="border-t border-border px-3 pb-1 pt-3" id={panelId}>
				{children}

				{index < total - 1 && (
					<Button
						className="mb-2"
						disabled={disabled}
						size="sm"
						variant="secondary"
						onClick={() => onMove("down")}
					>
						Move down
					</Button>
				)}
			</div>
		)}
	</li>
);
