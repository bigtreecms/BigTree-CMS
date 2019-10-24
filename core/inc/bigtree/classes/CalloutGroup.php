<?php
	/*
		Class: BigTree\CalloutGroup
			Provides an interface for handling BigTree callout groups.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 */
	
	class CalloutGroup extends JSONObject
	{
		
		protected $ID;
		
		public $Callouts;
		public $Name;
		
		public static $Store = "callout-groups";
		
		/*
			Constructor:
				Builds a CalloutGroup object referencing an existing database entry.

			Parameters:
				group - Either an ID (to pull a record) or an array (to use the array as the record)
				on_fail - An optional callable to call on non-exist or bad data (rather than triggering an error).
		*/
		
		public function __construct($group = null, ?callable $on_fail = null)
		{
			if ($group !== null) {
				// Passing in just an ID
				if (!is_array($group)) {
					$group = DB::get("callout-groups", $group);
				}
				
				// Bad data set
				if (!is_array($group)) {
					if ($on_fail) {
						return $on_fail();
					} else {
						trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
					}
				} else {
					$this->ID = $group["id"];
					$this->Name = $group["name"];
					$this->Callouts = array_filter((array) $group["callouts"]);
				}
			}
		}
		
		/*
			Function: create
				Creates a callout group.

			Parameters:
				name - The name of the group.
				callouts - An array of callout IDs to assign to the group.

			Returns:
				A BigTree\CalloutGroup object.
		*/
		
		public static function create(string $name, array $callouts = []): CalloutGroup {
			// Order callouts alphabetically by ID
			sort($callouts);
			
			// Insert group
			$id = DB::insert("callout-groups", [
				"name" => Text::htmlEncode($name),
				"callouts" => $callouts
			]);
			
			AuditTrail::track("config:callout-groups", $id, "add", "created");
			
			return new CalloutGroup($id);
		}
		
		/*
			Function: delete
				Deletes this callout group.
		
			Returns:
				True if successful
		*/
		
		public function delete(): ?bool {
			if (DB::delete("callout-groups", $this->ID)) {
				AuditTrail::track("config:callout-groups", $this->ID, "delete", "deleted");
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool {
			$this->Callouts = array_filter((array) $this->Callouts);
			sort($this->Callouts);
			
			$insert_data = [
				"name" => Text::htmlEncode($this->Name),
				"callouts" => $this->Callouts
			];
			
			if (empty($this->ID)) {
				$this->ID = DB::insert("callout-groups", $insert_data);
				AuditTrail::track("config:callout-groups", $this->ID, "add", "created");
			} else {
				DB::update("callout-groups", $this->ID, $insert_data);
				AuditTrail::track("config:callout-groups", $this->ID, "update", "updated");
			}
			
			return true;
		}
		
		/*
			Function: update
				Updates the callout group's name and callout list properties and saves them back to the database.

			Parameters:
				name - Name string.
				callouts - An array of callout IDs to assign to the group.
		*/
		
		public function update(string $name, array $callouts): ?bool {
			$this->Name = $name;
			$this->Callouts = $callouts;
			
			return $this->save();
		}
		
	}
