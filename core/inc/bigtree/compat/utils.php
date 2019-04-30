<?php
	
	/*
		Class: BigTree
			A utilities class with many useful functions.
	
	*/
	
	use BigTree\SQL;
	
	class BigTree
	{
		
		// Static properties
		public static $CountryList = ["United States", "Afghanistan", "Åland Islands", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia, Plurinational State of", "Bonaire, Sint Eustatius and Saba", "Bosnia and Herzegovina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo", "Congo, The Democratic Republic of the", "Cook Islands", "Costa Rica", "Côte d'Ivoire", "Croatia", "Cuba", "Curaçao", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guernsey", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard Island and McDonald Islands", "Holy See (Vatican City State)", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran, Islamic Republic of", "Iraq", "Ireland", "Isle of Man", "Israel", "Italy", "Jamaica", "Japan", "Jersey", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, Democratic People's Republic of", "Korea, Republic of", "Kuwait", "Kyrgyzstan", "Lao People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macao", "Macedonia, The Former Yugoslav Republic of", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia, Federated States of", "Moldova, Republic of", "Monaco", "Mongolia", "Montenegro", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", "Norway", "Occupied Palestinian Territory", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Réunion", "Romania", "Russian Federation", "Rwanda", "Saint Barthélemy", "Saint Helena, Ascension and Tristan da Cunha", "Saint Kitts and Nevis", "Saint Lucia", "Saint Martin (French part)", "Saint Pierre and Miquelon", "Saint Vincent and The Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Sint Maarten (Dutch part)", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia and the South Sandwich Islands", "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Svalbard and Jan Mayen", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan, Province of China", "Tajikistan", "Tanzania, United Republic of", "Thailand", "Timor-Leste", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States Minor Outlying Islands", "Uruguay", "Uzbekistan", "Vanuatu", "Venezuela, Bolivarian Republic of", "Viet Nam", "Virgin Islands, British", "Virgin Islands, U.S.", "Wallis and Futuna", "Western Sahara", "Yemen", "Zambia", "Zimbabwe"];
		public static $CountryListWithAbbreviations = ["AF" => "Afghanistan (‫افغانستان‬‎)", "AX" => "Åland Islands (Åland)", "AL" => "Albania (Shqipëri)", "DZ" => "Algeria (‫الجزائر‬‎)", "AS" => "American Samoa", "AD" => "Andorra", "AO" => "Angola", "AI" => "Anguilla", "AQ" => "Antarctica", "AG" => "Antigua and Barbuda", "AR" => "Argentina", "AM" => "Armenia (Հայաստան)", "AW" => "Aruba", "AC" => "Ascension Island", "AU" => "Australia", "AT" => "Austria (Österreich)", "AZ" => "Azerbaijan (Azərbaycan)", "BS" => "Bahamas", "BH" => "Bahrain (‫البحرين‬‎)", "BD" => "Bangladesh (বাংলাদেশ)", "BB" => "Barbados", "BY" => "Belarus (Беларусь)", "BE" => "Belgium (België)", "BZ" => "Belize", "BJ" => "Benin (Bénin)", "BM" => "Bermuda", "BT" => "Bhutan (འབྲུག)", "BO" => "Bolivia", "BA" => "Bosnia and Herzegovina (Босна и Херцеговина)", "BW" => "Botswana", "BV" => "Bouvet Island", "BR" => "Brazil (Brasil)", "IO" => "British Indian Ocean Territory", "VG" => "British Virgin Islands", "BN" => "Brunei", "BG" => "Bulgaria (България)", "BF" => "Burkina Faso", "BI" => "Burundi (Uburundi)", "KH" => "Cambodia (កម្ពុជា)", "CM" => "Cameroon (Cameroun)", "CA" => "Canada", "IC" => "Canary Islands (islas Canarias)", "CV" => "Cape Verde (Kabu Verdi)", "BQ" => "Caribbean Netherlands", "KY" => "Cayman Islands", "CF" => "Central African Republic (République centrafricaine)", "EA" => "Ceuta and Melilla (Ceuta y Melilla)", "TD" => "Chad (Tchad)", "CL" => "Chile", "CN" => "China (中国)", "CX" => "Christmas Island", "CP" => "Clipperton Island", "CC" => "Cocos (Keeling) Islands (Kepulauan Cocos (Keeling))", "CO" => "Colombia", "KM" => "Comoros (‫جزر القمر‬‎)", "CD" => "Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)", "CG" => "Congo (Republic) (Congo-Brazzaville)", "CK" => "Cook Islands", "CR" => "Costa Rica", "CI" => "Côte d’Ivoire", "HR" => "Croatia (Hrvatska)", "CU" => "Cuba", "CW" => "Curaçao", "CY" => "Cyprus (Κύπρος)", "CZ" => "Czech Republic (Česká republika)", "DK" => "Denmark (Danmark)", "DG" => "Diego Garcia", "DJ" => "Djibouti", "DM" => "Dominica", "DO" => "Dominican Republic (República Dominicana)", "EC" => "Ecuador", "EG" => "Egypt (‫مصر‬‎)", "SV" => "El Salvador", "GQ" => "Equatorial Guinea (Guinea Ecuatorial)", "ER" => "Eritrea", "EE" => "Estonia (Eesti)", "ET" => "Ethiopia", "FK" => "Falkland Islands (Islas Malvinas)", "FO" => "Faroe Islands (Føroyar)", "FJ" => "Fiji", "FI" => "Finland (Suomi)", "FR" => "France", "GF" => "French Guiana (Guyane française)", "PF" => "French Polynesia (Polynésie française)", "TF" => "French Southern Territories (Terres australes françaises)", "GA" => "Gabon", "GM" => "Gambia", "GE" => "Georgia (საქართველო)", "DE" => "Germany (Deutschland)", "GH" => "Ghana (Gaana)", "GI" => "Gibraltar", "GR" => "Greece (Ελλάδα)", "GL" => "Greenland (Kalaallit Nunaat)", "GD" => "Grenada", "GP" => "Guadeloupe", "GU" => "Guam", "GT" => "Guatemala", "GG" => "Guernsey", "GN" => "Guinea (Guinée)", "GW" => "Guinea-Bissau (Guiné Bissau)", "GY" => "Guyana", "HT" => "Haiti", "HM" => "Heard & McDonald Islands", "HN" => "Honduras", "HK" => "Hong Kong (香港)", "HU" => "Hungary (Magyarország)", "IS" => "Iceland (Ísland)", "IN" => "India (भारत)", "ID" => "Indonesia", "IR" => "Iran (‫ایران‬‎)", "IQ" => "Iraq (‫العراق‬‎)", "IE" => "Ireland", "IM" => "Isle of Man", "IL" => "Israel (‫ישראל‬‎)", "IT" => "Italy (Italia)", "JM" => "Jamaica", "JP" => "Japan (日本)", "JE" => "Jersey", "JO" => "Jordan (‫الأردن‬‎)", "KZ" => "Kazakhstan (Казахстан)", "KE" => "Kenya", "KI" => "Kiribati", "XK" => "Kosovo (Kosovë)", "KW" => "Kuwait (‫الكويت‬‎)", "KG" => "Kyrgyzstan (Кыргызстан)", "LA" => "Laos (ລາວ)", "LV" => "Latvia (Latvija)", "LB" => "Lebanon (‫لبنان‬‎)", "LS" => "Lesotho", "LR" => "Liberia", "LY" => "Libya (‫ليبيا‬‎)", "LI" => "Liechtenstein", "LT" => "Lithuania (Lietuva)", "LU" => "Luxembourg", "MO" => "Macau (澳門)", "MK" => "Macedonia (FYROM) (Македонија)", "MG" => "Madagascar (Madagasikara)", "MW" => "Malawi", "MY" => "Malaysia", "MV" => "Maldives", "ML" => "Mali", "MT" => "Malta", "MH" => "Marshall Islands", "MQ" => "Martinique", "MR" => "Mauritania (‫موريتانيا‬‎)", "MU" => "Mauritius (Moris)", "YT" => "Mayotte", "MX" => "Mexico (México)", "FM" => "Micronesia", "MD" => "Moldova (Republica Moldova)", "MC" => "Monaco", "MN" => "Mongolia (Монгол)", "ME" => "Montenegro (Crna Gora)", "MS" => "Montserrat", "MA" => "Morocco (‫المغرب‬‎)", "MZ" => "Mozambique (Moçambique)", "MM" => "Myanmar (Burma) (မြန်မာ)", "NA" => "Namibia (Namibië)", "NR" => "Nauru", "NP" => "Nepal (नेपाल)", "NL" => "Netherlands (Nederland)", "NC" => "New Caledonia (Nouvelle-Calédonie)", "NZ" => "New Zealand", "NI" => "Nicaragua", "NE" => "Niger (Nijar)", "NG" => "Nigeria", "NU" => "Niue", "NF" => "Norfolk Island", "MP" => "Northern Mariana Islands", "KP" => "North Korea (조선 민주주의 인민 공화국)", "NO" => "Norway (Norge)", "OM" => "Oman (‫عُمان‬‎)", "PK" => "Pakistan (‫پاکستان‬‎)", "PW" => "Palau", "PS" => "Palestine (‫فلسطين‬‎)", "PA" => "Panama (Panamá)", "PG" => "Papua New Guinea", "PY" => "Paraguay", "PE" => "Peru (Perú)", "PH" => "Philippines", "PN" => "Pitcairn Islands", "PL" => "Poland (Polska)", "PT" => "Portugal", "PR" => "Puerto Rico", "QA" => "Qatar (‫قطر‬‎)", "RE" => "Réunion (La Réunion)", "RO" => "Romania (România)", "RU" => "Russia (Россия)", "RW" => "Rwanda", "BL" => "Saint Barthélemy (Saint-Barthélemy)", "SH" => "Saint Helena", "KN" => "Saint Kitts and Nevis", "LC" => "Saint Lucia", "MF" => "Saint Martin (Saint-Martin (partie française))", "PM" => "Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)", "WS" => "Samoa", "SM" => "San Marino", "ST" => "São Tomé and Príncipe (São Tomé e Príncipe)", "SA" => "Saudi Arabia (‫المملكة العربية السعودية‬‎)", "SN" => "Senegal (Sénégal)", "RS" => "Serbia (Србија)", "SC" => "Seychelles", "SL" => "Sierra Leone", "SG" => "Singapore", "SX" => "Sint Maarten", "SK" => "Slovakia (Slovensko)", "SI" => "Slovenia (Slovenija)", "SB" => "Solomon Islands", "SO" => "Somalia (Soomaaliya)", "ZA" => "South Africa", "GS" => "South Georgia & South Sandwich Islands", "KR" => "South Korea (대한민국)", "SS" => "South Sudan (‫جنوب السودان‬‎)", "ES" => "Spain (España)", "LK" => "Sri Lanka (ශ්‍රී ලංකාව)", "VC" => "St. Vincent & Grenadines", "SD" => "Sudan (‫السودان‬‎)", "SR" => "Suriname", "SJ" => "Svalbard and Jan Mayen (Svalbard og Jan Mayen)", "SZ" => "Swaziland", "SE" => "Sweden (Sverige)", "CH" => "Switzerland (Schweiz)", "SY" => "Syria (‫سوريا‬‎)", "TW" => "Taiwan (台灣)", "TJ" => "Tajikistan", "TZ" => "Tanzania", "TH" => "Thailand (ไทย)", "TL" => "Timor-Leste", "TG" => "Togo", "TK" => "Tokelau", "TO" => "Tonga", "TT" => "Trinidad and Tobago", "TA" => "Tristan da Cunha", "TN" => "Tunisia (‫تونس‬‎)", "TR" => "Turkey (Türkiye)", "TM" => "Turkmenistan", "TC" => "Turks and Caicos Islands", "TV" => "Tuvalu", "UM" => "U.S. Outlying Islands", "VI" => "U.S. Virgin Islands", "UG" => "Uganda", "UA" => "Ukraine (Україна)", "AE" => "United Arab Emirates (‫الإمارات العربية المتحدة‬‎)", "GB" => "United Kingdom", "US" => "United States", "UY" => "Uruguay", "UZ" => "Uzbekistan (Oʻzbekiston)", "VU" => "Vanuatu", "VA" => "Vatican City (Città del Vaticano)", "VE" => "Venezuela", "VN" => "Vietnam (Việt Nam)", "WF" => "Wallis and Futuna", "EH" => "Western Sahara (‫الصحراء الغربية‬‎)", "YE" => "Yemen (‫اليمن‬‎)", "ZM" => "Zambia", "ZW" => "Zimbabwe"];
		public static $MonthList = ["1" => "January", "2" => "February", "3" => "March", "4" => "April", "5" => "May", "6" => "June", "7" => "July", "8" => "August", "9" => "September", "10" => "October", "11" => "November", "12" => "December"];
		public static $StateList = ['AL' => "Alabama", 'AK' => "Alaska", 'AZ' => "Arizona", 'AR' => "Arkansas", 'CA' => "California", 'CO' => "Colorado", 'CT' => "Connecticut", 'DE' => "Delaware", 'DC' => "District Of Columbia", 'FL' => "Florida", 'GA' => "Georgia", 'HI' => "Hawaii", 'ID' => "Idaho", 'IL' => "Illinois", 'IN' => "Indiana", 'IA' => "Iowa", 'KS' => "Kansas", 'KY' => "Kentucky", 'LA' => "Louisiana", 'ME' => "Maine", 'MD' => "Maryland", 'MA' => "Massachusetts", 'MI' => "Michigan", 'MN' => "Minnesota", 'MS' => "Mississippi", 'MO' => "Missouri", 'MT' => "Montana", 'NE' => "Nebraska", 'NV' => "Nevada", 'NH' => "New Hampshire", 'NJ' => "New Jersey", 'NM' => "New Mexico", 'NY' => "New York", 'NC' => "North Carolina", 'ND' => "North Dakota", 'OH' => "Ohio", 'OK' => "Oklahoma", 'OR' => "Oregon", 'PA' => "Pennsylvania", 'RI' => "Rhode Island", 'SC' => "South Carolina", 'SD' => "South Dakota", 'TN' => "Tennessee", 'TX' => "Texas", 'UT' => "Utah", 'VT' => "Vermont", 'VA' => "Virginia", 'WA' => "Washington", 'WV' => "West Virginia", 'WI' => "Wisconsin", 'WY' => "Wyoming"];
		
		/*
			Function: arrayToXML
				Turns a PHP array into an XML string.
			
			Parameters:
				array - The array to convert.
				tab - Current tab depth (for recursion).
			
			Returns:
				A string of XML.
		*/
		
		public static function arrayToXML($array, $tab = "") {
			return BigTree\Utils::arrayToXML($array, $tab);
		}
		
		/*
			Function: arrayValue
				Checks to see if a value is a string. If it is it will be JSON decoded into an array.

			Parameters:
				value - A variable

			Returns:
				An array.
		*/
		
		public static function arrayValue($value) {
			return BigTree\Utils::arrayValue($value);
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
		
		public static function centerCrop($file, $newfile, $cw, $ch, $retina = false, $grayscale = false) {
			$image = new BigTree\Image($file);
			
			return $image->centerCrop($newfile, $cw, $ch, $retina, $grayscale);
		}
		
		/*
			Function: cleanFile
				Makes sure that a file path doesn't contain abusive characters (i.e. ../)

			Parameters:
				file - A file name

			Returns:
				Cleaned up string.
		*/
		
		public static function cleanFile($file) {
			return BigTree\FileSystem::getSafePath($file);
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
		
		public static function colorMesh($first_color, $second_color, $percentage) {
			return BigTree\Utils::colorMesh($first_color, $second_color, $percentage);
		}
		
		/*
			Function: copyDirectory
				Copies a directory and sets writable permissions.

			Parameters:
				source - The location of the directory to copy.
				destination - The new folder location.

			Returns:
				true if the copy was successful
		*/
		
		public static function copyDirectory($source, $destination) {
			return BigTree\FileSystem::copyDirectory($source, $destination);
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
		
		public static function copyFile($from, $to) {
			return BigTree\FileSystem::copyFile($from, $to);
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
		
		public static function createCrop($file, $new_file, $x, $y, $target_width, $target_height, $width, $height, $retina = false, $grayscale = false) {
			return BigTree\Image::createCrop($file, $new_file, $x, $y, $target_width, $target_height, $width, $height, $retina, $grayscale);
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
		
		public static function createThumbnail($file, $new_file, $max_width, $max_height, $retina = false, $grayscale = false, $upscale = false) {
			return BigTree\Image::createThumbnail($file, $new_file, $max_width, $max_height, $retina, $grayscale, $upscale);
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
		
		public static function createUpscaledImage($file, $new_file, $min_width, $min_height) {
			return static::createThumbnail($file, $new_file, $min_width, $min_height, false, false, true);
		}
		
		/*
			Function: cURL
				Posts to a given URL and returns the response.
				Wrapper for cURL.

			Parameters:
				url - The URL to retrieve / POST to.
				post - A key/value pair array of things to POST (optional).
				options - A key/value pair of extra cURL options (optional).
				strict_security - SSL verification of the host and peer if true (defaults to true).
				output_file - A file location to dump the output of the request to (optional, replaces return value).

			Returns:
				The string response from the URL.
		*/
		
		public static function cURL($url, $post = false, $options = [], $strict_security = true, $output_file = false, $updating_bundle = false) {
			global $bigtree;
			
			$response = BigTree\cURL::request($url, $post ?: null, $options, $strict_security, $output_file ?: null, $updating_bundle);
			
			$bigtree["last_curl_response_code"] = BigTree\cURL::$ResponseCode;
			$bigtree["last_curl_error"] = BigTree\cURL::$Error;
			
			return $response;
		}
		
		/*
			Function: currentURL
				Return the current active URL with correct protocall and port

			Parameters:
				port - Whether to return the port for connections not on port 80 (defaults to false)
		*/
		
		public static function currentURL($port = false) {
			return BigTree\Link::currentURL($port);
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
		
		public static function dateFormat($date, $format = "Y-m-d H:i:s") {
			return BigTree\Date::format($date, $format);
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
		
		public static function dateFromOffset($start_date, $offset, $format = "Y-m-d H:i:s") {
			return BigTree\Date::fromOffset($start_date, $offset, $format);
		}
		
		/*
			Function: deleteDirectory
				Deletes a directory including everything in it.
			
			Parameters:
				dir - The directory to delete.

			Returns:
				true if successful
		*/
		
		public static function deleteDirectory($dir) {
			return BigTree\FileSystem::deleteDirectory($dir);
		}
		
		/*
			Function: deleteFile
				Deletes a file if it exists.

			Parameters:
				file - The file to delete

			Returns:
				true if successful
		*/
		
		public static function deleteFile($file) {
			return BigTree\FileSystem::deleteFile($file);
		}
		
		/*
			Function: describeTable
				Gives in depth information about a MySQL table's structure and keys.
			
			Parameters:
				table - The table name.
			
			Returns:
				An array of table information.
		*/
		
		public static function describeTable($table) {
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
		
		public static function directoryContents($directory, $recurse = true, $extension = false, $include_git = false) {
			return BigTree\FileSystem::getDirectoryContents($directory, $recurse, $extension, $include_git);
		}
		
		/*
			Function: formatBytes
				Formats bytes into larger units to make them more readable.
			
			Parameters:
				size - The number of bytes.
			
			Returns:
				A string with the number of bytes in kilobytes, megabytes, or gigabytes.
		*/
		
		public static function formatBytes($size) {
			return BigTree\Storage::formatBytes($size);
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
		
		public static function geocodeAddress($address) {
			$geocode = new BigTree\Geocode($address);
			
			return $geocode ? $geocode->Array : false;
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
		
		public static function getAvailableFileName($directory, $file, $prefixes = []) {
			return BigTree\FileSystem::getAvailableFileName($directory, $file, $prefixes);
		}
		
		/*
			Function: getCookie
				Gets a cookie set by setCookie and decodes it.

			Parameters:
				id - The id of the set cookie

			Returns:
				Decoded cookie or false if the cookie was not found.
		*/
		
		public static function getCookie($id) {
			return BigTree\Cookie::get($id);
		}
		
		/*
			Function: getFieldSelectOptions
				Get the <select> options of all the fields in a table.
			
			Parameters:
				table - The table to draw the fields for.
				default - The currently selected value.
				sorting - Whether to duplicate fields into "ASC" and "DESC" versions.
		*/
		
		public static function getFieldSelectOptions($table, $default = "", $sorting = false) {
			BigTree\SQL::drawColumnSelectOptions($table, $default, $sorting);
		}
		
		/*
			Function: getTableSelectOptions
				Get the <select> options for all of tables in the database excluding bigtree_ prefixed tables.
			
			Parameters:
				default - The currently selected value.
		*/
		
		public static function getTableSelectOptions($default = "") {
			BigTree\SQL::drawTableSelectOptions($default);
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
		
		public static function getThumbnailSizes($file, $maxwidth, $maxheight) {
			return BigTree\Image::getThumbnailSizes($file, $maxwidth, $maxheight);
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
		
		public static function getUpscaleSizes($file, $min_width, $min_height) {
			return BigTree\Image::getUpscaleSizes($file, $min_width, $min_height);
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
		
		public static function globalizeArray($array) {
			$args = func_get_args();
			
			return call_user_func_array("BigTree\\Globalize::arrayObject", $args);
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
		
		public static function globalizeGETVars() {
			$args = func_get_args();
			
			return call_user_func_array("BigTree\\Globalize::GET", $args);
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
		
		public static function globalizePOSTVars() {
			$args = func_get_args();
			
			return call_user_func_array("BigTree\\Globalize::POST", $args);
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
		
		public static function gravatar($email, $size = 56, $default = false, $rating = "g") {
			return BigTree\User::gravatar($email, $size, $default, $rating);
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
		
		public static function imageManipulationMemoryAvailable($source, $width, $height) {
			return BigTree\Image::getMemoryAvailability($source, $width, $height);
		}
		
		/*
			Function: isDirectoryWritable
				Extend's PHP's is_writable to support directories that don't exist yet.
			
			Parameters:
				path - The path to check the writable status of.
			
			Returns:
				true if the directory exists and is writable or could be created, otherwise false.
		*/
		
		public static function isDirectoryWritable($path, $recursion = false) {
			return BigTree\FileSystem::getDirectoryWritability($path);
		}
		
		/*
			Function: isExternalLink
				Check if URL is external, relative to site root
			
			Parameters:
				url - The URL to test.

			Returns:
				true if link is external
		*/
		
		public static function isExternalLink($url) {
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
		
		public static function json($var, $sql = false) {
			return BigTree\JSON::encode($var, $sql);
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
		
		public static function jsonExtract($data, $columns = [], $preserve_keys = false) {
			return BigTree\JSON::encodeColumns($data, $columns, $preserve_keys);
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
		
		public static function makeDirectory($directory) {
			return BigTree\FileSystem::createDirectory($directory);
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
		
		public static function moveFile($from, $to) {
			return BigTree\FileSystem::moveFile($from, $to);
		}
		
		/*
			Function: parsedFilesArray
				Parses the $_FILES array and returns an array more like a normal $_POST array.
			
			Parameters:
				part - (Optional) The key of the file tree to return.
			
			Returns:
				A more sensible array, or a piece of that sensible array if "part" is set.
		*/
		
		public static function parsedFilesArray($part = "") {
			return BigTree\Field::getParsedFilesArray($part);
		}
		
		/*
			Function: path
				Get the proper path for a file based on whether a custom override exists.
			
			Parameters:
				file - File path relative to either core/ or custom/
			
			Returns:
				Hard file path to a custom/ (preferred) or core/ file depending on what exists.
		*/
		
		public static function path($file) {
			return BigTree\Router::getIncludePath($file);
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
		
		public static function pathInfo($file) {
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
			return BigTree\Date::convertTojQuery($format);
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
		
		public static function placeholderImage($width, $height, $bg_color = false, $text_color = false, $icon_path = false, $text_string = false) {
			BigTree\Image::placeholder($width, $height, $bg_color, $text_color, $icon_path, $text_string);
		}
		
		/*
			Function: postMaxSize
				Returns in bytes the maximum size of a POST.
		*/
		
		public static function postMaxSize() {
			return BigTree\Storage::getPOSTMaxSize();
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
		
		public static function prefixFile($file, $prefix) {
			return BigTree\FileSystem::getPrefixedFile($file, $prefix);
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
		
		public static function putFile($file, $contents) {
			return BigTree\FileSystem::createFile($file, $contents);
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
		
		public static function randomString($length = 8, $type = "alphanum") {
			return BigTree\Text::getRandomString($length, $type);
		}
		
		/*
			Function: redirect
				Simple URL redirect via header with proper code #
			
			Parameters:
				url - The URL to redirect to.
				code - The status code of redirect, defaults to normal 302 redirect.
		*/
		
		public static function redirect($url, $codes = ["302"]) {
			BigTree\Router::redirect($url, $codes);
		}
		
		/*
			Function: relativeTime
				Turns a timestamp into "… hours ago" formatting.

			Parameters:
				time - A date/time stamp understandable by strtotime

			Returns:
				A string describing how long ago the passed time was.
		*/
		
		public static function relativeTime($time) {
			return BigTree\Date::relativeTime($time);
		}
		
		/*
			Function: remoteIP
				Returns the remote user's IP address (works with load balancers).
	
			Returns:
				An IP address
		*/
		
		public static function remoteIP() {
			return BigTree\Router::getRemoteIP();
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
		
		public static function replaceServerRoot($string, $replace = "") {
			return BigTree\Text::replaceServerRoot($string, $replace);
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
		
		public static function route($directory, $path) {
			return BigTree\Router::getRoutedFileAndCommands($directory, $path);
		}
		
		/*
			Function: routeLayouts
				Retrieves a list of route layout files (_header.php and _footer.php) for a given file path.

			Parameters:
				path - A file path

			Returns:
				An array of headers and an array of footers.
		*/
		
		public static function routeLayouts($path) {
			return BigTree\Router::getRoutedLayoutPartials($path);
		}
		
		/*
			Function: runningAsSU
				Checks if the current script is running as the owner of the script.
				Useful for determining whether you need to 777 a file you're creating.

			Returns:
				true if PHP is running as the user that owns the file
		*/
		
		public static function runningAsSU() {
			return BigTree\FileSystem::getRunningAsOwner();
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
		
		public static function runParser($item, $value, $code) {
			return BigTree\Module::runParser($item, $value, $code);
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
		
		public static function safeEncode($string) {
			return BigTree\Text::htmlEncode($string);
		}
		
		/*
			Function: sendEmail
				Sends an email using PHPMailer

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
		
		public static function sendEmail($to, $subject, $html, $text = "", $from = false, $return = false, $cc = false, $bcc = false, $headers = [], $smtp = []) {
			$email = new BigTree\Email;
			
			$email->To = $to;
			$email->Subject = $subject;
			$email->HTML = $html;
			$email->Text = $text;
			$email->From = $from;
			$email->ReplyTo = $return;
			$email->CC = $cc;
			$email->BCC = $bcc;
			$email->Headers = $headers;
			
			if (!empty($smtp["host"])) {
				$email->setService("SMTP", [
					"smtp_host" => $smtp["host"],
					"smtp_port" => $smtp["port"],
					"smtp_security" => $smtp["security"] ?: null,
					"smtp_user" => $smtp["user"],
					"smtp_password" => $smtp["password"]
				]);
			}
			
			$email->send();
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
		
		public static function setCookie($id, $value, $expiration = 0) {
			BigTree\Cookie::create($id, $value, $expiration);
		}
		
		/*
			Function: setDirectoryPermissions
				Sets writable permissions for a whole directory.
				If the web server is not running as the owner of the current script, permissions will be 777.

			Parameters:
				location - The directory to set permissions on.
		*/
		
		public static function setDirectoryPermissions($location) {
			return BigTree\FileSystem::setDirectoryPermissions($location);
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
		
		public static function setPermissions($location) {
			return BigTree\FileSystem::setPermissions($location);
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
		
		public static function tableCompare($table_a, $table_b) {
			return SQL::compareTables($table_a, $table_b);
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
		
		public static function tableContents($table) {
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
		
		public static function tableExists($table) {
			return SQL::tableExists($table);
		}
		
		/*
			Function: touchFile
				touch()s a file even if the directory for it doesn't exist yet.
			
			Parameters:
				file - The file path to touch.
		*/
		
		public static function touchFile($file) {
			return BigTree\FileSystem::touchFile($file);
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
		
		public static function translateArray($array) {
			return BigTree\Link::encode($array);
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
		
		public static function trimLength($string, $length) {
			return BigTree\Text::trimLength($string, $length);
		}
		
		/*
			Function: unformatBytes
				Formats a string of kilobytes / megabytes / gigabytes back into bytes.
			
			Parameters:
				size - The string of (kilo/mega/giga)bytes.
			
			Returns:
				The number of bytes.
		*/
		
		public static function unformatBytes($size) {
			return BigTree\Storage::unformatBytes($size);
		}
		
		/*
			Function: unsetCookie
				Removes a site-wide cookie set by setCookie.

			Parameters:
				id - The cookie identifier
		*/
		
		public static function unsetCookie($id) {
			BigTree\Cookie::delete($id);
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
		
		public static function untranslateArray($array) {
			return BigTree\Link::decode($array);
		}
		
		/*
			Function: unzip
				Unzips a file.
			
			Parameters:
				file - Location of the file to unzip
				destination - The full path to unzip the file's contents to.
		*/
		
		public static function unzip($file, $destination) {
			return BigTree\Updater::unzip($file, $destination);
		}
		
		/*
			Function: uploadMaxFileSize
				Returns Apache's max file size value for use in forms.
		
			Returns:
				The integer value for setting a form's MAX_FILE_SIZE.
		*/
		
		public static function uploadMaxFileSize() {
			return BigTree\Storage::getUploadMaxFileSize();
		}
		
		/*
			Function: urlExists
				Attempts to connect to a URL using cURL.

			Parameters:
				url - The URL to connect to.

			Returns:
				true if it can connect, false if connection failed.
		*/
		
		public static function urlExists($url) {
			return BigTree\Link::urlExists($url);
		}
		
	}
