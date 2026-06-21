import { useEffect } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { toast } from "@/lib/toast";

import { Button } from "./Button";
import { Toaster } from "./Toaster";

/**
 * Singleton toast surface (mounted once inside `<Shell />`). It reads the global
 * queue from `useToastStore` and handles auto-dismiss timers; pages enqueue via
 * `toast.success(...)` etc. from `@/lib/toast`. The cards pin to the top-right of
 * the viewport. Trigger the variants below.
 */
const ToasterDemo = () => {
	// Seed one persistent card of each variant so the catalog shows them on load;
	// clear the queue when leaving the story so it doesn't leak between stories.
	useEffect(() => {
		toast.clear();
		toast.success("Settings saved", { description: "Your changes are live.", duration: 0 });
		toast.error("Upload failed", { description: "The file was too large.", duration: 0 });
		toast.warning("Unsaved changes", {
			description: "Leaving will discard them.",
			duration: 0,
		});
		toast.info("New version available", { duration: 0 });

		return () => toast.clear();
	}, []);

	return (
		<div className="flex flex-wrap gap-2">
			<Button variant="primary" onClick={() => toast.success("Settings saved")}>
				Success
			</Button>
			<Button variant="danger" onClick={() => toast.error("Something went wrong")}>
				Error
			</Button>
			<Button
				variant="secondary"
				onClick={() => toast.warning("Heads up", { description: "Double-check this." })}
			>
				Warning
			</Button>
			<Button
				variant="secondary"
				onClick={() =>
					toast.info("FYI", {
						actionLabel: "Undo",
						onAction: () => toast.success("Undone"),
					})
				}
			>
				Info + action
			</Button>
			<Button variant="secondary" onClick={() => toast.clear()}>
				Clear all
			</Button>

			<Toaster />
		</div>
	);
};

const meta = {
	title: "UI/Toaster",
	component: Toaster,
	tags: ["autodocs"],
	render: () => <ToasterDemo />,
} satisfies Meta<typeof Toaster>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};
