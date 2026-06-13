<?php
	use BigTree\Services\ResourceService;
	use BigTree\Api\Exceptions\BadRequestException;

	function test_safe_storage_directory_rejects_traversal() {
		$m = new ReflectionMethod(ResourceService::class, "safeStorageDirectory");
		$m->setAccessible(true);

		// Accepts and normalizes good input.
		T::equals($m->invoke(null, "files/resources/crops", "x/"), "files/resources/crops/", "normalizes trailing slash");
		T::equals($m->invoke(null, "files/resources/crops/", "x/"), "files/resources/crops/", "keeps single trailing slash");
		T::equals($m->invoke(null, "", "files/resources/"), "files/resources/", "empty → default");
		T::equals($m->invoke(null, null, "files/resources/"), "files/resources/", "null → default");

		// Rejects traversal / absolute / scheme / drive / backslash / NUL.
		T::throws(fn () => $m->invoke(null, "../../etc", "x/"), BadRequestException::class, "rejects ..");
		T::throws(fn () => $m->invoke(null, "/etc/passwd", "x/"), BadRequestException::class, "rejects absolute");
		T::throws(fn () => $m->invoke(null, "files/../../x", "x/"), BadRequestException::class, "rejects mid-path ..");
		T::throws(fn () => $m->invoke(null, "http://evil/", "x/"), BadRequestException::class, "rejects scheme");
		T::throws(fn () => $m->invoke(null, "C:/Windows", "x/"), BadRequestException::class, "rejects drive letter");
		T::throws(fn () => $m->invoke(null, "files\\resources", "x/"), BadRequestException::class, "rejects backslash");
		T::throws(fn () => $m->invoke(null, "files/\0/x", "x/"), BadRequestException::class, "rejects NUL byte");
	}
