<?php
	/*
		Class: Vue
			A class used to manipulate .vue files into cached CSS and JS.
	*/
	
	namespace BigTree;
	
	class Vue
	{
		
		private static $ScopedClasses = [];
		
		public static function buildCache(): void
		{
			// Get core and custom vue files
			if (file_exists(SERVER_ROOT."custom/admin/vue/")) {
				$custom = FileSystem::getDirectoryContents(SERVER_ROOT."custom/admin/vue/", true, "vue");
			} else {
				$custom = [];
			}
			
			$core = FileSystem::getDirectoryContents(SERVER_ROOT."core/admin/vue/", true, "vue");
			
			foreach ($core as $index => $file) {
				$custom_path = str_replace(SERVER_ROOT."core/", SERVER_ROOT."custom/", $file);
				
				if (in_array($custom_path, $custom)) {
					unset($core[$index]);
				}
			}
			
			$files_to_parse = array_merge($core, $custom);
			
			// Gather any extension vue files
			$extensions = DB::getAll("extensions");
			
			foreach ($extensions as $extension) {
				if (file_exists(SERVER_ROOT."extensions/".$extension["id"]."/vue/")) {
					$extension_files = FileSystem::getDirectoryContents(SERVER_ROOT."extensions/".$extension["id"]."/vue/", true, "vue");
					$files_to_parse = array_merge($files_to_parse, $extension_files);
				}
			}
			
			$js_string = "";
			$css_string = "";
			
			foreach ($files_to_parse as $file) {
				$parsed = static::parseFile($file);
				
				if (trim($parsed["css"])) {
					$css_string .= $parsed["css"]."\n";
				}
				
				$js_string .= $parsed["js"]."\n";
			}
			
			FileSystem::createFile(SERVER_ROOT."cache/admin/vue.js", $js_string);
			FileSystem::createFile(SERVER_ROOT."cache/admin/vue.css", $css_string);
		}
		
		public static function parseFile(string $file): array
		{
			$dom = new \DOMDocument;
			$dom->loadXML("<root>".file_get_contents($file)."</root>");
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			
			$script = $dom->getElementsByTagName("script");
			$template = $dom->getElementsByTagName("template");
			$style = $dom->getElementsByTagName("style");
			
			$script_content = $script->count() ? trim(static::getDOMNodeContent($script->item(0))) : "";
			$template_content = $template->count() ? trim(static::getDOMNodeContent($template->item(0))) : "";
			$style_content = $style->count() ? static::getDOMNodeContent($style->item(0)) : "";
			
			if (!$script_content) {
				trigger_error("Vue file most contain a Vue.component declaration.", E_USER_ERROR);
			}
			
			if (strpos($script_content, "Vue.component") !== 0) {
				trigger_error("Script content of a .vue file must begin with Vue.component declaration.", E_USER_ERROR);
			}
			
			$scoped_id = null;
			
			if ($style_content) {
				$type_attr = $style->item(0)->attributes->getNamedItem("type");
				$scoped_attr = $style->item(0)->attributes->getNamedItem("scoped");
				
				if ($type_attr) {
					$type = strtolower($type_attr->nodeValue);
				} else {
					$type = "text/css";
				}
				
				if (!is_null($scoped_attr) && strtolower($scoped_attr->nodeValue) != "false") {
					if (!$template_content) {
						trigger_error("You must use a separate &lt;template&gt; definition with scoped styles.", E_USER_ERROR);
					}
					
					if ($type == "text/css") {
						$type = "text/sass";
					}
					
					$scoped_id = uniqid("scoped_");
					
					while (in_array($scoped_id, static::$ScopedClasses)) {
						$scoped_id = uniqid("scoped_");
					}
					
					static::$ScopedClasses[] = $scoped_id;
					
					$style_content = ".$scoped_id {\n".$style_content."\n}";
				}
				
				if ($type == "text/sass" || $type = "text/scss") {
					require_once SERVER_ROOT."vendor/scssphp/scssphp/scss.inc.php";
					$compiler = new \ScssPhp\ScssPhp\Compiler;
					$compiler->setImportPaths(pathinfo($file, PATHINFO_DIRNAME)."/");
					$compiler->setFormatter("\ScssPhp\ScssPhp\Formatter\Crunched");
					
					try {
						$style_content = $compiler->compile($style_content);
					} catch (\Exception $e) {
						trigger_error("Failed to parse SCSS.", E_USER_ERROR);
					}
				} elseif ($type == "text/less") {
					require_once SERVER_ROOT."core/inc/lib/less.php/lib/Less/Autoloader.php";
					\Less_Autoloader::register();
					$less_compiler = new \Less_Parser;
					$less_compiler->parse($style_content);
					$style_content = $less_compiler->getCss();
				} elseif ($type == "text/css") {
					// Nothing to do here
				} else {
					trigger_error("Unknown style type definition: $type. Supported: text/css, text/less, text/sass", E_USER_ERROR);
				}
				
				$style_content = preg_replace('#\s+#', ' ', $style_content);
				$style_content = preg_replace('#/\*.*?\*/#s', '', $style_content);
				$style_content = str_replace("; ", ";", $style_content);
				$style_content = str_replace(": ", ":", $style_content);
				$style_content = str_replace(" {", "{", $style_content);
				$style_content = str_replace("{ ", "{", $style_content);
				$style_content = str_replace(", ", ",", $style_content);
				$style_content = str_replace("} ", "}", $style_content);
				$style_content = str_replace(";}", "}", $style_content);
				$style_content = trim($style_content);
			}
			
			// Append the template
			if ($template_content) {
				// Class the root element
				if ($scoped_id) {
					$first_element = $template->item(0)->getElementsByTagName("*")->item(0);
					
					if ($first_element->getAttribute("class")) {
						trigger_error("Your root element for a scoped component must not have a class name.", E_USER_ERROR);
					}
					
					$first_element->setAttribute("class", $scoped_id);
					$template_content = trim(static::getDOMNodeContent($template->item(0)));
				}
				
				// Escape the template
				$template_content = str_replace("`", "\`", $template_content);
				
				// Add it to the component definition
				$parts = explode("{", $script_content);
				
				if (count($parts) == 1) {
					trigger_error("You must specify an empty object {} for your Vue component's parameters when injecting a template.", E_USER_ERROR);
				}
				
				$parts[1] = "template: `".$template_content."`,".$parts[1];
				$script_content = implode("{", $parts);
				
				// Make sure we didn't add a trailing comma to an empty component definition
				$parts = explode("}", $script_content);
				$last_part = trim($parts[count($parts) - 2]);
				
				if (substr($last_part, -1, 1) == ",") {
					$parts[count($parts) - 2] = substr($last_part, 0, -1);
				}
				
				$script_content = implode("}", $parts);
			}
			
			return ["js" => $script_content, "css" => $style_content];
		}
		
		private static function getDOMNodeContent($element): string
		{
			$string = "";
			$children = $element->childNodes;
			
			foreach ($children as $child) {
				$string .= $element->ownerDocument->saveHTML($child);
			}
			
			return $string;
		}
		
	}