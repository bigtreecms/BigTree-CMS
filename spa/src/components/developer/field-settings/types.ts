import type { ReactElement } from "react";

import type { FieldUseCase, SettingDescriptor } from "@/api/endpoints/field-types";

/**
 * Every settings control receives the descriptor plus the full settings object
 * (needed for `depends_on` / `show_if`) and patches back one or more keys. The
 * full object is passed — not just `settings[descriptor.id]` — because composite
 * controls (image_options) span several keys.
 */
export interface ControlProps {
	descriptor: SettingDescriptor;
	onPatch: (patch: Record<string, unknown>) => void;
	settings: Record<string, unknown>;
	useCase: FieldUseCase;
}

export type ControlComponent = (props: ControlProps) => ReactElement | null;
