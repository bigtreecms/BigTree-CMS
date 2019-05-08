<?php
	/*
		Class: BigTree\Field
			Provides an interface for BigTree field processing.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read array $Array
	 * @property-read array $ParsedFilesArray
	 */
	
	class Field extends SQLObject
	{
		
		public static $Count = 0;
		public static $CountryList = ["United States", "Afghanistan", "Åland Islands", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia, Plurinational State of", "Bonaire, Sint Eustatius and Saba", "Bosnia and Herzegovina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo", "Congo, The Democratic Republic of the", "Cook Islands", "Costa Rica", "Côte d'Ivoire", "Croatia", "Cuba", "Curaçao", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guernsey", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard Island and McDonald Islands", "Holy See (Vatican City State)", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran, Islamic Republic of", "Iraq", "Ireland", "Isle of Man", "Israel", "Italy", "Jamaica", "Japan", "Jersey", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, Democratic People's Republic of", "Korea, Republic of", "Kuwait", "Kyrgyzstan", "Lao People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macao", "Macedonia, The Former Yugoslav Republic of", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia, Federated States of", "Moldova, Republic of", "Monaco", "Mongolia", "Montenegro", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", "Norway", "Occupied Palestinian Territory", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Réunion", "Romania", "Russian Federation", "Rwanda", "Saint Barthélemy", "Saint Helena, Ascension and Tristan da Cunha", "Saint Kitts and Nevis", "Saint Lucia", "Saint Martin (French part)", "Saint Pierre and Miquelon", "Saint Vincent and The Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Sint Maarten (Dutch part)", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia and the South Sandwich Islands", "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Svalbard and Jan Mayen", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan, Province of China", "Tajikistan", "Tanzania, United Republic of", "Thailand", "Timor-Leste", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States Minor Outlying Islands", "Uruguay", "Uzbekistan", "Vanuatu", "Venezuela, Bolivarian Republic of", "Viet Nam", "Virgin Islands, British", "Virgin Islands, U.S.", "Wallis and Futuna", "Western Sahara", "Yemen", "Zambia", "Zimbabwe"];
		public static $CountryListWithAbbreviations = ["AF" => "Afghanistan (‫افغانستان‬‎)", "AX" => "Åland Islands (Åland)", "AL" => "Albania (Shqipëri)", "DZ" => "Algeria (‫الجزائر‬‎)", "AS" => "American Samoa", "AD" => "Andorra", "AO" => "Angola", "AI" => "Anguilla", "AQ" => "Antarctica", "AG" => "Antigua and Barbuda", "AR" => "Argentina", "AM" => "Armenia (Հայաստան)", "AW" => "Aruba", "AC" => "Ascension Island", "AU" => "Australia", "AT" => "Austria (Österreich)", "AZ" => "Azerbaijan (Azərbaycan)", "BS" => "Bahamas", "BH" => "Bahrain (‫البحرين‬‎)", "BD" => "Bangladesh (বাংলাদেশ)", "BB" => "Barbados", "BY" => "Belarus (Беларусь)", "BE" => "Belgium (België)", "BZ" => "Belize", "BJ" => "Benin (Bénin)", "BM" => "Bermuda", "BT" => "Bhutan (འབྲུག)", "BO" => "Bolivia", "BA" => "Bosnia and Herzegovina (Босна и Херцеговина)", "BW" => "Botswana", "BV" => "Bouvet Island", "BR" => "Brazil (Brasil)", "IO" => "British Indian Ocean Territory", "VG" => "British Virgin Islands", "BN" => "Brunei", "BG" => "Bulgaria (България)", "BF" => "Burkina Faso", "BI" => "Burundi (Uburundi)", "KH" => "Cambodia (កម្ពុជា)", "CM" => "Cameroon (Cameroun)", "CA" => "Canada", "IC" => "Canary Islands (islas Canarias)", "CV" => "Cape Verde (Kabu Verdi)", "BQ" => "Caribbean Netherlands", "KY" => "Cayman Islands", "CF" => "Central African Republic (République centrafricaine)", "EA" => "Ceuta and Melilla (Ceuta y Melilla)", "TD" => "Chad (Tchad)", "CL" => "Chile", "CN" => "China (中国)", "CX" => "Christmas Island", "CP" => "Clipperton Island", "CC" => "Cocos (Keeling) Islands (Kepulauan Cocos (Keeling))", "CO" => "Colombia", "KM" => "Comoros (‫جزر القمر‬‎)", "CD" => "Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)", "CG" => "Congo (Republic) (Congo-Brazzaville)", "CK" => "Cook Islands", "CR" => "Costa Rica", "CI" => "Côte d’Ivoire", "HR" => "Croatia (Hrvatska)", "CU" => "Cuba", "CW" => "Curaçao", "CY" => "Cyprus (Κύπρος)", "CZ" => "Czech Republic (Česká republika)", "DK" => "Denmark (Danmark)", "DG" => "Diego Garcia", "DJ" => "Djibouti", "DM" => "Dominica", "DO" => "Dominican Republic (República Dominicana)", "EC" => "Ecuador", "EG" => "Egypt (‫مصر‬‎)", "SV" => "El Salvador", "GQ" => "Equatorial Guinea (Guinea Ecuatorial)", "ER" => "Eritrea", "EE" => "Estonia (Eesti)", "ET" => "Ethiopia", "FK" => "Falkland Islands (Islas Malvinas)", "FO" => "Faroe Islands (Føroyar)", "FJ" => "Fiji", "FI" => "Finland (Suomi)", "FR" => "France", "GF" => "French Guiana (Guyane française)", "PF" => "French Polynesia (Polynésie française)", "TF" => "French Southern Territories (Terres australes françaises)", "GA" => "Gabon", "GM" => "Gambia", "GE" => "Georgia (საქართველო)", "DE" => "Germany (Deutschland)", "GH" => "Ghana (Gaana)", "GI" => "Gibraltar", "GR" => "Greece (Ελλάδα)", "GL" => "Greenland (Kalaallit Nunaat)", "GD" => "Grenada", "GP" => "Guadeloupe", "GU" => "Guam", "GT" => "Guatemala", "GG" => "Guernsey", "GN" => "Guinea (Guinée)", "GW" => "Guinea-Bissau (Guiné Bissau)", "GY" => "Guyana", "HT" => "Haiti", "HM" => "Heard & McDonald Islands", "HN" => "Honduras", "HK" => "Hong Kong (香港)", "HU" => "Hungary (Magyarország)", "IS" => "Iceland (Ísland)", "IN" => "India (भारत)", "ID" => "Indonesia", "IR" => "Iran (‫ایران‬‎)", "IQ" => "Iraq (‫العراق‬‎)", "IE" => "Ireland", "IM" => "Isle of Man", "IL" => "Israel (‫ישראל‬‎)", "IT" => "Italy (Italia)", "JM" => "Jamaica", "JP" => "Japan (日本)", "JE" => "Jersey", "JO" => "Jordan (‫الأردن‬‎)", "KZ" => "Kazakhstan (Казахстан)", "KE" => "Kenya", "KI" => "Kiribati", "XK" => "Kosovo (Kosovë)", "KW" => "Kuwait (‫الكويت‬‎)", "KG" => "Kyrgyzstan (Кыргызстан)", "LA" => "Laos (ລາວ)", "LV" => "Latvia (Latvija)", "LB" => "Lebanon (‫لبنان‬‎)", "LS" => "Lesotho", "LR" => "Liberia", "LY" => "Libya (‫ليبيا‬‎)", "LI" => "Liechtenstein", "LT" => "Lithuania (Lietuva)", "LU" => "Luxembourg", "MO" => "Macau (澳門)", "MK" => "Macedonia (FYROM) (Македонија)", "MG" => "Madagascar (Madagasikara)", "MW" => "Malawi", "MY" => "Malaysia", "MV" => "Maldives", "ML" => "Mali", "MT" => "Malta", "MH" => "Marshall Islands", "MQ" => "Martinique", "MR" => "Mauritania (‫موريتانيا‬‎)", "MU" => "Mauritius (Moris)", "YT" => "Mayotte", "MX" => "Mexico (México)", "FM" => "Micronesia", "MD" => "Moldova (Republica Moldova)", "MC" => "Monaco", "MN" => "Mongolia (Монгол)", "ME" => "Montenegro (Crna Gora)", "MS" => "Montserrat", "MA" => "Morocco (‫المغرب‬‎)", "MZ" => "Mozambique (Moçambique)", "MM" => "Myanmar (Burma) (မြန်မာ)", "NA" => "Namibia (Namibië)", "NR" => "Nauru", "NP" => "Nepal (नेपाल)", "NL" => "Netherlands (Nederland)", "NC" => "New Caledonia (Nouvelle-Calédonie)", "NZ" => "New Zealand", "NI" => "Nicaragua", "NE" => "Niger (Nijar)", "NG" => "Nigeria", "NU" => "Niue", "NF" => "Norfolk Island", "MP" => "Northern Mariana Islands", "KP" => "North Korea (조선 민주주의 인민 공화국)", "NO" => "Norway (Norge)", "OM" => "Oman (‫عُمان‬‎)", "PK" => "Pakistan (‫پاکستان‬‎)", "PW" => "Palau", "PS" => "Palestine (‫فلسطين‬‎)", "PA" => "Panama (Panamá)", "PG" => "Papua New Guinea", "PY" => "Paraguay", "PE" => "Peru (Perú)", "PH" => "Philippines", "PN" => "Pitcairn Islands", "PL" => "Poland (Polska)", "PT" => "Portugal", "PR" => "Puerto Rico", "QA" => "Qatar (‫قطر‬‎)", "RE" => "Réunion (La Réunion)", "RO" => "Romania (România)", "RU" => "Russia (Россия)", "RW" => "Rwanda", "BL" => "Saint Barthélemy (Saint-Barthélemy)", "SH" => "Saint Helena", "KN" => "Saint Kitts and Nevis", "LC" => "Saint Lucia", "MF" => "Saint Martin (Saint-Martin (partie française))", "PM" => "Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)", "WS" => "Samoa", "SM" => "San Marino", "ST" => "São Tomé and Príncipe (São Tomé e Príncipe)", "SA" => "Saudi Arabia (‫المملكة العربية السعودية‬‎)", "SN" => "Senegal (Sénégal)", "RS" => "Serbia (Србија)", "SC" => "Seychelles", "SL" => "Sierra Leone", "SG" => "Singapore", "SX" => "Sint Maarten", "SK" => "Slovakia (Slovensko)", "SI" => "Slovenia (Slovenija)", "SB" => "Solomon Islands", "SO" => "Somalia (Soomaaliya)", "ZA" => "South Africa", "GS" => "South Georgia & South Sandwich Islands", "KR" => "South Korea (대한민국)", "SS" => "South Sudan (‫جنوب السودان‬‎)", "ES" => "Spain (España)", "LK" => "Sri Lanka (ශ්‍රී ලංකාව)", "VC" => "St. Vincent & Grenadines", "SD" => "Sudan (‫السودان‬‎)", "SR" => "Suriname", "SJ" => "Svalbard and Jan Mayen (Svalbard og Jan Mayen)", "SZ" => "Swaziland", "SE" => "Sweden (Sverige)", "CH" => "Switzerland (Schweiz)", "SY" => "Syria (‫سوريا‬‎)", "TW" => "Taiwan (台灣)", "TJ" => "Tajikistan", "TZ" => "Tanzania", "TH" => "Thailand (ไทย)", "TL" => "Timor-Leste", "TG" => "Togo", "TK" => "Tokelau", "TO" => "Tonga", "TT" => "Trinidad and Tobago", "TA" => "Tristan da Cunha", "TN" => "Tunisia (‫تونس‬‎)", "TR" => "Turkey (Türkiye)", "TM" => "Turkmenistan", "TC" => "Turks and Caicos Islands", "TV" => "Tuvalu", "UM" => "U.S. Outlying Islands", "VI" => "U.S. Virgin Islands", "UG" => "Uganda", "UA" => "Ukraine (Україна)", "AE" => "United Arab Emirates (‫الإمارات العربية المتحدة‬‎)", "GB" => "United Kingdom", "US" => "United States", "UY" => "Uruguay", "UZ" => "Uzbekistan (Oʻzbekiston)", "VU" => "Vanuatu", "VA" => "Vatican City (Città del Vaticano)", "VE" => "Venezuela", "VN" => "Vietnam (Việt Nam)", "WF" => "Wallis and Futuna", "EH" => "Western Sahara (‫الصحراء الغربية‬‎)", "YE" => "Yemen (‫اليمن‬‎)", "ZM" => "Zambia", "ZW" => "Zimbabwe"];
		public static $GlobalTabIndex = 1;
		public static $HTMLFields = [];
		public static $LastFieldType = "";
		public static $ManyToMany = [];
		public static $MonthList = ["1" => "January", "2" => "February", "3" => "March", "4" => "April", "5" => "May", "6" => "June", "7" => "July", "8" => "August", "9" => "September", "10" => "October", "11" => "November", "12" => "December"];
		public static $Namespace = "bigtree_field_";
		public static $SimpleHTMLFields = [];
		public static $StateList = ['AL' => "Alabama", 'AK' => "Alaska", 'AZ' => "Arizona", 'AR' => "Arkansas", 'CA' => "California", 'CO' => "Colorado", 'CT' => "Connecticut", 'DE' => "Delaware", 'DC' => "District Of Columbia", 'FL' => "Florida", 'GA' => "Georgia", 'HI' => "Hawaii", 'ID' => "Idaho", 'IL' => "Illinois", 'IN' => "Indiana", 'IA' => "Iowa", 'KS' => "Kansas", 'KY' => "Kentucky", 'LA' => "Louisiana", 'ME' => "Maine", 'MD' => "Maryland", 'MA' => "Massachusetts", 'MI' => "Michigan", 'MN' => "Minnesota", 'MS' => "Mississippi", 'MO' => "Missouri", 'MT' => "Montana", 'NE' => "Nebraska", 'NV' => "Nevada", 'NH' => "New Hampshire", 'NJ' => "New Jersey", 'NM' => "New Mexico", 'NY' => "New York", 'NC' => "North Carolina", 'ND' => "North Dakota", 'OH' => "Ohio", 'OK' => "Oklahoma", 'OR' => "Oregon", 'PA' => "Pennsylvania", 'RI' => "Rhode Island", 'SC' => "South Carolina", 'SD' => "South Dakota", 'TN' => "Tennessee", 'TX' => "Texas", 'UT' => "Utah", 'VT' => "Vermont", 'VA' => "Virginia", 'WA' => "Washington", 'WV' => "West Virginia", 'WI' => "Wisconsin", 'WY' => "Wyoming"];
		
		public $Error;
		public $FieldsetClass = "";
		public $FileInput;
		public $FileOutput;
		public $ForcedRecrop;
		public $ID;
		public $Ignore;
		public $Input;
		public $Key;
		public $LabelClass = "";
		public $Output;
		public $Required = false;
		public $Settings;
		public $Subtitle;
		public $TabIndex;
		public $Title;
		public $Type;
		public $Value;
		
		/*
			Constructor:
				Sets up a Field object.

			Parameters:
				field - A field data array
		*/
		
		public function __construct(array $field)
		{
			$this->FileInput = $field["file_input"] ?: null;
			$this->ForcedRecrop = !empty($field["forced_recrop"]) ? true : false;
			$this->HasValue = !empty($field["value"]) ? true : !empty($field["has_value"]);
			$this->Input = $field["input"] ?: null;
			$this->Key = $field["key"] ?: null;
			$this->Output = false;
			$this->Settings = Link::decode(array_filter((array) ($field["settings"] ?: [])));
			$this->Subtitle = $field["subtitle"] ?: null;
			$this->TabIndex = $field["tabindex"] ?: static::$GlobalTabIndex++;
			$this->Title = $field["title"] ?: null;
			
			if (!empty($field["type"])) {
				$this->Type = FileSystem::getSafePath($field["type"]);
			}
			
			$this->Value = $field["value"] ?: null;
			
			// Give this field a unique ID within the field namespace
			static::$Count++;
			$this->ID = static::$Namespace.static::$Count;
		}
		
		/*
			Function: draw
				Draws a field in a form.

			Parameters:
				field - Field array
		*/
		
		public function draw(): void
		{
			global $admin, $bigtree, $cms, $db, $form;
			
			// Setup Validation Class
			if (!empty($this->Settings["validation"]) && strpos($this->Settings["validation"], "required") !== false) {
				$this->LabelClass .= "required";
				$this->Required = true;
				
				$label_validation_class = ' class="'.$this->LabelClass.'"'; // Backwards compat
			}
			
			// Save current context
			$bigtree["saved_extension_context"] = $bigtree["extension_context"];
			
			// Get path and set context
			if (strpos($this->Type, "*") !== false) {
				list($extension,$field_type) = explode("*", $this->Type);
				
				$bigtree["extension_context"] = $extension;
				$field_type_path = SERVER_ROOT."extensions/$extension/field-types/$field_type/draw.php";
			} else {
				$field_type_path = Router::getIncludePath("admin/field-types/".$this->Type."/draw.php");
			}
			
			// Backwards compatibility
			$field = $this->Array;
			$options = $this->Settings;
			
			// Only draw fields for which we have a file
			if (file_exists($field_type_path)) {
				
				// Don't draw the fieldset for field types that are declared as self drawing.
				if ($bigtree["field_types"][$this->Type]["self_draw"]) {
					include $field_type_path;
				} else {
					echo "<fieldset".($this->FieldsetClass ? ' class="'.trim($this->FieldsetClass).'"' : '').">\n";
					
					if ($this->Title) {
						echo "  <label".($this->LabelClass ? ' class="'.trim($this->LabelClass).'"' : '').">";
						echo $this->Title;
						
						if ($this->Subtitle) {
							echo "<small>".$this->Subtitle."</small>";
						}
						
						echo "</label>\n";
					}
					
					include $field_type_path;
					
					echo "\n</fieldset>";
					
					$bigtree["tabindex"]++;
				}
				
				static::$LastFieldType = $this->Type;
			}
			
			// Restore context
			$bigtree["extension_context"] = $bigtree["saved_extension_context"];
		}
		
		/*
			Function: drawArrayLevel
				An internal function used for drawing callout and matrix resource data.
		*/
		
		public function drawArrayLevel(array $keys, array $level): void
		{
			foreach ($level as $key => $value) {
				if (is_array($value)) {
					$this->drawArrayLevel(array_merge($keys, [$key]), $value);
				} else {
	?>
	<input type="hidden" name="<?=$this->Key?>[<?=implode("][", $keys)?>][<?=$key?>]" value="<?=Text::htmlEncode($value)?>"/>
	<?php
				}
			}
		}
		
		/*
			Function: getArray
				Returns an Array version of this Object.
		*/
		
		public function getArray(): array
		{
			$raw_properties = get_object_vars($this);
			$array = [];
			
			foreach ($raw_properties as $key => $value) {
				$array[$this->_camelCaseToUnderscore($key)] = $value;
			}
			
			return $array;
		}
		
		/*
			Function: getParsedFilesArray
				Parses the $_FILES array and returns an array more like a normal $_POST array.

			Parameters:
				part - (Optional) The key of the file tree to return.

			Returns:
				A more sensible array, or a piece of that sensible array if "part" is set.
		*/
		
		public static function getParsedFilesArray(?string $part = null): array
		{
			$clean = [];
			
			foreach ($_FILES as $key => $first_level) {
				// Hurray, we have a first level entry, just save it to the clean array.
				if (!is_array($first_level["name"])) {
					$clean[$key] = $first_level;
				} else {
					$clean[$key] = static::getParsedFilesArrayLoop($first_level["name"], $first_level["tmp_name"],
																   $first_level["type"], $first_level["error"],
																   $first_level["size"]);
				}
			}
			
			if (!is_null($part)) {
				return $clean[$part];
			}
			
			return $clean;
		}
		
		/*
			Function: parseFilesArrayLoop
				Private method used by parseFilesArray.
		*/
		
		protected static function getParsedFilesArrayLoop(array $name, array $tmp_name, array $type, array $error,
														  array $size): array
		{
			$array = [];
			
			foreach ($name as $k => $v) {
				if (!is_array($v)) {
					$array[$k]["name"] = $v;
					$array[$k]["tmp_name"] = $tmp_name[$k];
					$array[$k]["type"] = $type[$k];
					$array[$k]["error"] = $error[$k];
					$array[$k]["size"] = $size[$k];
				} else {
					$array[$k] = static::getParsedFilesArrayLoop($name[$k], $tmp_name[$k], $type[$k], $error[$k], $size[$k]);
				}
			}
			
			return $array;
		}
		
		/*
			Function: process
				Processes the field's input and returns its output

			Returns:
				Field output.
		*/
		
		public function process()
		{
			global $admin, $bigtree, $cms, $db, $form;
			
			// Save current context
			$bigtree["saved_extension_context"] = $bigtree["extension_context"];
			
			// Backwards compatibility
			$field = $this->Array;
			$options = $this->Settings;
			
			// Check if the field type is stored in an extension
			if (strpos($this->Type, "*") !== false) {
				list($extension,$field_type) = explode("*", $this->Type);
				
				$bigtree["extension_context"] = $extension;
				$field_type_path = SERVER_ROOT."extensions/$extension/field-types/$field_type/process.php";
			} else {
				$field_type_path = Router::getIncludePath("admin/field-types/".$this->Type."/process.php");
			}
			
			// If we have a customized handler for this data type, run it.
			if (file_exists($field_type_path)) {
				include $field_type_path;
				
				// If it's explicitly ignored return null
				if ($this->Ignore || $field["ignore"]) {
					return null;
				} else {
					$output = $this->Output ?: $field["output"];
				}
				
			// Fall back to default handling
			} else {
				if (is_array($this->Input)) {
					$output = $this->Input;
				} else {
					$output = Text::htmlEncode($this->Input);
				}
			}
			
			// Check validation
			if (!static::validate($output, $this->Settings["validation"])) {
				$error_message = $this->Error ?: $this->Settings["error_message"];
				$error_message = $error_message ?: static::validationErrorMessage($output, $this->Settings["validation"]);
				
				$bigtree["errors"][] = [
					"field" => $this->Title,
					"error" => $error_message
				];
			}
			
			// Translation of internal links
			if (is_array($output)) {
				$output = Link::encode($output);
			} else {
				$output = Link::encode($output);
			}
			
			// Restore context
			$bigtree["extension_context"] = $bigtree["saved_extension_context"];
			
			return $output;
		}
		
		/*
			Function: processImageUpload
				Processes image upload data for form fields and sets the FileOutput file to the file name.
				If you're emulating field information, the following properties are of interest in the field object:
				FileInput - a keyed array that needs at least "name" and "tmp_name" keys that contain the desired name of the file and the source file location, respectively.
				Settings - a keyed array of options for the field, keys of interest for photo processing are:
					"min_height" - Minimum Height required for the image
					"min_width" - Minimum Width required for the image
					"retina" - Whether to try to create a 2x size image when thumbnailing / cropping (if the source file / crop is large enough)
					"thumbs" - An array of thumbnail arrays, each of which has "prefix", "width", "height", and "grayscale" keys (prefix is prepended to the file name when creating the thumbnail, grayscale will make the thumbnail grayscale)
					"crops" - An array of crop arrays, each of which has "prefix", "width", "height" and "grayscale" keys (prefix is prepended to the file name when creating the crop, grayscale will make the thumbnail grayscale)). Crops can also have their own "thumbs" key that creates thumbnails of each crop (format mirrors that of "thumbs")
		
			Parameters:
				replace - If not looking for a unique filename (e.g. replacing an existing image) pass truthy value
				force_local_replace - If replacing a file, replace a local filepath regardless of default storage (defaults to false)
		*/
		
		public function processImageUpload($replace = false, $force_local_replace = false): ?string
		{
			global $bigtree;
			
			$name = $this->FileInput["name"];
			$temp_name = $this->FileInput["tmp_name"];
			$error = $this->FileInput["error"];
			
			// If a file upload error occurred, return the old image and set errors
			if ($error == 1 || $error == 2) {
				$bigtree["errors"][] = [
					"field" => $this->Title,
					"error" => Text::translate("The file you uploaded ($name) was too large &mdash; <strong>Max file size: :max_file_size:</strong>", false, [":max_file_size:" => ini_get("upload_max_filesize")])
				];
				
				return null;
			} elseif ($error == 3) {
				$bigtree["errors"][] = [
					"field" => $this->Title,
					"error" => Text::translate("The file upload failed (:name:).", false, [":name:" => $name])
				];
				
				return null;
			}
			
			// See if we're using image presets
			if ($this->Settings["preset"]) {
				$media_settings = DB::get("config", "media-settings");
				$preset = $media_settings["presets"][$this->Settings["preset"]];
				
				// If the preset still exists, copy its properties over to our options
				if ($preset) {
					foreach ($preset as $key => $val) {
						$this->Settings[$key] = $val;
					}
				}
			}
			
			// This is a file manager upload, add a 100x100 center crop
			if ($this->Settings["preset"] == "default") {
				if (!is_array($this->Settings["center_crops"])) {
					$this->Settings["center_crops"] = [];
				}
				
				$this->Settings["center_crops"][] = [
					"prefix" => "list-preview/",
					"width" => 100,
					"height" => 100
				];
			}
			
			// Load up the image class for doing manipulation / calculation and fix any EXIF rotations
			$image = new Image($temp_name, $this->Settings);
			
			if ($image->Error) {
				$bigtree["errors"][] = ["field" => $this->Title, "error" => $image->Error];
				$image->destroy();
				
				return null;
			}
			
			// For crops that don't meet the required image size, see if a sub-crop will work.
			$image->filterGeneratableCrops();
			
			// Get largest crop and thumbnail to check if we have the memory available to make them
			$largest_thumb = $image->getLargestThumbnail();
			$largest_crop = $image->getLargestCrop();
			
			if ((!is_null($largest_thumb) && $image->checkMemory($largest_thumb["width"], $largest_thumb["height"])) ||
				(!is_null($largest_crop) && !$image->checkMemory($largest_crop["width"], $largest_crop["height"]))
			) {
				$bigtree["errors"][] = [
					"field" => $this->Title,
					"error" => Text::translate("The image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.")
				];
				$image->destroy();
				
				return false;
			}
			
			// Upload the original to the proper place.
			if ($replace) {
				$this->FileOutput = $image->replace($name, $force_local_replace);
			} else {
				$this->FileOutput = $image->store($name);
			}
			
			// If the upload service didn't return a value, we failed to upload it for one reason or another.
			if (empty($this->FileOutput)) {
				$bigtree["errors"][] = [
					"field" => $this->Title,
					"error" => Text::translate($image->Error)
				];
				$image->destroy();
				
				return false;
			}
			
			// Handle crops and thumbnails
			$crops = $image->processCrops();
			$image->processThumbnails();
			$image->processCenterCrops();
			
			// If we don't have any crops, get rid of the temporary image we made.
			if (!count($crops)) {
				$image->destroy();
			} else {
				if (!is_array($bigtree["crops"])) {
					$bigtree["crops"] = [];
				}
				
				$bigtree["crops"] = array_merge($bigtree["crops"], $crops);
			}
			
			return $this->FileOutput;
		}
		
		/*
			Function: rectifyTypeChange
				Verifies that existing data for a field set will fit with the new field set.
				Modifies the passed in data to remove fields for which the type change is destructive.

			Parameters:
				data - Data, passed by reference and modified upon return
				new_resources - The resource fields for the data set to conform to
				old_resources - The resource fields the data set originated in

			Returns:
				An array of fields which should force re-crops
		*/
		
		public static function rectifyTypeChange(array &$data, array $new_resources, array $old_resources): array
		{
			$forced_recrops = [];
			$old_resources_keyed = [];
			$exact_types = ["callouts", "matrix", "list", "one-to-many", "media-gallery"];
			
			if (is_array($old_resources)) {
				foreach ($old_resources as $resource) {
					$old_resources_keyed[$resource["id"]] = $resource;
				}
			}
			
			foreach ($new_resources as $new) {
				$id = $new["id"];
				
				if (empty($data[$id])) {
					continue;
				}
				
				if (isset($old_resources_keyed[$id])) {
					$old = $old_resources_keyed[$id];
					
					if ($old["type"] != $new["type"]) {
						// Not even the same resource type, wipe data
						unset($data[$id]);
					} elseif (in_array($new["type"], $exact_types) && $new["settings"] != $old["settings"]) {
						// These fields need to match exactly to allow data to move over
						unset($data[$id]);
					} elseif ($new["type"] == "image-reference") {
						// Image references just need to ensure that the existing data meets the new requirements
						$new_min_width = empty($new["settings"]["min_width"]) ? 0 : intval($new["settings"]["min_width"]);
						$new_min_height = empty($new["settings"]["min_height"]) ? 0 : intval($new["settings"]["min_height"]);
						
						if (!SQL::exists("bigtree_resources", $data[$id])) {
							unset($data[$id]);
							continue;
						}

						$resource = new Resource($data[$id]);
						
						if ($resource->Width < $new_min_width || $resource->Height < $new_min_height) {
							unset($data[$id]);
						}
					} elseif ($new["type"] == "text") {
						// Sub-types changed, the data won't fit anymore
						if ((!empty($new["settings"]["sub_type"]) || !empty($old["settings"]["sub_type"])) &&
							$new["settings"]["sub_type"] != $old["settings"]["sub_type"])
						{
							unset($data[$id]);
						}
					} elseif ($new["type"] == "html" && !empty($new["settings"]["simple"]) && empty($old["settings"]["simple"])) {
						// New HTML is simple
						unset($data[$id]);
					} elseif ($new["type"] == "upload" && !empty($new["settings"]["image"])) {
						if ($new["settings"] == $old["settings"]) {
							continue;
						}
						
						$new_min_width = empty($new["settings"]["min_width"]) ? 0 : intval($new["settings"]["min_width"]);
						$new_min_height = empty($new["settings"]["min_height"]) ? 0 : intval($new["settings"]["min_height"]);
						list($w, $h) = @getimagesize($data[$id]);
						
						// Existing base image won't work
						if (empty($w) || empty($h) || $w < $new_min_width || $h < $new_min_height) {
							unset($data[$id]);
							continue;
						}
						
						$forced_recrops[$id] = true;
					}
				}
			}
			
			return $forced_recrops;
		}
		
		/*
			Function: validate
				Validates field data based on its validation requirements.
			
			Parameters:
				data - The form's posted data for a given field.
				type - Validation requirements (required, numeric, email, link).
		
			Returns:
				True if validation passed, otherwise false.
			
			See Also:
				<errorMessage>
		*/
		
		public static function validate($data, ?string $type): bool
		{
			if (is_null($type)) {
				return true;
			}
			
			$parts = explode(" ", $type);
			
			// Not required and it's blank
			if (!in_array("required", $parts) && !$data) {
				return true;
			} else {
				// Requires numeric and it isn't
				if (in_array("numeric", $parts) && !is_numeric($data)) {
					return false;
				// Requires email and it isn't
				} elseif (in_array("email", $parts) && !filter_var($data, FILTER_VALIDATE_EMAIL)) {
					return false;
				// Requires url and it isn't
				} elseif (in_array("link", $parts) && !filter_var($data, FILTER_VALIDATE_URL)) {
					return false;
				} elseif (in_array("required", $parts) && ($data === false || $data === "")) {
					return false;
				// It exists and validates as numeric, an email, or URL
				} else {
					return true;
				}
			}
		}
		
		/*
			Function: validationErrorMessage
				Returns an error message for a form element that failed validation.
			
			Parameters:
				data - The form's posted data for a given field.
				type - Validation requirements (required, numeric, email, link).
		
			Returns:
				A string containing reasons the validation failed.
				
			See Also:
				<validate>
		*/
		
		public static function validationErrorMessage($data, string $type): string
		{
			$parts = explode(" ", $type);
			// Not required and it's blank
			$message = "This field ";
			$mparts = [];
			
			if (!$data && in_array("required", $parts)) {
				$mparts[] = "is required";
			}
			
			// Requires numeric and it isn't
			if (in_array("numeric", $parts) && !is_numeric($data)) {
				$mparts[] = "must be numeric";
				// Requires email and it isn't
			} elseif (in_array("email", $parts) && !filter_var($data, FILTER_VALIDATE_EMAIL)) {
				$mparts[] = "must be an email address";
				// Requires url and it isn't
			} elseif (in_array("link", $parts) && !filter_var($data, FILTER_VALIDATE_URL)) {
				$mparts[] = "must be a link";
			}
			
			$message .= implode(" and ", $mparts).".";
			
			return $message;
		}
		
	}
