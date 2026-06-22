import { useEffect } from "react";
import { AlertCircle, AlertTriangle, Check, Info, X } from "lucide-react";

import { useToastStore, type ToastItem, type ToastVariant } from "@/lib/toast";
import { Card } from "@/components/ui/Card";

/**
 * Singleton toast surface mounted once inside <Shell />. Reads the global
 * queue from `useToastStore` and handles auto-dismiss timers. Pages call
 * `toast.success(...)` etc. from `@/lib/toast`.
 */
export const Toaster = () => {
	const items = useToastStore((s) => s.items);

	if (items.length === 0) {
		return null;
	}

	return (
		<div className="pointer-events-none fixed right-6 top-20 z-200 flex flex-col gap-3">
			{items.map((t) => (
				<ToastCard key={t.id} item={t} />
			))}
		</div>
	);
};

interface ToastCardProps {
	item: ToastItem;
}

const ToastCard = ({ item }: ToastCardProps) => {
	const dismiss = useToastStore((s) => s.dismiss);

	useEffect(() => {
		if (item.duration <= 0) {
			return;
		}

		const handle = window.setTimeout(() => {
			dismiss(item.id);
		}, item.duration);

		return () => window.clearTimeout(handle);
	}, [item.id, item.duration, dismiss]);

	const palette = paletteFor(item.variant);
	const Icon = palette.icon;

	return (
		<Card className="pointer-events-auto flex w-80 items-start gap-3 overflow-hidden shadow-lg">
			<div className={`w-1 self-stretch ${palette.bar}`} />

			<div className="flex flex-1 items-start gap-3 py-3 pr-2">
				<div
					className={`mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full ${palette.iconBg}`}
				>
					<Icon size={15} className={palette.iconFg} />
				</div>

				<div className="min-w-0 flex-1 text-[13px]">
					<div className="font-semibold tracking-[-0.01em] text-text">{item.title}</div>

					{item.description && (
						<div className="mt-0.5 text-text-2">{item.description}</div>
					)}

					{item.actionLabel && item.onAction && (
						<button
							type="button"
							onClick={() => {
								item.onAction?.();
								dismiss(item.id);
							}}
							className={`mt-1 hover:underline ${palette.iconFg}`}
						>
							{item.actionLabel}
						</button>
					)}
				</div>
			</div>

			<button
				type="button"
				onClick={() => dismiss(item.id)}
				className="relative mt-2 mr-2 text-text-3 before:absolute before:-inset-3 before:content-[''] hover:text-text"
				aria-label="Close notification"
			>
				<X size={16} />
			</button>
		</Card>
	);
};

interface Palette {
	bar: string;
	iconBg: string;
	iconFg: string;
	icon: typeof Check;
}

const paletteFor = (variant: ToastVariant): Palette => {
	if (variant === "success") {
		return {
			bar: "bg-success",
			iconBg: "bg-success-bg",
			iconFg: "text-success",
			icon: Check,
		};
	}

	if (variant === "error") {
		return {
			bar: "bg-danger",
			iconBg: "bg-danger/15",
			iconFg: "text-danger",
			icon: AlertCircle,
		};
	}

	if (variant === "warning") {
		return {
			bar: "bg-warn",
			iconBg: "bg-warn-bg",
			iconFg: "text-warn",
			icon: AlertTriangle,
		};
	}

	return {
		bar: "bg-info",
		iconBg: "bg-info-bg",
		iconFg: "text-info",
		icon: Info,
	};
};
