<?php
	/*
		Class: BigTree\AuditTrail
			Provides an interface for the bigtree_audit_trail table.
	*/
	
	namespace BigTree;
	
	class AuditTrail
	{
		
		/*
			Function: track
				Logs a user's actions to the audit trail table.

			Parameters:
				table - The table affected by the user.
				entry - The primary key of the entry affected by the user.
				type - The action taken by the user (delete, edit, create, etc.)
				user_id - A user ID to override the logged in user's ID
		*/
		
		public static function track(string $table, string $entry, string $type, ?int $user_id = null): void
		{
			$user = !is_null($user_id) ? $user_id : Auth::user()->ID;
			
			// If this is running fron cron or something, nobody is logged in so don't track.
			if (!is_null($user)) {
				SQL::insert("bigtree_audit_trail", [
					"table" => Text::htmlEncode($table),
					"user" => $user,
					"entry" => Text::htmlEncode($entry),
					"type" => Text::htmlEncode($type)
				]);
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
		
		public static function search(?string $user = null, ?string $table = null, ?string $entry = null,
									  ?string $start = null, ?string $end = null): array
		{
			$users = $where = $parameters = [];
			$deleted_users = Setting::value("bigtree-internal-deleted-users");
			$query = "SELECT * FROM bigtree_audit_trail";
			
			if (!is_null($user)) {
				$where[] = "user = ?";
				$parameters[] = $user;
			}
			
			if (!is_null($table)) {
				$where[] = "`table` = ?";
				$parameters[] = $table;
			}
			
			if (!is_null($entry)) {
				$where[] = "entry = ?";
				$parameters[] = $entry;
			}
			
			if (!is_null($start)) {
				$where[] = "`date` >= '".date("Y-m-d H:i:s", strtotime($start))."'";
			}
			
			if (!is_null($end)) {
				$where[] = "`date` <= '".date("Y-m-d H:i:s", strtotime($end))."'";
			}
			
			if (count($where)) {
				$query .= " WHERE ".implode(" AND ", $where);
			}
			
			// Push query onto the parameters array since we're using call_user_func_array
			array_unshift($parameters, $query." ORDER BY `date` DESC");
			
			$entries = call_user_func_array("BigTree\\SQL::fetchAll", $parameters);
			
			foreach ($entries as &$entry) {
				// Check the user cache
				if (!isset($users[$entry["user"]]) && $entry["user"]) {
					$user = SQL::fetch("SELECT id,name,email,level FROM bigtree_users WHERE id = ?", $entry["user"]);
					$users[$entry["user"]] = $user;
				}
				
				if (empty($users[$entry["user"]])) {
					$entry["user"] = $deleted_users[$entry["user"]];
					$entry["user"]["deleted"] = true;
				} else {
					$entry["user"] = $users[$entry["user"]];
					$entry["user"]["deleted"] = false;
				}
			}
			
			return $entries;
		}
		
	}
