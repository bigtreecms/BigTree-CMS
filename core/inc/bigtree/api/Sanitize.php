<?php
	namespace BigTree\Api;

	/**
	 * Shared, behavior-identical sanitizers used before security-sensitive
	 * operations (e.g. filesystem writes). Keep these canonical: never re-inline
	 * a copy of one of these checks in a service — call the helper instead, so a
	 * future hardening fix lands in exactly one place.
	 */
	class Sanitize {
		/**
		 * A single path segment is safe when it's non-empty, contains only
		 * letters / digits / dot / dash / underscore (extension ids are reverse-DNS
		 * like "com.fastspot.date-range"), and has no ".." traversal. The first
		 * character must be [a-z0-9], so leading dots (e.g. ".hidden") are rejected.
		 */
		public static function pathSegment(string $segment): bool {

			return $segment !== ""
				&& (bool)preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $segment)
				&& strpos($segment, "..") === false;
		}
	}
