<?php
	use BigTree\Api\Request;
	use BigTree\Api\Upload;
	use BigTree\Api\Exceptions\BadRequestException;

	// A real, on-disk tmp file so assertOk's is_uploaded_file()||file_exists()
	// tmp-presence guard passes under CLI (is_uploaded_file is always false here).
	function upload_tmp_file(int $size = 10): string {
		$path = tempnam(sys_get_temp_dir(), "bt_upload_test_");
		file_put_contents($path, str_repeat("x", $size));

		return $path;
	}

	function make_upload_request(?array $file): Request {
		$r = new Request();

		if ($file !== null) {
			$r->files = ["file" => [$file]];
		}

		return $r;
	}

	function ok_file(array $overrides = []): array {
		$tmp = upload_tmp_file();

		return array_merge([
			"name" => "photo.jpg",
			"tmp_name" => $tmp,
			"type" => "image/jpeg",
			"size" => 10,
			"error" => UPLOAD_ERR_OK,
		], $overrides);
	}

	function test_upload_require_single_returns_file() {
		$file = ok_file();
		$r = make_upload_request($file);
		T::equals(Upload::requireSingle($r), $file, "returns the single normalized file array");
		@unlink($file["tmp_name"]);
	}

	function test_upload_require_single_missing_throws() {
		$r = make_upload_request(null);
		T::throws(function () use ($r) { Upload::requireSingle($r); }, BadRequestException::class, "no file → BadRequestException");

		try {
			Upload::requireSingle($r);
		} catch (BadRequestException $e) {
			T::equals($e->code_string, "missing_file", "missing upload → missing_file code");
		}
	}

	function test_upload_require_single_custom_field_name() {
		$file = ok_file();
		$r = new Request();
		$r->files = ["package" => [$file]];
		T::equals(Upload::requireSingle($r, "package"), $file, "honors a non-default field name");
		@unlink($file["tmp_name"]);
	}

	function test_upload_assert_ok_passes_valid() {
		$file = ok_file();
		Upload::assertOk($file);
		T::ok(true, "valid file passes assertOk without throwing");
		@unlink($file["tmp_name"]);
	}

	function test_upload_assert_ok_transport_error() {
		try {
			Upload::assertOk(ok_file(["error" => UPLOAD_ERR_PARTIAL]));
			T::ok(false, "transport error should throw");
		} catch (BadRequestException $e) {
			T::equals($e->code_string, "upload_error", "transport error → upload_error");
		}
	}

	function test_upload_assert_ok_missing_tmp() {
		try {
			Upload::assertOk([
				"name" => "x.jpg",
				"tmp_name" => "/nonexistent/path/bt_missing_tmp",
				"type" => "image/jpeg",
				"size" => 10,
				"error" => UPLOAD_ERR_OK,
			]);
			T::ok(false, "missing tmp should throw");
		} catch (BadRequestException $e) {
			T::equals($e->code_string, "upload_error", "missing tmp → upload_error");
		}
	}

	function test_upload_assert_ok_empty() {
		$file = ok_file(["size" => 0]);

		try {
			Upload::assertOk($file);
			T::ok(false, "empty upload should throw");
		} catch (BadRequestException $e) {
			T::equals($e->code_string, "empty_upload", "size 0 → empty_upload");
		}

		@unlink($file["tmp_name"]);
	}

	function test_upload_assert_ok_too_large() {
		$file = ok_file(["size" => Upload::MAX_BYTES + 1]);

		try {
			Upload::assertOk($file);
			T::ok(false, "oversize upload should throw");
		} catch (BadRequestException $e) {
			T::equals($e->code_string, "file_too_large", "over cap → file_too_large");
		}

		@unlink($file["tmp_name"]);
	}

	function test_upload_error_message() {
		T::equals(Upload::errorMessage(UPLOAD_ERR_NO_FILE), "No file sent", "UPLOAD_ERR_NO_FILE message");
		T::equals(Upload::errorMessage(UPLOAD_ERR_PARTIAL), "Upload was interrupted", "UPLOAD_ERR_PARTIAL message");
		T::equals(Upload::errorMessage(999), "Unknown upload error (999)", "unknown code message");
	}

	function test_upload_max_bytes_is_256mb() {
		T::equals(Upload::MAX_BYTES, 268435456, "cap is 256MB");
	}
