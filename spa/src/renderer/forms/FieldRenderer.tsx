import { CalloutsField } from "@/renderer/fields/CalloutsField";
import { CheckboxField } from "@/renderer/fields/CheckboxField";
import { ColorField } from "@/renderer/fields/ColorField";
import { DateLikeField } from "@/renderer/fields/DateField";
import { GeocodingField } from "@/renderer/fields/GeocodingField";
import { HiddenField } from "@/renderer/fields/HiddenField";
import { HTMLField } from "@/renderer/fields/HTMLField";
import { ImageField } from "@/renderer/fields/ImageField";
import { LinkField } from "@/renderer/fields/LinkField";
import { MatrixField } from "@/renderer/fields/MatrixField";
import { MediaGalleryField } from "@/renderer/fields/MediaGalleryField";
import { NumberField } from "@/renderer/fields/NumberField";
import { RadioField } from "@/renderer/fields/RadioField";
import { RelationField } from "@/renderer/fields/RelationField";
import { ResourceReferenceField } from "@/renderer/fields/ResourceReferenceField";
import { RouteField } from "@/renderer/fields/RouteField";
import { SelectField } from "@/renderer/fields/SelectField";
import { StubField } from "@/renderer/fields/StubField";
import { TextField } from "@/renderer/fields/TextField";
import { TextareaField } from "@/renderer/fields/TextareaField";
import { UploadField } from "@/renderer/fields/UploadField";
import { VideoField } from "@/renderer/fields/VideoField";
import type { FieldComponentProps } from "@/renderer/fields/types";

/**
 * Maps a BigTree field-type slug to its renderer. Types not in the map fall
 * through to <StubField /> which preserves the value through round-trips but
 * doesn't allow editing. Adding a new type is purely a matter of importing
 * a component and adding a case here.
 *
 *  Implemented (all built-in BigTree field types):
 *    text, textarea, route, number, checkbox, list (select), radio,
 *    date, datetime, time, color, hidden, html,
 *    image, upload (file), video,
 *    image-reference, file-reference, video-reference,
 *    matrix, callouts, one-to-many, many-to-many,
 *    link, geocoding, media-gallery
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

		case "html":
			return <HTMLField {...props} />;

		case "image":
			return <ImageField {...props} />;

		case "upload":
			return <UploadField {...props} />;

		case "video":
			return <VideoField {...props} />;

		case "image-reference":
			return <ResourceReferenceField {...props} pickerType="image" />;

		case "file-reference":
			return <ResourceReferenceField {...props} pickerType="file" />;

		case "video-reference":
			return <ResourceReferenceField {...props} pickerType="video" />;

		case "matrix":
			return <MatrixField {...props} />;

		case "callouts":
			return <CalloutsField {...props} />;

		case "one-to-many":
			return <RelationField {...props} kind="one-to-many" />;

		case "many-to-many":
			return <RelationField {...props} kind="many-to-many" />;

		case "link":
			return <LinkField {...props} />;

		case "geocoding":
			return <GeocodingField {...props} />;

		case "media-gallery":
			return <MediaGalleryField {...props} />;

		default:
			return <StubField {...props} />;
	}
};
