import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import type { ReactNode } from "react";

interface SlideOverProps {
	children: ReactNode;
	description?: string;
	footer?: ReactNode;
	onOpenChange: (open: boolean) => void;
	open: boolean;
	title: string;
	width?: "sm" | "md" | "lg" | "xl";
}

const WIDTHS: Record<NonNullable<SlideOverProps["width"]>, string> = {
	sm: "w-[min(420px,100vw)]",
	md: "w-[min(560px,100vw)]",
	lg: "w-[min(720px,100vw)]",
	xl: "w-[min(900px,100vw)]",
};

/**
 * Right-edge drawer. Wraps Radix Dialog so we get focus trap, ESC, and a
 * scrim — but slides in from the right and sits flush against the edge of
 * the viewport instead of centering. Used for pickers and quick-edit panels.
 */
export const SlideOver = ({
	open,
	onOpenChange,
	title,
	description,
	children,
	footer,
	width = "md",
}: SlideOverProps) => {
	return (
		<Dialog.Root open={open} onOpenChange={onOpenChange}>
			<Dialog.Portal>
				<Dialog.Overlay className="bt-overlay fixed inset-0 z-50 bg-black/40 backdrop-blur-[1px]" />
				<Dialog.Content
					className={`bt-drawer fixed inset-y-0 right-0 z-50 flex flex-col border-l border-border bg-surface shadow-lg outline-none ${WIDTHS[width]}`}
				>
					<div className="flex items-start justify-between gap-3 border-b border-border bg-surface-2 px-5 py-3">
						<div className="min-w-0">
							<Dialog.Title className="text-[14px] font-semibold tracking-[-0.01em] text-text">
								{title}
							</Dialog.Title>

							{description && (
								<Dialog.Description className="mt-0.5 text-[12px] text-text-3">
									{description}
								</Dialog.Description>
							)}
						</div>

						<Dialog.Close
							aria-label="Close"
							className="relative rounded-md p-1 text-text-3 before:absolute before:-inset-2 before:content-[''] hover:bg-hover hover:text-text"
						>
							<X size={16} />
						</Dialog.Close>
					</div>

					<div className="flex-1 overflow-y-auto px-5 py-4">{children}</div>

					{footer && (
						<div className="border-t border-border bg-surface-2 px-5 py-3">
							{footer}
						</div>
					)}
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
