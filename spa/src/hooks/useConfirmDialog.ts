import { useState } from "react";

export interface UseConfirmDialogResult<T> {
	item: T | null;
	isOpen: boolean;
	open: (item: T) => void;
	close: () => void;
	/** Spread onto `<ConfirmDialog>`: `open` + `onOpenChange` (closes on dismiss). */
	dialogProps: {
		open: boolean;
		onOpenChange: (open: boolean) => void;
	};
}

export function useConfirmDialog<T = unknown>(): UseConfirmDialogResult<T> {
	const [item, setItem] = useState<T | null>(null);
	const isOpen = item !== null;

	const close = () => setItem(null);

	return {
		item,
		isOpen,
		open: (value: T) => setItem(value),
		close,
		dialogProps: {
			open: isOpen,
			onOpenChange: (open: boolean) => {
				if (!open) {
					close();
				}
			},
		},
	};
}
