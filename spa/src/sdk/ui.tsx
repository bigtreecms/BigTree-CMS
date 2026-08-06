import type { CSSProperties, HTMLAttributes, ReactNode } from "react";

/**
 * `@bigtree/ui` — stable primitives for custom module actions.
 *
 * Built on the admin design tokens (`bg-surface`, `text-text`, `border-border`, …)
 * so light and dark themes both work without hand-rolled colors. Prefer these
 * over raw HTML + inline styles.
 *
 * Import map: `public/sdk/ui.js` → `registerSdk()` globals. See registerSdk.ts.
 */

// ── Layout ───────────────────────────────────────────────────────────────────

interface StackProps {
	children: ReactNode;
	className?: string;
	/** Vertical gap in px (default 16). */
	gap?: number;
}

/** Vertical flex column. */
export const Stack = ({ children, gap = 16, className = "" }: StackProps) => {
	const style: CSSProperties = { display: "flex", flexDirection: "column", gap };

	return (
		<div className={className} style={style}>
			{children}
		</div>
	);
};

interface RowProps {
	children: ReactNode;
	className?: string;
	/** Horizontal gap in px (default 8). */
	gap?: number;
	/** Align items on the cross axis (default center). */
	align?: "start" | "center" | "end" | "stretch";
	/** Main-axis distribution. */
	justify?: "start" | "center" | "end" | "between";
	wrap?: boolean;
}

/** Horizontal flex row. */
export const Row = ({
	children,
	gap = 8,
	className = "",
	align = "center",
	justify = "start",
	wrap = false,
}: RowProps) => {
	const alignMap = {
		start: "flex-start",
		center: "center",
		end: "flex-end",
		stretch: "stretch",
	} as const;
	const justifyMap = {
		start: "flex-start",
		center: "center",
		end: "flex-end",
		between: "space-between",
	} as const;

	const style: CSSProperties = {
		display: "flex",
		alignItems: alignMap[align],
		justifyContent: justifyMap[justify],
		flexWrap: wrap ? "wrap" : "nowrap",
		gap,
	};

	return (
		<div className={className} style={style}>
			{children}
		</div>
	);
};

/** Horizontal rule using the border token. */
export const Divider = ({ className = "" }: { className?: string }) => (
	<hr className={`my-0 border-0 border-t border-border ${className}`} />
);

/** Flexible spacer for Row/Stack. */
export const Spacer = () => <div className="min-w-0 flex-1" />;

/** CSS grid container + item (sidebar layouts, multi-column forms, …). */
export { Grid, GridItem } from "./Grid";
export type { GridCollapseBelow, GridColumns } from "./Grid";

// ── Typography ───────────────────────────────────────────────────────────────

type HeadingLevel = 1 | 2 | 3 | 4;

const HEADING_CLASS: Record<HeadingLevel, string> = {
	1: "text-[18px] font-semibold tracking-tight text-text text-balance",
	2: "text-[15px] font-semibold tracking-tight text-text text-balance",
	3: "text-[13px] font-semibold text-text text-balance",
	4: "text-[12px] font-semibold text-text-2 text-balance",
};

interface HeadingProps {
	children: ReactNode;
	className?: string;
	/** Visual + semantic level (default 2). */
	level?: HeadingLevel;
}

/** Page / section heading. Levels map to h1–h4 with token colors. */
export const Heading = ({ children, level = 2, className = "" }: HeadingProps) => {
	const Tag = (`h${level}` as "h1" | "h2" | "h3" | "h4");

	return <Tag className={`${HEADING_CLASS[level]} ${className}`}>{children}</Tag>;
};

interface TextProps {
	children: ReactNode;
	className?: string;
	/** Muted secondary body copy. */
	muted?: boolean;
	/** Slightly smaller dense body. */
	size?: "sm" | "md";
}

/** Body copy. Prefer over bare `<p>` so dark mode stays correct. */
export const Text = ({ children, muted, size = "md", className = "" }: TextProps) => (
	<p
		className={`${size === "sm" ? "text-[12.5px]" : "text-[13px]"} leading-relaxed text-pretty ${
			muted ? "text-text-3" : "text-text-2"
		} ${className}`}
	>
		{children}
	</p>
);

/** Muted helper / caption. */
export const Note = ({ children, className = "" }: { children: ReactNode; className?: string }) => (
	<p className={`text-[12px] leading-snug text-text-3 ${className}`}>{children}</p>
);

/** Uppercase overline for grouping controls (settings sections, palettes). */
export { SectionLabel } from "@/components/ui/SectionLabel";

export { MonoText } from "@/components/ui/MonoText";

// ── Surfaces ─────────────────────────────────────────────────────────────────

export { Card, CardFooter, CardHeader } from "@/components/ui/Card";

interface PanelProps extends Omit<HTMLAttributes<HTMLElement>, "title"> {
	children: ReactNode;
	/** Optional header bar title. */
	title?: ReactNode;
	/** Sub-line under the title. */
	description?: ReactNode;
	/** Actions aligned to the right of the header. */
	actions?: ReactNode;
	/** Remove default padding from the body. */
	flush?: boolean;
}

/**
 * Section panel — bordered card with an optional header bar. Use for form
 * editor columns, settings groups, and field cards.
 */
