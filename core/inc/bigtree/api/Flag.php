<?php
	namespace BigTree\Api;

	/**
	 * Formatting helpers for BigTree's legacy "on"/"" boolean flags — the storage
	 * convention an HTML checkbox posts ("on" when ticked, absent otherwise) that
	 * the CMS persists verbatim in JSONDB rows and SQL columns. Keep the
	 * truthy→flag conversion in one place rather than re-inlining `? "on" : ""` at
	 * each field, so the legacy format lives in a single, swappable spot.
	 *
	 * Named Flag (not Json) to avoid colliding with the Json request middleware;
	 * the audit doc that proposed this refers to it as "Json::checkbox".
	 */
	class Flag {
		/**
		 * Convert any truthy value to the legacy "on" flag, everything else to "".
		 * Missing/empty/false input becomes "" (mirrors the historical
		 * `!empty($x) ? "on" : ""` idiom callers used inline).
		 */
		public static function checkbox(mixed $value): string {

			return !empty($value) ? "on" : "";
		}

		/**
		 * Read a legacy flag back into a boolean — the inverse of checkbox(). A
		 * stored value is "on" exactly when the flag is set, so this is a strict
		 * comparison against the canonical string rather than a loose truthiness
		 * check; it folds the scattered `$col === "on"` reads in the service layer.
		 */
		public static function isOn(mixed $value): bool {

			return $value === "on";
		}
	}
