<?php
	/*
		Class: BigTree\Field
			Provides an interface for BigTree field processing.
	*/

	namespace BigTree;
	
	class Field extends BaseObject {

		public $Error;
		public $FieldsetClass = "";
		public $FileInput;
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

		static $Count = 0;
		static $CountryList = array("United States","Afghanistan","Åland Islands","Albania","Algeria","American Samoa","Andorra","Angola","Anguilla","Antarctica","Antigua and Barbuda","Argentina","Armenia","Aruba","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bermuda","Bhutan","Bolivia, Plurinational State of","Bonaire, Sint Eustatius and Saba","Bosnia and Herzegovina","Botswana","Bouvet Island","Brazil","British Indian Ocean Territory","Brunei Darussalam","Bulgaria","Burkina Faso","Burundi","Cambodia","Cameroon","Canada","Cape Verde","Cayman Islands","Central African Republic","Chad","Chile","China","Christmas Island","Cocos (Keeling) Islands","Colombia","Comoros","Congo","Congo, The Democratic Republic of the","Cook Islands","Costa Rica","Côte d'Ivoire","Croatia","Cuba","Curaçao","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea","Estonia","Ethiopia","Falkland Islands (Malvinas)","Faroe Islands","Fiji","Finland","France","French Guiana","French Polynesia","French Southern Territories","Gabon","Gambia","Georgia","Germany","Ghana","Gibraltar","Greece","Greenland","Grenada","Guadeloupe","Guam","Guatemala","Guernsey","Guinea","Guinea-Bissau","Guyana","Haiti","Heard Island and McDonald Islands","Holy See (Vatican City State)","Honduras","Hong Kong","Hungary","Iceland","India","Indonesia","Iran, Islamic Republic of","Iraq","Ireland","Isle of Man","Israel","Italy","Jamaica","Japan","Jersey","Jordan","Kazakhstan","Kenya","Kiribati","Korea, Democratic People's Republic of","Korea, Republic of","Kuwait","Kyrgyzstan","Lao People's Democratic Republic","Latvia","Lebanon","Lesotho","Liberia","Libyan Arab Jamahiriya","Liechtenstein","Lithuania","Luxembourg","Macao","Macedonia, The Former Yugoslav Republic of","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Martinique","Mauritania","Mauritius","Mayotte","Mexico","Micronesia, Federated States of","Moldova, Republic of","Monaco","Mongolia","Montenegro","Montserrat","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Caledonia","New Zealand","Nicaragua","Niger","Nigeria","Niue","Norfolk Island","Northern Mariana Islands","Norway","Occupied Palestinian Territory","Oman","Pakistan","Palau","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Pitcairn","Poland","Portugal","Puerto Rico","Qatar","Réunion","Romania","Russian Federation","Rwanda","Saint Barthélemy","Saint Helena, Ascension and Tristan da Cunha","Saint Kitts and Nevis","Saint Lucia","Saint Martin (French part)","Saint Pierre and Miquelon","Saint Vincent and The Grenadines","Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Sint Maarten (Dutch part)","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Georgia and the South Sandwich Islands","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Svalbard and Jan Mayen","Swaziland","Sweden","Switzerland","Syrian Arab Republic","Taiwan, Province of China","Tajikistan","Tanzania, United Republic of","Thailand","Timor-Leste","Togo","Tokelau","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Turks and Caicos Islands","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States Minor Outlying Islands","Uruguay","Uzbekistan","Vanuatu","Venezuela, Bolivarian Republic of","Viet Nam","Virgin Islands, British","Virgin Islands, U.S.","Wallis and Futuna","Western Sahara","Yemen","Zambia","Zimbabwe");
		static $CountryListWithAbbreviations = array("AF" => "Afghanistan (‫افغانستان‬‎)","AX" => "Åland Islands (Åland)","AL" => "Albania (Shqipëri)","DZ" => "Algeria (‫الجزائر‬‎)","AS" => "American Samoa","AD" => "Andorra","AO" => "Angola","AI" => "Anguilla","AQ" => "Antarctica","AG" => "Antigua and Barbuda","AR" => "Argentina","AM" => "Armenia (Հայաստան)","AW" => "Aruba","AC" => "Ascension Island","AU" => "Australia","AT" => "Austria (Österreich)","AZ" => "Azerbaijan (Azərbaycan)","BS" => "Bahamas","BH" => "Bahrain (‫البحرين‬‎)","BD" => "Bangladesh (বাংলাদেশ)","BB" => "Barbados","BY" => "Belarus (Беларусь)","BE" => "Belgium (België)","BZ" => "Belize","BJ" => "Benin (Bénin)","BM" => "Bermuda","BT" => "Bhutan (འབྲུག)","BO" => "Bolivia","BA" => "Bosnia and Herzegovina (Босна и Херцеговина)","BW" => "Botswana","BV" => "Bouvet Island","BR" => "Brazil (Brasil)","IO" => "British Indian Ocean Territory","VG" => "British Virgin Islands","BN" => "Brunei","BG" => "Bulgaria (България)","BF" => "Burkina Faso","BI" => "Burundi (Uburundi)","KH" => "Cambodia (កម្ពុជា)","CM" => "Cameroon (Cameroun)","CA" => "Canada","IC" => "Canary Islands (islas Canarias)","CV" => "Cape Verde (Kabu Verdi)","BQ" => "Caribbean Netherlands","KY" => "Cayman Islands","CF" => "Central African Republic (République centrafricaine)","EA" => "Ceuta and Melilla (Ceuta y Melilla)","TD" => "Chad (Tchad)","CL" => "Chile","CN" => "China (中国)","CX" => "Christmas Island","CP" => "Clipperton Island","CC" => "Cocos (Keeling) Islands (Kepulauan Cocos (Keeling))","CO" => "Colombia","KM" => "Comoros (‫جزر القمر‬‎)","CD" => "Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)","CG" => "Congo (Republic) (Congo-Brazzaville)","CK" => "Cook Islands","CR" => "Costa Rica","CI" => "Côte d’Ivoire","HR" => "Croatia (Hrvatska)","CU" => "Cuba","CW" => "Curaçao","CY" => "Cyprus (Κύπρος)","CZ" => "Czech Republic (Česká republika)","DK" => "Denmark (Danmark)","DG" => "Diego Garcia","DJ" => "Djibouti","DM" => "Dominica","DO" => "Dominican Republic (República Dominicana)","EC" => "Ecuador","EG" => "Egypt (‫مصر‬‎)","SV" => "El Salvador","GQ" => "Equatorial Guinea (Guinea Ecuatorial)","ER" => "Eritrea","EE" => "Estonia (Eesti)","ET" => "Ethiopia","FK" => "Falkland Islands (Islas Malvinas)","FO" => "Faroe Islands (Føroyar)","FJ" => "Fiji","FI" => "Finland (Suomi)","FR" => "France","GF" => "French Guiana (Guyane française)","PF" => "French Polynesia (Polynésie française)","TF" => "French Southern Territories (Terres australes françaises)","GA" => "Gabon","GM" => "Gambia","GE" => "Georgia (საქართველო)","DE" => "Germany (Deutschland)","GH" => "Ghana (Gaana)","GI" => "Gibraltar","GR" => "Greece (Ελλάδα)","GL" => "Greenland (Kalaallit Nunaat)","GD" => "Grenada","GP" => "Guadeloupe","GU" => "Guam","GT" => "Guatemala","GG" => "Guernsey","GN" => "Guinea (Guinée)","GW" => "Guinea-Bissau (Guiné Bissau)","GY" => "Guyana","HT" => "Haiti","HM" => "Heard & McDonald Islands","HN" => "Honduras","HK" => "Hong Kong (香港)","HU" => "Hungary (Magyarország)","IS" => "Iceland (Ísland)","IN" => "India (भारत)","ID" => "Indonesia","IR" => "Iran (‫ایران‬‎)","IQ" => "Iraq (‫العراق‬‎)","IE" => "Ireland","IM" => "Isle of Man","IL" => "Israel (‫ישראל‬‎)","IT" => "Italy (Italia)","JM" => "Jamaica","JP" => "Japan (日本)","JE" => "Jersey","JO" => "Jordan (‫الأردن‬‎)","KZ" => "Kazakhstan (Казахстан)","KE" => "Kenya","KI" => "Kiribati","XK" => "Kosovo (Kosovë)","KW" => "Kuwait (‫الكويت‬‎)","KG" => "Kyrgyzstan (Кыргызстан)","LA" => "Laos (ລາວ)","LV" => "Latvia (Latvija)","LB" => "Lebanon (‫لبنان‬‎)","LS" => "Lesotho","LR" => "Liberia","LY" => "Libya (‫ليبيا‬‎)","LI" => "Liechtenstein","LT" => "Lithuania (Lietuva)","LU" => "Luxembourg","MO" => "Macau (澳門)","MK" => "Macedonia (FYROM) (Македонија)","MG" => "Madagascar (Madagasikara)","MW" => "Malawi","MY" => "Malaysia","MV" => "Maldives","ML" => "Mali","MT" => "Malta","MH" => "Marshall Islands","MQ" => "Martinique","MR" => "Mauritania (‫موريتانيا‬‎)","MU" => "Mauritius (Moris)","YT" => "Mayotte","MX" => "Mexico (México)","FM" => "Micronesia","MD" => "Moldova (Republica Moldova)","MC" => "Monaco","MN" => "Mongolia (Монгол)","ME" => "Montenegro (Crna Gora)","MS" => "Montserrat","MA" => "Morocco (‫المغرب‬‎)","MZ" => "Mozambique (Moçambique)","MM" => "Myanmar (Burma) (မြန်မာ)","NA" => "Namibia (Namibië)","NR" => "Nauru","NP" => "Nepal (नेपाल)","NL" => "Netherlands (Nederland)","NC" => "New Caledonia (Nouvelle-Calédonie)","NZ" => "New Zealand","NI" => "Nicaragua","NE" => "Niger (Nijar)","NG" => "Nigeria","NU" => "Niue","NF" => "Norfolk Island","MP" => "Northern Mariana Islands","KP" => "North Korea (조선 민주주의 인민 공화국)","NO" => "Norway (Norge)","OM" => "Oman (‫عُمان‬‎)","PK" => "Pakistan (‫پاکستان‬‎)","PW" => "Palau","PS" => "Palestine (‫فلسطين‬‎)","PA" => "Panama (Panamá)","PG" => "Papua New Guinea","PY" => "Paraguay","PE" => "Peru (Perú)","PH" => "Philippines","PN" => "Pitcairn Islands","PL" => "Poland (Polska)","PT" => "Portugal","PR" => "Puerto Rico","QA" => "Qatar (‫قطر‬‎)","RE" => "Réunion (La Réunion)","RO" => "Romania (România)","RU" => "Russia (Россия)","RW" => "Rwanda","BL" => "Saint Barthélemy (Saint-Barthélemy)","SH" => "Saint Helena","KN" => "Saint Kitts and Nevis","LC" => "Saint Lucia","MF" => "Saint Martin (Saint-Martin (partie française))","PM" => "Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)","WS" => "Samoa","SM" => "San Marino","ST" => "São Tomé and Príncipe (São Tomé e Príncipe)","SA" => "Saudi Arabia (‫المملكة العربية السعودية‬‎)","SN" => "Senegal (Sénégal)","RS" => "Serbia (Србија)","SC" => "Seychelles","SL" => "Sierra Leone","SG" => "Singapore","SX" => "Sint Maarten","SK" => "Slovakia (Slovensko)","SI" => "Slovenia (Slovenija)","SB" => "Solomon Islands","SO" => "Somalia (Soomaaliya)","ZA" => "South Africa","GS" => "South Georgia & South Sandwich Islands","KR" => "South Korea (대한민국)","SS" => "South Sudan (‫جنوب السودان‬‎)","ES" => "Spain (España)","LK" => "Sri Lanka (ශ්‍රී ලංකාව)","VC" => "St. Vincent & Grenadines","SD" => "Sudan (‫السودان‬‎)","SR" => "Suriname","SJ" => "Svalbard and Jan Mayen (Svalbard og Jan Mayen)","SZ" => "Swaziland","SE" => "Sweden (Sverige)","CH" => "Switzerland (Schweiz)","SY" => "Syria (‫سوريا‬‎)","TW" => "Taiwan (台灣)","TJ" => "Tajikistan","TZ" => "Tanzania","TH" => "Thailand (ไทย)","TL" => "Timor-Leste","TG" => "Togo","TK" => "Tokelau","TO" => "Tonga","TT" => "Trinidad and Tobago","TA" => "Tristan da Cunha","TN" => "Tunisia (‫تونس‬‎)","TR" => "Turkey (Türkiye)","TM" => "Turkmenistan","TC" => "Turks and Caicos Islands","TV" => "Tuvalu","UM" => "U.S. Outlying Islands","VI" => "U.S. Virgin Islands","UG" => "Uganda","UA" => "Ukraine (Україна)","AE" => "United Arab Emirates (‫الإمارات العربية المتحدة‬‎)","GB" => "United Kingdom","US" => "United States","UY" => "Uruguay","UZ" => "Uzbekistan (Oʻzbekiston)","VU" => "Vanuatu","VA" => "Vatican City (Città del Vaticano)","VE" => "Venezuela","VN" => "Vietnam (Việt Nam)","WF" => "Wallis and Futuna","EH" => "Western Sahara (‫الصحراء الغربية‬‎)","YE" => "Yemen (‫اليمن‬‎)","ZM" => "Zambia","ZW" => "Zimbabwe");
		static $MonthList = array("1" => "January","2" => "February","3" => "March","4" => "April","5" => "May","6" => "June","7" => "July","8" => "August","9" => "September","10" => "October","11" => "November","12" => "December");
		static $Namespace = "bigtree_field_";
		static $StateList = array('AL'=>"Alabama",'AK'=>"Alaska",'AZ'=>"Arizona",'AR'=>"Arkansas",'CA'=>"California",'CO'=>"Colorado",'CT'=>"Connecticut",'DE'=>"Delaware",'DC'=>"District Of Columbia", 'FL'=>"Florida",'GA'=>"Georgia",'HI'=>"Hawaii",'ID'=>"Idaho",'IL'=>"Illinois",'IN'=>"Indiana",'IA'=>"Iowa",'KS'=>"Kansas",'KY'=>"Kentucky",'LA'=>"Louisiana",'ME'=>"Maine",'MD'=>"Maryland",'MA'=>"Massachusetts",'MI'=>"Michigan",'MN'=>"Minnesota",'MS'=>"Mississippi",'MO'=>"Missouri",'MT'=>"Montana",'NE'=>"Nebraska",'NV'=>"Nevada",'NH'=>"New Hampshire",'NJ'=>"New Jersey",'NM'=>"New Mexico",'NY'=>"New York",'NC'=>"North Carolina",'ND'=>"North Dakota",'OH'=>"Ohio",'OK'=>"Oklahoma",'OR'=>"Oregon",'PA'=>"Pennsylvania",'RI'=>"Rhode Island",'SC'=>"South Carolina",'SD'=>"South Dakota",'TN'=>"Tennessee",'TX'=>"Texas",'UT'=>"Utah",'VT'=>"Vermont",'VA'=>"Virginia",'WA'=>"Washington",'WV'=>"West Virginia",'WI'=>"Wisconsin",'WY'=>"Wyoming");

		/*
			Constructor:
				Sets up a Field object.

			Parameters:
				field - A field data array
		*/

		function __construct($field) {
			$this->FileInput = $field["file_input"] ?: null;
			$this->Input = $field["input"] ?: null;
			$this->Key = $field["key"] ?: null;
			$this->Output = false;
			$this->Settings = array_filter((array) ($field["options"] ?: array()));
			$this->Subtitle = $field["subtitle"] ?: null;
			$this->TabIndex = $field["tabindex"] ?: null;
			$this->Title = $field["title"] ?: null;
			$this->Type = FileSystem::getSafePath($field["type"]);
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

		function draw() {
			global $admin,$bigtree,$cms,$db;

			// Setup Validation Class
			if (!empty($this->Settings["validation"]) && strpos($this->Settings["validation"],"required") !== false) {
				$this->LabelClass .= " required";
				$this->Required = true;
			}

			// Save current context
			$bigtree["saved_extension_context"] = $bigtree["extension_context"];

			// Get the field path in case it's an extension field type
			if (strpos($this->Type,"*") !== false) {
				list($extension,$field_type) = explode("*",$this->Type);

				$bigtree["extension_context"] = $extension;
				$field_type_path = SERVER_ROOT."extensions/$extension/field-types/$field_type/draw.php";
			} else {
				$field_type_path = Router::getIncludePath("admin/form-field-types/draw/".$this->Type.".php");
			}

			// Backwards compatibility
			$field = $this->Array;
			$this->Settings = $this->Settings;

			// Only draw fields for which we have a file
			if (file_exists($field_type_path)) {

				// Don't draw the fieldset for field types that are declared as self drawing.
				if ($bigtree["field_types"][$this->Type]["self_draw"]) {
					include $field_type_path;
				} else {
?>
<fieldset<?php if ($this->FieldsetClass) { ?> class="<?=trim($this->FieldsetClass)?>"<?php } ?>>
	<?php if ($this->Title) { ?>
	<label<?php if ($this->LabelClass) { ?> class="<?=trim($this->LabelClass)?>"<?php } ?>>
		<?=$this->Title?>
		<?php if ($this->Subtitle) { ?> <small><?=$this->Subtitle?></small><?php } ?>
	</label>
	<?php } ?>
	<?php include $field_type_path ?>
</fieldset>
<?php
					$bigtree["tabindex"]++;
				}

				$bigtree["last_resource_type"] = $this->Type;
			}

			// Restore context
			$bigtree["extension_context"] = $bigtree["saved_extension_context"];
		}

		/*
			Function: drawArrayLevel
				An internal function used for drawing callout and matrix resource data.
		*/

		function drawArrayLevel($keys,$level) {
			foreach ($level as $key => $value) {
				if (is_array($value)) {
					$this->drawArrayLevel(array_merge($keys,array($key)),$value);
				} else {
?>
<input type="hidden" name="<?=$this->Key?>[<?=implode("][",$keys)?>][<?=$key?>]" value="<?=Text::htmlEncode($value)?>" />
<?php
				}
			}
		}

		/*
			Function: getArray
				Returns an Array version of this Object.
		*/

		function getArray() {
			$raw_properties = get_object_vars($this);
			$array = array();

			foreach ($raw_properties as $key => $value) {
				$array[$this->_camelCaseToUnderscore($key)] = $value;
			}

			$array["options"] = $array["settings"];

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

		static function getParsedFilesArray($part = "") {
			$clean = array();

			foreach ($_FILES as $key => $first_level) {
				// Hurray, we have a first level entry, just save it to the clean array.
				if (!is_array($first_level["name"])) {
					$clean[$key] = $first_level;
				} else {
					$clean[$key] = static::getParsedFilesArrayLoop($first_level["name"],$first_level["tmp_name"],$first_level["type"],$first_level["error"],$first_level["size"]);
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

		private static function getParsedFilesArrayLoop($name,$tmp_name,$type,$error,$size) {
			$array = array();

			foreach ($name as $k => $v) {
				if (!is_array($v)) {
					$array[$k]["name"] = $v;
					$array[$k]["tmp_name"] = $tmp_name[$k];
					$array[$k]["type"] = $type[$k];
					$array[$k]["error"] = $error[$k];
					$array[$k]["size"] = $size[$k];
				} else {
					$array[$k] = static::getParsedFilesArrayLoop($name[$k],$tmp_name[$k],$type[$k],$error[$k],$size[$k]);
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

		function process() {
			global $admin,$bigtree,$cms,$db;

			// Save current context
			$bigtree["saved_extension_context"] = $bigtree["extension_context"];

			// Backwards compatibility
			$field = $this->Array;
			$this->Settings = $this->Settings;

			// Check if the field type is stored in an extension
			if (strpos($this->Type,"*") !== false) {
				list($extension,$field_type) = explode("*",$this->Type);

				$bigtree["extension_context"] = $extension;
				$field_type_path = SERVER_ROOT."extensions/$extension/field-types/$field_type/process.php";
			} else {
				$field_type_path = Router::getIncludePath("admin/form-field-types/process/".$this->Type.".php");
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
			if (!static::validate($output,$this->Settings["validation"])) {
				$error_message = $this->Error ?: $this->Settings["error_message"];
				$error_message = $error_message ?: static::validationErrorMessage($output,$this->Settings["validation"]);

				$bigtree["errors"][] = array(
					"field" => $this->Title,
					"error" => $error_message
				);
			}

			// Translation of internal links
			if (is_array($output)) {
				$output = Link::encodeArray($output);
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
		*/

		function processImageUpload() {
			global $bigtree;

			$failed = false;
			$name = $this->FileInput["name"];
			$temp_name = $this->FileInput["tmp_name"];
			$error = $this->FileInput["error"];

			// If a file upload error occurred, return the old image and set errors
			if ($error == 1 || $error == 2) {
				$bigtree["errors"][] = array("field" => $this->Title, "error" => "The file you uploaded ($name) was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
				return false;
			} elseif ($error == 3) {
				$bigtree["errors"][] = array("field" => $this->Title, "error" => "The file upload failed ($name).");
				return false;
			}

			// We're going to tell Storage to handle forcing images into JPEGs instead of writing the code 20x
			$storage = new Storage;
			$storage->AutoJPEG = $bigtree["config"]["image_force_jpeg"];

			// Let's check the minimum requirements for the image first before we store it anywhere.
			$image_info = @getimagesize($temp_name);
			$iwidth = $image_info[0];
			$iheight = $image_info[1];
			$itype = $image_info[2];
			$channels = $image_info["channels"];

			// See if we're using image presets
			if ($this->Settings["preset"]) {
				$media_settings = Seting::value("bigtree-internal-media-settings");
				$preset = $media_settings["presets"][$this->Settings["preset"]];
				// If the preset still exists, copy its properties over to our options
				if ($preset) {
					foreach ($preset as $key => $val) {
						$this->Settings[$key] = $val;
					}
				}
			}

			// If the minimum height or width is not meant, do NOT let the image through.  Erase the change or update from the database.
			if ((isset($this->Settings["min_height"]) && $iheight < $this->Settings["min_height"]) || (isset($this->Settings["min_width"]) && $iwidth < $this->Settings["min_width"])) {
				$error = "Image uploaded (".htmlspecialchars($name).") did not meet the minimum size of ";
				if ($this->Settings["min_height"] && $this->Settings["min_width"]) {
					$error .= $this->Settings["min_width"]."x".$this->Settings["min_height"]." pixels.";
				} elseif ($this->Settings["min_height"]) {
					$error .= $this->Settings["min_height"]." pixels tall.";
				} elseif ($this->Settings["min_width"]) {
					$error .= $this->Settings["min_width"]." pixels wide.";
				}
				$bigtree["errors"][] = array("field" => $this->Title, "error" => $error);
				$failed = true;
			}

			// If it's not a valid image, throw it out!
			if ($itype != IMAGETYPE_GIF && $itype != IMAGETYPE_JPEG && $itype != IMAGETYPE_PNG) {
				$bigtree["errors"][] = array("field" => $this->Title, "error" =>  "An invalid file was uploaded. Valid file types: JPG, GIF, PNG.");
				$failed = true;
			}

			// See if it's CMYK
			if ($channels == 4) {
				$bigtree["errors"][] = array("field" => $this->Title, "error" =>  "A CMYK encoded file was uploaded. Please upload an RBG image.");
				$failed = true;
			}

			// See if we have enough memory for all our crops and thumbnails
			if (!$failed && ((is_array($this->Settings["crops"]) && count($this->Settings["crops"])) || (is_array($this->Settings["thumbs"]) && count($this->Settings["thumbs"])))) {
				if (is_array($this->Settings["crops"])) {
					foreach ($this->Settings["crops"] as $crop) {
						if (!$failed && is_array($crop) && array_filter($crop)) {
							if ($this->Settings["retina"]) {
								$crop["width"] *= 2;
								$crop["height"] *= 2;
							}
							// We don't want to add multiple errors so we check if we've already failed
							if (!Image::getMemoryAvailability($temp_name,$crop["width"],$crop["height"])) {
								$bigtree["errors"][] = array("field" => $this->Title, "error" => "Image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.");
								$failed = true;
							}
						}
					}
				}
				if (is_array($this->Settings["thumbs"])) {
					foreach ($this->Settings["thumbs"] as $thumb) {
						// We don't want to add multiple errors and we also don't want to waste effort getting thumbnail sizes if we already failed.
						if (!$failed && is_array($thumb) && array_filter($thumb)) {
							if ($this->Settings["retina"]) {
								$thumb["width"] *= 2;
								$thumb["height"] *= 2;
							}
							$sizes = Image::getThumbnailSizes($temp_name,$thumb["width"],$thumb["height"]);
							if (!Image::getMemoryAvailability($temp_name,$sizes[3],$sizes[4])) {
								$bigtree["errors"][] = array("field" => $this->Title, "error" => "Image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.");
								$failed = true;
							}
						}
					}
				}
				if (is_array($this->Settings["center_crops"])) {
					foreach ($this->Settings["center_crops"] as $crop) {
						// We don't want to add multiple errors and we also don't want to waste effort getting thumbnail sizes if we already failed.
						if (!$failed && is_array($crop) && array_filter($crop)) {
							list($w,$h) = getimagesize($temp_name);
							if (!Image::getMemoryAvailability($temp_name,$w,$h)) {
								$bigtree["errors"][] = array("field" => $this->Title, "error" => "Image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.");
								$failed = true;
							}
						}
					}
				}
			}

			if (!$failed) {
				// Make a temporary copy to be used for thumbnails and crops.
				$itype_exts = array(IMAGETYPE_PNG => ".png", IMAGETYPE_JPEG => ".jpg", IMAGETYPE_GIF => ".gif");

				// Make a first copy
				$first_copy = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
				FileSystem::moveFile($temp_name,$first_copy);

				// Do EXIF Image Rotation
				if ($itype == IMAGETYPE_JPEG && function_exists("exif_read_data")) {
					$exif = @exif_read_data($first_copy);
					$o = $exif['Orientation'];
					if ($o == 3 || $o == 6 || $o == 8) {
						$source = imagecreatefromjpeg($first_copy);

						if ($o == 3) {
							$source = imagerotate($source,180,0);
						} elseif ($o == 6) {
							$source = imagerotate($source,270,0);
						} else {
							$source = imagerotate($source,90,0);
						}

						// We're going to create a PNG so that we don't lose quality when we resave
						imagepng($source,$first_copy);
						rename($first_copy,substr($first_copy,0,-3)."png");
						$first_copy = substr($first_copy,0,-3)."png";

						// Force JPEG since we made the first copy a PNG
						$storage->AutoJPEG = true;

						// Clean up memory
						imagedestroy($source);

						// Get new width/height/type
						list($iwidth,$iheight,$itype,$iattr) = getimagesize($first_copy);
					}
				}

				// Create a temporary copy that we will use later for crops and thumbnails
				$temp_copy = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
				FileSystem::copyFile($first_copy,$temp_copy);

				// Gather up an array of file prefixes
				$prefixes = array();
				if (is_array($this->Settings["thumbs"])) {
					foreach ($this->Settings["thumbs"] as $thumb) {
						if (!empty($thumb["prefix"])) {
							$prefixes[] = $thumb["prefix"];
						}
					}
				}
				if (is_array($this->Settings["center_crops"])) {
					foreach ($this->Settings["center_crops"] as $crop) {
						if (!empty($crop["prefix"])) {
							$prefixes[] = $crop["prefix"];
						}
					}
				}
				if (is_array($this->Settings["crops"])) {
					foreach ($this->Settings["crops"] as $crop) {
						if (is_array($crop)) {
							if (!empty($crop["prefix"])) {
								$prefixes[] = $crop["prefix"];
							}
							if (is_array($crop["thumbs"])) {
								foreach ($crop["thumbs"] as $thumb) {
									if (!empty($thumb["prefix"])) {
										$prefixes[] = $thumb["prefix"];
									}
								}
							}
							if (is_array($crop["center_crops"])) {
								foreach ($crop["center_crops"] as $center_crop) {
									if (!empty($center_crop["prefix"])) {
										$prefixes[] = $center_crop["prefix"];
									}
								}
							}
						}
					}
				}

				// Upload the original to the proper place.
				$this->FileOutput = $storage->store($first_copy,$name,$this->Settings["directory"],true,$prefixes);

 				// If the upload service didn't return a value, we failed to upload it for one reason or another.
 				if (!$this->FileOutput) {
 					if ($storage->DisabledFileError) {
						$bigtree["errors"][] = array("field" => $this->Title, "error" => "Could not upload file. The file extension is not allowed.");
					} else {
						$bigtree["errors"][] = array("field" => $this->Title, "error" => "Could not upload file. The destination is not writable.");
					}
					unlink($temp_copy);
					unlink($first_copy);

				    // Failed, we keep the current value
					return false;
				// If we did upload it successfully, check on thumbs and crops.
				} else {
					// Get path info on the file.
					$pinfo = pathinfo($this->FileOutput);

					// Handle Crops
					if (is_array($this->Settings["crops"])) {
						foreach ($this->Settings["crops"] as $crop) {
							if (is_array($crop)) {
								// Make sure the crops have a width/height and it's numeric
								if ($crop["width"] && $crop["height"] && is_numeric($crop["width"]) && is_numeric($crop["height"])) {
									$cwidth = $crop["width"];
									$cheight = $crop["height"];
		
									// Check to make sure each dimension is greater then or equal to, but not both equal to the crop.
									if (($iheight >= $cheight && $iwidth > $cwidth) || ($iwidth >= $cwidth && $iheight > $cheight)) {
										// Make a square if for some reason someone only entered one dimension for a crop.
										if (!$cwidth) {
											$cwidth = $cheight;
										} elseif (!$cheight) {
											$cheight = $cwidth;
										}
										$bigtree["crops"][] = array(
											"image" => $temp_copy,
											"directory" => $this->Settings["directory"],
											"retina" => $this->Settings["retina"],
											"name" => $pinfo["basename"],
											"width" => $cwidth,
											"height" => $cheight,
											"prefix" => $crop["prefix"],
											"thumbs" => $crop["thumbs"],
											"center_crops" => $crop["center_crops"],
											"grayscale" => $crop["grayscale"]
										);
									// If it's the same dimensions, let's see if they're looking for a prefix for whatever reason...
									} elseif ($iheight == $cheight && $iwidth == $cwidth) {
										// See if we want thumbnails
										if (is_array($crop["thumbs"])) {
											foreach ($crop["thumbs"] as $thumb) {
												// Make sure the thumbnail has a width or height and it's numeric
												if (($thumb["width"] && is_numeric($thumb["width"])) || ($thumb["height"] && is_numeric($thumb["height"]))) {
													// Create a temporary thumbnail of the image on the server before moving it to it's destination.
													$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
													Image::createThumbnail($temp_copy,$temp_thumb,$thumb["width"],$thumb["height"],$this->Settings["retina"],$thumb["grayscale"]);
													// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
													$storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$this->Settings["directory"]);
												}
											}
										}
	
										// See if we want center crops
										if (is_array($crop["center_crops"])) {
											foreach ($crop["center_crops"] as $center_crop) {
												// Make sure the crop has a width and height and it's numeric
												if ($center_crop["width"] && is_numeric($center_crop["width"]) && $center_crop["height"] && is_numeric($center_crop["height"])) {
													// Create a temporary crop of the image on the server before moving it to it's destination.
													$temp_crop = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
													Image::centerCrop($temp_copy,$temp_crop,$center_crop["width"],$center_crop["height"],$this->Settings["retina"],$center_crop["grayscale"]);
													// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
													$storage->replace($temp_crop,$center_crop["prefix"].$pinfo["basename"],$this->Settings["directory"]);
												}
											}
										}
										
										if ($crop["prefix"]) {
											$storage->store($temp_copy,$crop["prefix"].$pinfo["basename"],$this->Settings["directory"],false);
										}
									}
								}
							}
						}
					}

					// Handle thumbnailing
					if (is_array($this->Settings["thumbs"])) {
						foreach ($this->Settings["thumbs"] as $thumb) {
							// Make sure the thumbnail has a width or height and it's numeric
							if (($thumb["width"] && is_numeric($thumb["width"])) || ($thumb["height"] && is_numeric($thumb["height"]))) {
								$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
								Image::createThumbnail($temp_copy,$temp_thumb,$thumb["width"],$thumb["height"],$this->Settings["retina"],$thumb["grayscale"]);
								// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
								$storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$this->Settings["directory"]);
							}
						}
					}

					// Handle center crops
					if (is_array($this->Settings["center_crops"])) {
						foreach ($this->Settings["center_crops"] as $crop) {
							// Make sure the crop has a width and height and it's numeric
							if ($crop["width"] && is_numeric($crop["width"]) && $crop["height"] && is_numeric($crop["height"])) {
								$temp_crop = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
								Image::centerCrop($temp_copy,$temp_crop,$crop["width"],$crop["height"],$this->Settings["retina"],$crop["grayscale"]);
								// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
								$storage->replace($temp_crop,$crop["prefix"].$pinfo["basename"],$this->Settings["directory"]);
							}
						}
					}

					// If we don't have any crops, get rid of the temporary image we made.
					if (!count($bigtree["crops"])) {
						unlink($temp_copy);
					}
				}
			// We failed, keep the current value.
			} else {
				return false;
			}

			return $this->FileOutput;
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
		
		static function validate($data,$type) {
			$parts = explode(" ",$type);
			// Not required and it's blank
			if (!in_array("required",$parts) && !$data) {
				return true;
			} else {
				// Requires numeric and it isn't
				if (in_array("numeric",$parts) && !is_numeric($data)) {
					return false;
				// Requires email and it isn't
				} elseif (in_array("email",$parts) && !filter_var($data,FILTER_VALIDATE_EMAIL)) {
					return false;
				// Requires url and it isn't
				} elseif (in_array("link",$parts) && !filter_var($data,FILTER_VALIDATE_URL)) {
					return false;
				} elseif (in_array("required",$parts) && ($data === false || $data === "")) {
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
		
		static function validationErrorMessage($data,$type) {
			$parts = explode(" ",$type);
			// Not required and it's blank
			$message = "This field ";
			$mparts = array();
			
			if (!$data && in_array("required",$parts)) {
				$mparts[] = "is required";
			}
			
			// Requires numeric and it isn't
			if (in_array("numeric",$parts) && !is_numeric($data)) {
				$mparts[] = "must be numeric";
			// Requires email and it isn't
			} elseif (in_array("email",$parts) && !filter_var($data,FILTER_VALIDATE_EMAIL)) {
				$mparts[] = "must be an email address";
			// Requires url and it isn't
			} elseif (in_array("link",$parts) && !filter_var($data,FILTER_VALIDATE_URL)) {
				$mparts[] = "must be a link";
			}
			
			$message .= implode(" and ",$mparts).".";
			
			return $message;
		}
	}