export const Panel = ({
	children,
	title,
	description,
	actions,
	flush,
	className = "",
	...rest
}: PanelProps) => (
	<section
		className={`overflow-hidden rounded-xl border border-border bg-surface ${className}`}
		{...rest}
	>
		{(title || description || actions) && (
			<header className="flex items-start justify-between gap-3 border-b border-border bg-surface-2 px-4 py-3">
				<div className="min-w-0">
					{title ? <h3 className="text-[13px] font-semibold text-text">{title}</h3> : null}
					{description ? <p className="mt-0.5 text-[11.5px] text-text-3">{description}</p> : null}
				</div>
				{actions ? <div className="flex shrink-0 items-center gap-1.5">{actions}</div> : null}
			</header>
		)}
		<div className={flush ? undefined : "p-4"}>{children}</div>
	</section>
);

// ── Tabs ─────────────────────────────────────────────────────────────────────

export { TabPanel, Tabs } from "./Tabs";
export type { TabItem } from "./Tabs";

// ── Feedback ─────────────────────────────────────────────────────────────────

export { Alert } from "@/components/ui/Alert";
export type { AlertTone } from "@/components/ui/Alert";
export { Badge } from "@/components/ui/Badge";
export { EmptyState } from "@/components/ui/EmptyState";
export { LoadingText } from "@/components/ui/LoadingText";

// ── Actions ──────────────────────────────────────────────────────────────────

export { Button } from "@/components/ui/Button";
export type { ButtonSize, ButtonVariant } from "@/components/ui/Button";
export { IconButton } from "@/components/ui/IconButton";
export type { IconButtonSize, IconButtonTone } from "@/components/ui/IconButton";

// ── Form controls (token-driven, labeled) ────────────────────────────────────

export {
	CheckboxInput,
	SelectInput,
	TextareaInput,
	TextInput,
} from "@/components/developer/module-designer/inputs";

// Bare primitives for inline option rows, etc.
export { Checkbox } from "@/components/ui/Checkbox";
export { Field, FieldLabel, RequiredMarker } from "@/components/ui/Field";
export { Select } from "@/components/ui/Select";
export { SelectField } from "@/components/ui/SelectField";
export { TextArea } from "@/components/ui/TextArea";
export { TextField } from "@/components/ui/TextField";
export { inputClass, inputClassFor, TextInput as TextControl } from "@/components/ui/TextInput";
/** @deprecated Prefer `inputClass` / `TextControl` — kept for existing modules. */
export { inputClass as INPUT_CLASS } from "@/components/ui/TextInput";

/** Labeled date / datetime / time pickers (native inputs, MySQL-friendly values). */
export {
	DateField,
	DateTimeField,
	TimeField,
	fromDateInputValue,
	toDateInputValue,
} from "./DateFields";

// ── Drag & drop ──────────────────────────────────────────────────────────────

export {
	BIGTREE_DRAG_TYPE,
	DragHandle,
	DragSource,
	SortableList,
	useDragReorder,
} from "./SortableList";
export type { ExternalDropInfo, SortableItem, SortableItemRenderApi } from "./SortableList";

// ── Overlays ─────────────────────────────────────────────────────────────────

/** Right-edge drawer (focus trap, ESC, scrim) — pickers, field settings, quick edit. */
export { SlideOver } from "@/components/ui/SlideOver";

/**
 * Modal confirmation (focus trap, ESC, scrim). Controlled via `open` /
 * `onOpenChange`; pair with `useConfirmDialog` for open-with-payload state.
 */
export { ConfirmDialog } from "@/components/ui/ConfirmDialog";
export type { ConfirmDialogProps } from "@/components/ui/ConfirmDialog";

/** Open/close state + payload for a single ConfirmDialog instance. */
export { useConfirmDialog } from "@/hooks/useConfirmDialog";
export type { UseConfirmDialogResult } from "@/hooks/useConfirmDialog";

// ── Misc chrome ──────────────────────────────────────────────────────────────

export { Chip } from "@/components/ui/Chip";
export { RowReorderControls } from "@/components/ui/RowReorderControls";
export { Toolbar } from "@/components/ui/Toolbar";

/**
 * List toolbar search (icon + input + clear). Pair with `Toolbar` / `Pager`
 * for the same list chrome as built-in module views.
 */
export { SearchInput } from "@/components/ui/SearchInput";
export type { SearchInputProps } from "@/components/ui/SearchInput";

/**
 * List pagination (prev/next + page numbers, ellipsis for long ranges).
 * Renders nothing when `totalPages <= 1`.
 */
export { Pager } from "@/components/ui/Pager";
export type { PagerProps } from "@/components/ui/Pager";

/**
 * CSS-grid list table used by searchable module views and other admin lists.
 * Supports sort headers, row click, empty/loading states, and optional reorder.
 */
export { DataTable } from "@/components/ui/DataTable";
export type {
	DataTableColumn,
	DataTableProps,
	DataTableSort,
} from "@/components/ui/DataTable";

/**
 * Lucide icons commonly used in list/table row actions. Custom modules can't
 * import `lucide-react` directly (not on the import map); use these with
 * `IconButton` instead.
 */
export {
	Archive,
	ArrowRight,
	Check,
	Download,
	Edit,
	Eye,
	Plus,
	Star,
	Trash,
} from "lucide-react";
