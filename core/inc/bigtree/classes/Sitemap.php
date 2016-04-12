<?php
	/*
		Class: BigTree\Sitemap
			Provides an interface for handling the BigTree sitemap.
	*/

	namespace BigTree;

	class Sitemap extends BaseObject {

		/*
			Function: getXML
				Returns an XML sitemap.
		*/

		static function getXML() {
			$response = '<?xml version="1.0" encoding="UTF-8" ?>';
			$response .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';

			$pages = SQL::fetchAll("SELECT id, template, external, path FROM bigtree_pages 
									WHERE archived = '' AND (publish_at >= NOW() OR publish_at IS NULL) ORDER BY id ASC");

			foreach ($pages as $page) {
				if ($page["template"] || strpos($page["external"], DOMAIN)) {
					if (!$page["template"]) {
						$link = Link::iplDecode($page["external"]);
					} else {
						$link = WWW_ROOT.$page["path"].(($page["id"] > 0) ? "/" : ""); // Fix sitemap adding trailing slashes to home
					}

					$response .= "<url><loc>".$link."</loc></url>\n";

					// Added routed template support
					$module_class = SQL::fetchSingle("SELECT bigtree_modules.class
													  FROM bigtree_templates JOIN bigtree_modules 
													  ON bigtree_modules.id = bigtree_templates.module
													  WHERE bigtree_templates.id = ?", $page["template"]);

					if ($module_class) {
						$module = new $module_class;
						if (method_exists($module, "getSitemap")) {
							$subnav = $module->getSitemap($page);
							foreach ($subnav as $entry) {
								$response .= "<url><loc>".$entry["link"]."</loc></url>\n";
							}
						}
					}
				}
			}

			$response .= '</urlset>';

			return $response;
		}

		/*
			Function: pingSearchEngines
				Sends the latest sitemap.xml out to search engine ping services if enabled in settings.
		*/

		static function pingSearchEngines() {
			$setting = Setting::value("ping-search-engines");

			if ($setting == "on") {
				// Google
				BigTree::cURL("http://www.google.com/webmasters/tools/ping?sitemap=".urlencode(WWW_ROOT."sitemap.xml"));
				// Bing
				BigTree::cURL("http://www.bing.com/webmaster/ping.aspx?siteMap=".urlencode(WWW_ROOT."sitemap.xml"));
			}
		}
	}
