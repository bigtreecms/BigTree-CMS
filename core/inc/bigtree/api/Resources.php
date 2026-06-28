<?php
	namespace BigTree\Api;

	use BigTree;

	/**
	 * Shared cleaning for the resources array carried by callouts and templates
	 * (and conceptually by modules). Normalizes an incoming resources list into
	 * the canonical {id, type, title, subtitle, settings} shape, safe-encoding the
	 * scalar fields and recursively filtering the settings. The canonical home for
	 * future resource-field cleaning changes.
	 */
	class Resources {
		/**
		 * Normalize a resources array. Entries without an id are dropped; "settings"
		 * falls back to the legacy "options" key and is JSON-decoded when supplied as
		 * a string. Accepts mixed input (cast to array) to tolerate malformed request
		 * bodies rather than fataling.
		 *
		 * @param mixed $resources Raw resources value from a request body.
		 * @return array The cleaned, canonically-shaped resources list.
		 */
		public static function clean($resources): array {
			$out = [];

			foreach ((array)$resources as $r) {
				if (empty($r["id"])) {
					continue;
				}

				$settings = $r["settings"] ?? ($r["options"] ?? []);

				if (is_string($settings)) {
					$settings = json_decode($settings, true) ?: [];
				}

				$out[] = [
					"id" => BigTree::safeEncode($r["id"]),
					"type" => BigTree::safeEncode($r["type"] ?? "text"),
					"title" => BigTree::safeEncode($r["title"] ?? ""),
					"subtitle" => BigTree::safeEncode($r["subtitle"] ?? ""),
					"settings" => BigTree::arrayFilterRecursive($settings ?: []),
				];
			}

			return $out;
		}
	}
