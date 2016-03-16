<?php
	/*
		Class: BigTree\AuditTrail
			Provides an interface for the bigtree_audit_trail table.
	*/

	namespace BigTree;
	
	use BigTreeAdmin;
	use BigTreeCMS;

	class AuditTrail {

		/*
			Function: track
				Logs a user's actions to the audit trail table.

			Parameters:
				table - The table affected by the user.
				entry - The primary key of the entry affected by the user.
				type - The action taken by the user (delete, edit, create, etc.)
		*/

		static function track($table,$entry,$type) {
			global $admin;

			// If this is running fron cron or something, nobody is logged in so don't track.
			if (get_class($admin) == "BigTreeAdmin" && $admin->ID) {
				BigTreeAdmin::$DB->insert("bigtree_audit_trail",array(
					"table" => $table,
					"user" => $admin->ID,
					"entry" => $entry,
					"type" => $type
				));
			}
		}
		
	}
