<?php
	/*
		Class: BigTree\AuditTrail
			Provides an interface for the bigtree_audit_trail table.
	*/

	namespace BigTree;
	
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
				SQL::insert("bigtree_audit_trail",array(
					"table" => $table,
					"user" => $admin->ID,
					"entry" => $entry,
					"type" => $type
				));
			}
		}

		/*
			Function: search
				Searches the audit trail for a set of data.

			Parameters:
				user - User to restrict results to (optional)
				table - Table to restrict results to (optional)
				entry - Entry to restrict results to (optional)
				start - Start date/time to restrict results to (optional)
				end - End date/time to restrict results to (optional)

			Returns:
				An array of adds/edits/deletions from the audit trail.
		*/

		static function search($user = false,$table = false,$entry = false,$start = false,$end = false) {
			$users = $items = $where = $parameters = array();
			$query = "SELECT * FROM bigtree_audit_trail";

			if ($user) {
				$where[] = "user = ?";
				$parameters[] = $user;
			}
			if ($table) {
				$where[] = "`table` = ?";
				$parameters[] = $table;
			}
			if ($entry) {
				$where[] = "entry = ?";
				$parameters[] = $entry;
			}
			if ($start) {
				$where[] = "`date` >= '".date("Y-m-d H:i:s",strtotime($start))."'";
			}
			if ($end) {
				$where[] = "`date` <= '".date("Y-m-d H:i:s",strtotime($end))."'";
			}
			if (count($where)) {
				$query .= " WHERE ".implode(" AND ",$where);
			}

			$entries = SQL::fetchAll($query." ORDER BY `date` DESC");
			foreach ($entries as &$entry) {
				// Check the user cache
				if (!$users[$entry["user"]]) {
					$user = SQL::fetch("SELECT id,name,email,level FROM bigtree_users WHERE id = ?", $entry["user"]);
					$users[$entry["user"]] = $user;
				}

				$entry["user"] = $users[$entry["user"]];
			}

			return $entries;
		}
		
	}
