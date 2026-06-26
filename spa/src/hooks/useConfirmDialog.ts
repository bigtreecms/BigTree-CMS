import { useState } from "react";

export interface UseConfirmDialogResult<T> {
	item: T | null;
	isOpen: boolean;
	open: (item: T) => void;
	close: () => void;
}

export function useConfirmDialog<T = unknown>(): UseConfirmDialogResult<T> {
	const [item, setItem] = useState<T | null>(null);

	return {
		item,
		isOpen: item !== null,
		open: (value: T) => setItem(value),
		close: () => setItem(null),
	};
}
