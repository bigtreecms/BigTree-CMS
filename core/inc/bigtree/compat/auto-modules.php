<?php
	use BigTree\SQL;

	/*
		Class: BigTreeAutoModule
			Handles functions for auto module forms / views created in Developer.
	*/

	class BigTreeAutoModule {
		
		/*
			Function: cacheNewItem
				Caches a new database entry by investigating associated views.
			
			Parameters:
				id - The id of the new item.
				table - The table the new item is in.
				pending - Whether this is actually a pending entry or not.
			
			See Also:
				<recacheItem>
		*/
		
		static function cacheNewItem($id, $table, $pending = false) {
			BigTree\ModuleView::cacheForAll($id, $table, $pending);
		}
		
		static function cacheRecord() {
			trigger_error("BigTreeAutoModule::cacheRecord is not meant to be called directly. Please use BigTree\\ModuleView::cacheForAll", E_USER_WARNING);

			return false;
		}
		
		/*
			Function: cacheViewData
				Grabs all the data from a view and does parsing on it based on automatic assumptions and manual parsers.
			
			Parameters:
				view - The view entry to cache data for.
		*/
		
		static function cacheViewData($view) {
			$view = new BigTree\ModuleView($view);
			$view->cacheAllData();
		}
		
		/*
			Function: changeExists
				Checks to see if a change exists for a given item in the bigtree_pending_changes table.

			Parameters:
				table - The table the item is from.
				id - The ID of the item.

			Returns:
				true or false
		*/

		static function changeExists($table, $id) {
			return BigTree\PendingChange::existsForEntry($table, $id);
		}

		/*
			Function: clearCache
				Clears the cache of a view or all views with a given table.
			
			Parameters:
				view - The view id or view entry to clear the cache for or a table to find all views for (and clear their caches).
		*/
		
		static function clearCache($view) {
			if (is_array($view) || is_numeric($view)) {
				$view = new BigTree\ModuleView($view);
				$view->clearCache();
			} else {
				BigTree\ModuleView::clearCacheForTable($view);
			}
		}
		
		/*
			Function: createItem
				Creates an entry in the database for an auto module form.
		
			Parameters:
				table - The table to put the data in.
				data - An array of form data to enter into the table. This function determines what data in the array applies to a column in the database and discards the rest.
				many_to_many - Many to many relationship entries.
				tags - Tags for the entry.
			
			Returns:
				The id of the new entry in the database.
		*/

		static function createItem($table, $data, $many_to_many = array(), $tags = array()) {
			// Create a generic module form
			$form = new BigTree\ModuleForm(array("table" => $table));

			return $form->createEntry($data, $many_to_many, $tags);
		}
		
		/*
			Function: createPendingItem
				Creates an entry in the bigtree_pending_changes table for an auto module form.
		
			Parameters:
				module - The module for the entry.
				table - The table to put the data in.
				data - An array of form data to enter into the table. This function determines what data in the array applies to a column in the database and discards the rest.
				many_to_many - Many to many relationship entries.
				tags - Tags for the entry.
				publish_hook - A function to call when this change is published from the Dashboard.
				embedded_form - If this is being called from an embedded form, set the user to NULL (defaults to false)
			
			Returns:
				The id of the new entry in the bigtree_pending_changes table.
		*/

		static function createPendingItem($module, $table, $data, $many_to_many = array(), $tags = array(), $publish_hook = null, $embedded_form = false) {
			// Create fake module form
			if ($embedded_form) {
				$form = new BigTree\ModuleEmbedForm(array(
					"module" => $module,
					"table" => $table,
					"settings" => json_encode(array("hooks" => array("publish" => $publish_hook)))
				));
			} else {
				$form = new BigTree\ModuleForm(array(
					"module" => $module,
					"table" => $table,
					"settings" => json_encode(array("hooks" => array("publish" => $publish_hook)))
				));
			}
			
			return $form->createPendingEntry($data, $many_to_many, $tags);
		}
		
		/*
			Function: deleteItem
				Deletes an item from the given table and removes any pending changes, then uncaches it from its views.
			
			Parameters:
				table - The table to delete an entry from.
				id - The id of the entry.
		*/

		static function deleteItem($table, $id) {
			// Create fake module form
			$form = new BigTree\ModuleForm(array("table" => $table));

			$form->deleteEntry($id);
		}
		
		/*
			Function: deletePendingItem
				Deletes a pending item from bigtree_pending_changes and uncaches it.
			
			Parameters:
				table - The table the entry would have been in (should it have ever been published).
				id - The id of the pending entry.
		*/
		
		static function deletePendingItem($table, $id) {
			// Create fake module form
			$form = new BigTree\ModuleForm(array("table" => $table));

			$form->deletePendingEntry($id);
		}

		/*
			Function: getDependentViews
				Returns all views that have a dependence on a given table.

			Parameters:
				table - Table name

			Returns:
				An array of view rows from bigtree_module_interfaces
		*/

		static function getDependentViews($table) {
			$table = SQL::escape($table);

			return SQL::fetchAll("SELECT * FROM bigtree_module_interfaces 
											  WHERE `type` = 'view' AND `settings` LIKE '%$table%'");
		}

		/*
			Function: getEditAction
				Returns a module action for the given module and form IDs.

			Parameters:
				module - Module ID
				form - Form ID

			Returns:
				A bigtree_module_actions entry.
		*/

		static function getEditAction($module, $form) {
			$module = new BigTree\Module($module);
			$action = $module->getEditAction($form);

			return $action ? $action->Array : false;
		}

		/*
			Function: getEmbedForm
				Returns a module embeddable form.
			
			Parameters:
				id - The id of the form.
			
			Returns:
				A module form entry with fields decoded.
		*/

		static function getEmbedForm($id) {
			$form = new BigTree\ModuleEmbedForm($id);

			return $form ? $form->Array : false;
		}

		/*
			Function: getEmbedFormByHash
				Returns a module embeddable form.
			
			Parameters:
				hash - The hash of the form.
			
			Returns:
				A module form entry with fields decoded.
		*/

		static function getEmbedFormByHash($hash) {
			$form = BigTree\ModuleEmbedForm::getByHash($hash);

			return $form ? $form->Array : false;
		}
		
		/*
			Function: getFilterQuery
				Returns a query string that is used for searching views based on group permissions.
				Can only be called when logged into the admin.
			
			Parameters:
				view - The view to create a filter for.
			
			Returns:
				A set of MySQL statements that filter out information the user cannot access.
		*/
		
		static function getFilterQuery($view) {
			$view = new BigTree\ModuleView($view);

			return $view ? $view->FilterQuery : "";
		}
		
		/*
			Function: getForm
				Returns a module form.
			
			Parameters:
				id - The id of the form.
			
			Returns:
				A module form entry with fields decoded.
		*/

		static function getForm($id) {
			$form = new BigTree\ModuleForm($id);

			return $form ? $form->Array : false;
		}
		
		/*
			Function: getGroupsForView
				Returns all groups in the view cache for a view.
			
			Parameters:
				view - The view entry.
			
			Returns:
				An array of groups.
		*/
		
		static function getGroupsForView($view) {
			$view = new BigTree\ModuleView($view);

			return $view ? $view->Groups : false;
		}

		/*
			Function: getItem
				Returns an entry from a table with all its related information.
				If a pending ID is passed in (prefixed with a p) getPendingItem is called instead.

			Parameters:
				table - The table to pull the entry from.
				id - The id of the entry.

			Returns:
				An array with the following key/value pairs:
				"item" - The entry from the table with pending changes already applied.
				"tags" - A list of tags for the entry.
				
				Returns false if the entry could not be found.
		*/

		static function getItem($table, $id) {
			// Create fake form
			$form = new BigTree\ModuleForm(array("table" => $table));

			return $form->getEntry($id);
		}
		
		/*
			Function: getModuleForForm
				Returns the associated module id for the given form.
				DEPRECATED - Please use getModuleForInterface.
			
			Parameters:
				form - Either a form entry or form id.
			
			Returns:
				The id of the module the form is a member of.

			See Also:
				<getModuleForInterface>
		*/
		
		static function getModuleForForm($form) {
			return self::getModuleForInterface($form);
		}

		/*
			Function: getModuleForInterface
				Returns the associated module id for the given interface.
			
			Parameters:
				interface - Either a interface array or interface id.
			
			Returns:
				The id of the module the interface is a member of.
		*/
		
		static function getModuleForInterface($interface) {
			// May already have the info we need
			if (is_array($interface)) {
				if ($interface["module"]) {
					return $interface["module"];
				}
				$interface = $interface["id"];
			}

			return SQL::fetchSingle("SELECT module FROM bigtree_module_actions WHERE interface = ?", $interface);
		}
		
		/*
			Function: getModuleForView
				Returns the associated module id for the given view.
				DEPRECATED - Please use getModuleForInterface.
			
			Parameters:
				view - Either a view entry or view id.
			
			Returns:
				The id of the module the view is a member of.

			See Also:
				<getModuleForInterface>
		*/

		static function getModuleForView($view) {
			return self::getModuleForInterface($view);
		}
		
		/*
			Function: getPendingItem
				Gets an entry from a table with all its related information and pending changes applied.
			
			Parameters:
				table - The table to pull the entry from.
				id - The id of the entry.
			
			Returns:
				An array with the following key/value pairs:
				"item" - The entry from the table with pending changes already applied.
				"mtm" - A list of many to many pending changes.
				"tags" - A list of tags for the entry.
				"status" - Whether the item is pending ("pending"), published ("published"), or has changes ("updated") awaiting publish.
				
				Returns false if the entry could not be found.
		*/

		static function getPendingItem($table, $id) {
			// Create fake form
			$form = new BigTree\ModuleForm(array("table" => $table));

			return $form->getPendingEntry($id);
		}

		/*
			Function: getRelatedFormForReport
				Returns the form for the same table as the given report.
			
			Parameters:
				report - A report entry.
			
			Returns:
				A form entry with fields decoded.
		*/

		static function getRelatedFormForReport($report) {
			$report = new BigTree\ModuleReport($report);
			$form = $report->RelatedModuleForm;

			return $form ? $form->Array : false;
		}
		
		/*
			Function: getRelatedFormForView
				Returns the form for the same table as the given view.
			
			Parameters:
				view - A view entry.
			
			Returns:
				A form entry with fields decoded.
		*/

		static function getRelatedFormForView($view) {
			$view = new BigTree\ModuleView($view);
			$form = $view->RelatedModuleForm;

			return $form ? $form->Array : false;
		}
		
		/*
			Function: getRelatedViewForForm
				Returns the view for the same table as the given form.
			
			Parameters:
				form - A form entry.
			
			Returns:
				A view entry.
		*/

		static function getRelatedViewForForm($form) {
			$form = new BigTree\ModuleForm($form);
			$view = $form->RelatedModuleView;

			return $view ? $view->Array : false;
		}

		/*
			Function: getRelatedViewForReport
				Returns the view for the same table as the given report.
			
			Parameters:
				report - A report entry.
			
			Returns:
				A view entry.
		*/

		static function getRelatedViewForReport($report) {
			$report = new BigTree\ModuleReport($report);
			$view = $report->RelatedModuleView;

			return $view ? $view->Array : false;
		}

		/*
			Function: getReport
				Returns a report with the filters and fields decoded.

			Parameters:
				id - The ID of the report

			Returns:
				An array of report information.
		*/

		static function getReport($id) {
			$report = new BigTree\ModuleReport($id);

			return $report ? $report->Array : false;
		}

		/*
			Function: getReportResults
				Returns rows from the table that match the filters provided.

			Parameters:
				report - A report interface entry.
				view - A view interface array.
				form - A form interface array.
				filters - The submitted filters to run.
				sort_field - The field to sort by.
				sort_direction - The direction to sort by.

			Returns:
				An array of entries from the report's table.
		*/

		static function getReportResults($report, $view, $form, $filters, $sort_field = "id", $sort_direction = "DESC") {
			$report = new BigTree\ModuleReport($report);

			return $report->getResults($filters, $sort_field, $sort_direction);
		}
		
		/*
			Function: getSearchResults
				Returns results from the bigtree_module_view_cache table.
		
			Parameters:
				view - The view to pull data for.
				page - The page of data to retrieve.
				query - The query string to search against.
				sort - The column and direction to sort.
				group - The group to pull information for.
		
			Returns:
				An array containing "pages" with the number of result pages and "results" with the results for the given page.
		*/
		
		static function getSearchResults($view, $page = 1, $query = "", $sort = "id DESC", $group = false) {
			$view = new BigTree\ModuleView($view);

			return $view->searchData($page, $query, $sort, $group);
		}
		
		/*
			Function: getTagsForEntry
				Returns the tags for an entry.
				
			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
				full - Whether to return a full tag array or just the tag string (defaults to full tag array)
			
			Returns:
				An array ot tags from bigtree_tags.
		*/
		
		public static function getTagsForEntry($table, $id, $full = true) {
			if ($full) {
				return SQL::fetchAll("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel
									  ON bigtree_tags_rel.tag = bigtree_tags.id
									  WHERE bigtree_tags_rel.`table` = ?
									    AND bigtree_tags_rel.`entry` = ?
						  			  ORDER BY bigtree_tags.tag ASC", $table, $id);
			}
			
			return SQL::fetchAllSingle("SELECT bigtree_tags.tag FROM bigtree_tags JOIN bigtree_tags_rel
									    ON bigtree_tags_rel.tag = bigtree_tags.id
									    WHERE bigtree_tags_rel.`table` = ?
									      AND bigtree_tags_rel.`entry` = ?
						  			    ORDER BY bigtree_tags.tag ASC", $table, $id);
		}
		
		/*
			Function: getView
				Returns a view.
			
			Parameters:
				id - The id of the view.
				decode_ipl - Whether we want to decode internal page link on the preview url (defaults to true)
				
			Returns:
				A view entry with actions, settings, and fields decoded.  fields also receive a width column for the view.
		*/

		static function getView($id) {
			$view = new BigTree\ModuleView($id);
			$view->calculateFieldWidths();

			return $view->Array;
		}
		
		/*
			Function: getViewData
				Gets a list of data for a view.
			
			Parameters:
				view - The view entry to pull data for.
				sort - The sort direction, defaults to most recent.
				type - Whether to get only active entries, pending entries, or both.
				group - The group to get data for (defaults to all).
			
			Returns:
				An array of items from bigtree_module_view_cache.
		*/
		
		static function getViewData($view, $sort = "id DESC", $type = "both", $group = false) {
			$view = new BigTree\ModuleView($view["id"]);

			return $view->getData($sort, $type, $group);
		}
		
		/*
			Function: getViewDataForGroup
				Gets a list of data for a view in a given group.
			
			Parameters:
				view - The view entry to pull data for.
				group - The group to get data for.
				sort - The sort direction, defaults to most recent.
				type - Whether to get only active entries, pending entries, or both.
			
			Returns:
				An array of items from bigtree_module_view_cache.
		*/
		
		static function getViewDataForGroup($view, $group, $sort, $type = "both") {
			return static::getViewData($view, $sort, $type, $group);
		}
		
		/*
			Function: getViewForTable
				Gets a view for a given table for showing change lists in Pending Changes.
			
			Parameters:
				table - Table name.
			
			Returns:
				A view entry with settings, and fields decoded and field widths set for Pending Changes.
		*/
		
		static function getViewForTable($table) {
			$view = SQL::fetch("SELECT * FROM bigtree_module_interfaces WHERE `type` = 'view' AND `table` = ?", $table);

			if (!$view) {
				return false;
			}

			$view = new BigTree\ModuleView($view);

			return $view->getArray($view->PreviewURL ? 578 : 633);
		}
		
		/*
			Function: parseViewData
				Parses data and returns the parsed columns (runs parsers and populated lists).
			
			Parameters:
				view - The view to parse items for.
				items - An array of entries to parse.
			
			Returns:
				An array of parsed entries.
		*/

		static function parseViewData($view, $items) {
			$view = new BigTree\ModuleView($view);

			return $view->parseData($items);
		}

		/*
			Function: publishPendingItem
				Publishes a pending item and caches it.
				
			Parameters:
				table - The table to store the entry in.
				id - The id of the pending entry.
				data - The form data to create an entry with.
				many_to_many - Many to Many information
				tags - Tag information
			
			Returns:
				The id of the new entry.
		*/
		
		static function publishPendingItem($table, $id, $data, $many_to_many = array(), $tags = array()) {
			return self::createItem($table, $data, $many_to_many, $tags, $id);
		}
		
		/*
			Function: recacheItem
				Re-caches a database entry.
			
			Parameters:
				id - The id of the entry.
				table - The table the entry is in.
				pending - Whether the entry is pending or not.
			
			See Also:
				<cacheNewItem>
		*/
		
		static function recacheItem($id, $table, $pending = false) {
			return BigTree\ModuleView::cacheForAll($id, $table, $pending);
		}

		/*
			Function: sanitizeData
				Processes form data into values understandable by the MySQL table.
			
			Parameters:
				table - The table to sanitize data for
				data - Array of key->value pairs
				existing_description - If the table has already been described, pass it in instead of making sanitizeData do it twice. (defaults to false)
			
			Returns:
				Array of data safe for MySQL.
		*/
		
		static function sanitizeData($table, $data, $existing_description = false) {
			return BigTree\SQL::prepareData($table, $data, $existing_description);
		}

		/*
			Function: submitChange
				Creates a change request for an item and caches it.
				Can only be called when logged into the admin.
			
			Parameters:
				module - The module for the entry.
				table - The table the entry is stored in.
				id - The id of the entry.
				data - The change request data.
				many_to_many - The many to many changes.
				tags - The tag changes.
				publish_hook - A function to call when this change is published from the Dashboard.
			
			Returns:
				The id of the pending change.
		*/
		
		static function submitChange($module, $table, $id, $data, $many_to_many = array(), $tags = array(), $publish_hook = null) {
			// Create fake module form
			$form = new BigTree\ModuleForm(array(
				"module" => $module,
				"table" => $table,
				"settings" => json_encode(array("hooks" => array("publish" => $publish_hook)))
			));

			return $form->createChangeRequest($id, $data, $many_to_many, $tags);
		}

		/*
			Function: track
				Used internally by the class to facilitate audit trail tracking when a logged in user is making a call.

			Parameters:
				table - The table that is being changed
				id - The id of the record being changed
				action - The action being taken
		*/

		static function track($table, $id, $action) {
			BigTree\AuditTrail::track($table, $id, $action);
		}
		
		/*
			Function: uncacheItem
				Removes a database entry from the view cache.
			
			Parameters:
				id - The id of the entry.
				table - The table the entry is in.
		*/
		
		static function uncacheItem($id, $table) {
			BigTree\ModuleView::uncacheForAll($id, $table);
		}

		/*
			Function: updateItem
				Update an entry and cache it.
			
			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
				data - The data to update in the entry.
				many_to_many - Many To Many information
				tags - Tag information.
		*/
		
		static function updateItem($table, $id, $data, $many_to_many = array(), $tags = array()) {
			// Create a generic module form
			$form = new BigTree\ModuleForm(array("table" => $table));

			$form->updateEntry($id, $data, $many_to_many, $tags);
		}

		/*
			Function: updatePendingItemField
				Update a pending item's field with a given value.
			
			Parameters:
				id - The id of the entry.
				field - The field to change.
				value - The value to set.
		*/
		
		static function updatePendingItemField($id, $field, $value) {
			BigTree\ModuleForm::updatePendingEntryField($id, $field, $value);
		}

		/*
			Function: validate
				Validates a form element based on its validation requirements.
			
			Parameters:
				data - The form's posted data for a given field.
				type - Validation requirements (required, numeric, email, link).
		
			Returns:
				True if validation passed, otherwise false.
			
			See Also:
				<errorMessage>
		*/
		
		static function validate($data, $type) {
			return BigTree\Field::validate($data, $type);
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
		
		static function validationErrorMessage($data, $type) {
			return BigTree\Field::validationErrorMessage($data, $type);
		}
	}
