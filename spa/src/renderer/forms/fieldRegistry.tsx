import type { ReactElement } from "react";

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
import { TextField } from "@/renderer/fields/TextField";
import { TextareaField } from "@/renderer/fields/TextareaField";
import { UploadField } from "@/renderer/fields/UploadField";
import { VideoField } from "@/renderer/fields/VideoField";
import type { FieldComponentProps } from "@/renderer/fields/types";

/**
 * How a field type produces its input UI. Today every built-in is a first-party
 * React component ("core-component"). The custom-field-types design
 * (`spa/.custom-field-types-design.md`) adds "declarative", "module", and
 * "sandbox" paths against this same registry; they will resolve here without
 * reshaping the built-ins.
 */
export type FieldRenderKind = "core-component";

export interface FieldRegistryEntry {
	/** How the input renders. */
	render: FieldRenderKind;
	/** Renders the field input for the given controlled props. */
	component: (props: FieldComponentProps) => ReactElement;
}

const registry = new Map<string, FieldRegistryEntry>();

/**
 * Register a field type's renderer against its slug. Later registrations win,
 * so an extension can override a built-in. Built-ins seed the registry below.
 */
export const registerFieldType = (type: string, entry: FieldRegistryEntry): void => {
	registry.set(type, entry);
};

/** Look up a field type's renderer, or undefined if none is registered. */
export const lookupFieldType = (type: string): FieldRegistryEntry | undefined => registry.get(type);

/** Convenience for the common case: a first-party React component. */
const core = (component: (props: FieldComponentProps) => ReactElement): FieldRegistryEntry => ({
	render: "core-component",
	component,
});

/**
 * Built-in field types. Mirrors the former hardcoded switch in FieldRenderer
 * one-for-one; the few entries that pass a fixed prop (kind / pickerType) keep
 * doing so via a thin wrapper. Types not registered here fall through to
 * <StubField /> in FieldRenderer.
 *
 *  text, textarea, route, number, checkbox, list (select), radio,
 *  date, datetime, time, color, hidden, html,
 *  image, upload (file), video,
 *  image-reference, file-reference, video-reference,
 *  matrix, callouts, one-to-many, many-to-many,
 *  link, geocoding, media-gallery
 */
registerFieldType(
	"text",
	core((props) => <TextField {...props} />)
);
registerFieldType(
	"textarea",
	core((props) => <TextareaField {...props} />)
);
registerFieldType(
	"route",
	core((props) => <RouteField {...props} />)
);
registerFieldType(
	"number",
	core((props) => <NumberField {...props} />)
);
registerFieldType(
	"checkbox",
	core((props) => <CheckboxField {...props} />)
);
registerFieldType(
	"list",
	core((props) => <SelectField {...props} />)
);
registerFieldType(
	"radio",
	core((props) => <RadioField {...props} />)
);
registerFieldType(
	"date",
	core((props) => <DateLikeField {...props} kind="date" />)
);
registerFieldType(
	"datetime",
	core((props) => <DateLikeField {...props} kind="datetime" />)
);
registerFieldType(
	"time",
	core((props) => <DateLikeField {...props} kind="time" />)
);
registerFieldType(
	"color",
	core((props) => <ColorField {...props} />)
);
registerFieldType(
	"hidden",
	core((props) => <HiddenField {...props} />)
);
registerFieldType(
	"html",
	core((props) => <HTMLField {...props} />)
);
registerFieldType(
	"image",
	core((props) => <ImageField {...props} />)
);
registerFieldType(
	"upload",
	core((props) => <UploadField {...props} />)
);
registerFieldType(
	"video",
	core((props) => <VideoField {...props} />)
);
registerFieldType(
	"image-reference",
	core((props) => <ResourceReferenceField {...props} pickerType="image" />)
);
registerFieldType(
	"file-reference",
	core((props) => <ResourceReferenceField {...props} pickerType="file" />)
);
registerFieldType(
	"video-reference",
	core((props) => <ResourceReferenceField {...props} pickerType="video" />)
);
registerFieldType(
	"matrix",
	core((props) => <MatrixField {...props} />)
);
registerFieldType(
	"callouts",
	core((props) => <CalloutsField {...props} />)
);
registerFieldType(
	"one-to-many",
	core((props) => <RelationField {...props} kind="one-to-many" />)
);
registerFieldType(
	"many-to-many",
	core((props) => <RelationField {...props} kind="many-to-many" />)
);
registerFieldType(
	"link",
	core((props) => <LinkField {...props} />)
);
registerFieldType(
	"geocoding",
	core((props) => <GeocodingField {...props} />)
);
registerFieldType(
	"media-gallery",
	core((props) => <MediaGalleryField {...props} />)
);
