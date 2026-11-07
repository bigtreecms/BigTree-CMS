<?php
	namespace BigTree\Services;

	use BigTree\Api\Exceptions\AuthenticationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\ValidationException;
	use BigTree\Api\Jwt;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Upload;
	use BigTreeAutoModule;
	use BigTreeStorage;

	/**
	 * Public (unauthenticated) runtime for embeddable module forms — the API side
	 * of the SPA's /embed/:hash route, replacing the legacy
	 * ajax/auto-modules/embeddable-form/* screens.
	 *
	 *   GET  /embed-forms/{hash}               config + bot-check challenge
	 *   POST /embed-forms/{hash}/images        upload an image field's file
	 *   POST /embed-forms/{hash}/images/crop   finalize one manual crop
	 *   POST /embed-forms/{hash}/files         upload an upload field's file
	 *   POST /embed-forms/{hash}/submit        write the entry
	 *
	 * Nothing a visitor sends is trusted for where or how a file is stored: the
	 * upload endpoints read the field's settings (directory, crops, thumbs,
	 * preset) from the stored form by field path — a column, or a dotted path
	 * through matrix columns (`gallery.photo`) for a field inside a matrix. Each
	 * stored file comes back with a signed token bound to this form, that field
	 * path and the stored file, and submit only
	 * accepts a file value that carries a matching token — so a visitor can't
	 * point a column at an arbitrary path or pull a media-library file in with
	 * `resource://`. Manual crops work the same way: each pending crop carries a
	 * signed copy of its spec, and the crop endpoint uses only that spec.
	 *
	 * Bot check: the config carries a signed challenge stamped with the time it
	 * was issued. Submit (and the upload endpoints) require it, submit rejects it
	 * when it comes back sooner than MIN_FILL_SECONDS, and a filled `honeypot`
	 * (the legacy `_bigtree_email` trap) rejects the submission outright.
	 */
	class EmbedFormService {
		/** A human can't fill a form faster than this; a script posting straight away can. */
		public const MIN_FILL_SECONDS = 3;

		/** How long a loaded form stays submittable, and an upload stays claimable. */
		public const TOKEN_TTL = 86400;

		/** Field types whose value is a file the visitor uploads through this service. */
		private const IMAGE_TYPES = ["image"];
		private const FILE_TYPES = ["upload"];

		/**
		 * Field types that point at the media library. A visitor can't browse the
		 * library, so any value sent for these is dropped.
		 */
		private const LIBRARY_TYPES = ["file-reference", "image-reference", "video-reference", "media-gallery"];

		/**
		 * Returns just the config the renderer needs. Hooks are intentionally NOT
		 * returned — they are PHP callbacks that run server-side during submission.
		 */
		public function get(Request $request) {
			$form = $this->requireForm($request);

			return Response::ok([
				"id" => $form["id"] ?? "",
				"module" => $form["module"] ?? "",
				"title" => $form["title"] ?? "",
				"table" => $form["table"] ?? "",
				"fields" => array_values((array)($form["fields"] ?? [])),
				"css" => $form["css"] ?? "",
				"thank_you_message" => $form["thank_you_message"] ?? "",
				"redirect_url" => $form["redirect_url"] ?? "",
				"default_pending" => !empty($form["default_pending"]),
				"challenge" => self::mintChallenge((string)$form["hash"]),
			]);
		}

		/**
		 * Multipart: `file`, `column` (a field path), `challenge`. Stores the image
		 * under the field's own settings and returns the ImageService pipeline result, with
		 * an upload token and a signed token on each pending crop.
		 */
		public function processImage(Request $request) {
			$form = $this->requireForm($request);
			$hash = (string)$form["hash"];
			self::verifyChallenge($request->bodyString("challenge"), $hash, false);

			$column = $request->bodyString("column");
			$field = self::requireField($form, $column, self::IMAGE_TYPES);
			$file = Upload::requireSingle($request);
			$settings = $field["settings"];

			$result = (new ImageService())->processFile($file["tmp_name"], $file["name"], $settings);
			$result["token"] = self::mintUploadToken($hash, $column, $result["file"]);

			foreach ($result["pending_crops"] as $index => $crop) {
				$result["pending_crops"][$index]["token"] = self::mintCropToken($hash, $column, $result["file"], $crop);
			}

			return Response::created($result, null);
		}

		/**
		 * Body: `token` (from a pending crop) plus the selection rect x / y /
		 * width / height in source pixels. Everything else about the crop comes
		 * from the token.
		 */
		public function cropImage(Request $request) {
			$form = $this->requireForm($request);
			$claims = self::verifyToken($request->bodyString("token"), "embed-crop", (string)$form["hash"]);
			$spec = is_array($claims["s"] ?? null) ? $claims["s"] : [];
			$spec["file"] = (string)($claims["f"] ?? "");
			$spec["target_width"] = (int)($spec["width"] ?? 0);
			$spec["target_height"] = (int)($spec["height"] ?? 0);

			$result = (new ImageService())->cropFromSpec(
				$spec,
				$request->bodyInt("x"),
				$request->bodyInt("y"),
				$request->bodyInt("width"),
				$request->bodyInt("height")
			);

			return Response::created($result, null);
		}

		/**
		 * Multipart: `file`, `column` (a field path), `challenge`. Stores a plain
		 * file in the upload field's directory. Returns `{ file, token }`.
		 */
		public function uploadFile(Request $request) {
			$form = $this->requireForm($request);
			$hash = (string)$form["hash"];
			self::verifyChallenge($request->bodyString("challenge"), $hash, false);

			$column = $request->bodyString("column");
			$field = self::requireField($form, $column, self::FILE_TYPES);
			$file = Upload::requireSingle($request);
			$settings = $field["settings"];

			if (!self::extensionAllowed((string)$file["name"], (string)($settings["valid_extensions"] ?? ""))) {
				throw new BadRequestException("That type of file isn't accepted here.", "file_type_not_allowed");
			}

			$directory = (new ImageService())->cleanDirectory($settings["directory"] ?? "files/");
			$storage = new BigTreeStorage();
			$stored = $storage->store($file["tmp_name"], $file["name"], $directory);

			if (!$stored) {
				if ($storage->DisabledFileError) {
					throw new BadRequestException("That type of file isn't accepted here.", "file_type_not_allowed");
				}

				throw new BadRequestException("The file could not be stored.", "storage_failed");
			}

			return Response::created([
				"file" => $stored,
				"token" => self::mintUploadToken($hash, $column, $stored),
			], null);
		}

		/**
		 *   POST /embed-forms/{hash}/submit
		 *   { values: { <column>: <value> }, challenge, honeypot, upload_tokens: [<token>, ...] }
		 *
		 * Mirrors the legacy embeddable-form/process.php: runs the form's pre
		 * hook, processes each field via FieldProcessingService::processField,
		 * then writes either directly via createItem or as a pending change via
		 * createPendingItem (driven by `default_pending`). Post/publish hooks run
		 * on success. Field validation failures come back as a 422 keyed by column
		 * and nothing is written.
		 */
		public function submit(Request $request) {
			global $bigtree;

			$form = $this->requireForm($request);
			$hash = (string)$form["hash"];

			if ($request->bodyString("honeypot") !== "") {
				throw new BadRequestException("Your submission could not be accepted.", "spam_check_failed");
			}

			self::verifyChallenge($request->bodyString("challenge"), $hash, true);

			$values = $request->bodyMap("values");
			$upload_tokens = $request->bodyMap("upload_tokens");
			$fields = array_values(array_filter((array)($form["fields"] ?? []), "is_array"));
			$post_data = self::cleanPostData($values, $fields, $upload_tokens, $hash);

			if (!empty($form["hooks"]["pre"]) && is_callable($form["hooks"]["pre"])) {
				$pre = call_user_func($form["hooks"]["pre"], $post_data);

				if (is_array($pre)) {
					$post_data = array_merge($post_data, $pre);
				}
			}

			// Field type process.php files read the whole post through the global,
			// and push validation failures onto $bigtree["errors"].
			$bigtree["post_data"] = $post_data;
			$bigtree["errors"] = [];
			$bigtree["crops"] = [];
			$entry = [];
			$errors = [];

			// Field processors push referenced resource ids into IRLsCreated as they
			// run; reset it so we allocate only this submission's resources.
			ResourceAllocationService::$IRLsCreated = [];

			foreach ($fields as $resource) {
				$column = (string)($resource["column"] ?? "");

				if ($column === "") {
					continue;
				}

				$error_count = count($bigtree["errors"]);
				$field = [
					"type" => $resource["type"] ?? "text",
					"title" => $resource["title"] ?? "",
					"key" => $column,
					"settings" => $resource["settings"] ?? $resource["options"] ?? [],
					"ignore" => false,
					"input" => $post_data[$column] ?? null,
					"file_input" => null,
				];

				$output = FieldProcessingService::processField($field);

				foreach (array_slice($bigtree["errors"], $error_count) as $error) {
					$errors[] = [
						"code" => "validation_failed",
						"field" => $column,
						"message" => strip_tags(html_entity_decode((string)($error["error"] ?? "Invalid value."))),
					];
				}

				if (!is_null($output)) {
					$entry[$column] = $output;
				}
			}

			if ($errors) {
				throw new ValidationException($errors);
			}

			// Keep anything pre-processing added that wasn't a field in the form.
			if (isset($pre) && is_array($pre)) {
				foreach ($pre as $key => $value) {
					if (!isset($entry[$key])) {
						$entry[$key] = $value;
					}
				}
			}

			$entry = BigTreeAutoModule::sanitizeData($form["table"], $entry);

			$tags = is_array($post_data["_tags"] ?? null) ? $post_data["_tags"] : [];
			$pending = !empty($form["default_pending"]);

			if ($pending) {
				$edit_id = BigTreeAutoModule::createPendingItem(
					$form["module"] ?? "",
					$form["table"],
					$entry,
					[],
					$tags,
					$form["hooks"]["publish"] ?? "",
					true
				);
				$status = "pending";
			} else {
				$edit_id = BigTreeAutoModule::createItem($form["table"], $entry, [], $tags);
				$status = "published";
			}

			// Track resource allocation against the new row (pending rows key on "p{id}").
			ResourceAllocationService::allocateResources($form["table"], $pending ? "p" . $edit_id : $edit_id);

			if (!empty($form["hooks"]["post"]) && is_callable($form["hooks"]["post"])) {
				call_user_func($form["hooks"]["post"], $edit_id, $entry, !$pending);
			}

			if (!$pending && !empty($form["hooks"]["publish"]) && is_callable($form["hooks"]["publish"])) {
				call_user_func($form["hooks"]["publish"], $form["table"], $edit_id, $entry, [], $tags);
			}

			return Response::ok([
				"id" => $edit_id,
				"status" => $status,
				"thank_you_message" => $form["thank_you_message"] ?? "",
				"redirect_url" => $form["redirect_url"] ?? "",
			]);
		}

		// — Tokens —

		public static function mintChallenge(string $hash): string {

			return self::mint(["k" => "embed-challenge", "h" => $hash, "iat" => time()]);
		}

		/**
		 * Verify a challenge minted for this form. With $enforce_fill_time, also
		 * reject one returned sooner than MIN_FILL_SECONDS after it was issued.
		 */
		public static function verifyChallenge(string $raw, string $hash, bool $enforce_fill_time): array {
			try {
				$claims = self::verifyToken($raw, "embed-challenge", $hash);
			} catch (AuthenticationException $e) {
				throw new BadRequestException("This form has expired. Please submit it again.", "spam_check_failed");
			}

			if ($enforce_fill_time && time() - (int)($claims["iat"] ?? 0) < self::MIN_FILL_SECONDS) {
				throw new BadRequestException("That was quicker than expected. Please check your entries and submit again.", "spam_check_failed");
			}

			return $claims;
		}

		public static function mintUploadToken(string $hash, string $column, string $file): string {

			return self::mint(["k" => "embed-upload", "h" => $hash, "c" => $column, "f" => $file]);
		}

		public static function mintCropToken(string $hash, string $column, string $file, array $crop): string {
			$spec = [];

			foreach (["prefix", "width", "height", "retina", "grayscale", "thumbs", "center_crops", "directory", "name"] as $key) {
				$spec[$key] = $crop[$key] ?? null;
			}

			return self::mint(["k" => "embed-crop", "h" => $hash, "c" => $column, "f" => $file, "s" => $spec]);
		}

		/**
		 * True when $token is an upload token this form issued for $column and
		 * $file. Never throws — a bad token just means the value isn't accepted.
		 */
		public static function uploadTokenMatches(string $token, string $hash, string $column, string $file): bool {
			try {
				$claims = self::verifyToken($token, "embed-upload", $hash);
			} catch (\Throwable $e) {
				return false;
			}

			return ($claims["c"] ?? null) === $column && ($claims["f"] ?? null) === $file;
		}

		/**
		 * The values submit hands to the field processors, cleaned of anything a
		 * visitor shouldn't be able to set: file values without a matching upload
		 * token, media-library references, a library pick on a video field, the
		 * `__{column}_recrop__` / `__unchanged` style control keys, and
		 * `resource://` values anywhere (which would copy a library file into the
		 * entry). Matrix rows are cleaned the same way, column by column.
		 *
		 * $upload_tokens is a list of the tokens the uploads returned (a map's
		 * keys are ignored); each one vouches for one file at one field path.
		 */
		public static function cleanPostData(array $values, array $fields, array $upload_tokens, string $hash): array {
			$claimed = [];

			foreach ($upload_tokens as $token) {
				if (!is_string($token)) {
					continue;
				}

				try {
					$claims = self::verifyToken($token, "embed-upload", $hash);
				} catch (\Throwable $e) {
					continue;
				}

				$claimed[(string)($claims["c"] ?? "") . "\0" . (string)($claims["f"] ?? "")] = true;
			}

			return self::cleanValues($values, $fields, $claimed, "");
		}

		/**
		 * Clean one level of values (the form, or one matrix row) against its
		 * fields. $prefix is the field path of the enclosing matrix, if any.
		 */
		private static function cleanValues(array $values, array $fields, array $claimed, string $prefix): array {
			$clean = [];

			foreach ($values as $key => $value) {
				if (is_string($key) && substr($key, 0, 2) === "__") {
					continue;
				}

				$clean[$key] = self::stripResourceRefs($value);
			}

			foreach ($fields as $field) {
				$column = (string)($field["column"] ?? "");
				$type = (string)($field["type"] ?? "");
				$path = $prefix . $column;

				if ($column === "" || !array_key_exists($column, $clean)) {
					continue;
				}

				if (in_array($type, self::LIBRARY_TYPES, true)) {
					unset($clean[$column]);
				} elseif (in_array($type, self::IMAGE_TYPES, true) || in_array($type, self::FILE_TYPES, true)) {
					$file = is_string($clean[$column]) ? $clean[$column] : "";

					if ($file === "" || empty($claimed[$path . "\0" . $file])) {
						$clean[$column] = "";
					}
				} elseif ($type === "video" && is_array($clean[$column])) {
					unset($clean[$column]["managed"]);
				} elseif ($type === "matrix") {
					$rows = is_array($clean[$column]) ? $clean[$column] : [];
					$columns = self::matrixColumns(self::fieldSettings($field));
					$clean[$column] = [];

					foreach ($rows as $row) {
						if (is_array($row)) {
							$clean[$column][] = self::cleanValues($row, $columns, $claimed, $path . ".");
						}
					}
				}
			}

			return $clean;
		}

		/** Whether $name's extension is in a comma-separated `valid_extensions` list (empty = any). */
		public static function extensionAllowed(string $name, string $valid_extensions): bool {
			$allowed = array_filter(array_map(function ($ext) {
				return strtolower(ltrim(trim($ext), "."));
			}, explode(",", $valid_extensions)));

			if (!$allowed) {
				return true;
			}

			$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

			return in_array($extension, $allowed, true);
		}

		private static function mint(array $claims): string {
			$claims["exp"] = time() + self::TOKEN_TTL;

			return Pagination::encodeCursor($claims, Jwt::currentSecret());
		}

		private static function verifyToken(string $raw, string $kind, string $hash): array {
			if ($raw === "") {
				throw new AuthenticationException("Missing token", "missing_token");
			}

			try {
				$claims = Pagination::decodeCursor($raw, Jwt::currentSecret());
			} catch (\Throwable $e) {
				throw new AuthenticationException("Invalid token", "invalid_token");
			}

			if (!is_array($claims) || ($claims["k"] ?? null) !== $kind || ($claims["h"] ?? null) !== $hash) {
				throw new AuthenticationException("Invalid token", "invalid_token");
			}

			if ((int)($claims["exp"] ?? 0) < time()) {
				throw new AuthenticationException("Token expired", "token_expired");
			}

			return $claims;
		}

		private static function stripResourceRefs($value) {
			if (is_array($value)) {
				return array_map([self::class, "stripResourceRefs"], $value);
			}

			if (is_string($value) && stripos(ltrim($value), "resource://") === 0) {
				return "";
			}

			return $value;
		}

		// — Lookups —

		private function requireForm(Request $request): array {
			$hash = $request->routeParam("hash");

			if ($hash === "") {
				throw new NotFoundException("Embed form not found");
			}

			$form = BigTreeAutoModule::getEmbedFormByHash($hash);

			if (!$form) {
				throw new NotFoundException("Embed form not found");
			}

			$form["hash"] = $hash;

			return $form;
		}

		/**
		 * The upload field at $path — a top-level column, or a dotted path through
		 * matrix columns — with its settings normalized. Throws unless it exists
		 * and is one of $types.
		 */
		public static function requireField(array $form, string $path, array $types): array {
			$fields = array_values(array_filter((array)($form["fields"] ?? []), "is_array"));
			$segments = explode(".", $path);
			$last = count($segments) - 1;

			foreach ($segments as $depth => $segment) {
				$match = null;

				foreach ($fields as $field) {
					if ($segment !== "" && (string)($field["column"] ?? "") === $segment) {
						$match = $field;

						break;
					}
				}

				if ($match === null) {
					break;
				}

				$settings = self::fieldSettings($match);
				$type = (string)($match["type"] ?? "");

				if ($depth === $last) {
					if (in_array($type, $types, true)) {
						return ["column" => $path, "type" => $type, "settings" => $settings];
					}

					break;
				}

				if ($type !== "matrix") {
					break;
				}

				$fields = self::matrixColumns($settings);
			}

			throw new BadRequestException("This form has no upload field named '$path'.", "unknown_field");
		}

		/** A field's settings as an array (stored as an array, or JSON in older forms). */
		private static function fieldSettings(array $field): array {
			foreach (["settings", "options"] as $key) {
				$value = $field[$key] ?? null;

				if (is_string($value)) {
					$value = json_decode($value, true);
				}

				if (is_array($value) && $value) {
					return $value;
				}
			}

			return [];
		}

		/**
		 * A matrix's columns in field shape (`column` rather than the stored `id`).
		 * Sub-fields with no directory store under `files/pages/`, as the matrix
		 * processor sets it.
		 */
		private static function matrixColumns(array $settings): array {
			$columns = [];

			foreach ((array)($settings["columns"] ?? []) as $column) {
				if (!is_array($column) || !isset($column["id"])) {
					continue;
				}

				$column_settings = self::fieldSettings($column);

				if (empty($column_settings["directory"])) {
					$column_settings["directory"] = "files/pages/";
				}

				$columns[] = [
					"column" => (string)$column["id"],
					"type" => (string)($column["type"] ?? ""),
					"settings" => $column_settings,
				];
			}

			return $columns;
		}
	}
