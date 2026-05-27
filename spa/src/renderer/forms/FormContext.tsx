import { createContext, useContext } from "react";

/**
 * Surface the surrounding module/form/entry identity to deeply-nested field
 * renderers without prop-drilling. Used today by ManyToManyField (needs the
 * module + form id to call /modules/{id}/forms/{sid}/relation-options) and
 * by OneToManyField (same). Optional for fields that don't need it.
 */
export interface FormRenderContextValue {
	moduleId: string;
	formId: string;
	/** Numeric entry id when editing; null when creating a fresh row. */
	entryId: number | null;
}

const FormRenderContext = createContext<FormRenderContextValue | null>(null);

export const FormRenderContextProvider = FormRenderContext.Provider;

/**
 * Returns the context value or `null` when the field is rendered outside of a
 * module-form (e.g. ad-hoc usage in the Developer section). Fields that
 * require the context should handle the null case with a clear "not available
 * here" stub rather than throwing.
 */
export const useFormRenderContext = (): FormRenderContextValue | null =>
	useContext(FormRenderContext);
