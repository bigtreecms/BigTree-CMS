<?php
	/*
		Class: BigTree\Sitemap
			Provides an interface for handling the BigTree sitemap.
	*/
	
	namespace BigTree;
	
	class Sitemap extends SQLObject
	{
		
		/*
			Function: getXML
				Returns an XML sitemap.
		*/
		
		public static function getXML(): string
		{
			$response = '<?xml version="1.0" encoding="UTF-8" ?>';
			$response .= '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9/" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="https://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';
			
			$pages = SQL::fetchAll("SELECT id, template, external, path FROM bigtree_pages 
									WHERE archived = '' AND (publish_at >= NOW() OR publish_at IS NULL) ORDER BY id ASC");
			
			foreach ($pages as $page) {
				if ($page["template"] || strpos($page["external"], DOMAIN)) {
					if (!$page["template"]) {
						$link = Link::iplDecode($page["external"]);
					} else {
						$link = Link::byPath($page["path"]);
					}
					
					$response .= "<url><loc>".$link."</loc></url>\n";

					// Added routed template support
					$template = DB::get("templates", $page["template"]);
	
					if ($template["module"]) {
						$module = DB::get("modules", $template["module"]);
	
						if ($module && $module["class"]) {
							$mod = new $module["class"];
	
							if (method_exists($mod, "getSitemap")) {
								$subnav = $mod->getSitemap($page);
	
								foreach ($subnav as $s) {
									$links[] = $s["link"];
								}
							}
	
							$mod = $subnav = null;
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
		
		public static function pingSearchEngines(): void
		{
			$setting = Setting::value("ping-search-engines");
			
			if ($setting == "on") {
				// Google
				cURL::request("https://www.google.com/webmasters/tools/ping?sitemap=".urlencode(WWW_ROOT."sitemap.xml"));
				// Bing
				cURL::request("https://www.bing.com/webmaster/ping.aspx?siteMap=".urlencode(WWW_ROOT."sitemap.xml"));
			}
		}
		
	}
