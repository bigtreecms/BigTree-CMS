<?php
	namespace BigTree\Api;

	class ETag {
		public static function fromString($value) {

			return '"' . substr(sha1($value), 0, 16) . '"';
		}

		public static function fromMtimes(array $paths, $extra = "") {
			$parts = [];

			foreach ($paths as $p) {
				$parts[] = $p . ":" . @filemtime($p);
			}

			return self::fromString(implode("|", $parts) . "|" . $extra);
		}

		public static function check(Request $request, $etag) {
			$header = $request->header("if-none-match");

			if (!$header) {
				return false;
			}
			$candidates = array_map("trim", explode(",", $header));

			return in_array($etag, $candidates, true) || in_array($etag, array_map(function ($c) { return trim($c, "W/"); }, $candidates), true);
		}
	}
