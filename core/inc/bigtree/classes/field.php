<?php
	/*
		Class: BigTree\Field
			Provides an interface for BigTree field processing.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

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
		static $Namespace = ;

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
			$this->Type = BigTree::cleanFile($field["type"]);
			$this->Value = $field["value"] ?: null;

			// Give this field a unique ID within the field namespace
			static::$Count++;
			$this->ID = static::$Namespace.static::$Count;
		}

		/*
			Get Magic Method:
				Allows retrieval of the write-protected ID property.
		*/

		function __get($property) {
			if ($property == "ID") {
				return $this->ID;
			}

			return parent::__get($property);
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

			// Get the field path in case it's an extension field type
			if (strpos($this->Type,"*") !== false) {
				list($extension,$field_type) = explode("*",$this->Type);
				$field_type_path = SERVER_ROOT."extensions/$extension/field-types/$field_type/draw.php";
			} else {
				$field_type_path = BigTree::path("admin/form-field-types/draw/".$this->Type.".php");
			}

			// Backwards compatibility
			$field = $this->Array;
			$field["options"] = $this->Settings;

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
		}

		/*
			Function: process
				Processes the field's input and returns its output

			Returns:
				Field output.
		*/

		function process() {
			global $admin,$bigtree,$cms,$db;

			// Backwards compatibility
			$field = $this->Array;
			$field["options"] = $this->Settings;

			// Check if the field type is stored in an extension
			if (strpos($this->Type,"*") !== false) {
				list($extension,$field_type) = explode("*",$this->Type);
				$field_type_path = SERVER_ROOT."extensions/$extension/field-types/$field_type/process.php";
			} else {
				$field_type_path = BigTree::path("admin/form-field-types/process/".$this->Type.".php");
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
					$output = BigTree::safeEncode($this->Input);
				}
			}

			// Check validation
			if (!static::validate($output,$this->Settings["validation"])) {
				$error_message = $this->Error ?: $field["options"]["error_message"];
				$error_message = $error_message ?: static::validationErrorMessage($output,$this->Settings["validation"]);

				$bigtree["errors"][] = array(
					"field" => $this->Title,
					"error" => $error_message
				);
			}

			// Translation of internal links
			if (is_array($output)) {
				$output = BigTree::translateArray($output);
			} else {
				$output = Link::encode($output);
			}

			return $output;
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
