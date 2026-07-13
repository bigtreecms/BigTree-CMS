<?php
	namespace BigTree\Services;

	use BigTreeCMS;
	use BigTree;
	use SQL;
	use BigTreeJSONDB;
	use DOMDocument;

	/**
	 * IPL/IRL/URL link utilities. IPL/IRL/URL link utilities (cluster C).
	 */
	class LinkService {

		public static function autoIPL($html) {
			if (empty($html)) {
				return $html;
			}

			// If this string is actually just a URL, IPL it.
			if ((substr($html, 0, 7) == "http://" || substr($html, 0, 8) == "https://") && strpos($html, "\n") === false && strpos($html, "\r") === false) {
				$html = static::makeIPL($html);
				// Otherwise, switch all the image srcs and javascripts srcs and whatnot to {wwwroot}.
			} else {
				$html = preg_replace_callback('/href="([^"]*)"/', [self::class, "autoIPLCallbackHref"], $html);
				$html = preg_replace_callback('/src="([^"]*)"/', [self::class, "autoIPLCallbackSrc"], $html);
				$html = BigTreeCMS::replaceHardRoots($html);
			}

			return $html;
		}

		private static function autoIPLCallbackHref($matches) {
			$href = static::makeIPL(BigTreeCMS::replaceRelativeRoots($matches[1]));

			return 'href="'.$href.'"';
		}

		private static function autoIPLCallbackSrc($matches) {
			$src = static::makeIPL(BigTreeCMS::replaceRelativeRoots($matches[1]));

			return 'src="'.$src.'"';
		}

		public static function checkHTML($relative_path, $html, $external = false) {
			if (!$html || !is_string($html)) {
				return [];
			}

			$errors = [];
			$doc = new DOMDocument();
			@$doc->loadHTML($html); // Silenced because the HTML could be invalid.
			// Check A tags.
			$links = $doc->getElementsByTagName("a");
			foreach ($links as $link) {
				$href = $link->getAttribute("href");
				$href = BigTreeCMS::replaceRelativeRoots($href);

				if ($href == WWW_ROOT || $href == STATIC_ROOT || $href == ADMIN_ROOT) {
					continue;
				}

				// See if the link matches something local
				$local = false;

				if (substr($href, 0, 4) == "http") {
					foreach (BigTreeCMS::$ReplaceableRootTokens as $local_key) {
						if (strpos($href, $local_key) === 0) {
							$local = true;
						}
					}
				}

				if ((substr($href, 0, 2) == "//" || substr($href, 0, 4) == "http") && !$local) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (!static::urlExists($href)) {
							$errors["a"][] = $href;
						}
					}
				} elseif (substr($href, 0, 6) == "ipl://") {
					if (!static::iplExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href, 0, 6) == "irl://") {
					if (!static::irlExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href, 0, 7) == "mailto:" || substr($href, 0, 1) == "#" || substr($href, 0, 5) == "data:" || substr($href, 0, 4) == "tel:") {
					// Don't do anything, it's a page mark, data URI, or email address
				} elseif (substr($href, 0, 4) == "http") {
					// It's a local hard link
					if (!static::urlExists($href)) {
						$errors["a"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					if (!static::urlExists($local)) {
						$errors["a"][] = $local;
					}
				}
			}
			// Check IMG tags.
			$images = $doc->getElementsByTagName("img");
			foreach ($images as $image) {
				$href = $image->getAttribute("src");

				if (substr($href, 0, 5) == "data:") {
					continue;
				}

				$href = BigTreeCMS::replaceRelativeRoots($href);

				// See if the link matches something local
				$local = false;

				if (substr($href, 0, 4) == "http") {
					foreach (BigTreeCMS::$ReplaceableRootTokens as $local_key) {
						if (strpos($href, $local_key) === 0) {
							$local = true;
						}
					}
				}

				if ((substr($href, 0, 2) == "//" || substr($href, 0, 4) == "http") && !$local) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (!static::urlExists($href)) {
							$errors["img"][] = $href;
						}
					}
				} elseif (substr($href, 0, 6) == "irl://") {
					if (!static::irlExists($href)) {
						$errors["img"][] = $href;
					}
				} elseif (substr($href, 0, 5) == "data:") {
					// Do nothing, it's a data URI
				} elseif (substr($href, 0, 4) == "http") {
					// It's a local hard link
					if (!static::urlExists($href)) {
						$errors["img"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					if (!static::urlExists($local)) {
						$errors["img"][] = $local;
					}
				}
			}

			return $errors;
		}

		public static function iplExists($ipl) {
			$ipl = explode("//", $ipl);

			// See if the page it references still exists.
			$nav_id = $ipl[1];

			if (!sqlrows(sqlquery("SELECT id FROM bigtree_pages WHERE id = '$nav_id'"))) {
				return false;
			}

			// Decode the commands attached to the page
			$commands = json_decode(base64_decode($ipl[2]), true);

			// If there are no commands, we're good.
			if (empty($commands[0])) {
				return true;
			}

			// If it's a hash tag link, we're also good.
			if (substr($commands[0], 0, 1) == "#") {
				return true;
			}

			// Get template for the navigation id to see if it's a routed template
			$template_id = SQL::fetchSingle("SELECT template FROM bigtree_pages WHERE id = ?", $nav_id);

			// If we're a routed template, we're good.
			if ($template_id) {
				$template = BigTreeJSONDB::get("templates", $template_id);

				if (!empty($template["routed"])) {
					return true;
				}
			}

			// We may have been on a page, but there's extra routes that don't go anywhere or do anything so it's a 404.
			return false;
		}

		public static function irlExists($irl) {
			$irl = explode("//", $irl);
			$resource = ResourceAllocationService::getResource($irl[1]);
			if ($resource) {
				return true;
			}

			return false;
		}

		public static function makeIPL($url) {
			global $bigtree;

			if (substr($url, 0, 6) === "irl://") {
				$parts = explode("//", substr($url, 6), 2);

				if (!empty($parts[0]) && is_numeric($parts[0])) {
					ResourceAllocationService::trackResource($parts[0]);
				}

				return $url;
			}

			$resource = ResourceAllocationService::getResourceByUrl($url);

			if ($resource) {
				ResourceAllocationService::trackResource($resource["id"]);

				return "irl://".$resource["id"]."//".$resource["prefix"];
			}

			if (strpos($url, WWW_ROOT) === 0) {
				$path_components = explode("/", rtrim(substr($url, strlen(WWW_ROOT)), "/"));
			} else {
				$path_components = explode("/", rtrim($url, "/"));
			}

			// See if this is a file
			$local_path = str_replace([WWW_ROOT, STATIC_ROOT], SITE_ROOT, $url);

			if (substr($local_path, 0, 2) !== "//" && (
				($path_components[0] !== "files" || $path_components[1] !== "resources") &&
				(substr($local_path, 0, 1) == "/" || substr($local_path, 0, 2) == "\\\\") &&
				file_exists($local_path)
			)) {

				return BigTreeCMS::replaceHardRoots($url);
			}

			// If we have multiple sites, try each domain
			if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
				foreach ($bigtree["config"]["sites"] as $site_key => $configuration) {
					$site_roots = array_filter([
						$configuration["www_root"] ?? "",
						$configuration["static_root"] ?? ""
					]);
					$matched_root = false;

					foreach ($site_roots as $site_root) {
						if ($site_root && strpos($url, $site_root) !== false) {
							$matched_root = $site_root;

							break;
						}
					}

					// This is the site we're pointing to
					if ($matched_root) {
						$path_components = explode("/", rtrim(str_replace($matched_root, "", $url), "/"));

						// Get the root path of the site for calculating an IPL and add it to the path components
						$f = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".$configuration["trunk"]."'"));
						$path_components = array_filter(array_merge(explode("/", $f["path"]), $path_components));

						// Check for page link
						[$navid, $commands, $routed_state, $get_vars, $hash] = PageService::getPageIDForPath($path_components);

						if ($navid) {
							return "ipl://".$navid."//".base64_encode(json_encode($commands))."//".base64_encode($get_vars)."//".base64_encode($hash);
						} else {
							return BigTreeCMS::replaceHardRoots($url);
						}
					}
				}

				return BigTreeCMS::replaceHardRoots($url);
			} else {
				// Check for page link
				[$navid, $commands, $routed_state, $get_vars, $hash] = PageService::getPageIDForPath($path_components);
			}

			if (!$navid) {
				return BigTreeCMS::replaceHardRoots($url);
			}

			return "ipl://".$navid."//".base64_encode(json_encode($commands))."//".base64_encode($get_vars)."//".base64_encode($hash);
		}

		public static function stripMultipleRootTokens($string) {
			global $bigtree;

			if (empty($bigtree["config"]["sites"]) || !array_filter((array) $bigtree["config"]["sites"])) {
				return $string;
			}

			foreach ($bigtree["config"]["sites"] as $key => $data) {
				$string = str_replace(
					["{wwwroot:$key}", "{staticroot:$key}"],
					["{wwwroot}", "{staticroot}"],
					$string
				);
			}

			return $string;
		}

		public static function urlExists($url) {
			return BigTree::urlExists($url);
		}
	}
