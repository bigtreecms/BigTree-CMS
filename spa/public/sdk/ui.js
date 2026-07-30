// SDK import-map shim for `@bigtree/ui` — the curated primitives kit. Backed by
// the global registerSdk() sets (src/sdk/ui.tsx).
const ui = globalThis.__BIGTREE_SDK__ && globalThis.__BIGTREE_SDK__["@bigtree/ui"];

if (!ui) {
	throw new Error("BigTree SDK not initialized — `@bigtree/ui` is unavailable.");
}

export default ui;

// Named re-exports so `import { X } from "@bigtree/ui"` works via the import map.
// Keep in sync with spa/src/sdk/ui.tsx (and rebuild admin dist after changes).
export const {
	// Layout
	Stack,
	Row,
	Divider,
	Spacer,
	Grid,
	GridItem,
	// Typography
	Heading,
	Text,
	Note,
	SectionLabel,
	MonoText,
	// Surfaces
	Card,
	CardHeader,
	CardFooter,
	Panel,
	// Tabs
	Tabs,
	TabPanel,
	// Feedback
	Alert,
	Badge,
	EmptyState,
	LoadingText,
	// Actions
	Button,
	IconButton,
	// Form controls (labeled designer wrappers)
	TextInput,
	SelectInput,
	CheckboxInput,
	TextareaInput,
	// Form primitives
	Checkbox,
	Field,
	FieldLabel,
	RequiredMarker,
	Select,
	SelectField,
	TextArea,
	TextField,
	TextControl,
	inputClass,
	inputClassFor,
	INPUT_CLASS,
	DateField,
	DateTimeField,
	TimeField,
	toDateInputValue,
	fromDateInputValue,
	// Drag & drop
	DragHandle,
	DragSource,
	SortableList,
	useDragReorder,
	BIGTREE_DRAG_TYPE,
	// Overlays
	SlideOver,
	// Misc
	Chip,
	RowReorderControls,
	Toolbar,
} = ui;
