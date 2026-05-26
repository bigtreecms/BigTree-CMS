import { create } from "zustand";

/**
 * Global toast queue. Pages should call `toast.success(...)`, `toast.error(...)`,
 * etc. instead of maintaining their own local toast arrays. The actual rendering
 * lives in <Toaster /> which is mounted once inside <Shell />.
 */

export type ToastVariant = "success" | "error" | "info" | "warning";

export interface ToastItem {
	id: number;
	variant: ToastVariant;
	title: string;
	description?: string;
	actionLabel?: string;
	onAction?: () => void;
	/** Milliseconds before auto-dismiss; 0 disables auto-dismiss. */
	duration: number;
}

export interface ToastOptions {
	description?: string;
	actionLabel?: string;
	onAction?: () => void;
	duration?: number;
}

interface ToastStore {
	items: ToastItem[];
	push: (variant: ToastVariant, title: string, opts?: ToastOptions) => number;
	dismiss: (id: number) => void;
	clear: () => void;
}

let nextId = 1;

const DEFAULT_DURATION: Record<ToastVariant, number> = {
	success: 4000,
	info: 4000,
	warning: 5000,
	error: 6000,
};

export const useToastStore = create<ToastStore>((set) => ({
	items: [],

	push: (variant, title, opts) => {
		const id = nextId++;
		const item: ToastItem = {
			id,
			variant,
			title,
			description: opts?.description,
			actionLabel: opts?.actionLabel,
			onAction: opts?.onAction,
			duration: opts?.duration ?? DEFAULT_DURATION[variant],
		};

		set((state) => ({ items: [...state.items, item] }));

		return id;
	},

	dismiss: (id) => {
		set((state) => ({ items: state.items.filter((t) => t.id !== id) }));
	},

	clear: () => set({ items: [] }),
}));

/**
 * Imperative facade for non-React callers (mutations, error handlers, etc.).
 */
export const toast = {
	success: (title: string, opts?: ToastOptions) =>
		useToastStore.getState().push("success", title, opts),

	error: (title: string, opts?: ToastOptions) =>
		useToastStore.getState().push("error", title, opts),

	info: (title: string, opts?: ToastOptions) =>
		useToastStore.getState().push("info", title, opts),

	warning: (title: string, opts?: ToastOptions) =>
		useToastStore.getState().push("warning", title, opts),

	dismiss: (id: number) => useToastStore.getState().dismiss(id),

	clear: () => useToastStore.getState().clear(),
};
