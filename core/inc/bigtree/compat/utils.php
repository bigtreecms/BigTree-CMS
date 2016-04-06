<?php
	use BigTree\FileSystem;

	/*
		Class: BigTree
			A utilities class with many useful functions.
	
	*/
	
	class BigTree {

		// Static properties
		public static $CountryList = array("United States","Afghanistan","Åland Islands","Albania","Algeria","American Samoa","Andorra","Angola","Anguilla","Antarctica","Antigua and Barbuda","Argentina","Armenia","Aruba","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bermuda","Bhutan","Bolivia, Plurinational State of","Bonaire, Sint Eustatius and Saba","Bosnia and Herzegovina","Botswana","Bouvet Island","Brazil","British Indian Ocean Territory","Brunei Darussalam","Bulgaria","Burkina Faso","Burundi","Cambodia","Cameroon","Canada","Cape Verde","Cayman Islands","Central African Republic","Chad","Chile","China","Christmas Island","Cocos (Keeling) Islands","Colombia","Comoros","Congo","Congo, The Democratic Republic of the","Cook Islands","Costa Rica","Côte d'Ivoire","Croatia","Cuba","Curaçao","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea","Estonia","Ethiopia","Falkland Islands (Malvinas)","Faroe Islands","Fiji","Finland","France","French Guiana","French Polynesia","French Southern Territories","Gabon","Gambia","Georgia","Germany","Ghana","Gibraltar","Greece","Greenland","Grenada","Guadeloupe","Guam","Guatemala","Guernsey","Guinea","Guinea-Bissau","Guyana","Haiti","Heard Island and McDonald Islands","Holy See (Vatican City State)","Honduras","Hong Kong","Hungary","Iceland","India","Indonesia","Iran, Islamic Republic of","Iraq","Ireland","Isle of Man","Israel","Italy","Jamaica","Japan","Jersey","Jordan","Kazakhstan","Kenya","Kiribati","Korea, Democratic People's Republic of","Korea, Republic of","Kuwait","Kyrgyzstan","Lao People's Democratic Republic","Latvia","Lebanon","Lesotho","Liberia","Libyan Arab Jamahiriya","Liechtenstein","Lithuania","Luxembourg","Macao","Macedonia, The Former Yugoslav Republic of","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Martinique","Mauritania","Mauritius","Mayotte","Mexico","Micronesia, Federated States of","Moldova, Republic of","Monaco","Mongolia","Montenegro","Montserrat","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Caledonia","New Zealand","Nicaragua","Niger","Nigeria","Niue","Norfolk Island","Northern Mariana Islands","Norway","Occupied Palestinian Territory","Oman","Pakistan","Palau","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Pitcairn","Poland","Portugal","Puerto Rico","Qatar","Réunion","Romania","Russian Federation","Rwanda","Saint Barthélemy","Saint Helena, Ascension and Tristan da Cunha","Saint Kitts and Nevis","Saint Lucia","Saint Martin (French part)","Saint Pierre and Miquelon","Saint Vincent and The Grenadines","Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Sint Maarten (Dutch part)","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Georgia and the South Sandwich Islands","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Svalbard and Jan Mayen","Swaziland","Sweden","Switzerland","Syrian Arab Republic","Taiwan, Province of China","Tajikistan","Tanzania, United Republic of","Thailand","Timor-Leste","Togo","Tokelau","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Turks and Caicos Islands","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States Minor Outlying Islands","Uruguay","Uzbekistan","Vanuatu","Venezuela, Bolivarian Republic of","Viet Nam","Virgin Islands, British","Virgin Islands, U.S.","Wallis and Futuna","Western Sahara","Yemen","Zambia","Zimbabwe");
		public static $CountryListWithAbbreviations = array("AF" => "Afghanistan (‫افغانستان‬‎)","AX" => "Åland Islands (Åland)","AL" => "Albania (Shqipëri)","DZ" => "Algeria (‫الجزائر‬‎)","AS" => "American Samoa","AD" => "Andorra","AO" => "Angola","AI" => "Anguilla","AQ" => "Antarctica","AG" => "Antigua and Barbuda","AR" => "Argentina","AM" => "Armenia (Հայաստան)","AW" => "Aruba","AC" => "Ascension Island","AU" => "Australia","AT" => "Austria (Österreich)","AZ" => "Azerbaijan (Azərbaycan)","BS" => "Bahamas","BH" => "Bahrain (‫البحرين‬‎)","BD" => "Bangladesh (বাংলাদেশ)","BB" => "Barbados","BY" => "Belarus (Беларусь)","BE" => "Belgium (België)","BZ" => "Belize","BJ" => "Benin (Bénin)","BM" => "Bermuda","BT" => "Bhutan (འབྲུག)","BO" => "Bolivia","BA" => "Bosnia and Herzegovina (Босна и Херцеговина)","BW" => "Botswana","BV" => "Bouvet Island","BR" => "Brazil (Brasil)","IO" => "British Indian Ocean Territory","VG" => "British Virgin Islands","BN" => "Brunei","BG" => "Bulgaria (България)","BF" => "Burkina Faso","BI" => "Burundi (Uburundi)","KH" => "Cambodia (កម្ពុជា)","CM" => "Cameroon (Cameroun)","CA" => "Canada","IC" => "Canary Islands (islas Canarias)","CV" => "Cape Verde (Kabu Verdi)","BQ" => "Caribbean Netherlands","KY" => "Cayman Islands","CF" => "Central African Republic (République centrafricaine)","EA" => "Ceuta and Melilla (Ceuta y Melilla)","TD" => "Chad (Tchad)","CL" => "Chile","CN" => "China (中国)","CX" => "Christmas Island","CP" => "Clipperton Island","CC" => "Cocos (Keeling) Islands (Kepulauan Cocos (Keeling))","CO" => "Colombia","KM" => "Comoros (‫جزر القمر‬‎)","CD" => "Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)","CG" => "Congo (Republic) (Congo-Brazzaville)","CK" => "Cook Islands","CR" => "Costa Rica","CI" => "Côte d’Ivoire","HR" => "Croatia (Hrvatska)","CU" => "Cuba","CW" => "Curaçao","CY" => "Cyprus (Κύπρος)","CZ" => "Czech Republic (Česká republika)","DK" => "Denmark (Danmark)","DG" => "Diego Garcia","DJ" => "Djibouti","DM" => "Dominica","DO" => "Dominican Republic (República Dominicana)","EC" => "Ecuador","EG" => "Egypt (‫مصر‬‎)","SV" => "El Salvador","GQ" => "Equatorial Guinea (Guinea Ecuatorial)","ER" => "Eritrea","EE" => "Estonia (Eesti)","ET" => "Ethiopia","FK" => "Falkland Islands (Islas Malvinas)","FO" => "Faroe Islands (Føroyar)","FJ" => "Fiji","FI" => "Finland (Suomi)","FR" => "France","GF" => "French Guiana (Guyane française)","PF" => "French Polynesia (Polynésie française)","TF" => "French Southern Territories (Terres australes françaises)","GA" => "Gabon","GM" => "Gambia","GE" => "Georgia (საქართველო)","DE" => "Germany (Deutschland)","GH" => "Ghana (Gaana)","GI" => "Gibraltar","GR" => "Greece (Ελλάδα)","GL" => "Greenland (Kalaallit Nunaat)","GD" => "Grenada","GP" => "Guadeloupe","GU" => "Guam","GT" => "Guatemala","GG" => "Guernsey","GN" => "Guinea (Guinée)","GW" => "Guinea-Bissau (Guiné Bissau)","GY" => "Guyana","HT" => "Haiti","HM" => "Heard & McDonald Islands","HN" => "Honduras","HK" => "Hong Kong (香港)","HU" => "Hungary (Magyarország)","IS" => "Iceland (Ísland)","IN" => "India (भारत)","ID" => "Indonesia","IR" => "Iran (‫ایران‬‎)","IQ" => "Iraq (‫العراق‬‎)","IE" => "Ireland","IM" => "Isle of Man","IL" => "Israel (‫ישראל‬‎)","IT" => "Italy (Italia)","JM" => "Jamaica","JP" => "Japan (日本)","JE" => "Jersey","JO" => "Jordan (‫الأردن‬‎)","KZ" => "Kazakhstan (Казахстан)","KE" => "Kenya","KI" => "Kiribati","XK" => "Kosovo (Kosovë)","KW" => "Kuwait (‫الكويت‬‎)","KG" => "Kyrgyzstan (Кыргызстан)","LA" => "Laos (ລາວ)","LV" => "Latvia (Latvija)","LB" => "Lebanon (‫لبنان‬‎)","LS" => "Lesotho","LR" => "Liberia","LY" => "Libya (‫ليبيا‬‎)","LI" => "Liechtenstein","LT" => "Lithuania (Lietuva)","LU" => "Luxembourg","MO" => "Macau (澳門)","MK" => "Macedonia (FYROM) (Македонија)","MG" => "Madagascar (Madagasikara)","MW" => "Malawi","MY" => "Malaysia","MV" => "Maldives","ML" => "Mali","MT" => "Malta","MH" => "Marshall Islands","MQ" => "Martinique","MR" => "Mauritania (‫موريتانيا‬‎)","MU" => "Mauritius (Moris)","YT" => "Mayotte","MX" => "Mexico (México)","FM" => "Micronesia","MD" => "Moldova (Republica Moldova)","MC" => "Monaco","MN" => "Mongolia (Монгол)","ME" => "Montenegro (Crna Gora)","MS" => "Montserrat","MA" => "Morocco (‫المغرب‬‎)","MZ" => "Mozambique (Moçambique)","MM" => "Myanmar (Burma) (မြန်မာ)","NA" => "Namibia (Namibië)","NR" => "Nauru","NP" => "Nepal (नेपाल)","NL" => "Netherlands (Nederland)","NC" => "New Caledonia (Nouvelle-Calédonie)","NZ" => "New Zealand","NI" => "Nicaragua","NE" => "Niger (Nijar)","NG" => "Nigeria","NU" => "Niue","NF" => "Norfolk Island","MP" => "Northern Mariana Islands","KP" => "North Korea (조선 민주주의 인민 공화국)","NO" => "Norway (Norge)","OM" => "Oman (‫عُمان‬‎)","PK" => "Pakistan (‫پاکستان‬‎)","PW" => "Palau","PS" => "Palestine (‫فلسطين‬‎)","PA" => "Panama (Panamá)","PG" => "Papua New Guinea","PY" => "Paraguay","PE" => "Peru (Perú)","PH" => "Philippines","PN" => "Pitcairn Islands","PL" => "Poland (Polska)","PT" => "Portugal","PR" => "Puerto Rico","QA" => "Qatar (‫قطر‬‎)","RE" => "Réunion (La Réunion)","RO" => "Romania (România)","RU" => "Russia (Россия)","RW" => "Rwanda","BL" => "Saint Barthélemy (Saint-Barthélemy)","SH" => "Saint Helena","KN" => "Saint Kitts and Nevis","LC" => "Saint Lucia","MF" => "Saint Martin (Saint-Martin (partie française))","PM" => "Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)","WS" => "Samoa","SM" => "San Marino","ST" => "São Tomé and Príncipe (São Tomé e Príncipe)","SA" => "Saudi Arabia (‫المملكة العربية السعودية‬‎)","SN" => "Senegal (Sénégal)","RS" => "Serbia (Србија)","SC" => "Seychelles","SL" => "Sierra Leone","SG" => "Singapore","SX" => "Sint Maarten","SK" => "Slovakia (Slovensko)","SI" => "Slovenia (Slovenija)","SB" => "Solomon Islands","SO" => "Somalia (Soomaaliya)","ZA" => "South Africa","GS" => "South Georgia & South Sandwich Islands","KR" => "South Korea (대한민국)","SS" => "South Sudan (‫جنوب السودان‬‎)","ES" => "Spain (España)","LK" => "Sri Lanka (ශ්‍රී ලංකාව)","VC" => "St. Vincent & Grenadines","SD" => "Sudan (‫السودان‬‎)","SR" => "Suriname","SJ" => "Svalbard and Jan Mayen (Svalbard og Jan Mayen)","SZ" => "Swaziland","SE" => "Sweden (Sverige)","CH" => "Switzerland (Schweiz)","SY" => "Syria (‫سوريا‬‎)","TW" => "Taiwan (台灣)","TJ" => "Tajikistan","TZ" => "Tanzania","TH" => "Thailand (ไทย)","TL" => "Timor-Leste","TG" => "Togo","TK" => "Tokelau","TO" => "Tonga","TT" => "Trinidad and Tobago","TA" => "Tristan da Cunha","TN" => "Tunisia (‫تونس‬‎)","TR" => "Turkey (Türkiye)","TM" => "Turkmenistan","TC" => "Turks and Caicos Islands","TV" => "Tuvalu","UM" => "U.S. Outlying Islands","VI" => "U.S. Virgin Islands","UG" => "Uganda","UA" => "Ukraine (Україна)","AE" => "United Arab Emirates (‫الإمارات العربية المتحدة‬‎)","GB" => "United Kingdom","US" => "United States","UY" => "Uruguay","UZ" => "Uzbekistan (Oʻzbekiston)","VU" => "Vanuatu","VA" => "Vatican City (Città del Vaticano)","VE" => "Venezuela","VN" => "Vietnam (Việt Nam)","WF" => "Wallis and Futuna","EH" => "Western Sahara (‫الصحراء الغربية‬‎)","YE" => "Yemen (‫اليمن‬‎)","ZM" => "Zambia","ZW" => "Zimbabwe");
		public static $JSONEncoding = false;
		public static $MonthList = array("1" => "January","2" => "February","3" => "March","4" => "April","5" => "May","6" => "June","7" => "July","8" => "August","9" => "September","10" => "October","11" => "November","12" => "December");
		public static $RouteParamNames = array();
		public static $RouteParamNamesPath = array();
		public static $StateList = array('AL'=>"Alabama",'AK'=>"Alaska",'AZ'=>"Arizona",'AR'=>"Arkansas",'CA'=>"California",'CO'=>"Colorado",'CT'=>"Connecticut",'DE'=>"Delaware",'DC'=>"District Of Columbia", 'FL'=>"Florida",'GA'=>"Georgia",'HI'=>"Hawaii",'ID'=>"Idaho",'IL'=>"Illinois",'IN'=>"Indiana",'IA'=>"Iowa",'KS'=>"Kansas",'KY'=>"Kentucky",'LA'=>"Louisiana",'ME'=>"Maine",'MD'=>"Maryland",'MA'=>"Massachusetts",'MI'=>"Michigan",'MN'=>"Minnesota",'MS'=>"Mississippi",'MO'=>"Missouri",'MT'=>"Montana",'NE'=>"Nebraska",'NV'=>"Nevada",'NH'=>"New Hampshire",'NJ'=>"New Jersey",'NM'=>"New Mexico",'NY'=>"New York",'NC'=>"North Carolina",'ND'=>"North Dakota",'OH'=>"Ohio",'OK'=>"Oklahoma",'OR'=>"Oregon",'PA'=>"Pennsylvania",'RI'=>"Rhode Island",'SC'=>"South Carolina",'SD'=>"South Dakota",'TN'=>"Tennessee",'TX'=>"Texas",'UT'=>"Utah",'VT'=>"Vermont",'VA'=>"Virginia",'WA'=>"Washington",'WV'=>"West Virginia",'WI'=>"Wisconsin",'WY'=>"Wyoming");
		public static $SUTestResult = null;
		
		/*
			Function: arrayToXML
				Turns a PHP array into an XML string.
			
			Parameters:
				array - The array to convert.
				tab - Current tab depth (for recursion).
			
			Returns:
				A string of XML.
		*/				
		
		static function arrayToXML($array,$tab = "") {
			$xml = "";
			foreach ($array as $key => $val) {
				if (is_array($val)) {
					$xml .= "$tab<$key>\n".static::arrayToXML($val,"$tab\t")."$tab</$key>\n";
				} else {
					if (strpos($val,">") === false && strpos($val,"<") === false && strpos($val,"&") === false) {
						$xml .= "$tab<$key>$val</$key>\n";
					} else {
						$xml .= "$tab<$key><![CDATA[$val]]></$key>\n";
					}
				}
			}

			// Remove trailing new line
			if ($tab === "") {
				$xml = trim($xml);
			}

			return $xml;
		}

		/*
			Function: arrayValue
				Checks to see if a value is a string. If it is it will be JSON decoded into an array.

			Parameters:
				value - A variable

			Returns:
				An array.
		*/

		static function arrayValue($value) {
			if (is_string($value)) {
				$value = (array)@json_decode($value,true);
			}
			return $value;
		}

		/*
			Function: centerCrop
				Crop from the center of an image to create a new one.
			
			Parameters:
				file - The location of the image to crop.
				newfile - The location to save the new cropped image.
				cw - The crop width.
				ch - The crop height.
				retina - Whether to try to create a retina crop (2x, defaults false)
				grayscale - Whether to convert to grayscale (defaults false)

			Returns:
				The new file name if successful, false if there was not enough memory available.
		*/
		
		static function centerCrop($file, $newfile, $cw, $ch, $retina = false, $grayscale = false) {
			return BigTree\Image::centerCrop($file, $newfile, $cw, $ch, $retina, $grayscale);
		}

		/*
			Function: classAutoLoader
				Internal function to automatically load module classes as needed.
		*/

		static function classAutoLoader($class) {
			global $bigtree;

			// Known class in the cache file
			if ($path = $bigtree["class_list"][$class]) {
				if (substr($path,0,11) != "extensions/" && substr($path,0,7) != "custom/") {
					$path = static::path($path);
				} else {
					$path = SERVER_ROOT.$path;
				}
				if (file_exists($path)) {
					include_once $path;
					return;
				}

			// Auto loadable via the path
			} elseif (substr($class,0,8) == "BigTree\\") {
				$path = static::path("inc/bigtree/classes/".str_replace("\\","/",substr($class,8)).".php");
				
				if (file_exists($path)) {
					include_once $path;
					return;
				}
			}

			// Clear the module class list just in case we're missing something.
			FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
		}

		/*
			Function: cleanFile
				Makes sure that a file path doesn't contain abusive characters (i.e. ../)

			Parameters:
				file - A file name

			Returns:
				Cleaned up string.
		*/

		static function cleanFile($file) {
			return FileSystem::getSafePath($file);
		}
		
		/*
			Function: colorMesh
				Returns a color a % of the way between two colors.
			
			Parameters:
				first_color - The first color.
				second_color - The second color.
				percentage - The percentage between the first color and the second you want to move.
			
			Returns:
				A hex value color between the first and second colors.
		*/
		
		static function colorMesh($first_color,$second_color,$percentage) {
			$percentage = intval(str_replace("%","",$percentage));
			$first_color = ltrim($first_color,"#");
			$second_color = ltrim($second_color,"#");
		
			// Get the RGB values for the colors
			$fc_r = hexdec(substr($first_color,0,2));
			$fc_g = hexdec(substr($first_color,2,2));
			$fc_b = hexdec(substr($first_color,4,2));
		
			$sc_r = hexdec(substr($second_color,0,2));
			$sc_g = hexdec(substr($second_color,2,2));
			$sc_b = hexdec(substr($second_color,4,2));
		
			$r_diff = ceil(($sc_r - $fc_r) * $percentage / 100);
			$g_diff = ceil(($sc_g - $fc_g) * $percentage / 100);
			$b_diff = ceil(($sc_b - $fc_b) * $percentage / 100);
		
			$new_color = "#".str_pad(dechex($fc_r + $r_diff),2,"0",STR_PAD_LEFT).str_pad(dechex($fc_g + $g_diff),2,"0",STR_PAD_LEFT).str_pad(dechex($fc_b + $b_diff),2,"0",STR_PAD_LEFT);
		
			return strtoupper($new_color);
		}
		
		/*
			Function: copyFile
				Copies a file into a directory, even if that directory doesn't exist yet.
			
			Parameters:
				from - The current location of the file.
				to - The location of the new copy.
			
			Returns:
				true if the copy was successful, false if the directories were not writable.
		*/
		
		static function copyFile($from,$to) {
			return FileSystem::copyFile($from, $to);
		}
		
		/*
			Function: createCrop
				Creates a cropped image from a source image.
			
			Parameters:
				file - The location of the image to crop.
				new_file - The location to save the new cropped image.
				x - The starting x value of the crop.
				y - The starting y value of the crop.
				target_width - The desired width of the new image.
				target_height - The desired height of the new image.
				width - The width to crop from the original image.
				height - The height to crop from the original image.
				retina - Whether to create a retina-style image (2x, lower quality) if able, defaults to false
				grayscale - Whether to make the crop be in grayscale or not, defaults to false

			Returns:
				The new file name if successful, false if there was not enough memory available or an invalid source image was provided.
		*/
		
		static function createCrop($file,$new_file,$x,$y,$target_width,$target_height,$width,$height,$retina = false,$grayscale = false) {
			return BigTree\Image::createCrop($file,$new_file,$x,$y,$target_width,$target_height,$width,$height,$retina,$grayscale);
		}
		
		/*
			Function: createThumbnail
				Creates a thumbnailed image from a source image.
			
			Parameters:
				file - The location of the image to crop.
				new_file - The location to save the new cropped image.
				maxwidth - The maximum width of the new image (0 for no max).
				maxheight - The maximum height of the new image (0 for no max).
				retina - Whether to create a retina-style image (2x, lower quality) if able (defaults to false).
				grayscale - Whether to make the crop be in grayscale or not (defaults to false).
				upscale - If set to true, upscales to the maxwidth / maxheight instead of downscaling (defaults to false, disables retina).

			Returns:
				The new file name if successful, false if there was not enough memory available or an invalid source image was provided.
			
			See Also:
				createUpscaledImage
		*/
		
		static function createThumbnail($file,$new_file,$maxwidth,$maxheight,$retina = false,$grayscale = false,$upscale = false) {
			return BigTree\Image::createThumbnail($file,$new_file,$max_width,$max_height,$retina,$grayscale,$upscale);
		}

		/*
			Function: createUpscaledImage
				Creates a upscaled image from a source image.
			
			Parameters:
				file - The location of the image to crop.
				new_file - The location to save the new cropped image.
				min_width - The minimum width of the new image (0 for no min).
				min_height - The minimum height of the new image (0 for no min).
			
			Returns:
				The new file name if successful, false if there was not enough memory available or an invalid source image was provided.

			See Also:
				createThumbnail
		*/
		
		static function createUpscaledImage($file,$new_file,$min_width,$min_height) {
			return static::createThumbnail($file,$new_file,$min_width,$min_height,false,false,true);
		}
		
		/*
			Function: cURL
				Posts to a given URL and returns the response.
				Wrapper for cURL.
			
			Parameters:
				url - The URL to retrieve / POST to.
				post - A key/value pair array of things to POST (optional).
				options - A key/value pair of extra cURL options (optional).
				strict_security - Force SSL verification of the host and peer if true (optional, defaults to false).
				output_file - A file location to dump the output of the request to (optional, replaces return value).
			
			Returns:
				The string response from the URL.
		*/
		
		static function cURL($url,$post = false,$options = array(),$strict_security = false,$output_file = false) {
			global $bigtree;

			// Startup cURL and set the URL
			$ch = curl_init();
			curl_setopt($ch,CURLOPT_URL, $url);

			// Determine whether we're forcing valid SSL on the peer and host
			if (!$strict_security) {
				curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
				curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0); 
			}

			// If we're returning to a file we setup a file pointer rather than waste RAM capturing to a variable
			if ($output_file) {
				$file_pointer = fopen($output_file,"w");
				curl_setopt($ch,CURLOPT_FILE,$file_pointer);
			} else {
				curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
			}

			// Setup post data
			if ($post !== false) {
				// Use cURLFile for any file uploads
				if (function_exists("curl_file_create")) {
					foreach ($post as &$post_field) {
						if (substr($post_field,0,1) == "@" && file_exists(substr($post_field,1))) {
							$post_field = curl_file_create(substr($post_field,1));
						}
					}
					unset($post_field);
				}

				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			}

			// Any additional cURL options
			if (count($options)) {
				foreach ($options as $key => $opt) {
					curl_setopt($ch, $key, $opt);
				}
			}

			$output = curl_exec($ch);
			$bigtree["last_curl_response_code"] = curl_getinfo($ch,CURLINFO_HTTP_CODE);
			curl_close($ch);

			// If we're outputting to a file, close the handle and return nothing
			if ($output_file) {
				fclose($file_pointer);
				return;
			}

			return $output;
		}
		
		/*
			Function: currentURL
				Return the current active URL with correct protocall and port

			Parameters:
				port - Whether to return the port for connections not on port 80 (defaults to false)
		*/

		static function currentURL($port = false) {
			$protocol = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
			if ($_SERVER["SERVER_PORT"] != "80" && $port) {
				return $protocol.$_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
			} else {
				return $protocol.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
			}
		}

		/*
			Function: dateFormat
				Formats a date that originates in the config defined date format into another.

			Parameters:
				date - Date (in any format that strtotime understands or a unix timestamp)
				format - Format (in any format that PHP's date function understands, defaults to Y-m-d H:i:s)

			Returns:
				A date string or false if date parsing failed
		*/

		static function dateFormat($date,$format = "Y-m-d H:i:s") {
			global $bigtree;
			
			$date_object = DateTime::createFromFormat($bigtree["config"]["date_format"],$date);

			// Fallback to SQL standards for handling pre 4.2 values
			if (!$date_object) {
				$date_object = DateTime::createFromFormat("Y-m-d",$date);
			}

			if ($date_object) {
				return $date_object->format($format);
			}
			return false;
		}

		/*
			Function: dateFromOffset
				Returns a formatted date from a date and an offset.
				e.g. "January 1, 2015" and "2 months" returns "2015-03-01 00:00:00"

			Parameters:
				start_date - Date to start at (in any format that strtotime understands or a unix timestamp)
				offset - Offset (in any "relative" PHP time format)
				format - Format for returned date (in any format that PHP's date function understands, defaults to Y-m-d H:i:s)

			Returns:
				A date string

			See Also:
				http://php.net/manual/en/datetime.formats.php (for strtotime formats)
				http://php.net/manual/en/datetime.formats.relative.php (for relative time formats)
				http://php.net/manual/en/function.date.php (for date formats)
		*/

		static function dateFromOffset($start_date,$offset,$format = "Y-m-d H:i:s") {
			$time = is_numeric($start_date) ? $start_date : strtotime($start_date);
			$date = DateTime::createFromFormat("Y-m-d H:i:s",date("Y-m-d H:i:s",$time));
			$date->add(DateInterval::createFromDateString($offset));
			return $date->format($format);
		}
				
		/*
			Function: deleteDirectory
				Deletes a directory including everything in it.
			
			Parameters:
				dir - The directory to delete.

			Returns:
				true if successful
		*/
		
		static function deleteDirectory($dir) {
			return FileSystem::deleteDirectory($dir);
		}

		/*
			Function: deleteFile
				Deletes a file if it exists.

			Parameters:
				file - The file to delete

			Returns:
				true if successful
		*/

		static function deleteFile($file) {
			return FileSystem::deleteFile($file);
		}
		
		/*
			Function: describeTable
				Gives in depth information about a MySQL table's structure and keys.
			
			Parameters:
				table - The table name.
			
			Returns:
				An array of table information.
		*/
		
		static function describeTable($table) {
			return BigTree\SQL::describeTable($table);
		}

		/*
			Function: directoryContents
				Returns a directory's files and subdirectories (with their files) in a flat array with file paths.

			Parameters:
				directory - The directory to search
				recursive - Set to false to not recurse subdirectories (defaults to true).
				extension - Limit the results to a specific file extension (defaults to false).
				include_git - .git and .gitignore will be ignored unless set to true (defaults to false).

			Returns:
				An array of files/folder paths.
				Returns false if the directory cannot be read.
		*/

		static function directoryContents($directory,$recurse = true,$extension = false,$include_git = false) {
			return FileSystem::getDirectoryContents($directory, $recurse, $extension, $include_git);
		}

		/*
			Function: formatBytes
				Formats bytes into larger units to make them more readable.
			
			Parameters:
				size - The number of bytes.
			
			Returns:
				A string with the number of bytes in kilobytes, megabytes, or gigabytes.
		*/
		
		static function formatBytes($size) {
			$units = array(' B', ' KB', ' MB', ' GB', ' TB');
			for ($i = 0; $size >= 1024 && $i < 4; $i++) {
				$size /= 1024;
			}
			return round($size, 2).$units[$i];
		}
		
		/*
			Function: geocodeAddress
				Returns a latitude and longitude for a given address.
				This method is deprecated and exists only for backwards compatibility (BigTreeGeocoding should be used directly).
			
			Parameters:
				address - The address to geocode.
			
			Returns:
				An associative array with "latitude" and "longitude" keys (or false if geocoding failed).
		*/
		
		static function geocodeAddress($address) {
			$geocoder = new BigTreeGeocoding;
			return $geocoder->geocode($address);
		}
		
		/*
			Function: getAvailableFileName
				Gets a web safe available file name in a given directory.
			
			Parameters:
				directory - The destination directory.
				file - The desired file name.
				prefixes - A list of file prefixes that also need to be accounted for when checking file name availability.
			
			Returns:
				An available, web safe file name.
		*/
		
		static function getAvailableFileName($directory,$file,$prefixes = array()) {
			return FileSystem::getAvailableFileName($directory, $file, $prefixes);
		}

		/*
			Function: getCookie
				Gets a cookie set by setCookie and decodes it.

			Parameters:
				id - The id of the set cookie

			Returns:
				Decoded cookie or false if the cookie was not found.
		*/

		static function getCookie($id) {
			if (strpos($id,"[") !== false) {
				$pieces = explode("[",$id);
				$cookie = $_COOKIE;
				foreach ($pieces as $piece) {
					$piece = str_replace("]","",$piece);
					if (isset($cookie[$piece])) {
						$cookie = $cookie[$piece];
					} else {
						return false;
					}
				}
				return json_decode($cookie,true);
			} else {
				if (isset($_COOKIE[$id])) {
					return json_decode($_COOKIE[$id],true);
				} else {
					return false;
				}
			}
		}
		
		/*
			Function: getFieldSelectOptions
				Get the <select> options of all the fields in a table.
			
			Parameters:
				table - The table to draw the fields for.
				default - The currently selected value.
				sorting - Whether to duplicate fields into "ASC" and "DESC" versions.
		*/
		
		static function getFieldSelectOptions($table,$default = "",$sorting = false) {
			$table_description = static::describeTable($table);
			if (!$table_description) {
				echo '<option>ERROR: Table Missing</option>';
				return;
			}
			echo '<option></option>';
			foreach ($table_description["columns"] as $col) {
				if ($sorting) {
					if ($default == $col["name"]." ASC" || $default == "`".$col["name"]."` ASC") {
						echo '<option selected="selected">`'.$col["name"].'` ASC</option>';
					} else {
						echo '<option>`'.$col["name"].'` ASC</option>';
					}
					
					if ($default == $col["name"]." DESC" || $default == "`".$col["name"]."` DESC") {
						echo '<option selected="selected">`'.$col["name"].'` DESC</option>';
					} else {
						echo '<option>`'.$col["name"].'` DESC</option>';
					}
				} else {
					if ($default == $col["name"]) {
						echo '<option selected="selected">'.$col["name"].'</option>';
					} else {
						echo '<option>'.$col["name"].'</option>';
					}
				}
			}
		}
		
		/*
			Function: getTableSelectOptions
				Get the <select> options for all of tables in the database excluding bigtree_ prefixed tables.
			
			Parameters:
				default - The currently selected value.
		*/
		
		static function getTableSelectOptions($default = "") {
			global $bigtree;
			
			$tables = SQL::fetchAllSingle("SHOW TABLES");
			foreach ($tables as $table_name) {
				if (isset($bigtree["config"]["show_all_tables_in_dropdowns"]) || ((substr($table_name,0,8) !== "bigtree_")) || $table_name == $default) {
					if ($default == $table_name) {
						echo '<option selected="selected">'.$table_name.'</option>';
					} else {
						echo '<option>'.$table_name.'</option>';
					}
				}
			}
		}
		
		/*
			Function: getThumbnailSizes
				Returns a list of sizes of an image and the result sizes.
			
			Parameters:
				file - The location of the image to crop.
				maxwidth - The maximum width of the new image (0 for no max).
				maxheight - The maximum height of the new image (0 for no max).
			
			Returns:
				An array with (type,width,height,result width,result height)
		*/
		
		static function getThumbnailSizes($file,$maxwidth,$maxheight) {
			return BigTree\Image::getThumbnailSizes($file,$maxwidth,$maxheight);
		}

		/*
			Function: getUpscaleSizes
				Returns a list of sizes of an image and the result sizes.
			
			Parameters:
				file - The location of the image to crop.
				min_width - The minimum width of the new image (0 for no min).
				min_height - The maximum height of the new image (0 for no min).
			
			Returns:
				An array with (type,width,height,result width,result height)
		*/
		
		static function getUpscaleSizes($file,$min_width,$min_height) {
			return BigTree\Image::getUpscaleSizes($file,$min_width,$min_height);
		}
		
		/*
			Function: globalizeArray
				Globalizes all the keys of an array into global variables without compromising super global ($_) variables.
				Optionally runs a list of functions (passed in after the array) on the data.
			
			Parameters:
				array - An array with key/value pairs.
				functions - Pass in additional arguments to run functions (i.e. "htmlspecialchars") on the data
			
			See Also:
				<globalizeGETVars>
				<globalizePOSTVars>
		*/
		
		static function globalizeArray($array) {
			if (!is_array($array)) {
				return false;
			}

			// We don't want to lose track of our array while globalizing, so we're going to save things into $bigtree
			// Since we're not in the global scope, it doesn't matter that we're junking up $bigtree
			$bigtree = array("functions" => array_slice(func_get_args(),1),"array" => $array);

			foreach ($bigtree["array"] as $bigtree["key"] => $bigtree["val"]) {
				// Prevent messing with super globals
				if (substr($bigtree["key"],0,1) != "_" && !in_array($bigtree["key"],array("admin","bigtree","cms"))) {
					// Fix for PHP 7
					$key = $bigtree["key"];
					global $$key;
										
					if (is_array($bigtree["val"])) {
						$$key = static::globalizeArrayRecursion($bigtree["val"],$bigtree["functions"]);
					} else {
						foreach ($bigtree["functions"] as $bigtree["function"]) {
							// Backwards compatibility with old array passed syntax
							if (is_array($bigtree["function"])) {
								foreach ($bigtree["function"] as $bigtree["f"]) {
									$bigtree["val"] = $bigtree["f"]($bigtree["val"]);
								}
							} else {
								$bigtree["val"] = $bigtree["function"]($bigtree["val"]);
							}
						}
						$$key = $bigtree["val"];
					}
				}
			}
			
			return true;
		}

		/*
			Function: globalizeArrayRecursion
				Used by globalizeArray for recursion.
		*/

		static function globalizeArrayRecursion($data,$functions) {
			foreach ($data as $key => $val) {
				if (is_array($val)) {
					$data[$key] = static::globalizeArrayRecursion($val,$functions);
				} else {
					foreach ($functions as $func) {
						// Backwards compatibility with old array passed syntax
						if (is_array($func)) {
							foreach ($func as $f) {
								$val = $f($val);
							}
						} else {
							$val = $func($val);
						}
					}
					$data[$key] = $val;
				}
			}
			return $data;
		}
		
		/*
			Function: globalizeGETVars
				Globalizes all the $_GET variables without compromising $_ variables.
				Optionally runs a list of functions passed in as arguments on the data.
			
			Parameters:
				functions - Pass in additional arguments to run functions (i.e. "htmlspecialchars") on the data
			
			See Also:
				<globalizeArray>
				<globalizePOSTVars>
				
		*/
		
		static function globalizeGETVars() {
			$args = func_get_args();
			return call_user_func_array("BigTree::globalizeArray",array_merge(array($_GET),$args));
		}
		
		/*
			Function: globalizePOSTVars
				Globalizes all the $_POST variables without compromising $_ variables.
				Optionally runs a list of functions passed in as arguments on the data.
			
			Parameters:
				functions - Pass in additional arguments to run functions (i.e. "htmlspecialchars") on the data
			
			See Also:
				<globalizeArray>
				<globalizeGETVars>
		*/
		
		static function globalizePOSTVars() {
			$args = func_get_args();
			return call_user_func_array("BigTree::globalizeArray",array_merge(array($_POST),$args));
		}
		
		/*
			Function: gravatar
				Returns a properly formatted gravatar url.
			
			Parameters:
				email - User's email address.
				size - Image size; defaults to 56
				default - Default profile image; defaults to BigTree icon
				rating - Defaults to "g" (options include "g", "pg", "r", "x")
		*/
		
		static function gravatar($email,$size = 56,$default = false,$rating = "g") {
			if (!$default) {
				global $bigtree;
				$default = !empty($bigtree["config"]["default_gravatar"]) ? $bigtree["config"]["default_gravatar"] : "https://www.bigtreecms.org/images/bigtree-gravatar.png";
			}
			return "https://secure.gravatar.com/avatar/".md5(strtolower($email))."?s=$size&d=".urlencode($default)."&rating=$rating";
		}
		
		/*
			Function: imageManipulationMemoryAvailable
				Checks whether there is enough memory available to perform an image manipulation.

			Parameters:
				source - The source image file
				width - The width of the new image to be created
				height - The height of the new image to be created

			Returns:
				true if the image can be created, otherwise false.
		*/

		static function imageManipulationMemoryAvailable($source,$width,$height) {
			return BigTree\Image::getMemoryAvailability($source,$width,$height);
		}
		
		/*
			Function: isDirectoryWritable
				Extend's PHP's is_writable to support directories that don't exist yet.
			
			Parameters:
				path - The path to check the writable status of.
			
			Returns:
				true if the directory exists and is writable or could be created, otherwise false.
		*/

		static function isDirectoryWritable($path, $recursion = false) {
			return FileSystem::getDirectoryWritability($path);
		}
		
		/*
			Function: isExternalLink
				Check if URL is external, relative to site root
			
			Parameters:
				url - The URL to test.

			Returns:
				true if link is external
		*/
		
		static function isExternalLink($url) {
			return BigTree\Link::isExternal($url);
		}

		/*
			Function: json
				Encodes a variable as JSON. Uses pretty print if available. Optionally escapes for SQL.

			Parameters:
				var - Variable to JSON encode.
				sql - Whether to SQL escape the JSON (defaults to false).

			Returns:
				A JSON encoded string.
		*/

		static function json($var,$sql = false) {
			// Only run version compare once in case we're encoding a lot of JSON
			if (static::$JSONEncoding === false) {
				if (version_compare(PHP_VERSION,"5.4.0") >= 0) {
					static::$JSONEncoding = 1;
				} else {
					static::$JSONEncoding = 0;
				}
			}

			// Use pretty print if we have PHP 5.4 or higher
			$json = (static::$JSONEncoding) ? json_encode($var,JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES) : json_encode($var);
			// SQL escape if requested
			if ($sql) {
				return SQL::escape($json);
			}
			return $json;
		}

		/*
			Function: jsonExtract
				Returns a JSON string of only the specified columns from each row in a dataset in compact format.

			Parameters:
				data - An array of rows/arrays
				columns - The columns of each row/sub-array to return in JSON
				preserve_keys - Whether to perserve keys (false turns the output into a JSON array, defaults to false)

			Returns:
				A JSON string.
		*/

		static function jsonExtract($data,$columns = array(),$preserve_keys = false) {
			// Only run version compare once in case we're encoding a lot of JSON
			if (static::$JSONEncoding === false) {
				if (version_compare(PHP_VERSION,"5.4.0") >= 0) {
					static::$JSONEncoding = 1;
				} else {
					static::$JSONEncoding = 0;
				}
			}

			$simple_data = array();
			foreach ($data as $key => $val) {
				$row = array();
				foreach ($columns as $column) {
					$row[$column] = $val[$column];
				}
				if ($preserve_keys) {
					$simple_data[$key] = $row;
				} else {
					$simple_data[] = $row;
				}
			}
			return (static::$JSONEncoding) ? json_encode($simple_data,JSON_UNESCAPED_SLASHES) : json_encode($simple_data);
		}
		
		/*
			Function: makeDirectory
				Makes a directory (and all applicable parent directories).
				Sets permissions to 777.
			
			Parameters:
				directory - The full path to the directory to be made.

			Returns:
				true if successful
		*/
		
		static function makeDirectory($directory) {
			return FileSystem::createDirectory($directory);
		}
		
		/*
			Function: moveFile
				Moves a file into a directory, even if that directory doesn't exist yet.
			
			Parameters:
				from - The current location of the file.
				to - The location of the new copy.
			
			Returns:
				true if the move was successful, false if the directories were not writable.
		*/
		
		static function moveFile($from,$to) {
			return FileSystem::moveFile($from, $to);
		}

		/*
			Function: parsedFilesArray
				Parses the $_FILES array and returns an array more like a normal $_POST array.
			
			Parameters:
				part - (Optional) The key of the file tree to return.
			
			Returns:
				A more sensible array, or a piece of that sensible array if "part" is set.
		*/

		static function parsedFilesArray($part = "") {
			$clean = array();
			foreach ($_FILES as $key => $first_level) {
				// Hurray, we have a first level entry, just save it to the clean array.
				if (!is_array($first_level["name"])) {
					$clean[$key] = $first_level;
				} else {
					$clean[$key] = self::parsedFilesArrayLoop($first_level["name"],$first_level["tmp_name"],$first_level["type"],$first_level["error"],$first_level["size"]);
				}
			}
			if ($part) {
				return $clean[$part];
			}
			return $clean;
		}

		/*
			Function: parseFilesArrayLoop
				Private method used by parseFilesArray.
		*/

		private static function parsedFilesArrayLoop($name,$tmp_name,$type,$error,$size) {
			$array = array();
			foreach ($name as $k => $v) {
				if (!is_array($v)) {
					$array[$k]["name"] = $v;
					$array[$k]["tmp_name"] = $tmp_name[$k];
					$array[$k]["type"] = $type[$k];
					$array[$k]["error"] = $error[$k];
					$array[$k]["size"] = $size[$k];
				} else {
					$array[$k] = self::parsedFilesArrayLoop($name[$k],$tmp_name[$k],$type[$k],$error[$k],$size[$k]);
				}
			}
			return $array;
		}
		
		/*
			Function: path
				Get the proper path for a file based on whether a custom override exists.
			
			Parameters:
				file - File path relative to either core/ or custom/
			
			Returns:
				Hard file path to a custom/ (preferred) or core/ file depending on what exists.
		*/
		
		static function path($file) {
			if (file_exists(SERVER_ROOT."custom/".$file)) {
				return SERVER_ROOT."custom/".$file;
			} else {
				return SERVER_ROOT."core/".$file;
			}
		}
		
		/*
			Function: pathInfo
				Wrapper for PHP's pathinfo to make sure it supports returning "filename"
			
			Parameters:
				file - The full file path.
			
			Returns:
				Everything PHP's pathinfo() returns (with "filename" even when PHP doesn't support it).
			
			See Also:
				<http://php.net/manual/en/function.pathinfo.php>
		*/
		
		static function pathInfo($file) {
			return pathinfo($file);
		}

		/*
			Function: phpDateTojQuery
				Converts a PHP date() format to jQuery date picker format.

			Parameters:
				format - PHP date() formatting string

			Returns:
				jQuery date picker formatting string.
		*/

		function phpDateTojQuery($format) {
			$new_format = "";
			for ($i = 0; $i < strlen($format); $i++) {
				$c = substr($format,$i,1);
				// Day with leading zeroes
				if ($c == "d") {
					$new_format .= "dd";
				// Day without leading zeroes
				} elseif ($c == "j") {
					$new_format .= "d";
				// Full day name (i.e. Sunday)
				} elseif ($c == "l") {
					$new_format .= "DD";
				// Numeric day of the year (0-365)
				} elseif ($c == "z") {
					$new_format .= "o";
				// Full month name (i.e. January)
				} elseif ($c == "F") {
					$new_format .= "MM";
				// Month with leading zeroes
				} elseif ($c == "m") {
					$new_format .= "mm";
				// Month without leading zeroes
				} elseif ($c == "n") {
					$new_format .= "m";
				// 4 digit year
				} elseif ($c == "Y") {
					$new_format .= "yy";
				// Many others are the same or not a date format part
				} else {
					$new_format .= $c;
				}
			}
			return $new_format;
		}
		
		/*
			Function: placeholderImage
				Generates placeholder image data.
			
			Parameters:
				width - The width of desired image
				height - The height of desired image
				bg_color - The background color; must be full 6 charachter hex value
				text_color - The text color; must be full 6 charachter hex value
				icon_path - Image to render, disables text rendering, must be gif, jpeg, or png
				text_string - Text to render; overrides default dimension display
				
			Returns:
				Nothing; Renders a placeholder image
		*/
		
		static function placeholderImage($width, $height, $bg_color = false, $text_color = false, $icon_path = false, $text_string = false) {
			BigTree\Image::placeholder($width, $height, $bg_color, $text_color, $icon_path, $text_string);
		}

		/*
			Function: postMaxSize
				Returns in bytes the maximum size of a POST.
		*/

		static function postMaxSize() {
			$post_max_size = ini_get("post_max_size");
			if (!is_integer($post_max_size)) {
				$post_max_size = static::unformatBytes($post_max_size);
			}
			
			return $post_max_size;
		}

		/*
			Function: prefixFile
				Prefixes a file name with a given prefix.
			
			Parameters:
				file - A file name or full file path.
				prefix - The prefix for the file name.
			
			Returns:
				The full path or file name with a prefix appended to the file name.
		*/
		
		static function prefixFile($file,$prefix) {
			return FileSystem::getPrefixedFile($file, $prefix);
		}
		
		/*
			Function: putFile
				Writes data to a file, even if that directory for the file doesn't exist yet.
				Sets the file permissions to 777 if the file did not exist.
			
			Parameters:
				file - The location of the file.
				contents - The data to write.
			
			Returns:
				true if the move was successful, false if the directories were not writable.
		*/
		
		static function putFile($file,$contents) {
			return FileSystem::createFile($file, $contents);
		}

		/*
			Function: randomString
				Returns a random string.
			
			Parameters:
				length - The number of characters to return.
				types - The set of characters to use ("alpha" for lowercase letters, "numeric" for numbers, "alphanum" for uppercase letters and numbers, "hexidec" for hexidecimal)
			
			Returns:
				A random string.
		*/
		
		static function randomString($length = 8, $type = "alphanum") {
			// Character sets
			$types = array(
				"alpha" => "abcdefghijklmnopqrstuvwqyz",
				"numeric" => "0123456789",
				"alphanum" => "ABCDEFGHJKLMNPQRTUVWXY0123456789",
				"hexidec" => "0123456789abcdef"
			);
		
			$character_set = $types[$type];
		
			// Seed the random number generator
			list($usec, $sec) = explode(' ', microtime());
			mt_srand((float) $sec + ((float) $usec * 100000));
		
			// Generate
			$sting = "";
			$character_set_length = strlen($character_set) - 1;
			for ($i = 0; $i < $length; $i++) {
				$string .= $character_set[mt_rand(0,$character_set_length)];
			}

			return $string;
		}
		
		/*
			Function: redirect
				Simple URL redirect via header with proper code #
			
			Parameters:
				url - The URL to redirect to.
				code - The status code of redirect, defaults to normal 302 redirect.
		*/
		
		static function redirect($url, $codes = array("302")) {
			// If we're presently in the admin we don't want to allow the possibility of a redirect outside our site via malicious URLs
			if (defined("BIGTREE_ADMIN_ROUTED")) {
				$pieces = explode("/",$url);
				$bt_domain_pieces = explode("/",DOMAIN);
				if (strtolower($pieces[2]) != strtolower($bt_domain_pieces[2])) {
					return false;
				}
			}
			$status_codes = array(
				"200" => "OK",
				"300" => "Multiple Choices",
				"301" => "Moved Permanently",
				"302" => "Found",
				"304" => "Not Modified",
				"307" => "Temporary Redirect",
				"400" => "Bad Request",
				"401" => "Unauthorized",
				"403" => "Forbidden",
				"404" => "Not Found",
				"410" => "Gone",
				"500" => "Internal Server Error",
				"501" => "Not Implemented",
				"503" => "Service Unavailable",
				"550" => "Permission denied"
			);
			if (!is_array($codes)) {
				$codes = array($codes);
			}
			foreach ($codes as $code) {
				if ($status_codes[$code]) {
					header($_SERVER["SERVER_PROTOCOL"]." $code ".$status_codes[$code]);
				}
			}
			header("Location: $url");
			die();
		}

		/*
			Function: relativeTime
				Turns a timestamp into "… hours ago" formatting.

			Parameters:
				time - A date/time stamp understandable by strtotime

			Returns:
				A string describing how long ago the passed time was.
		*/

		static function relativeTime($time) {
			$minute = 60;
			$hour = 3600;
			$day = 86400;
			$month = 2592000;			
			$delta = strtotime(date('r')) - strtotime($time);
			
			if ($delta < 2 * $minute) {
				return "1 min ago";
			} elseif ($delta < 45 * $minute) {
				$minutes = floor($delta / $minute);
				return  $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
			} elseif ($delta < 24 * $hour) {
				$hours = floor($delta / $hour);
				return $hours == 1 ? "1 hour ago" : "$hours hours ago";
			} elseif ($delta < 30 * $day) {
				$days = floor($delta / $day);
				return  $days == 1 ? "yesterday" : "$days days ago";
			} elseif ($delta < 12 * $month) {
				$months = floor($delta / $day / 30);
				return $months == 1 ? "1 month ago" : "$months months ago";
			} else {
				$years = floor($delta / $day / 365);
				return $years == 1 ? "1 year ago" : "$years years ago";
			}
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

		static function replaceServerRoot($string,$replace = "") {
			if (strpos($string,SERVER_ROOT) === 0) {
				return $replace.substr($string,strlen(SERVER_ROOT));
			}
			return $string;
		}

		/*
			Function: route
				Returns the proper file to include based on existence of subdirectories or .php files with given route names.
				Used by the CMS for routing ajax and modules.

			Parameters:
				directory - Root directory to begin looking in.
				path - An array of routes.

			Returns:
				An array with the first element being the file to include and the second element being an array containing extraneous routes from the end of the path.
		*/

		static function route($directory,$path) {
			$commands = array();
			$inc_file = $directory;
			$inc_dir = $directory;
			$ended = false;
			$found_file = false;
			foreach ($path as $piece) {
				// Prevent path exploitation
				if ($piece == "..") {
					die();
				}
				// We're done, everything is a command now.
				if ($ended) {
					$commands[] = $piece;
				// Keep looking for directories.
				} elseif (is_dir($inc_dir.$piece)) {
					$inc_file .= $piece."/";
					$inc_dir .= $piece."/";
				// File exists, we're ending now.
				} elseif ($piece != "_header" && $piece != "_footer" && file_exists($inc_file.$piece.".php")) {
					$inc_file .= $piece.".php";
					$ended = true;
					$found_file = true;
				// Couldn't find a file or directory.
				} else {
					$commands[] = $piece;
					$ended = true;
				}
			}

			if (!$found_file) {
				// If we have default in the routed directory, use it.
				if (file_exists($inc_dir."default.php")) {
					$inc_file = $inc_dir."default.php";
				// See if we can change the directory name into .php file in case the directory is empty but we have .php
				} elseif (file_exists(rtrim($inc_dir,"/").".php")) {
					$inc_file = rtrim($inc_dir,"/").".php";
				// We couldn't route anywhere apparently.
				} else {
					return array(false,false);
				}
			}
			return array($inc_file,$commands);
		}

		/*
			Function: routeLayouts
				Retrieves a list of route layout files (_header.php and _footer.php) for a given file path.

			Parameters:
				path - A file path

			Returns:
				An array of headers and an array of footers.
		*/

		static function routeLayouts($path) {
			$file_location = ltrim(static::replaceServerRoot($path),"/");
			$include_root = false;
			$pathed_includes = false;
			$headers = $footers = array();

			// Get our path pieces and include roots setup properly
			if (strpos($file_location,"custom/admin/modules/") === 0) {
				$include_root = "admin/modules/";
				$pathed_includes = true;
				$pieces = explode("/",substr($file_location,21));
			} elseif (strpos($file_location,"core/admin/modules/") === 0) {
				$include_root = "admin/modules/";
				$pathed_includes = true;
				$pieces = explode("/",substr($file_location,19));
			} elseif (strpos($file_location,"custom/admin/ajax/")) {
				$include_root = "admin/ajax/";
				$pathed_includes = true;
				$pieces = explode("/",substr($file_location,18));
			} elseif (strpos($file_location,"core/admin/ajax/") === 0) {
				$include_root = "admin/ajax/";
				$pathed_includes = true;
				$pieces = explode("/",substr($file_location,16));
			} elseif (strpos($file_location,"templates/routed/") === 0) {
				$include_root = "templates/routed/";
				$pieces = explode("/",substr($file_location,17));
			} elseif (strpos($file_location,"templates/ajax/") === 0) {
				$include_root = "templates/ajax/";
				$pieces = explode("/",substr($file_location,15));
			} elseif (strpos($file_location,"extensions/") === 0) {
				$pieces = explode("/",$file_location);
				if ($pieces[2] == "templates" && ($pieces[3] == "routed" || $pieces[3] == "ajax")) {
					$include_root = "extensions/".$pieces[1]."/templates/".$pieces[3]."/";
					$pieces = array_slice($pieces,4);
				} elseif ($pieces[2] == "modules") {
					$include_root = "extensions/".$pieces[1]."/modules/";
					$pieces = array_slice($pieces,3);
				} elseif ($pieces[2] == "ajax") {
					$include_root = "extensions/".$pieces[1]."/ajax/";
					$pieces = array_slice($pieces,3);
				}
			}

			// Only certain places include headers and footers
			if ($include_root) {
				$inc_path = "";
				foreach ($pieces as $piece) {
					if (substr($piece,-4,4) != ".php") {
						$inc_path .= $piece."/";
						if ($pathed_includes) {
							$header = static::path($include_root.$inc_path."_header.php");
							$footer = static::path($include_root.$inc_path."_footer.php");
						} else {
							$header = SERVER_ROOT.$include_root.$inc_path."_header.php";
							$footer = SERVER_ROOT.$include_root.$inc_path."_footer.php";
						}
						if (file_exists($header)) {
							$headers[] = $header;
						}
						if (file_exists($footer)) {
							$footers[] = $footer;
						}
					}
				}
			}

			return array($headers,array_reverse($footers));
		}

		/*
			Function: routeRegex
				Helper function for pattern based routing.
		*/ 

		static function routeRegex($path,$pattern) {
			// This method is based almost entirely on the Slim Framework's routing implementation (http://www.slimframework.com/)
			static::$RouteParamNames = array();
			static::$RouteParamNamesPath = array();

			// Convert URL params into regex patterns, construct a regex for this route, init params
			$regex_pattern = preg_replace_callback('#:([\w]+)\+?#',"BigTree::routeRegexCallback",str_replace(')',')?',$pattern));

			if (substr($pattern, -1) === '/') {
				$regex_pattern .= '?';
			}
		
			$regex = '#^'.$regex_pattern.'$#';
		
			// Do the regex match
			if (!preg_match($regex,$path,$values)) {
				return false;
			}

			$params = array();
			foreach (static::$RouteParamNames as $name) {
				if (isset($values[$name])) {
					if (isset(static::$RouteParamNamesPath[$name])) {
						$params[$name] = explode('/', urldecode($values[$name]));
					} else {
						$params[$name] = urldecode($values[$name]);
					}
				}
			}

			return $params;
		}

		/*
			Function: routeRegexCallback
				Regex callback for routeRegex
		*/

		static function routeRegexCallback($match) {
			static::$RouteParamNames[] = $match[1];
			if (substr($match[0], -1) === '+') {
				static::$RouteParamNamesPath[$match[1]] = 1;	
				return '(?P<'.$match[1].'>.+)';
			}
	
			return '(?P<'.$match[1].'>[^/]+)';
		}

		/*
			Function: runningAsSU
				Checks if the current script is running as the owner of the script.
				Useful for determining whether you need to 777 a file you're creating.

			Returns:
				true if PHP is running as the user that owns the file
		*/

		static function runningAsSU() {
			return FileSystem::getRunningAsOwner();
		}

		/*
			Function: runParser
				Evaluates code in a function scope with $item and $value
				Used mostly internally in the admin for parsers.

			Parameters:
				item - Full array of data
				value - The value to be manipulated and returned
				code - The code to be run in eval()

			Returns:
				Modified $value
		*/

		static function runParser($item,$value,$code) {
			eval($code);
			return $value;
		}

		/*
			Function: safeEncode
				Modifies a string so that it is safe for display on the web (tags and quotes modified for usage inside attributes) without double-encoding.
				Ensures that other html entities (like &hellip;) turn into UTF-8 characters before encoding.
				Only to be used when your website's character set is UTF-8.

			Parameters:
				string - String to encode

			Returns:
				Encoded string.
		*/

		static function safeEncode($string) {
			return htmlspecialchars(html_entity_decode($string,ENT_COMPAT,"UTF-8"));
		}
		
		/*
			Function: sendEmail
				Sends an email using htmlMimeMail

			Parameters:
				to - String or array of recipient email address(es)
				subject - Subject line text
				html - HTML Email Body
				text - Text Email Body
				from - From email address
				return - Return email address (if different than from)
				cc - String or array of carbon copy email address(es)
				bcc - String or array of blind carbon copy email address(es)
				headers - Key/value pair array of extra headers

			Returns:
				true if email is sent, otherwise false.
		*/
		
		static function sendEmail($to,$subject,$html,$text = "",$from = false,$return = false,$cc = false,$bcc = false,$headers = array()) {
			$mailer = new PHPMailer;

			foreach ($headers as $key => $val) {
				$mailer->addCustomHeader($key,$val);
			}

			$mailer->Subject = $subject;
			if ($html) {
				$mailer->isHTML(true);
				$mailer->Body = $html;
				$mailer->AltBody = $text;
			} else {
				$mailer->Body = $text;
			}

			if (!$from) {
				$from = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.","",$_SERVER["HTTP_HOST"]) : str_replace(array("http://www.","https://www.","http://","https://"),"",DOMAIN));
				$from_name = "BigTree CMS";
			} else {
				// Parse out from and reply-to names
				$from_name = false;
				$from = trim($from);
				if (strpos($from,"<") !== false && substr($from,-1,1) == ">") {
					$from_pieces = explode("<",$from);
					$from_name = trim($from_pieces[0]);
					$from = substr($from_pieces[1],0,-1);
				}
			}
			$mailer->From = $from;
			$mailer->FromName = $from_name;
			
			if ($return) {
				$return_name = "";
				$return = trim($return);
				if (strpos($return,"<") !== false && substr($return,-1,1) == ">") {
					$return_pieces = explode("<",$return);
					$return_name = trim($return_pieces[0]);
					$return = substr($return_pieces[1],0,-1);
				}
				$mailer->addReplyTo($return,$return_name);
			}
			
			if ($cc) {
				if (is_array($cc)) {
					foreach ($cc as $item) {
						$mailer->addCC($item);
					}
				} else {
					$mailer->addCC($cc);
				}
			}

			if ($bcc) {
				if (is_array($bcc)) {
					foreach ($bcc as $item) {
						$mailer->addBCC($item);
					}
				} else {
					$mailer->addBCC($bcc);
				}
			}

			if (is_array($to)) {
				foreach ($to as $item) {
					$mailer->addAddress($item);
				}
			} else {
				$mailer->addAddress($to);
			}
			
			return $mailer->send();
		}

		/*
			Function: setCookie
				Sets a site-wide cookie with support for arrays.
				Cookies set by setCookie should be retrieved via getCookie (all values are JSON encoded).

			Parameters:
				id - The cookie identifier
				value - The value to set for the cookie
				expiration - Cookie expiration time (in seconds since UNIX epoch) or a string value compatible with strtotime (defaults to session expiration)
		*/

		static function setCookie($id,$value,$expiration = 0) {
			$expiration = is_string($expiration) ? strtotime($expiration) : $expiration;

			$_COOKIE[$id] = json_encode($value);
			setcookie($id,json_encode($value),$expiration,str_replace(DOMAIN,"",WWW_ROOT));
		}

		/*
			Function: setDirectoryPermissions
				Sets writable permissions for a whole directory.
				If the web server is not running as the owner of the current script, permissions will be 777.

			Parameters:
				location - The directory to set permissions on.
		*/

		static function setDirectoryPermissions($location) {
			return FileSystem::setDirectoryPermissions($location);
		}

		/*
			Function: setPermissions
				Checks to see if the current user the web server is running as is the owner of the current script.
				If they are not the same user, the file/directory is given a 777 permission so that the script owner can still manage the file.

			Parameters:
				location - The file or directory to set permissions on.

			Returns:
				true if successful
		*/

		static function setPermissions($location) {
			return FileSystem::setPermissions($location);
		}

		/*
			Function: tableCompare
				Returns a list of SQL commands required to turn one table into another.

			Parameters:
				table_a - The table that is being translated
				table_b - The table that the first table will become

			Returns:
				An array of SQL calls to perform to turn Table A into Table B.
		*/

		static function tableCompare($table_a,$table_b) {
			return SQL::compareTables($table_a,$table_b);
		}

		/*
			Function: tableContents
				Returns an array of INSERT statements for the rows of a given table.
				The INSERT statements will be binary safe with binary columns requested in hex.

			Parameters:
				table - Table to pull data from.

			Returns:
				An array.
		*/

		static function tableContents($table) {
			return SQL::dumpTable($table);
		}

		/*
			Function: tableExists
				Determines whether a SQL table exists.

			Parameters:
				table - The table name.

			Returns:
				true if table exists, otherwise false.
		*/

		static function tableExists($table) {
			return SQL::tableExists($table);
		}

		/*
			Function: touchFile
				touch()s a file even if the directory for it doesn't exist yet.
			
			Parameters:
				file - The file path to touch.
		*/
		
		static function touchFile($file) {
			return FileSystem::touchFile($file);
		}
		
		/*
			Function: translateArray
				Steps through an array and creates internal page links for all parts of it.
			
			Parameters:
				array - The array to process.
			
			Returns:
				An array with internal page links encoded.
			
			See Also:
				<untranslateArray>
		*/
		
		static function translateArray($array) {
			foreach ($array as &$piece) {
				if (is_array($piece)) {
					$piece = static::translateArray($piece);
				} else {
					$piece = BigTreeAdmin::autoIPL($piece);
				}
			}
			return $array;
		}
		
		/*
			Function: trimLength
				A smarter version of trim that works with HTML.
			
			Parameters:
				string - A string of text or HTML.
				length - The number of characters to trim to.
			
			Returns:
				A string trimmed to the proper number of characters.
		*/
		
		static function trimLength($string,$length) {
			$ns = "";
			$opentags = array();
			$string = trim($string);
			if (strlen(html_entity_decode(strip_tags($string))) < $length) {
				return $string;
			}
			if (strpos($string," ") === false && strlen(html_entity_decode(strip_tags($string))) > $length) {
				return substr($string,0,$length)."&hellip;";
			}
			$x = 0;
			$z = 0;
			while ($z < $length && $x <= strlen($string)) {
				$char = substr($string,$x,1);
				$ns .= $char;		// Add the character to the new string.
				if ($char == "<") {
					// Get the full tag -- but compensate for bad html to prevent endless loops.
					$tag = "";
					while ($char != ">"	 && $char !== false) {
						$x++;
						$char = substr($string,$x,1);
						$tag .= $char;
					}
					$ns .= $tag;
		
					$tagexp = explode(" ",trim($tag));
					$tagname = str_replace(">","",$tagexp[0]);
		
					// If it's a self contained <br /> tag or similar, don't add it to open tags.
					if ($tagexp[1] != "/" && $tagexp[1] != "/>") {
						// See if we're opening or closing a tag.
						if (substr($tagname,0,1) == "/") {
							$tagname = str_replace("/","",$tagname);
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
						$char = substr($string,$x,1);
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
						$ns .= substr($entity,0,-1);
						$x -= 2;
					}
				} else {
					$z++;
				}
				$x++;
			}
			while ($x < strlen($string) && !in_array(substr($string,$x,1),array(" ","!",".",",","<","&"))) {
				$ns .= substr($string,$x,1);
				$x++;
			}
			if (strlen(strip_tags($ns)) < strlen(strip_tags($string))) {
				$ns.= "&hellip;";
			}
			$opentags = array_reverse($opentags);
			foreach ($opentags as $key => $val) {
				$ns .= "</".$val.">";
			}
			return $ns;
		}
		
		/*
			Function: unformatBytes
				Formats a string of kilobytes / megabytes / gigabytes back into bytes.
			
			Parameters:
				size - The string of (kilo/mega/giga)bytes.
			
			Returns:
				The number of bytes.
		*/
		
		static function unformatBytes($size) {
			$type = substr($size,-1,1);
			$num = substr($size,0,-1);
			if ($type == "M") {
				return $num * 1048576;
			} elseif ($type == "K") {
				return $num * 1024;
			} elseif ($type == "G") {
				return ($num * 1024 * 1024 * 1024);
			}
			return 0;
		}

		/*
			Function: unsetCookie
				Removes a site-wide cookie set by setCookie.

			Parameters:
				id - The cookie identifier
		*/

		static function unsetCookie($id) {
			setcookie($id,"",strtotime("-1 week"),str_replace(DOMAIN,"",WWW_ROOT));
			unset($_COOKIE[$id]);
		}
		
		/*
			Function: untranslateArray
				Steps through an array and creates hard links for all internal page links.
			
			Parameters:
				array - The array to process.
			
			Returns:
				An array with internal page links decoded.
			
			See Also:
				<translateArray>
		*/
		
		static function untranslateArray($array) {
			foreach ($array as &$piece) {
				if (is_array($piece)) {
					$piece = static::untranslateArray($piece);
				} else {
					$piece = BigTreeCMS::replaceInternalPageLinks($piece);
				}
			}
			return $array;
		}
		
		/*
			Function: unzip
				Unzips a file.
			
			Parameters:
				file - Location of the file to unzip
				destination - The full path to unzip the file's contents to.
		*/
		
		static function unzip($file,$destination) {
			// If we can't write the output directory, we're not getting anywhere.
			if (!FileSystem::getDirectoryWritability($destination)) {
				return false;
			}

			// Up the memory limit for the unzip.
			ini_set("memory_limit","512M");
			
			$destination = rtrim($destination)."/";
			FileSystem::createDirectory($destination);
			
			// If we have the built in ZipArchive extension, use that.
			if (class_exists("ZipArchive")) {
				$z = new ZipArchive;
				
				if (!$z->open($file)) {
					// Bad zip file.
					return false;
				}
				
				for ($i = 0; $i < $z->numFiles; $i++) {
					if (!$info = $z->statIndex($i)) {
						// Unzipping the file failed for some reason.
						return false;
					}
					
					// If it's a directory, ignore it. We'll create them in putFile.
					if (substr($info["name"],-1) == "/") {
						continue;
					}
					
					// Ignore __MACOSX and all it's files.
					if (substr($info["name"],0,9) == "__MACOSX/") {
						continue;
					}

					$content = $z->getFromIndex($i);
					if ($content === false) {
						// File extraction failed.
						return false;
					}
					FileSystem::createFile($destination.$file["name"],$content);
				}
				
				$z->close();
				return true;

			// Fall back on PclZip if we don't have the "native" version.
			} else {
				// WordPress claims this could be an issue, so we'll make sure multibyte encoding isn't overloaded.
				if (ini_get('mbstring.func_overload') && function_exists('mb_internal_encoding')) {
					$previous_encoding = mb_internal_encoding();
					mb_internal_encoding('ISO-8859-1');
				}
				
				$z = new PclZip($file);
				$archive = $z->extract(PCLZIP_OPT_EXTRACT_AS_STRING);

				// If we saved a previous encoding, reset it now.
				if (isset($previous_encoding)) {
					mb_internal_encoding($previous_encoding);
					unset($previous_encoding);
				}
				
				// If it's not an array, it's not a good zip. Also, if it's empty it's not a good zip.
				if (!is_array($archive) || !count($archive)) {
					return false;
				}

				foreach ($archive as $item) {
					// If it's a directory, ignore it. We'll create them in putFile.
					if ($item["folder"]) {
						continue;
					}
					
					// Ignore __MACOSX and all it's files.
					if (substr($item["filename"],0,9) == "__MACOSX/") {
						continue;
					}
					
					FileSystem::createFile($destination.$item["filename"],$item["content"]);
				}
				
				return true;
			}
		}
		
		/*
			Function: uploadMaxFileSize
				Returns Apache's max file size value for use in forms.
		
			Returns:
				The integer value for setting a form's MAX_FILE_SIZE.
		*/
		
		static function uploadMaxFileSize() {
			$upload_max_filesize = ini_get("upload_max_filesize");
			if (!is_integer($upload_max_filesize)) {
				$upload_max_filesize = static::unformatBytes($upload_max_filesize);
			}
			
			$post_max_size = static::postMaxSize();
			if ($post_max_size < $upload_max_filesize) {
				$upload_max_filesize = $post_max_size;
			}
			
			return $upload_max_filesize;
		}

		/*
			Function: urlExists
				Attempts to connect to a URL using cURL.

			Parameters:
				url - The URL to connect to.

			Returns:
				true if it can connect, false if connection failed.
		*/

		static function urlExists($url) {
			return BigTree\Link::urlExists($url);
		}
		
	}

	// For servers that don't have multibyte string extensions…
	if (!function_exists("mb_strlen")) {
		function mb_strlen($string) { return strlen($string); }
	}
	if (!function_exists("mb_strtolower")) {
		function mb_strtolower($string) { return strtolower($string); }
	}