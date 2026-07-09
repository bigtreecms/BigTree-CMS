<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\BadRequestException;

	/**
	 * Multipart upload intake for the media/image/extension endpoints.
	 *
	 * The "pull the single uploaded file off the request, then validate it"
	 * sequence was copy-pasted across ImageService, ResourceService, and
	 * ExtensionService — each carrying its own `assertUploadOk()` /
	 * `uploadErrorMessage()` pair and its own 256MB cap constant. The copies had
	 * already drifted: ExtensionService hand-rolled a weaker check that skipped the
	 * empty-file and size-cap guards. This folds all three onto one definition so
	 * the cap lives in exactly one place and every upload path enforces it.
	 *
	 * All failures throw BadRequestException with the same `code_string`s the SPA
	 * matches on (`missing_file`, `upload_error`, `empty_upload`, `file_too_large`).
	 */
	final class Upload {
		// 256MB hard cap, enforced in addition to PHP's ini upload_max_filesize.
		public const MAX_BYTES = 268435456;

		/**
		 * Pull the single uploaded file for $name off the request and validate it.
		 * Callers that only ever accept one file (all of them, in v1) use this;
		 * throws `missing_file` when nothing was uploaded under that field name.
		 *
		 * @return array The normalized file array (name, tmp_name, type, size, error).
		 */
		public static function requireSingle(Request $request, string $name = "file"): array {
			$file_set = $request->file($name);

			if (!$file_set) {
				throw new BadRequestException("Missing '$name' upload", "missing_file");
			}

			$file = $file_set[0];
			self::assertOk($file);

			return $file;
		}

		/**
		 * Validate an already-extracted upload: transport error, present tmp file,
		 * non-empty payload, within the size cap. Split out from requireSingle() so a
		 * caller that has already pulled the file off the request can reuse the checks.
		 */
		public static function assertOk(array $file): void {
			if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				throw new BadRequestException("Upload error: " . self::errorMessage((int)$file["error"]), "upload_error");
			}

			if (!is_uploaded_file($file["tmp_name"]) && !file_exists($file["tmp_name"])) {
				throw new BadRequestException("Upload tmp file missing", "upload_error");
			}

			if (($file["size"] ?? 0) <= 0) {
				throw new BadRequestException("Empty upload", "empty_upload");
			}

			if (($file["size"] ?? 0) > self::MAX_BYTES) {
				throw new BadRequestException("File too large", "file_too_large");
			}
		}

		/**
		 * Human-readable message for a PHP UPLOAD_ERR_* code.
		 */
		public static function errorMessage(int $code): string {
			switch ($code) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE: return "File exceeds size limit (" . ini_get("upload_max_filesize") . ")";

				case UPLOAD_ERR_PARTIAL: return "Upload was interrupted";

				case UPLOAD_ERR_NO_FILE: return "No file sent";

				case UPLOAD_ERR_NO_TMP_DIR: return "Server is missing tmp dir";

				case UPLOAD_ERR_CANT_WRITE: return "Server could not write the upload";

				case UPLOAD_ERR_EXTENSION: return "Upload blocked by a PHP extension";

				default: return "Unknown upload error ($code)";
			}
		}
	}
