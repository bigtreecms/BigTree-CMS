<?php
	/*
		Class: BigTree\ModuleEmbedForm
			Provides an interface for handling BigTree module embeddable forms.
	*/

	namespace BigTree;

	class ModuleEmbedForm extends BaseObject {

		protected $EmbedCode;
		protected $Hash;
		protected $ID;
		protected $Interface;

		public $CSS;
		public $DefaultPending;
		public $DefaultPosition;
		public $Fields;
		public $Hooks;
		public $Module;
		public $RedirectURL;
		public $Tagging;
		public $ThankYouMessage;
		public $Title;

		/*
			Constructor:
				Builds a ModuleEmbedForm object referencing an existing database entry.

			Parameters:
				interface - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($interface) {
			// Passing in just an ID
			if (!is_array($interface)) {
				$interface = SQL::fetch("SELECT * FROM bigtree_module_interfaces WHERE id = ?", $interface);
			}

			// Bad data set
			if (!is_array($interface)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
			} else {
				$this->ID = $interface["id"];
				$this->Interface = new ModuleInterface($interface);
				$this->Hash = $this->Interface->Settings["hash"];

				$this->CSS = $this->Interface->Settings["css"];
				$this->DefaultPending = $this->Interface->Settings["default_pending"] ? true : false;
				$this->DefaultPosition = $this->Interface->Settings["default_position"];
				$this->Fields = $this->Interface->Settings["fields"];
				$this->Hooks = array_filter((array) $this->Interface->Settings["hooks"]);
				$this->Module = $interface["module"];
				$this->RedirectURL = $this->Interface->Settings["redirect_url"];
				$this->Table = $interface["table"]; // We can't declare this publicly because it's static for the BaseObject class
				$this->ThankYouMessage = $this->Interface->Settings["thank_you_message"];
				$this->Title = $interface["title"];

				// Generate an embed code
				$this->EmbedCode = '<div id="bigtree_embeddable_form_container_'.$this->ID.'">'.$this->Title.'</div>'."\n".'<script type="text/javascript" src="'.ADMIN_ROOT.'js/embeddable-form.js?id='.$this->ID.'&hash='.$this->Hash.'"></script>');
			}
		}

		/*
			Function: create
				Creates an embeddable form.

			Parameters:
				module - The module ID that this form relates to.
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				hooks - An array of "pre", "post", and "publish" keys that can be function names to call
				default_position - Default position for entries to the form (if the view is positioned).
				default_pending - Whether the submissions to default to pending or not ("on" or "").
				css - URL of a CSS file to include.
				redirect_url - The URL to redirect to upon completion of submission.
				thank_you_message - The message to display upon completeion of submission.

			Returns:
				A ModuleEmbedForm object.
		*/

		static function create($module,$title,$table,$fields,$hooks = array(),$default_position = "",$default_pending = "",$css = "",$redirect_url = "",$thank_you_message = "") {
			// Clean up fields to ensure proper formatting
			foreach ($fields as $key => $field) {
				$field["options"] = is_array($field["options"]) ? $field["options"] : array_filter((array)json_decode($field["options"],true));
				$field["column"] = $field["column"] ? $field["column"] : $key;
				$fields[$key] = $field;
			}
	
			// Make sure we get a unique hash
			$hash = uniqid("embeddable-form-",true);
			while (SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_module_interfaces WHERE `type` = 'embeddable-form' AND 
									 (`settings` LIKE '%\"hash\":\"".SQL::escape($hash)."\"%' OR
									  `settings` LIKE '%\"hash\": \"".SQL::escape($hash)."\"%')")) {
				$hash = uniqid("embeddable-form-",true);
			}

			$interface = ModuleInterface::create("embeddable-form",$module,$title,$table,array(
				"fields" => $fields,
				"default_position" => $default_position,
				"default_pending" => $default_pending ? "on" : "",
				"css" => Text::htmlEncode(Link::tokenize($css)),
				"hash" => $hash,
				"redirect_url" => $redirect_url ? Text::htmlEncode(Link::encode($redirect_url)) : "",
				"thank_you_message" => $thank_you_message,
				"hooks" => is_string($hooks) ? json_decode($hooks,true) : $hooks
			));

			return new ModuleEmbedForm($interface->Array);
		}

		/*
			Function: getByHash
				Returns a ModuleEmbedForm for the given hash.

			Parameters:
				hash - The hash of the form.

			Returns:
				A ModuleEmbedForm object or false.
		*/

		static function getByHash($hash) {
			$hash = SQL::escape($hash);
			$form = SQL::fetch("SELECT * FROM bigtree_module_interfaces 
								WHERE `type` = 'embeddable-form' 
								  AND (`settings` LIKE '%\"hash\":\"$hash\"%' OR `settings` LIKE '%\"hash\": \"$hash\"%')");

			return $form ? new ModuleEmbedForm($form) : false;
		}

		/*
			Function: save
				Saves object properties back to the ModuleInterface based and the database.
		*/

		function save() {
			$this->Interface->Settings = array(
				"fields" => $this->Fields,
				"default_position" => $this->DefaultPosition,
				"default_pending" => $this->DefaultPending ? "on" : "",
				"css" => Text::htmlEncode(Link::tokenize($this->CSS)),
				"hash" => $this->Hash,
				"redirect_url" => $this->RedirectURL ? Text::htmlEncode(Link::encode($this->RedirectURL)) : "",
				"thank_you_message" => $this->ThankYouMessage,
				"hooks" => $this->Hooks
			);

			$this->Interface->save();
		}

		/*
			Function: update
				Updates the embeddable form's properties and saves them back to the database.

			Parameters:
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				hooks - An array of "pre", "post", and "publish" keys that can be function names to call
				default_position - Default position for entries to the form (if the view is positioned).
				default_pending - Whether the submissions to default to pending or not ("on" or "").
				css - URL of a CSS file to include.
				redirect_url - The URL to redirect to upon completion of submission.
				thank_you_message - The message to display upon completeion of submission.
		*/

		function update($title,$table,$fields,$hooks = array(),$default_position = "",$default_pending = "",$css = "",$redirect_url = "",$thank_you_message = "") {
			foreach ($fields as $key => $field) {
				$field["options"] = json_decode($field["options"],true);
				$field["column"] = $key;
				$fields[] = $field;
			}

			$this->CSS = $css;
			$this->DefaultPending = $default_pending ? true : false;
			$this->DefaultPosition = $default_position;
			$this->Fields = $fields;
			$this->Hooks = is_string($hooks) ? json_decode($hooks,true) : $hooks;
			$this->RedirectURL = $redirect_url;
			$this->Table = $table;
			$this->ThankYouMessage = $thank_you_message;
			$this->Title = $title;

			$this->save();
		}

	}
	