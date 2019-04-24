<?php
	/*
		Class: BigTree\Lock
			Provides an interface for handling BigTree locks.
	*/
	
	namespace BigTree;
	
	class Lock extends SQLObject
	{
		
		public static $Table = "bigtree_locks";
		
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
		
		public function __construct($lock = null)
		{
			if ($lock !== null) {
				// Passing in just an ID
				if (!is_array($lock)) {
					$lock = SQL::fetch("SELECT * FROM bigtree_locks WHERE id = ?", $lock);
				}
				
				// Bad data set
				if (!is_array($lock)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $lock["id"];
					$this->ItemID = $lock["item_id"];
					$this->LastAccessed = $lock["last_accessed"];
					$this->Table = $lock["table"];
					$this->User = $lock["user"];
				}
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
				A Lock object or null if the logged in user does not own the lock.
		*/
		
		public static function enforce(string $table, string $id, string $include, bool $force = false): ?Lock
		{
			global $admin, $bigtree, $cms, $db;
			
			$user = Auth::user()->ID;
			
			// Make sure a user is logged in
			if (is_null($user)) {
				throw new \Exception("Lock::enforce cannot be called outside logged-in user context.");
			}
			
			$lock = SQL::fetch("SELECT * FROM bigtree_locks WHERE `table` = ? AND item_id = ?", $table, $id);
			
			// Not currently locked, lock it
			if (empty($lock)) {
				$id = SQL::insert("bigtree_locks", [
					"table" => $table,
					"item_id" => $id,
					"user" => $user
				]);
				
				return new Lock($id);
			}
			
			// Lock exists and the logged-in user doesn't own it (and it's not old) and we're not forcing our way through
			if ($lock["user"] != $user && strtotime($lock["last_accessed"]) > (time() - 300) && !$force) {
				$user = new User($lock["user"]);
				
				$locked_by = $user->Array;
				$last_accessed = $lock["last_accessed"];
				
				include Router::getIncludePath($include);
				Auth::stop();
				
				return null;
			}
			
			// We're taking over the lock, force was sent or this is an old lock
			SQL::update("bigtree_locks", $lock["id"], [
				"user" => $user
			]);
			
			return new Lock($lock["id"]);
		}
		
		/*
			Function: refresh
				Refreshes a lock's access time and user.

			Parameters:
				table - SQL table of the locked entry.
				id - The ID of the locked entry.

		*/
		
		public static function refresh(string $table, string $id): void
		{
			$user = Auth::user()->ID;
			
			// Make sure a user is logged in
			if (is_null($user)) {
				throw new \Exception("Lock::refresh cannot be called outside logged-in user context.");
			}
			
			// Update the access time and user
			SQL::update("bigtree_locks", ["table" => $table, "item_id" => $id, "user" => $user], ["last_accessed" => "NOW()"]);
		}
		
		/*
			Function: remove
				Removes a lock from a table entry.

			Parameters:
				table - SQL table of the locked entry.
				id - The ID of the locked entry.
		*/
		
		public static function remove(string $table, string $id): void
		{
			SQL::delete("bigtree_locks", ["table" => $table, "item_id" => $id]);
		}
		
	}
