<?php
	/*
		Class: BigTree\Text
			Provides an interface for manipulating text.
	*/
	
	namespace BigTree;
	
	class Text
	{
		
		public static $Language = [];
		
		/*
			Function: getRandomString
				Returns a random string.
			
			Parameters:
				length - The number of characters to return.
				types - The set of characters to use ("alpha" for lowercase letters, "numeric" for numbers, "alphanum" for uppercase letters and numbers, "hexidec" for hexidecimal)
			
			Returns:
				A random string.
		*/
		
		public static function getRandomString(int $length = 8, string $type = "alphanum"): string
		{
			// Character sets
			$types = [
				"alpha" => "abcdefghijklmnopqrstuvwqyz",
				"numeric" => "0123456789",
				"alphanum" => "ABCDEFGHJKLMNPQRTUVWXY0123456789",
				"hexidec" => "0123456789abcdef"
			];
			
			$character_set = $types[$type];
			
			// Seed the random number generator
			list($usec, $sec) = explode(' ', microtime());
			mt_srand((float) $sec + ((float) $usec * 100000));
			
			// Generate
			$string = "";
			$character_set_length = strlen($character_set) - 1;
			
			for ($i = 0; $i < $length; $i++) {
				$string .= $character_set[mt_rand(0, $character_set_length)];
			}
			
			return $string;
		}
		
		/*
			Function: htmlEncode
				Modifies a string so that it is safe for display on the web (tags and quotes modified for usage inside attributes) without double-encoding.
				Ensures that other html entities (like &hellip;) turn into UTF-8 characters before encoding.
				Only to be used when your website's character set is UTF-8.

			Parameters:
				string - String to encode

			Returns:
				Encoded string.
		*/
		
		public static function htmlEncode(?string $string): ?string
		{
			if (is_null($string)) {
				return null;
			}
			
			return htmlspecialchars(html_entity_decode($string, ENT_COMPAT, "UTF-8"));
		}
		
		/*
			Function: replaceServerRoot
				Replaces the server root in a string (as long as it is at the beginning of the string)

			Parameters:
				string - String to modify
				replace - Replacement string for SERVER_ROOT

			Returns:
				A string.
		*/
		
		public static function replaceServerRoot(string $string, string $replace = ""): string
		{
			if (strpos($string, SERVER_ROOT) === 0) {
				return $replace.substr($string, strlen(SERVER_ROOT));
			}
			
			return $string;
		}
		
		/*
			Function: setLanguage
				Sets the language pack for drawing text strings via the Text::draw method.

			Parameters:
				An array of key/value pairs of the english text and the translation.
		*/
		
		public static function setLanguage(string $language): void
		{
			static::$Language = $language;
		}
		
		/*
			Function: translate
				Returns a translated string if a translation text is found in the Text::$Language array or the passed in string if no match is found.

			Parameters:
				string - A text string.
				html_encode - Whether to return an encoded string (safer for things inside an attribute, defaults to false)
		*/
		
		public static function translate(string $string, bool $html_encode = false, array $replacements = []): string
		{
			$string = isset(static::$Language[$string]) ? static::$Language[$string] : $string;
			
			// Allow for wildcard replacements where language isn't an issue
			if (count($replacements)) {
				$string = strtr($string, $replacements);
			}
			
			return $html_encode ? static::htmlEncode($string) : $string;
		}
		
		/*
			Function: trimLength
				Trims text with HTML tags to a given length (ignoring tag characters in length calculation).
			
			Parameters:
				string - A string of text or HTML.
				length - The number of characters to trim to.
			
			Returns:
				A string trimmed to the proper number of characters.
		*/
		
		public static function trimLength(string $string, int $length): string
		{
			$ns = "";
			$opentags = [];
			$string = trim($string);
			
			if (strlen(html_entity_decode(strip_tags($string))) < $length) {
				return $string;
			}
			
			if (strpos($string, " ") === false && strlen(html_entity_decode(strip_tags($string))) > $length) {
				return substr($string, 0, $length)."&hellip;";
			}
			
			$x = 0;
			$z = 0;
			
			while ($z < $length && $x <= strlen($string)) {
				$char = substr($string, $x, 1);
				$ns .= $char;		// Add the character to the new string.
				
				if ($char == "<") {
					// Get the full tag -- but compensate for bad html to prevent endless loops.
					$tag = "";
					
					while ($char != ">" && $char !== false) {
						$x++;
						$char = substr($string, $x, 1);
						$tag .= $char;
					}
					
					$ns .= $tag;
					$tagexp = explode(" ", trim($tag));
					$tagname = str_replace(">", "", $tagexp[0]);
					
					// If it's a self contained <br /> tag or similar, don't add it to open tags.
					if ($tagexp[1] != "/" && $tagexp[1] != "/>") {
						// See if we're opening or closing a tag.
						if (substr($tagname, 0, 1) == "/") {
							$tagname = str_replace("/", "", $tagname);
							
							// We're closing the tag. Kill the most recently opened aspect of the tag.
							$done = false;
							reset($opentags);
							
							while (current($opentags) && !$done) {
								if (current($opentags) == $tagname) {
									unset($opentags[key($opentags)]);
									$done = true;
								}
								
								next($opentags);
							}
						} else {
							// Open a new tag.
							$opentags[] = $tagname;
						}
					}
				} elseif ($char == "&") {
					$entity = "";
					
					while ($char != ";" && $char != " " && $char != "<") {
						$x++;
						$char = substr($string, $x, 1);
						$entity .= $char;
					}
					
					if ($char == ";") {
						$z++;
						$ns .= $entity;
					} elseif ($char == " ") {
						$z += strlen($entity);
						$ns .= $entity;
					} else {
						$z += strlen($entity);
						$ns .= substr($entity, 0, -1);
						$x -= 2;
					}
				} else {
					$z++;
				}
				
				$x++;
			}
			
			while ($x < strlen($string) && !in_array(substr($string, $x, 1), [" ", "!", ".", ",", "<", "&"])) {
				$ns .= substr($string, $x, 1);
				$x++;
			}
			
			if (strlen(strip_tags($ns)) < strlen(strip_tags($string))) {
				$ns .= "&hellip;";
			}
			
			$opentags = array_reverse($opentags);
			
			foreach ($opentags as $key => $val) {
				$ns .= "</".$val.">";
			}
			
			return $ns;
		}
		
		/*
			Function: versionToDecimal
				Returns a decimal number of a BigTree version for numeric comparisons.

			Parameters:
				version - BigTree version number (i.e. 4.2.0)

			Returns:
				A number
		*/
		
		public static function versionToDecimal(string $version): int
		{
			$pieces = explode(".", $version);
			$number = $pieces[0] * 10000;
			
			if (isset($pieces[1])) {
				$number += $pieces[1] * 100;
			}
			
			if (isset($pieces[2])) {
				$number += $pieces[2];
			}
			
			return $number;
		}
		
	}
