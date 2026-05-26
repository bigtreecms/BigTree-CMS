import { CheckboxField } from "@/renderer/fields/CheckboxField";
import { ColorField } from "@/renderer/fields/ColorField";
import { DateLikeField } from "@/renderer/fields/DateField";
import { HiddenField } from "@/renderer/fields/HiddenField";
import { NumberField } from "@/renderer/fields/NumberField";
import { RadioField } from "@/renderer/fields/RadioField";
import { RouteField } from "@/renderer/fields/RouteField";
import { SelectField } from "@/renderer/fields/SelectField";
import { StubField } from "@/renderer/fields/StubField";
import { TextField } from "@/renderer/fields/TextField";
import { TextareaField } from "@/renderer/fields/TextareaField";
import type { FieldComponentProps } from "@/renderer/fields/types";

/**
 * Maps a BigTree field-type slug to its renderer. Types not in the map fall
 * through to <StubField /> which preserves the value through round-trips but
 * doesn't allow editing. Adding a new type is purely a matter of importing
 * a component and adding a case here.
 *
 *  Implemented in Phase 7 Chunk A:
 *    text, textarea, route, number, checkbox, list (select), radio,
 *    date, datetime, time, color, hidden
 *
 *  Deferred to later chunks (rendered via StubField for now):
 *    html, image, file, video, upload, media-gallery, callouts,
 *    many-to-many, one-to-many, matrix, geocoding, link, image-reference,
 *    file-reference, video-reference
 */
export const FieldRenderer = (props: FieldComponentProps) => {
	const { field } = props;

	switch (field.type) {
		case "text":
			return <TextField {...props} />;

		case "textarea":
			return <TextareaField {...props} />;

		case "route":
			return <RouteField {...props} />;

		case "number":
			return <NumberField {...props} />;

		case "checkbox":
			return <CheckboxField {...props} />;

		case "list":
			return <SelectField {...props} />;

		case "radio":
			return <RadioField {...props} />;

		case "date":
			return <DateLikeField {...props} kind="date" />;

		case "datetime":
			return <DateLikeField {...props} kind="datetime" />;

		case "time":
			return <DateLikeField {...props} kind="time" />;

		case "color":
			return <ColorField {...props} />;

		case "hidden":
			return <HiddenField {...props} />;

		default:
			return <StubField {...props} />;
	}
};
