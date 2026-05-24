<?php
	/**
	 * JSON schema descriptors for the 19 built-in field types listed in
	 * BigTreeAdmin::getCachedFieldTypes(). These describe what a SPA needs to
	 * render and validate a field instance natively.
	 *
	 * Structure:
	 *   id           - field type id, matches the directory name in core/admin/field-types/
	 *   name         - human display name
	 *   category     - input | textual | media | reference | composite
	 *   value_type   - string | int | bool | array | object | resource_id
	 *   ui.component - SPA component identifier (mapping table on SPA side)
	 *   ui.props     - settings the SPA should pass through to the component
	 *   settings_schema  - per-instance settings the developer configured on this field
	 *   validation   - hints the SPA can use to validate client-side (server still re-validates)
	 *   self_draw    - if true, the SPA should use POST /field-types/{id}/render instead
	 *
	 * Custom override: drop a file at custom/inc/bigtree/api/field-type-schemas.php
	 * returning an array keyed by field-type id — entries override or extend these.
	 */
	return [
		"text" => [
			"id" => "text", "name" => "Text", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "TextInput", "props" => ["sub_type", "max_length", "placeholder", "validation"]],
			"settings_schema" => [
				["id" => "sub_type", "type" => "enum", "options" => ["", "name", "phone", "address", "email", "url", "color"], "label" => "Sub-type"],
				["id" => "max_length", "type" => "int", "label" => "Max length"],
				["id" => "placeholder", "type" => "string", "label" => "Placeholder"],
				["id" => "validation", "type" => "string", "label" => "Validation class"],
			],
			"validation" => ["max_length" => "settings.max_length"],
			"self_draw" => false,
		],
		"textarea" => [
			"id" => "textarea", "name" => "Text Area", "category" => "textual", "value_type" => "string",
			"ui" => ["component" => "TextArea", "props" => ["rows", "max_length", "placeholder"]],
			"settings_schema" => [
				["id" => "rows", "type" => "int", "label" => "Rows"],
				["id" => "max_length", "type" => "int", "label" => "Max length"],
				["id" => "placeholder", "type" => "string", "label" => "Placeholder"],
			],
			"validation" => ["max_length" => "settings.max_length"],
			"self_draw" => false,
		],
		"html" => [
			"id" => "html", "name" => "HTML Area", "category" => "textual", "value_type" => "string",
			"ui" => ["component" => "HtmlEditor", "props" => ["simple", "max_length"]],
			"settings_schema" => [
				["id" => "simple", "type" => "bool", "label" => "Simple toolbar"],
				["id" => "max_length", "type" => "int", "label" => "Max length"],
			],
			"validation" => ["max_length" => "settings.max_length"],
			"self_draw" => false,
		],
		"link" => [
			"id" => "link", "name" => "Link", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "LinkPicker", "props" => ["allow_external", "internal_only"]],
			"settings_schema" => [
				["id" => "allow_external", "type" => "bool", "label" => "Allow external"],
				["id" => "internal_only", "type" => "bool", "label" => "Internal pages only"],
			],
			"self_draw" => false,
		],
		"upload" => [
			"id" => "upload", "name" => "File Upload", "category" => "media", "value_type" => "string",
			"ui" => ["component" => "FileUpload", "props" => ["extension_whitelist"]],
			"settings_schema" => [
				["id" => "extension_whitelist", "type" => "string", "label" => "Allowed extensions (comma-separated)"],
			],
			"self_draw" => false,
		],
		"image" => [
			"id" => "image", "name" => "Image Upload", "category" => "media", "value_type" => "string",
			"ui" => ["component" => "ImageUpload", "props" => ["preset", "min_width", "min_height", "crops", "thumbs"]],
			"settings_schema" => [
				["id" => "preset", "type" => "string", "label" => "Image preset"],
				["id" => "min_width", "type" => "int", "label" => "Min width"],
				["id" => "min_height", "type" => "int", "label" => "Min height"],
				["id" => "crops", "type" => "array", "label" => "Crops"],
				["id" => "thumbs", "type" => "array", "label" => "Thumbnails"],
				["id" => "center_crops", "type" => "array", "label" => "Center crops"],
			],
			"validation" => ["min_width" => "settings.min_width", "min_height" => "settings.min_height"],
			"self_draw" => false,
		],
		"video" => [
			"id" => "video", "name" => "Video (YouTube or Vimeo)", "category" => "media", "value_type" => "object",
			"ui" => ["component" => "VideoEmbed", "props" => []],
			"settings_schema" => [],
			"self_draw" => false,
		],
		"file-reference" => [
			"id" => "file-reference", "name" => "File Reference", "category" => "reference", "value_type" => "resource_id",
			"ui" => ["component" => "ResourcePicker", "props" => ["folder", "extension_whitelist"]],
			"settings_schema" => [
				["id" => "folder", "type" => "int", "label" => "Starting folder"],
				["id" => "extension_whitelist", "type" => "string", "label" => "Allowed extensions"],
			],
			"self_draw" => false,
		],
		"image-reference" => [
			"id" => "image-reference", "name" => "Image Reference", "category" => "reference", "value_type" => "resource_id",
			"ui" => ["component" => "ImagePicker", "props" => ["folder", "min_width", "min_height"]],
			"settings_schema" => [
				["id" => "folder", "type" => "int", "label" => "Starting folder"],
				["id" => "min_width", "type" => "int", "label" => "Min width"],
				["id" => "min_height", "type" => "int", "label" => "Min height"],
			],
			"self_draw" => false,
		],
		"video-reference" => [
			"id" => "video-reference", "name" => "Video Reference", "category" => "reference", "value_type" => "resource_id",
			"ui" => ["component" => "VideoPicker", "props" => ["folder"]],
			"settings_schema" => [
				["id" => "folder", "type" => "int", "label" => "Starting folder"],
			],
			"self_draw" => false,
		],
		"list" => [
			"id" => "list", "name" => "List (Select)", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "Select", "props" => ["list_type", "list", "pop_module", "pop_description", "pop_sort", "parser"]],
			"settings_schema" => [
				["id" => "list_type", "type" => "enum", "options" => ["static", "db", "module"], "label" => "Source"],
				["id" => "list", "type" => "array", "label" => "Static options"],
				["id" => "pop_module", "type" => "int", "label" => "Source module"],
				["id" => "pop_description", "type" => "string", "label" => "Description column"],
				["id" => "pop_sort", "type" => "string", "label" => "Sort column"],
				["id" => "parser", "type" => "string", "label" => "Optional parser function"],
			],
			"self_draw" => false,
		],
		"checkbox" => [
			"id" => "checkbox", "name" => "Checkbox", "category" => "input", "value_type" => "bool",
			"ui" => ["component" => "Checkbox", "props" => ["default_checked", "custom_value"]],
			"settings_schema" => [
				["id" => "default_checked", "type" => "bool", "label" => "Default checked"],
				["id" => "custom_value", "type" => "string", "label" => "Custom value when checked"],
			],
			"self_draw" => false,
		],
		"date" => [
			"id" => "date", "name" => "Date", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "DatePicker", "props" => ["min_date", "max_date"]],
			"settings_schema" => [
				["id" => "min_date", "type" => "string", "label" => "Earliest date"],
				["id" => "max_date", "type" => "string", "label" => "Latest date"],
			],
			"self_draw" => false,
		],
		"time" => [
			"id" => "time", "name" => "Time", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "TimePicker", "props" => []],
			"settings_schema" => [],
			"self_draw" => false,
		],
		"datetime" => [
			"id" => "datetime", "name" => "Date & Time", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "DateTimePicker", "props" => []],
			"settings_schema" => [],
			"self_draw" => false,
		],
		"media-gallery" => [
			"id" => "media-gallery", "name" => "Media Gallery", "category" => "composite", "value_type" => "array",
			"ui" => ["component" => "MediaGallery", "props" => ["preset", "min_width", "min_height", "max_items"]],
			"settings_schema" => [
				["id" => "preset", "type" => "string", "label" => "Image preset"],
				["id" => "min_width", "type" => "int", "label" => "Min width"],
				["id" => "min_height", "type" => "int", "label" => "Min height"],
				["id" => "max_items", "type" => "int", "label" => "Max items"],
			],
			"self_draw" => false,
		],
		"callouts" => [
			"id" => "callouts", "name" => "Callouts", "category" => "composite", "value_type" => "array",
			"ui" => ["component" => "Callouts", "props" => ["callouts", "max_items"]],
			"settings_schema" => [
				["id" => "callouts", "type" => "array", "label" => "Allowed callout types"],
				["id" => "max_items", "type" => "int", "label" => "Max items"],
			],
			"self_draw" => false,
		],
		"matrix" => [
			"id" => "matrix", "name" => "Matrix", "category" => "composite", "value_type" => "array",
			"ui" => ["component" => "Matrix", "props" => ["columns", "max_items"]],
			"settings_schema" => [
				["id" => "columns", "type" => "array", "label" => "Matrix columns"],
				["id" => "max_items", "type" => "int", "label" => "Max rows"],
			],
			"self_draw" => false,
		],
		"one-to-many" => [
			"id" => "one-to-many", "name" => "One to Many", "category" => "composite", "value_type" => "array",
			"ui" => ["component" => "OneToMany", "props" => ["other_table", "other_view", "pop_description"]],
			"settings_schema" => [
				["id" => "other_table", "type" => "string", "label" => "Related table"],
				["id" => "other_view", "type" => "int", "label" => "Related view"],
				["id" => "pop_description", "type" => "string", "label" => "Description column"],
			],
			"self_draw" => false,
		],
		"route" => [
			"id" => "route", "name" => "Generated Route", "category" => "input", "value_type" => "string",
			"ui" => ["component" => "RouteInput", "props" => []],
			"settings_schema" => [],
			"self_draw" => true,
		],
	];
