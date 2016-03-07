<?php
	/*
		Class: BigTree\Link
			Provides an interface for handling BigTree links.
	*/

	namespace BigTree;

	use BigTreeCMS;

	class Link {

		public static $IRLCache = array();
		public static $IPLCache = array();
		public static $TokenKeys = array();
		public static $TokenValues = array();

		/*
			Function: decode
				Replaces the internal page links in a string with hard links.
			
			Parameters:
				html - An HTML block
			
			Returns:
				An HTML block with links hard-linked.
		*/
		
		static function decode($html) {
			// Save time if there's no content
			if (trim($html) === "") {
				return "";
			}
			
			if (substr($html,0,6) == "ipl://" || substr($html,0,6) == "irl://") {
				$html = static::iplDecode($html);
			} else {
				$html = static::detokenize($html);
				$html = preg_replace_callback('^="(ipl:\/\/[a-zA-Z0-9\_\:\/\.\?\=\-]*)"^',array('BigTree\Link',"decodeHook"),$html);
				$html = preg_replace_callback('^="(irl:\/\/[a-zA-Z0-9\_\:\/\.\?\=\-]*)"^',array('BigTree\Link',"decodeHook"),$html);
			}

			return $html;
		}
		private static function decodeHook($matches) {
			return '="'.static::iplDecode($matches[1]).'"';
		}

		/*
			Function: detokenize
				Replaces all root tokens in a URL (i.e. {wwwroot}) with hard links.

			Parameters:
				string - A string with root tokens.

			Returns:
				A string with hard links.
		*/

		static function detokenize($string) {
			return str_replace(array("{adminroot}","{wwwroot}","{staticroot}"),array(ADMIN_ROOT,WWW_ROOT,STATIC_ROOT),$string);
		}

		/*
			Function: encode
				Converts links in a string into internal page links.

			Parameters:
				string - A string of contents that may contain URLs

			Returns:
				A string with hard links converted into internal page links.
		*/

		static function encode($string) {
			// If this string is actually just a URL, IPL it.
			if ((substr($string,0,7) == "http://" || substr($string,0,8) == "https://") && strpos($string,"\n") === false && strpos($string,"\r") === false) {
				$string = static::iplEncode($string);
			// Otherwise, switch all the image srcs and javascripts srcs and whatnot to {wwwroot}.
			} else {
				$string = preg_replace_callback('/href="([^"]*)"/',array('BigTree\Link',"encodeHref"),$string);
				$string = preg_replace_callback('/src="([^"]*)"/',array('BigTree\Link',"encodeSrc"),$string);
				$string = static::tokenize($string);
			}
			return $string;
		}
		
		private static function encodeHref($matches) {
			$href = static::iplEncode(static::detokenize($matches[1]));
			return 'href="'.$href.'"';
		}

		private static function encodeSrc($matches) {
			$src = static::iplEncode(static::detokenize($matches[1]));
			return 'src="'.$src.'"';
		}

		/*
			Function: get
				Returns the public link to a page in the database.
			
			Parameters:
				id - The ID of the page.
			
			Returns:
				Public facing URL.
		*/
		
		static function get($id) {
			global $bigtree;

			// Homepage, just return the web root.
			if ($id == 0) {
				return WWW_ROOT;
			}

			// If someone is requesting the link of the page they're already on we don't need to request it from the database.
			if ($bigtree["page"]["id"] == $id) {
				if ($bigtree["config"]["trailing_slash_behavior"] == "none") {
					return WWW_ROOT.$bigtree["page"]["path"];					
				} else {
					return WWW_ROOT.$bigtree["page"]["path"]."/";
				}
			}

			// Otherwise we'll grab the page path from the db.
			$path = BigTreeCMS::$DB->fetchSingle("SELECT path FROM bigtree_pages WHERE archived != 'on' AND id = ?",$id);
			if ($path) {
				if ($bigtree["config"]["trailing_slash_behavior"] == "none") {
					return WWW_ROOT.$path;
				} else {
					return WWW_ROOT.$path."/";
				}
			}
			return false;
		}

		/*
			Function: iplDecode
				Returns a hard link to the page's publicly accessible URL from its encoded soft link URL.
			
			Parameters:
				ipl - Internal Page Link (ipl://, irl://, {wwwroot}, or regular URL encoding)
			
			Returns:
				Public facing URL.
		*/
		
		static function iplDecode($ipl) {
			global $bigtree;

			// Regular links
			if (substr($ipl,0,6) != "ipl://" && substr($ipl,0,6) != "irl://") {
				return static::detokenize($ipl);
			}
			$ipl = explode("//",$ipl);
			$navid = $ipl[1];

			// Resource Links
			if ($ipl[0] == "irl:") {
				// See if it's in the cache.
				if (isset(static::$IRLCache[$navid])) {
					if ($ipl[2]) {
						return BigTree::prefixFile(static::$IRLCache[$navid],$ipl[2]);
					} else {
						return static::$IRLCache[$navid];
					}
				} else {
					$resource = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_resources WHERE id = ?",$navid);
					$file = $resource ? static::detokenize($resource["file"]) : false;
					static::$IRLCache[$navid] = $file;
					if ($ipl[2]) {
						return BigTree::prefixFile($file,$ipl[2]);
					} else {
						return $file;
					}
				}
			}
			
			// New IPLs are encoded in JSON
			$c = json_decode(base64_decode($ipl[2]));
			
			// If it can't be rectified, we still don't want a warning.
			if (is_array($c) && count($c)) {
				$last = end($c);
				$commands = implode("/",$c);
				if (strpos($last,"#") === false && strpos($last,"?") === false) {
					$commands .= "/";
				}
			} else {
				$commands = "";
			}

			// See if it's in the cache.
			if (isset(static::$IPLCache[$navid])) {
				if ($bigtree["config"]["trailing_slash_behavior"] != "none" || $commands != "") {
					return static::$IPLCache[$navid]."/".$commands;
				} else {
					return static::$IPLCache[$navid];
				}
			} else {
				// Get the page's path
				$path = BigTreeCMS::$DB->fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?",$navid);

				// Set the cache
				static::$IPLCache[$navid] = WWW_ROOT.$path;

				if (!empty($bigtree["config"]["trailing_slash_behavior"]) && $bigtree["config"]["trailing_slash_behavior"] != "none" || $commands != "") {
					return WWW_ROOT.$path."/".$commands;
				} else {
					return WWW_ROOT.$path;
				}
			}
		}

		/*
			Function: iplEncode
				Creates an internal page link out of a URL.

			Parameters:
				url - A URL

			Returns:
				An internal page link (if possible) or just the same URL (if it's not internal).
		*/

		static function iplEncode($url) {
			// See if this is a file
			$local_path = str_replace(WWW_ROOT,SITE_ROOT,$url);
			if ((substr($local_path,0,1) == "/" || substr($local_path,0,2) == "\\\\") && file_exists($local_path)) {
				return static::tokenize($url);
			}

			$command = explode("/",rtrim(str_replace(WWW_ROOT,"",$url),"/"));
			
			// Check for resource link
			if ($command[0] == "files" && $command[1] == "resources") {
				$resource = BigTree\Resource::file($url);
				if ($resource) {
					BigTree\Resource::$CreationLog[] = $resource["id"];
					return "irl://".$resource["id"]."//".$resource["prefix"];
				}
			}

			// Check for page link
			list($navid,$commands) = BigTreeAdmin::getPageIDForPath($command);
			if (!$navid) {
				return static::tokenize($url);
			}

			return "ipl://".$navid."//".base64_encode(json_encode($commands));
		}

		/*
			Function: iplExists
				Determines whether an internal page link still exists or not.

			Parameters:
				ipl - An internal page link

			Returns:
				True if it is still a valid link, otherwise false.
		*/

		static function iplExists($ipl) {
			$ipl = explode("//",$ipl);

			// See if the page it references still exists.
			$nav_id = $ipl[1];
			if (!BigTreeCMS::$DB->exists("bigtree_pages",$nav_id)) {
				return false;
			}

			// Decode the commands attached to the page
			$commands = json_decode(base64_decode($ipl[2]),true);
			// If there are no commands, we're good.
			if (empty($commands[0])) {
				return true;
			}
			// If it's a hash tag link, we're also good.
			if (substr($commands[0],0,1) == "#") {
				return true;
			}
			// Get template for the navigation id to see if it's a routed template
			$routed = BigTreeCMS::$DB->fetchSingle("SELECT bigtree_templates.routed FROM bigtree_templates JOIN bigtree_pages 
													ON bigtree_templates.id = bigtree_pages.template 
													WHERE bigtree_pages.id = ?", $nav_id);
			// If we're a routed template, we're good.
			if ($routed) {
				return true;
			}

			// We may have been on a page, but there's extra routes that don't go anywhere or do anything so it's a 404.
			return false;
		}

		/*
			Function: irlExists
				Determines whether an internal resource link still exists or not.

			Parameters:
				irl - An internal resource link

			Returns:
				True if it is still a valid link, otherwise false.
		*/

		static function irlExists($irl) {
			$irl = explode("//",$irl);
			return BigTree\Resource::get($irl[1]) ? true : false;
		}

		/*
			Function: tokenize
				Replaces all hard roots in a URL with tokens (i.e. {wwwroot}).

			Parameters:
				string - A string with hard roots.

			Returns:
				A string with tokens.
		*/

		static function tokenize($string) {
			// Figure out what roots we can replace
			if (!count(static::$TokenKeys)) {
				if (substr(ADMIN_ROOT,0,7) == "http://" || substr(ADMIN_ROOT,0,8) == "https://") {
					static::$TokenKeys[] = ADMIN_ROOT;
					static::$TokenValues[] = "{adminroot}";
				}
				if (substr(STATIC_ROOT,0,7) == "http://" || substr(STATIC_ROOT,0,8) == "https://") {
					static::$TokenKeys[] = STATIC_ROOT;
					static::$TokenValues[] = "{staticroot}";
				}
				if (substr(WWW_ROOT,0,7) == "http://" || substr(WWW_ROOT,0,8) == "https://") {
					static::$TokenKeys[] = WWW_ROOT;
					static::$TokenValues[] = "{wwwroot}";
				}
			}
			return str_replace(static::$TokenKeys,static::$TokenValues,$string);
		}
	}
