<?php
	/*
		Class: BigTree\Lock
			Provides an interface for handling BigTree locks.
	*/

	namespace BigTree;

	use BigTree;

	class Lock extends BaseObject {

		static $Table = "bigtree_locks";

		protected $ID;

		public $ItemID;
		public $LastAccessed;
		public $User;

		/*
			Constructor:
				Builds a Lock object referencing an existing database entry.

			Parameters:
				lock - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($lock) {
			// Passing in just an ID
			if (!is_array($lock)) {
				$lock = SQL::fetch("SELECT * FROM bigtree_locks WHERE id = ?", $lock);
			}

			// Bad data set
			if (!is_array($lock)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $lock["id"];
				$this->ItemID = $lock["item_id"];
				$this->LastAccessed = $lock["last_accessed"];
				$this->Table = $lock["table"];
				$this->User = $lock["user"];
			}
		}

		/*
			Function: enforce
				Checks if a lock exists for the given table and ID.
				If a lock exists and it's currently active, stops page execution and shows the lock page.
				If a lock is the logged-in user's, refreshes the lock.
				If there is no lock, creates one.

			Parameters:
				table - The table to check.
				id - The id of the entry to check.
				include - The lock page to include (relative to /core/ or /custom/)
				force - Whether to force through the lock or not.

			Returns:
				A Lock object.
		*/

		static function enforce($table,$id,$include,$force = false) {
			global $admin,$bigtree,$cms,$db;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || !$admin->ID) {
				throw new Exception("Lock::enforce cannot be called outside logged-in user context.");
				return false;
			}

			$lock = static::$DB->fetch("SELECT * FROM bigtree_locks WHERE `table` = ? AND item_id = ?", $table, $id);

			// Lock exists and the logged-in user doesn't own it (and it's not old) and we're not forcing our way through
			if ($lock && $lock["user"] != $admin->ID && strtotime($lock["last_accessed"]) > (time() - 300) && !$force) {
				$user = new User($lock["user"]);

				$locked_by = $user->Array;
				$last_accessed = $lock["last_accessed"];
				
				include BigTree::path($include);
				$admin->stop();
				
				return false;
			}

			// We're taking over the lock, force was sent or this is an old lock
			if ($lock) {
				SQL::update("bigtree_locks",$lock["id"],array(
					"user" => $admin->ID
				));

				return new Lock($lock["id"]);
			
			// No lock, we're creating a new one
			} else {
				$id = SQL::insert("bigtree_locks",array(
					"table" => $table,
					"item_id" => $id,
					"user" => $this->ID
				));

				return new Lock($id);
			}
		}

		/*
			Function: refresh
				Refreshes a lock's access time and user.

			Parameters:
				table - SQL table of the locked entry.
				id - The ID of the locked entry.

		*/

		static function refresh($table,$id) {
			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || !$admin->ID) {
				throw new Exception("Lock::refresh cannot be called outside logged-in user context.");
				return false;
			}

			// Update the access time and user
			SQL::update("bigtree_locks",array("table" => $table,"item_id" => $id, "user" => $admin->ID), array("last_accessed" => "NOW()"));
		}

		/*
			Function: remove
				Removes a lock from a table entry.

			Parameters:
				table - SQL table of the locked entry.
				id - The ID of the locked entry.
		*/

		static function unlock($table,$id) {
			SQL::delete("bigtree_locks",array("table" => $table, "item_id" => $id));
		}
		
	}
