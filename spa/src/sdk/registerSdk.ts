import React from "react";
import * as ReactJSXRuntime from "react/jsx-runtime";

import * as BigTreeFields from "./fields";
import * as BigTreeUI from "./ui";

/**
 * The singletons custom action modules resolve their bare imports to, via the
 * SDK import map (index.html → public/sdk/*.js, which read this global). Keeping
 * one React instance + the app's own components is what makes a custom action
 * look and behave native. See spa/.custom-module-actions-design.md.
 *
 * IMPORTANT: every export is referenced by name below. Custom actions import
 * these via the import-map shims at runtime, so Rollup cannot see the uses.
 * A bare `import * as UI` + assign would tree-shake away Panel, SortableList,
 * etc. and the shim would then throw "doesn't provide an export named X".
 */
export interface BigTreeSdkGlobals {
	"@bigtree/fields": typeof BigTreeFields;
	"@bigtree/ui": typeof BigTreeUI;
	react: typeof React;
	"react/jsx-runtime": typeof ReactJSXRuntime;
}

declare global {
	var __BIGTREE_SDK__: BigTreeSdkGlobals | undefined;
}

/** Populate the SDK global. Call once at startup, before any module action runs. */
export const registerSdk = (): void => {
	// Named property reads — keep this list in sync with spa/src/sdk/ui.tsx and
	// public/sdk/ui.js. Prefer adding to all three places when growing the kit.
	const ui = {
		// Layout
		Stack: BigTreeUI.Stack,
		Row: BigTreeUI.Row,
		Divider: BigTreeUI.Divider,
		Spacer: BigTreeUI.Spacer,
		Grid: BigTreeUI.Grid,
		GridItem: BigTreeUI.GridItem,
		// Typography
		Heading: BigTreeUI.Heading,
		Text: BigTreeUI.Text,
		Note: BigTreeUI.Note,
		SectionLabel: BigTreeUI.SectionLabel,
		MonoText: BigTreeUI.MonoText,
		// Surfaces
		Card: BigTreeUI.Card,
		CardHeader: BigTreeUI.CardHeader,
		CardFooter: BigTreeUI.CardFooter,
		Panel: BigTreeUI.Panel,
		// Tabs
		Tabs: BigTreeUI.Tabs,
		TabPanel: BigTreeUI.TabPanel,
		// Feedback
		Alert: BigTreeUI.Alert,
		Badge: BigTreeUI.Badge,
		EmptyState: BigTreeUI.EmptyState,
		LoadingText: BigTreeUI.LoadingText,
		// Actions
		Button: BigTreeUI.Button,
		IconButton: BigTreeUI.IconButton,
		// Form controls (labeled)
		TextInput: BigTreeUI.TextInput,
		SelectInput: BigTreeUI.SelectInput,
		CheckboxInput: BigTreeUI.CheckboxInput,
		TextareaInput: BigTreeUI.TextareaInput,
		// Form primitives
		Checkbox: BigTreeUI.Checkbox,
		Field: BigTreeUI.Field,
		FieldLabel: BigTreeUI.FieldLabel,
		RequiredMarker: BigTreeUI.RequiredMarker,
		Select: BigTreeUI.Select,
		SelectField: BigTreeUI.SelectField,
		TextArea: BigTreeUI.TextArea,
		TextField: BigTreeUI.TextField,
		TextControl: BigTreeUI.TextControl,
		inputClass: BigTreeUI.inputClass,
		inputClassFor: BigTreeUI.inputClassFor,
		INPUT_CLASS: BigTreeUI.INPUT_CLASS,
		DateField: BigTreeUI.DateField,
		DateTimeField: BigTreeUI.DateTimeField,
		TimeField: BigTreeUI.TimeField,
		toDateInputValue: BigTreeUI.toDateInputValue,
		fromDateInputValue: BigTreeUI.fromDateInputValue,
		// Drag & drop
		DragHandle: BigTreeUI.DragHandle,
		DragSource: BigTreeUI.DragSource,
		SortableList: BigTreeUI.SortableList,
		useDragReorder: BigTreeUI.useDragReorder,
		BIGTREE_DRAG_TYPE: BigTreeUI.BIGTREE_DRAG_TYPE,
		// Overlays
		SlideOver: BigTreeUI.SlideOver,
		ConfirmDialog: BigTreeUI.ConfirmDialog,
		useConfirmDialog: BigTreeUI.useConfirmDialog,
		// Misc
		Chip: BigTreeUI.Chip,
		RowReorderControls: BigTreeUI.RowReorderControls,
		Toolbar: BigTreeUI.Toolbar,
	} satisfies typeof BigTreeUI;

	const fields = {
		FieldRenderer: BigTreeFields.FieldRenderer,
		settingsOf: BigTreeFields.settingsOf,
	} satisfies typeof BigTreeFields;

	globalThis.__BIGTREE_SDK__ = {
		react: React,
		"react/jsx-runtime": ReactJSXRuntime,
		"@bigtree/ui": ui,
		"@bigtree/fields": fields,
	};
};
