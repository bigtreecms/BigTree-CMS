import type { ReactNode } from "react";

export interface DescriptionListItem {
	/** Term shown in the label column (`<dt>`). */
	label: ReactNode;
	/** Definition shown in the value column (`<dd>`). */
	value: ReactNode;
	/** Extra classes appended to this row's `<dd>` (e.g. `font-mono`, `truncate`). */
	valueClassName?: string;
}

interface DescriptionListProps {
	items: DescriptionListItem[];
	/** Label-column width in px. Default 120. */
	labelWidth?: number;
	/** Wrap in the bordered `surface-2` card treatment (vs. a bare in-flow grid). */
	boxed?: boolean;
	/** Layout-only classes appended to the `<dl>`. */
	className?: string;
}

/**
 * Two-column key/value detail grid — the
 * `<dl className="grid grid-cols-[Npx_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-[12.5px]">`
 * block (with `text-text-3` terms / `text-text-2` definitions) that was
 * reimplemented across MessageThread, FileDetail, PendingChangeDetail,
 * ExtensionBuild and the Extensions/Debug detail panels. Pass `boxed` for the
 * `surface-2` card framing; tune the label column with `labelWidth`.
 */
export const DescriptionList = ({
	items,
	labelWidth = 120,
	boxed,
	className,
}: DescriptionListProps) => {
	const box = boxed ? "rounded-lg border border-border bg-surface-2 p-3 " : "";
	const extra = className ? ` ${className}` : "";

	return (
		<dl
			className={`grid gap-x-3 gap-y-1.5 text-[12.5px] ${box}`.trimEnd() + extra}
			style={{ gridTemplateColumns: `${labelWidth}px minmax(0, 1fr)` }}
		>
			{items.map((item, i) => (
				<div key={i} className="contents">
					<dt className="text-text-3">{item.label}</dt>
					<dd
						className={`min-w-0 text-text-2${item.valueClassName ? ` ${item.valueClassName}` : ""}`}
					>
						{item.value}
					</dd>
				</div>
			))}
		</dl>
	);
};
