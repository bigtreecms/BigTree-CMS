<?php
	/**
	 * JSON schema descriptors for the built-in field types. These describe what a
	 * SPA needs to render and validate a field instance natively AND — via
	 * settings_schema — how to draw the per-field developer settings form that
	 * replaces the legacy core/admin/field-types/<id>/settings.php pages.
	 *
	 * Top-level structure:
	 *   id           - field type id, matches the directory name in core/admin/field-types/
	 *   name         - human display name
	 *   category     - input | textual | media | reference | composite
	 *   value_type   - string | int | bool | array | object | resource_id
	 *   ui.component - SPA component identifier (mapping table on SPA side)
	 *   ui.props     - settings the SPA should pass through to the component
	 *   settings_schema  - per-instance settings the developer configures on this field
	 *   validation   - hints the SPA can use to validate client-side (server still re-validates)
	 *   self_draw    - if true, the SPA should use POST /field-types/{id}/render instead
	 *   render       - explicit render contract for the SPA, overrides the derived
	 *                  default: core-component | declarative | module | server.
	 *                  Built-ins are core-component (a first-party React component);
	 *                  custom types are usually declarative or module/server.
	 *   input_schema - (render "declarative") ordered list of sub-field descriptors
	 *                  composed from the primitive field types into one object value:
	 *                    id       - key written into the composite object value
	 *                    type     - a primitive field-type slug (text, list, image, …)
	 *                    title    - sub-field label
	 *                    subtitle - optional inline hint
	 *                    required - bool
	 *                    settings - per-sub-field settings (same shape as a form field)
	 *   contract_version - integer; the SPA falls back if it can't honor the contract
	 *   trust        - core | verified | marketplace (gates in-context vs sandbox JS)
	 *   asset_url    - (render "module") URL of the field type's ES module bundle
	 *
	 * settings_schema descriptor:
	 *   id           - the settings key written to storage (MUST match what the
	 *                  field type's draw.php / process.php reads — these are faithful
	 *                  to the legacy settings.php files)
	 *   control      - which SPA control renders it (see control vocabulary below)
	 *   label        - field label
	 *   hint         - small grey helper text shown after the label
	 *   note         - paragraph of help text rendered under the control
	 *   placeholder  - input placeholder
	 *   options      - [{value,label}] for enum/select controls
	 *   default      - default value when unset
	 *   required     - bool, marks the control required
	 *   value_types  - (universal descriptors only) the field `value_type`s this
	 *                  setting applies to; omitted means "every one"
	 *   depends_on   - id of another setting this control reads (db_column → its table)
	 *   context_defaults - {use_case: default} map (directory control)
	 *   contexts     - only show this setting for these use_cases (e.g. ["templates"])
	 *   show_if      - conditional visibility against another setting:
	 *                  {field, equals?: scalar, in?: [...], empty?: true, not_empty?: true}
	 *   heading      - (control "heading") a section divider label
	 *
	 * Control vocabulary (SPA control registry):
	 *   string, int, textarea, bool, enum, note, heading, directory,
	 *   db_table, db_column, db_column_sort,
	 *   list_maker, source_fields, callout_groups, image_options, matrix_columns
	 *
	 * Universal settings: the reserved "_universal" entry below is not a field type.
	 * Its settings_schema is appended to *every* field type's — built-in, custom and
	 * extension alike — by FieldTypeService::settingsSchema() and by the
	 * GET /field-types/{id}/schema payload, so a setting every field carries is
	 * declared once instead of copied into two dozen schemas. It is still a declared
	 * descriptor id, which is what the AI seams' setting-key guard checks against.
	 *
	 * "Universal" is about the *reader*, not about every field type: a descriptor may
	 * name the `value_type`s it can mean anything for, and withUniversalSettings()
	 * filters on it. `default` holds one scalar, so it belongs to the field types
	 * whose value is one — a scalar default on a `matrix` or a `media-gallery` hands
	 * a template's `foreach` a string, and on an `image-reference` names a resource id
	 * nothing resolved.
	 *
	 * Custom override: drop a file at custom/inc/bigtree/api/field-type-schemas.php
	 * returning an array keyed by field-type id — entries override or extend these.
	 */

	$context_directory_defaults = [
		"templates" => "files/pages/",
		"callouts" => "files/callouts/",
		"settings" => "files/settings/",
		"modules" => "files/modules/",
		"feeds" => "files/modules/",
	];

	return [
		// Not a field type — see "Universal settings" above. Appended to every field
		// type's settings_schema.
		"_universal" => [
			"settings_schema" => [
				["id" => "default", "control" => "string", "label" => "Default Value", "value_types" => ["string", "bool"], "hint" => "(used when the field has never been filled in)", "note" => "Seeded into a new record's form and written into a page's stored resources for any field left untouched, so front-end templates never see an undefined value."],
			],
		],
		"text" => [
			"id" => "text", "name" => "Text", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "TextInput", "props" => ["sub_type", "max_length"]],
			"settings_schema" => [
				["id" => "sub_type", "control" => "enum", "label" => "Sub Type", "options" => [
					["value" => "", "label" => ""],
					["value" => "name", "label" => "Name"],
					["value" => "address", "label" => "Address"],
					["value" => "email", "label" => "Email"],
					["value" => "website", "label" => "Website"],
					["value" => "phone", "label" => "Phone Number"],
				]],
				["id" => "max_length", "control" => "int", "label" => "Maximum Character Length", "hint" => "(leave empty or 0 for no max)", "placeholder" => "0", "show_if" => ["field" => "sub_type", "empty" => true]],
				["id" => "seo_h1", "control" => "bool", "label" => "Use For <H1> SEO Score", "hint" => "(only a single field can be used)", "contexts" => ["templates"], "show_if" => ["field" => "sub_type", "empty" => true]],
			],
			"validation" => ["max_length" => "settings.max_length"],
			"self_draw" => false,
		],
		"textarea" => [
			"id" => "textarea", "name" => "Text Area", "category" => "textual", "value_type" => "string",
			"ui" => ["component" => "TextArea", "props" => ["max_length"]],
			"settings_schema" => [
				["id" => "max_length", "control" => "int", "label" => "Maximum Character Length", "hint" => "(leave empty or 0 for no max)", "placeholder" => "0"],
			],
			"validation" => ["max_length" => "settings.max_length"],
			"self_draw" => false,
		],
		"html" => [
			"id" => "html", "name" => "HTML Area", "category" => "textual", "value_type" => "string",
			"ui" => ["component" => "HtmlEditor", "props" => ["simple"]],
			"settings_schema" => [
				["id" => "seo_body", "control" => "bool", "label" => "Use For Body Copy SEO Score", "contexts" => ["templates"]],
				["id" => "simple", "control" => "bool", "label" => "Simple Mode", "hint" => "(less options)"],
				["id" => "simple_by_permission", "control" => "enum", "label" => "Simple Mode Via Permissions", "hint" => "(minimum access level)", "options" => [
					["value" => "0", "label" => ""],
					["value" => "1", "label" => "Administrator"],
					["value" => "2", "label" => "Developer"],
				], "note" => "If a user is below this permission level, this HTML area will switch to Simple Mode."],
			],
			"self_draw" => false,
		],
		"link" => [
			"id" => "link", "name" => "Link", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "LinkPicker", "props" => []],
			"settings_schema" => [],
			"self_draw" => false,
		],
		"upload" => [
			"id" => "upload", "name" => "File Upload", "category" => "media", "value_type" => "string",
			"ui" => ["component" => "FileUpload", "props" => ["directory", "valid_extensions"]],
			"settings_schema" => [
				["id" => "directory", "control" => "directory", "label" => "Upload Directory", "hint" => "(required, relative to SITE_ROOT)", "required" => true, "context_defaults" => $context_directory_defaults],
				["id" => "valid_extensions", "control" => "textarea", "label" => "Valid File Extensions", "hint" => "(comma separated, include \".\" prefix for each extension)", "placeholder" => "Leaving this field empty will allow all non-executable files to be uploaded."],
			],
			"self_draw" => false,
		],
		"image" => [
			"id" => "image", "name" => "Image Upload", "category" => "media", "value_type" => "string",
			"ui" => ["component" => "ImageUpload", "props" => ["directory", "preset", "min_width", "min_height", "crops", "thumbs", "center_crops"]],
			"settings_schema" => [
				["id" => "directory", "control" => "directory", "label" => "Upload Directory", "hint" => "(required, relative to SITE_ROOT)", "required" => true, "context_defaults" => $context_directory_defaults],
				["id" => "_image_options", "control" => "image_options", "label" => "Image Options"],
			],
			"validation" => ["min_width" => "settings.min_width", "min_height" => "settings.min_height"],
			"self_draw" => false,
		],
		"image-reference" => [
			"id" => "image-reference", "name" => "Image Reference", "category" => "reference", "value_type" => "resource_id",
			"ui" => ["component" => "ImagePicker", "props" => ["min_width", "min_height"]],
			"settings_schema" => [
				["id" => "min_width", "control" => "int", "label" => "Minimum Width", "hint" => "(numeric value in pixels)"],
				["id" => "min_height", "control" => "int", "label" => "Minimum Height", "hint" => "(numeric value in pixels)"],
			],
			"self_draw" => false,
		],
		"file-reference" => [
			"id" => "file-reference", "name" => "File Reference", "category" => "reference", "value_type" => "resource_id",
			"ui" => ["component" => "ResourcePicker", "props" => []],
			"settings_schema" => [],
			"self_draw" => false,
		],
		"video" => [
			"id" => "video", "name" => "Video (YouTube or Vimeo)", "category" => "media", "value_type" => "object",
			"ui" => ["component" => "VideoEmbed", "props" => ["directory"]],
			"settings_schema" => [
				["id" => "directory", "control" => "directory", "label" => "Upload Directory", "hint" => "(relative to SITE_ROOT)", "context_defaults" => $context_directory_defaults],
				["id" => "_image_options", "control" => "image_options", "label" => "Thumbnail / Poster Image Options"],
			],
			"self_draw" => false,
		],
		"video-reference" => [
			"id" => "video-reference", "name" => "Video Reference", "category" => "reference", "value_type" => "resource_id",
			"ui" => ["component" => "VideoPicker", "props" => []],
			"settings_schema" => [],
			"self_draw" => false,
		],
		"list" => [
			"id" => "list", "name" => "List (Select)", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "Select", "props" => ["list_type", "list", "pop-table", "pop-description", "pop-sort", "parser"]],
			"settings_schema" => [
				["id" => "list_type", "control" => "enum", "label" => "List Type", "default" => "static", "options" => [
					["value" => "static", "label" => "Static"],
					["value" => "db", "label" => "Database Populated"],
					["value" => "state", "label" => "State List"],
					["value" => "country", "label" => "Country List"],
				]],
				["id" => "allow-empty", "control" => "enum", "label" => "Allow Empty", "hint" => "(first option is blank)", "default" => "Yes", "options" => [
					["value" => "Yes", "label" => "Yes"],
					["value" => "No", "label" => "No"],
				]],
				["id" => "list", "control" => "list_maker", "label" => "Static List Options", "columns" => ["Value", "Description"], "keys" => ["value", "description"], "show_if" => ["field" => "list_type", "in" => ["", "static"]]],
				["id" => "pop-table", "control" => "db_table", "label" => "Table", "show_if" => ["field" => "list_type", "equals" => "db"]],
				["id" => "pop-description", "control" => "db_column", "label" => "Description Field", "depends_on" => "pop-table", "show_if" => ["field" => "list_type", "equals" => "db"]],
				["id" => "pop-sort", "control" => "db_column_sort", "label" => "Sort By", "depends_on" => "pop-table", "show_if" => ["field" => "list_type", "equals" => "db"]],
				["id" => "parser", "control" => "string", "label" => "List Parser Function", "note" => "Your function will receive an array of the available entries and should return a modified array.", "show_if" => ["field" => "list_type", "equals" => "db"]],
			],
			"self_draw" => false,
		],
		"checkbox" => [
			"id" => "checkbox", "name" => "Checkbox", "category" => "input", "value_type" => "bool",
			"ui" => ["component" => "Checkbox", "props" => ["default_checked", "custom_value"]],
			"settings_schema" => [
				["id" => "default_checked", "control" => "bool", "label" => "Default to Checked"],
				["id" => "custom_value", "control" => "string", "label" => "Value", "hint" => "(defaults to \"on\")"],
			],
			"self_draw" => false,
		],
		"date" => [
			"id" => "date", "name" => "Date", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "DatePicker", "props" => ["default_today"]],
			"settings_schema" => [
				["id" => "default_today", "control" => "bool", "label" => "Default to Today's Date"],
			],
			"self_draw" => false,
		],
		"time" => [
			"id" => "time", "name" => "Time", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "TimePicker", "props" => ["ignore_timezones"]],
			"settings_schema" => [
				["id" => "ignore_timezones", "control" => "bool", "label" => "Ignore BigTree User Timezones"],
			],
			"self_draw" => false,
		],
		"datetime" => [
			"id" => "datetime", "name" => "Date & Time", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "DateTimePicker", "props" => ["default_now", "ignore_timezones"]],
			"settings_schema" => [
				["id" => "default_now", "control" => "bool", "label" => "Default to Today's Date & Time"],
				["id" => "ignore_timezones", "control" => "bool", "label" => "Ignore BigTree User Timezones"],
			],
			"self_draw" => false,
		],
		"route" => [
			"id" => "route", "name" => "Generated Route", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "RouteInput", "props" => []],
			"settings_schema" => [
				["id" => "source", "control" => "source_fields", "label" => "Source Fields", "hint" => "(the table columns to use for route generation)"],
				["id" => "not_unique", "control" => "bool", "label" => "Disregard Uniqueness", "hint" => "(if this box is checked duplicate routes can exist)"],
				["id" => "keep_original", "control" => "bool", "label" => "Keep Original Route", "hint" => "(check to keep the first generated route)"],
			],
			"self_draw" => true,
		],
		"geocoding" => [
			"id" => "geocoding", "name" => "Geocoding", "category" => "input", "value_type" => "object",
			"ui" => ["component" => "Geocoding", "props" => []],
			"settings_schema" => [
				["id" => "fields", "control" => "source_fields", "label" => "Source Fields", "hint" => "(the table columns to use for address generation)"],
			],
			"self_draw" => false,
		],
		"callouts" => [
			"id" => "callouts", "name" => "Callouts", "category" => "composite", "value_type" => "array",
			"ui" => ["component" => "Callouts", "props" => ["groups", "max"]],
			"settings_schema" => [
				["id" => "groups", "control" => "callout_groups", "label" => "Groups", "hint" => "(if you don't choose at least one group, all callouts will be available)"],
				["id" => "noun", "control" => "string", "label" => "Noun", "hint" => "(defaults to \"Callout\")"],
				["id" => "max", "control" => "int", "label" => "Maximum Entries", "hint" => "(defaults to unlimited)"],
			],
			"self_draw" => false,
		],
		"matrix" => [
			"id" => "matrix", "name" => "Matrix", "category" => "composite", "value_type" => "array",
			"ui" => ["component" => "Matrix", "props" => ["columns", "max", "style"]],
			"settings_schema" => [
				["id" => "max", "control" => "int", "label" => "Maximum Entries", "hint" => "(defaults to unlimited)"],
				["id" => "style", "control" => "enum", "label" => "Style", "default" => "list", "options" => [
					["value" => "list", "label" => "List (like Many to Many)"],
					["value" => "callout", "label" => "Blocks (like Callouts)"],
				]],
				["id" => "columns", "control" => "matrix_columns", "label" => "Columns"],
			],
			"self_draw" => false,
		],
		"media-gallery" => [
			"id" => "media-gallery", "name" => "Media Gallery", "category" => "composite", "value_type" => "array",
			"ui" => ["component" => "MediaGallery", "props" => ["directory", "columns", "max"]],
			"settings_schema" => [
				["id" => "_gallery_heading", "control" => "heading", "heading" => "Gallery Options"],
				["id" => "max", "control" => "int", "label" => "Maximum Entries", "hint" => "(defaults to unlimited)"],
				["id" => "disable_photos", "control" => "bool", "label" => "Disable Photos"],
				["id" => "disable_youtube", "control" => "bool", "label" => "Disable YouTube Videos"],
				["id" => "disable_vimeo", "control" => "bool", "label" => "Disable Vimeo Videos"],
				["id" => "enable_manual", "control" => "bool", "label" => "Enable Manually Uploaded Videos"],
				["id" => "_image_heading", "control" => "heading", "heading" => "Image Options"],
				["id" => "directory", "control" => "directory", "label" => "Upload Directory", "hint" => "(relative to SITE_ROOT)", "context_defaults" => $context_directory_defaults],
				["id" => "_image_options", "control" => "image_options", "label" => "Image Options"],
				["id" => "_fields_heading", "control" => "heading", "heading" => "Additional Fields"],
				["id" => "columns", "control" => "matrix_columns", "label" => "Additional Fields"],
			],
			"self_draw" => false,
		],
		"one-to-many" => [
			"id" => "one-to-many", "name" => "One to Many", "category" => "composite", "value_type" => "array",
			"ui" => ["component" => "OneToMany", "props" => ["table", "title_column", "sort_by_column"]],
			"settings_schema" => [
				["id" => "table", "control" => "db_table", "label" => "Table"],
				["id" => "title_column", "control" => "db_column", "label" => "Title Field", "depends_on" => "table"],
				["id" => "sort_by_column", "control" => "db_column_sort", "label" => "Sort By", "depends_on" => "table"],
				["id" => "parser", "control" => "string", "label" => "List Parser Function", "note" => "The first parameter passed in is an array of data. The second is a boolean of whether you're receiving currently tagged entries (false) or the list of available entries that aren't currently tagged (true)."],
				["id" => "max", "control" => "int", "label" => "Maximum Entries", "hint" => "(defaults to unlimited)"],
				["id" => "show_add_all", "control" => "bool", "label" => "Enable Add All Button", "hint" => "(will not show if Maximum Entries is set)", "show_if" => ["field" => "max", "empty" => true]],
				["id" => "show_reset", "control" => "bool", "label" => "Enable Reset Button"],
			],
			"self_draw" => false,
		],
		"many-to-many" => [
			"id" => "many-to-many", "name" => "Many to Many", "category" => "composite", "value_type" => "array",
			"ui" => ["component" => "ManyToMany", "props" => ["mtm-connecting-table", "mtm-other-table"]],
			"settings_schema" => [
				["id" => "mtm-connecting-table", "control" => "db_table", "label" => "Connecting Table"],
				["id" => "mtm-my-id", "control" => "db_column", "label" => "My ID", "depends_on" => "mtm-connecting-table"],
				["id" => "mtm-other-id", "control" => "db_column", "label" => "Other ID", "depends_on" => "mtm-connecting-table"],
				["id" => "mtm-other-table", "control" => "db_table", "label" => "Other Table"],
				["id" => "mtm-other-descriptor", "control" => "db_column", "label" => "Other Descriptor", "depends_on" => "mtm-other-table"],
				["id" => "mtm-sort", "control" => "db_column_sort", "label" => "Sort By", "depends_on" => "mtm-other-table"],
				["id" => "mtm-list-parser", "control" => "string", "label" => "List Parser Function", "note" => "The first parameter passed in is an array of data. The second is a boolean of whether you're receiving currently tagged entries (false) or the list of available entries that aren't currently tagged (true)."],
				["id" => "max", "control" => "int", "label" => "Maximum Entries", "hint" => "(defaults to unlimited)"],
				["id" => "show_add_all", "control" => "bool", "label" => "Enable Add All Button", "hint" => "(will not show if Maximum Entries is set)", "show_if" => ["field" => "max", "empty" => true]],
				["id" => "show_reset", "control" => "bool", "label" => "Enable Reset Button"],
			],
			"self_draw" => false,
		],
	];
