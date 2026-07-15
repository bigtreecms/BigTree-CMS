import { useEffect } from "react";

import { TextInput } from "@/components/ui/TextInput";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";

/**
 * Upload directory. Legacy settings.php seed a context-specific default
 * (files/pages/, files/callouts/, …) into storage when empty; we do the same by
 * persisting the context default on mount so saved data matches the legacy admin.
 */
export const DirectoryControl = ({ descriptor, settings, onPatch, useCase }: ControlProps) => {
	const current = settings[descriptor.id];
	const fallback = descriptor.context_defaults?.[useCase] ?? "";

	useEffect(() => {
		if ((current === undefined || current === "") && fallback) {
			onPatch({ [descriptor.id]: fallback });
		}
		// Run once per mount for this descriptor; later edits are user-driven.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	return (
		<ControlShell
			hint={descriptor.hint}
			label={descriptor.label}
			note={descriptor.note}
			required={descriptor.required}
		>
			<TextInput
				dense
				placeholder={fallback}
				value={String(current ?? fallback ?? "")}
				onChange={(e) => onPatch({ [descriptor.id]: e.target.value })}
			/>
		</ControlShell>
	);
};
