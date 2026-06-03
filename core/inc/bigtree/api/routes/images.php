<?php
	use BigTree\Services\ImageService;

	/**
	 * Image form-field processing (the API equivalent of the legacy image field).
	 * These store originals under a field's own directory and generate crops —
	 * they do NOT create media-library resource rows. See ImageService.
	 */
	return [
		// Direct upload: stores the original + auto crops, returns pending manual crops.
		// Multipart: file in $request->files, "settings" JSON string in $request->body.
		// (Multipart routes are validated by the handler, not the Validate middleware.)
		"POST /images/process" => [
			"service" => [ImageService::class, "process"],
			"permission" => ["level" => 0],
			"multipart" => true,
		],

		// Re-run the pipeline against an existing image (Browse-from-media or Recrop).
		"POST /images/reprocess" => [
			"service" => [ImageService::class, "reprocess"],
			"permission" => ["level" => 0],
			"body" => [
				"source" => "required|array",
				"settings" => "array",
			],
		],

		// Finalize a single manual crop drawn in the cropper.
		"POST /images/crop" => [
			"service" => [ImageService::class, "crop"],
			"permission" => ["level" => 0],
			"body" => [
				"file" => "required|string|max:1024",
				"x" => "required|int|min:0",
				"y" => "required|int|min:0",
				"width" => "required|int|min:1",
				"height" => "required|int|min:1",
				"target_width" => "required|int|min:1",
				"target_height" => "required|int|min:1",
				"prefix" => "string|max:255",
				"name" => "string|max:255",
				"directory" => "string|max:255",
				"retina" => "bool",
				"grayscale" => "bool",
				"thumbs" => "array",
				"center_crops" => "array",
			],
		],
	];
