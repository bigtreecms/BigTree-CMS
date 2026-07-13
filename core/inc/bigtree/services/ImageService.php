<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Json;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Upload;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree;
	use BigTreeImage;
	use BigTreeJSONDB;
	use BigTreeStorage;
	use SQL;

	/**
	 * Image form-field processing — the API equivalent of the legacy admin's
	 * image field (core/admin/field-types/image/process.php +
	 * FieldProcessingService::processImageUpload / Admin::processCrops).
	 *
	 * Unlike ResourceService, this does NOT create media-library
	 * (bigtree_resources) rows. It stores the original under the field's own
	 * `directory` (default "files/"), auto-generates thumbs / center crops /
	 * exact-size crops, and reports any crops that need manual selection back to
	 * the caller. The SPA then drives a cropper and calls `crop()` once per
	 * pending crop to generate the derivative.
	 *
	 * The stored value handed back to the page/module form is a plain path
	 * string, so nothing downstream needs to know cropping happened.
	 */
	class ImageService {
		// — Direct upload (multipart) —

		public function process(Request $request) {
			$file = Upload::requireSingle($request);
			$settings = Json::decode($request->body["settings"] ?? null);
			$result = $this->runPipeline($file["tmp_name"], $file["name"], $settings);

			return Response::created($result, null);
		}

		// — Re-process an existing image (Browse-from-media or Recrop) —
		// Body: { source: { resource_id } | { file }, settings }

		public function reprocess(Request $request) {
			$source = $request->bodyMap("source");
			$settings = Json::decode($request->body["settings"] ?? null);

			// In-place recrop ("Choose New Crops"): regenerate crops against the
			// field's existing original without re-storing it (matches the legacy
			// recrop, which never duplicates the file or changes its path).
			if (!empty($source["in_place"]) && !empty($source["file"])) {
				$original = (string)$source["file"];
				$local = $this->resolveReadableImage($original);
				$result = $this->runPipeline($local, basename($local), $settings, $original);

				return Response::created($result, null);
			}

			if (!empty($source["resource_id"])) {
				$resource = Entity::fetchOrFail(
					"SELECT * FROM bigtree_resources WHERE id = ?",
					[(int)$source["resource_id"]],
					"Resource not found"
				);

				if ($resource["is_image"] !== "on") {
					throw new BadRequestException("Resource is not an image", "not_an_image");
				}
				$original = $resource["file"];
				$name = basename($resource["file"]);
			} elseif (!empty($source["file"])) {
				$original = (string)$source["file"];
				$name = basename(parse_url($original, PHP_URL_PATH) ?: $original);
			} else {
				throw new BadRequestException("source.resource_id or source.file is required", "missing_source");
			}

			// Copy the source original to a temp file so the pipeline can store a
			// fresh original under the field directory (mirrors the legacy admin
			// copying a resource:// file before processImageUpload).
			$local = $this->resolveReadableImage($original);
			$temp = SITE_ROOT . "files/" . uniqid("temp-reprocess-") . ".img";
			BigTree::copyFile($local, $temp);

			$result = $this->runPipeline($temp, $name, $settings);
			@unlink($temp);

			return Response::created($result, null);
		}

		// — Finalize a single manual crop —
		// Body: file (stored original), x/y/width/height (source px), plus the
		// crop spec carried over from a pending_crops entry.

		public function crop(Request $request) {
			$file = $request->bodyString("file", "", false);
			$x = $request->bodyInt("x");
			$y = $request->bodyInt("y");
			$w = $request->bodyInt("width");
			$h = $request->bodyInt("height");
			$target_w = $request->bodyInt("target_width");
			$target_h = $request->bodyInt("target_height");
			$prefix = $request->bodyString("prefix", "", false);
			$name = BigTree::cleanFile($request->bodyString("name", basename($file), false));
			$directory = $this->cleanDirectory($request->bodyString("directory", "files/", false));
			$retina = $request->bodyBool("retina");
			$grayscale = $request->bodyBool("grayscale");
			$thumbs = $request->bodyMap("thumbs");
			$center_crops = $request->bodyMap("center_crops");

			if ($w <= 0 || $h <= 0 || $target_w <= 0 || $target_h <= 0) {
				throw new BadRequestException("width/height/target_width/target_height must be > 0", "bad_dimensions");
			}

			$source = $this->resolveReadableImage($file);
			$image = new BigTreeImage($source);

			if ($image->Error) {
				throw new BadRequestException("Image processing failed: " . $image->Error, "image_invalid");
			}
			$storage = new BigTreeStorage();

			// The crop itself.
			$temp_crop = $image->getTempFileName();
			$image->crop($temp_crop, $x, $y, $target_w, $target_h, $w, $h, $retina, $grayscale);
			$temp_image = new BigTreeImage($temp_crop);

			// Thumbnails of the crop — re-cropped from the original at the thumb's
			// dimensions so we don't lose quality (mirrors Admin::processCrops).
			foreach ($thumbs as $thumb) {
				$temp_thumb = $temp_image->getTempFileName();
				$size = $temp_image->getThumbnailSize((int)($thumb["width"] ?? 0), (int)($thumb["height"] ?? 0));
				$image->crop($temp_thumb, $x, $y, $size["width"], $size["height"], $w, $h, $retina, !empty($thumb["grayscale"]));
				$storage->replace($temp_thumb, ($thumb["prefix"] ?? "") . $name, $directory);
			}

			// Center crops of the crop.
			foreach ($center_crops as $center_crop) {
				$temp_center = $image->getTempFileName();
				$temp_image->centerCrop($temp_center, (int)($center_crop["width"] ?? 0), (int)($center_crop["height"] ?? 0), $retina, !empty($center_crop["grayscale"]));
				$storage->replace($temp_center, ($center_crop["prefix"] ?? "") . $name, $directory);
			}

			$stored = $storage->replace($temp_crop, $prefix . $name, $directory);
			@unlink($temp_crop);

			if (!$stored) {
				throw new BadRequestException("Storage refused crop", "storage_failed");
			}

			return Response::created([
				"file" => $stored,
				"prefix" => $prefix,
				"width" => $target_w,
				"height" => $target_h,
			], null);
		}

		// — Shared pipeline —

		/**
		 * Store the original + auto-generate thumbs/center-crops/exact crops, and
		 * return the stored path plus any crops needing manual selection. Mirrors
		 * FieldProcessingService::processImageUpload minus the resource-row / global-crops
		 * bits.
		 *
		 * When $in_place_path is given, $source is treated as an existing original
		 * (a real path) that we regenerate crops against without re-storing — used
		 * for the recrop flow so the file and its URL stay put. Otherwise $source
		 * is a fresh upload / temp copy that gets stored under the field directory.
		 */
		private function runPipeline(string $source, string $name, array $settings, ?string $in_place_path = null): array {
			$settings = $this->applyPreset($settings);
			$settings["directory"] = $this->cleanDirectory($settings["directory"] ?? "files/");

			$in_place = $in_place_path !== null;
			$image = new BigTreeImage($source, $settings);

			if ($image->Error) {
				throw new BadRequestException($image->Error, "image_invalid");
			}

			// Sub-crop any crops that don't meet the required image size.
			$image->filterGeneratableCrops();

			// Bail before we run out of memory making the largest derivative.
			$largest_thumb = $image->getLargestThumbnail();
			$largest_crop = $image->getLargestCrop();

			if (($largest_thumb && !$image->checkMemory($largest_thumb["width"], $largest_thumb["height"])) ||
				($largest_crop && !$image->checkMemory($largest_crop["width"], $largest_crop["height"]))) {
				if (!$in_place) {
					$image->destroy();
				}

				throw new BadRequestException("The image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.", "image_too_large");
			}

			if ($in_place) {
				// Generate derivatives next to the existing original; keep its path.
				$image->StoredName = basename($source);
				$stored = $in_place_path;
			} else {
				$stored = $image->store($name);

				if (!$stored) {
					$image->destroy();

					throw new BadRequestException($image->Error ?: "Could not store the image.", "storage_failed");
				}
			}

			// Auto-generate everything that doesn't need user input. processCrops()
			// also emits exact-size crops + their nested thumbs/center_crops and
			// returns the registry of crops that DO need manual selection.
			$image->processThumbnails();
			$pending = $image->processCrops();

			// Capture dimensions before destroy() zeroes them.
			$width = $image->Width;
			$height = $image->Height;
			$pending_crops = [];

			foreach (is_array($pending) ? $pending : [] as $crop) {
				$pending_crops[] = [
					"prefix" => $crop["prefix"] ?? "",
					"width" => (int)$crop["width"],
					"height" => (int)$crop["height"],
					"retina" => !empty($crop["retina"]),
					"grayscale" => !empty($crop["grayscale"]),
					"thumbs" => array_values(is_array($crop["thumbs"] ?? null) ? $crop["thumbs"] : []),
					"center_crops" => array_values(is_array($crop["center_crops"] ?? null) ? $crop["center_crops"] : []),
					"directory" => $settings["directory"],
					"name" => $image->StoredName,
				];
			}

			// A prefix-less crop is stored over the original filename, so it must be
			// drawn LAST — otherwise it would clobber the source the remaining crops
			// are still cropped from. (PHP 8's usort is stable, so the relative order
			// of the prefixed crops is preserved.)
			usort($pending_crops, function ($a, $b) {

				return ($a["prefix"] === "" ? 1 : 0) <=> ($b["prefix"] === "" ? 1 : 0);
			});

			// For a fresh store, $this->File is a temp we no longer need. For an
			// in-place recrop, $this->File IS the original — destroy() would delete
			// it, so we must NOT call it.
			if (!$in_place) {
				$image->destroy();
			}

			return [
				"file" => $stored,
				"width" => $width,
				"height" => $height,
				"pending_crops" => $pending_crops,
			];
		}

		/**
		 * Merge a media preset's properties over the supplied settings, matching
		 * processImageUpload. Caller-supplied settings win for keys the preset
		 * doesn't define; preset values overwrite where present (legacy order).
		 */
		private function applyPreset(array $settings): array {
			if (empty($settings["preset"])) {
				return $settings;
			}

			$media = BigTreeJSONDB::get("config", "media-settings");
			$preset = $media["presets"][$settings["preset"]] ?? null;

			if (is_array($preset)) {
				foreach ($preset as $key => $value) {
					$settings[$key] = $value;
				}
			}

			return $settings;
		}

		// — Helpers —

		/**
		 * Resolve a stored path / template URL to something BigTreeImage can read,
		 * guarding against path traversal. Local paths must resolve inside
		 * SITE_ROOT; remote (cloud) URLs are passed through untouched.
		 */
		private function resolveReadableImage(string $file): string {
			if (preg_match('#^https?://#i', $file)) {
				$this->assertSafeRemoteImageUrl($file);

				return $file;
			}

			$local = str_replace(["{wwwroot}", "{staticroot}", WWW_ROOT, STATIC_ROOT], SITE_ROOT, $file);
			$real = realpath($local);
			$root = realpath(SITE_ROOT);

			if ($real === false || $root === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
				throw new BadRequestException("Image path is not accessible", "bad_path");
			}

			return $real;
		}

		/**
		 * Guard against SSRF when a caller hands us a remote image URL. The
		 * reprocess / crop flows legitimately receive cloud-storage / CDN URLs that
		 * BigTree itself previously returned, so we can't refuse remote fetches
		 * outright — but the URL is client-controlled, so we must ensure it can't be
		 * pointed at internal infrastructure (link-local cloud metadata at
		 * 169.254.169.254, loopback, RFC 1918 ranges, etc.).
		 *
		 * The host is resolved and every resulting address must be a public unicast
		 * address. An optional config allow-list (api.image_fetch_allowed_hosts)
		 * short-circuits the check for known storage hosts.
		 */
		private function assertSafeRemoteImageUrl(string $url): void {
			global $bigtree;

			$host = parse_url($url, PHP_URL_HOST);

			if (!is_string($host) || $host === "") {
				throw new BadRequestException("Image URL host is invalid", "bad_remote_host");
			}

			$host = strtolower($host);

			// Explicit allow-list (e.g. the configured cloud / CDN domains) wins.
			$allowed = $bigtree["config"]["api"]["image_fetch_allowed_hosts"] ?? [];

			foreach ((array)$allowed as $allowed_host) {
				$allowed_host = strtolower(trim((string)$allowed_host));

				if ($allowed_host === "") {
					continue;
				}

				if ($host === $allowed_host || substr($host, -(strlen($allowed_host) + 1)) === "." . $allowed_host) {
					return;
				}
			}

			// Resolve to every A / AAAA record and require each to be a public address.
			$ips = [];

			if (filter_var($host, FILTER_VALIDATE_IP)) {
				$ips[] = $host;
			} else {
				$a = @dns_get_record($host, DNS_A) ?: [];
				$aaaa = @dns_get_record($host, DNS_AAAA) ?: [];

				foreach (array_merge($a, $aaaa) as $record) {
					$ip = $record["ip"] ?? ($record["ipv6"] ?? null);

					if ($ip) {
						$ips[] = $ip;
					}
				}
			}

			if (empty($ips)) {
				throw new BadRequestException("Image URL host could not be resolved", "bad_remote_host");
			}

			foreach ($ips as $ip) {
				if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					throw new BadRequestException("Image URL resolves to a disallowed address", "blocked_remote_host");
				}
			}
		}

		private function cleanDirectory($raw): string {
			$dir = trim((string)$raw);

			if ($dir === "") {
				return "files/";
			}

			// Storage paths are relative to SITE_ROOT. We REJECT (rather than silently
			// strip) anything that looks like traversal, an absolute/encoded path, or
			// carries unexpected characters — silent rewriting of ".." is exactly what
			// makes such filters bypassable.
			if (strpbrk($dir, "\\\0") !== false) {
				throw new BadRequestException("Invalid storage directory", "bad_directory");
			}

			$segments = [];

			foreach (explode("/", $dir) as $segment) {
				if ($segment === "" || $segment === ".") {
					continue;
				}

				if ($segment === ".." || !preg_match('/^[A-Za-z0-9_.\- ]+$/', $segment)) {
					throw new BadRequestException("Invalid storage directory", "bad_directory");
				}

				$segments[] = $segment;
			}

			if (empty($segments)) {
				return "files/";
			}

			return implode("/", $segments) . "/";
		}
	}
