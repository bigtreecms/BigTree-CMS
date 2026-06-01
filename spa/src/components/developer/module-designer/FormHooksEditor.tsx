import { useState } from "react";
import { Zap } from "lucide-react";

import { SlideOver } from "@/components/ui/SlideOver";

import { TextInput } from "./inputs";

/**
 * "Manage Hooks" button + slide-over for a form's lifecycle hooks. Replaces the
 * raw JSON editor with the four named fields the legacy admin's "Manage Hooks"
 * dialog exposed (core/admin/modules/developer/templates/_form-content.php):
 * edit, pre, post, publish. Each value is the name of a PHP function called at
 * that stage of the form lifecycle.
 *
 * Keys already present on `value` that aren't one of the four known hooks are
 * preserved untouched, so any hand-authored extras survive a round-trip.
 */

interface FormHooksEditorProps {
	value: Record<string, unknown> | unknown[];
	onChange: (next: Record<string, unknown>) => void;
}

type HookKey = "edit" | "pre" | "post" | "publish";

interface HookField {
	key: HookKey;
	label: string;
	note: string;
}

const HOOK_FIELDS: HookField[] = [
	{
		key: "edit",
		label: "Editing Hook",
		note: "Called when the form is drawn for adding or editing an entry.",
	},
	{
		key: "pre",
		label: "Pre-processing Hook",
		note: "Called before the submitted data is processed.",
	},
	{
		key: "post",
		label: "Post-processing Hook",
		note: "Called after the entry is saved.",
	},
	{
		key: "publish",
		label: "Publishing Hook",
		note: "Called when a pending change is published.",
	},
];

const asRecord = (value: FormHooksEditorProps["value"]): Record<string, unknown> =>
	value && typeof value === "object" && !Array.isArray(value)
		? (value as Record<string, unknown>)
		: {};

const hookValue = (record: Record<string, unknown>, key: HookKey): string => {
	const raw = record[key];

	return typeof raw === "string" ? raw : "";
};

export const FormHooksEditor = ({ value, onChange }: FormHooksEditorProps) => {
	const [open, setOpen] = useState(false);

	const record = asRecord(value);
	const activeCount = HOOK_FIELDS.filter((f) => hookValue(record, f.key).trim() !== "").length;

	const setHook = (key: HookKey, next: string) => {
		const updated = { ...record };

		if (next.trim() === "") {
			delete updated[key];
		} else {
			updated[key] = next;
		}

		onChange(updated);
	};

	return (
		<div>
			<span className="mb-1 block text-[12px] font-medium text-text-2">Hooks</span>
			<button
				type="button"
				onClick={() => setOpen(true)}
				className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
			>
				<Zap size={13} />
				Manage Hooks
				{activeCount > 0 && (
					<span className="rounded bg-accent-soft px-1.5 py-0.5 text-[10.5px] font-medium text-accent">
						{activeCount}
					</span>
				)}
			</button>
			<span className="mt-1 block text-[11px] text-text-3">
				PHP functions called at each stage of the form lifecycle.
			</span>

			<SlideOver
				open={open}
				onOpenChange={setOpen}
				title="Manage Hooks"
				description="Enter the name of a PHP function to call at each stage of the form lifecycle. Leave a field blank to skip that hook."
				footer={
					<div className="flex justify-end">
						<button
							type="button"
							onClick={() => setOpen(false)}
							className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-white hover:opacity-90"
						>
							Done
						</button>
					</div>
				}
			>
				<div className="space-y-4">
					{HOOK_FIELDS.map((field) => (
						<TextInput
							key={field.key}
							label={field.label}
							value={hookValue(record, field.key)}
							onChange={(v) => setHook(field.key, v)}
							hint={field.note}
							mono
						/>
					))}
				</div>
			</SlideOver>
		</div>
	);
};
