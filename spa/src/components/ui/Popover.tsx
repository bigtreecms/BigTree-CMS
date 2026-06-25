import { useRef, type ReactNode } from "react";

import { useOnClickOutside } from "@/hooks/useOnClickOutside";

/**
 * The floating-panel surface shared by every popover control (Combobox,
 * IconSelect, LinkField, LinkFinder, RelationField, TagInput). Owns the
 * de-duplicated `absolute … rounded-md border border-border bg-surface shadow`
 * string those controls used to copy verbatim; callers pass `className` for the
 * per-site width / anchor / max-height / overflow (`w-full`, `top-full`,
 * `max-h-64`, `overflow-y-auto`, …).
 *
 * Render it yourself (inside an `open && …` guard) when the trigger owns
 * bespoke focus / keyboard logic — input-driven controls do this. Use the
 * `Popover` wrapper instead when the trigger is a plain click target.
 */
interface PopoverPanelProps {
	/** Per-site width / anchor / max-height / overflow classes. */
	className?: string;
	children: ReactNode;
}

export const PopoverPanel = ({ className, children }: PopoverPanelProps) => {
	return (
		<div
			className={`absolute z-20 mt-1 rounded-md border border-border bg-surface shadow-lg ${className ?? ""}`}
		>
			{children}
		</div>
	);
};

/**
 * Controlled popover: a `relative` container that renders a click `trigger`,
 * closes on outside `mousedown` (via `useOnClickOutside`), and shows
 * `children` inside a `PopoverPanel` while `open`. Suits button-style triggers
 * (Combobox, IconSelect); the trigger node wires its own `aria-expanded` /
 * `onClick`.
 */
interface PopoverProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	/** The clickable trigger (button-like), plus any trailing siblings. */
	trigger: ReactNode;
	/** Panel contents. */
	children: ReactNode;
	/** Extra classes for the relative container (typically the width). */
	className?: string;
	/** Panel width / anchor / max-height / overflow overrides. */
	panelClassName?: string;
}

export const Popover = ({
	open,
	onOpenChange,
	trigger,
	children,
	className,
	panelClassName,
}: PopoverProps) => {
	const containerRef = useRef<HTMLDivElement>(null);

	useOnClickOutside(containerRef, () => onOpenChange(false), open);

	return (
		<div ref={containerRef} className={`relative ${className ?? ""}`}>
			{trigger}
			{open && <PopoverPanel className={panelClassName}>{children}</PopoverPanel>}
		</div>
	);
};
