import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import type { ReactNode } from "react";

interface ModalProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	title: string;
	description?: string;
	children?: ReactNode;
	/** Footer content. In `bars` layout it sits in a bottom surface-2 bar; in `card` layout it is right-aligned beneath the body. */
	footer?: ReactNode;
	size?: "sm" | "md" | "lg" | "xl";
	/**
	 * "card" (default): title/description and body share one padded surface — for
	 *   confirm and form dialogs.
	 * "bars": title/description sit in a bordered surface-2 header bar with the
	 *   footer in a matching bottom bar and `children` filling the middle — for
	 *   full-bleed content like image croppers (caller owns the body's padding).
	 */
	layout?: "card" | "bars";
	/** Show a close (X) button in the header. Only applies to `bars` layout. */
	showClose?: boolean;
	/** Darker scrim for media/cropping dialogs that need image focus. */
	scrim?: "default" | "dark";
	/** Appended to Dialog.Content (layout only — can't override size/base). */
	className?: string;
}

const SIZES: Record<NonNullable<ModalProps["size"]>, string> = {
	sm: "w-[min(440px,92vw)]",
	md: "w-[min(620px,94vw)]",
	lg: "w-[min(780px,95vw)]",
	xl: "w-[min(960px,95vw)]",
};

const SCRIMS: Record<NonNullable<ModalProps["scrim"]>, string> = {
	default: "bg-black/40 backdrop-blur-[1px]",
	dark: "bg-black/60 backdrop-blur-[2px]",
};

/**
 * Centered modal dialog. Wraps Radix Dialog (focus trap, ESC, scrim) so we get
 * one consistent centered surface — the centered twin of {@link SlideOver}. Pick
 * `layout="card"` for padded confirm/form dialogs or `layout="bars"` for
 * full-bleed content with bordered header/footer bars.
 */
export const Modal = ({
	open,
	onOpenChange,
	title,
	description,
	children,
	footer,
	size = "sm",
	layout = "card",
	showClose = false,
	scrim = "default",
	className,
}: ModalProps) => {
	const contentBase =
		"fixed left-1/2 top-1/2 z-50 -translate-x-1/2 -translate-y-1/2 rounded-xl border border-border bg-surface shadow-lg focus:outline-none";

	const closeButton = showClose && (
		<Dialog.Close
			className="relative rounded-md p-1 text-text-3 before:absolute before:-inset-2 before:content-[''] hover:bg-hover hover:text-text"
			aria-label="Close"
		>
			<X size={16} />
		</Dialog.Close>
	);

	return (
		<Dialog.Root open={open} onOpenChange={onOpenChange}>
			<Dialog.Portal>
				<Dialog.Overlay className={`fixed inset-0 z-50 ${SCRIMS[scrim]}`} />

				{layout === "bars" ? (
					<Dialog.Content
						className={`${contentBase} flex max-h-[90vh] flex-col overflow-hidden ${SIZES[size]} ${className ?? ""}`}
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

							{closeButton}
						</div>

						<div className="min-h-0 flex-1 overflow-y-auto">{children}</div>

						{footer && (
							<div className="border-t border-border bg-surface-2 px-5 py-3">
								{footer}
							</div>
						)}
					</Dialog.Content>
				) : (
					<Dialog.Content
						className={`${contentBase} p-6 ${SIZES[size]} ${className ?? ""}`}
					>
						<Dialog.Title className="text-[15px] font-semibold tracking-[-0.01em]">
							{title}
						</Dialog.Title>

						{description && (
							<Dialog.Description className="mt-1 text-[12.5px] text-text-3">
								{description}
							</Dialog.Description>
						)}

						{children && <div className="mt-4">{children}</div>}

						{footer && <div className="mt-6 flex justify-end gap-3">{footer}</div>}
					</Dialog.Content>
				)}
			</Dialog.Portal>
		</Dialog.Root>
	);
};
