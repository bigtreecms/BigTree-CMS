<?php
	namespace BigTree;
	use Exception;
	
	/**
	 * @global ModuleForm $form
	 */
	
	$db_error = false;
	$is_group_based_perm = false;
	$module_access_level = null;
	$list_table = null;
	$list = [];

	// Database populated list.
	if ($this->Settings["list_type"] == "db") {
		$list_table = $this->Settings["pop-table"];
		$list_title = $this->Settings["pop-description"];
		$list_sort = $this->Settings["pop-sort"];
		
		// If debug is on we're going to check if the tables exists...
		if (Router::$Config["debug"] && !SQL::tableExists($list_table)) {
			$db_error = true;
		} else {
			try {
				$query = SQL::query("SELECT `id`,`$list_title` FROM `$list_table` ORDER BY $list_sort");
				
				// Check if we're doing module based permissions on this table.
				if (Router::$Module && !empty(Router::$Module->GroupBasedPermissions["enabled"]) &&
					!empty($form) && $form->Table == Router::$Module->GroupBasedPermissions["table"] &&
					$this->Key == Router::$Module->GroupBasedPermissions["group_field"]
				) {
					$is_group_based_perm = true;
					
					if ($this->Settings["allow-empty"] != "No") {
						$module_access_level = Auth::user()->getAccessLevel(Router::$Module);
					}
					
					while ($record = $query->fetch()) {
						// Find out whether the logged in user can access a given group, and if so, specify the access level.
						$access_level = Auth::user()->getGroupAccessLevel(Router::$Module, $record["id"]);
						
						if ($access_level) {
							$list[] = [
								"value" => $record["id"],
								"description" => $record[$list_title],
								"access_level" => $access_level
							];
						}
					}
					// We're not doing module group based permissions, get a regular list.
				} else {
					while ($record = $query->fetch()) {
						$list[] = [
							"value" => $record["id"],
							"description" => $record[$list_title]
						];
					}
				}
			} catch (Exception $e) {
				$db_error = $e->getMessage();
			}
		}
	// State List
	} elseif ($this->Settings["list_type"] == "state") {
		foreach (Field::$StateList as $abbreviation => $state) {
			$list[] = [
				"value" => $abbreviation,
				"description" => $state
			];
		}
	// Country List
	} elseif ($this->Settings["list_type"] == "country") {
		foreach (Field::$CountryList as $country) {
			$list[] = [
				"value" => $country,
				"description" => $country
			];
		}
	// Static List
	} else {
		$list = $this->Settings["list"];
	}

	// If we have a parser, send a list of the available items through it.
	if (isset($this->Settings["parser"]) && $this->Settings["parser"]) {
		$list = call_user_func($this->Settings["parser"], $list);
	}

	// If the table was deleted for a database populated list, throw an error.
	if ($db_error) {
?>
<p class="error_message">
	<?php
		if ($db_error === true) {
			echo Text::translate("The table for this field no longer exists (:table:).", true, [":table:" => $list_table]);
		} else {
			echo Text::translate($db_error);
		}
	?>
</p>
<?php
	// Draw the list.
	} else {
		$class = [];

		if ($is_group_based_perm) {
			$class[] = "gbp_select";
		}

		if ($this->Required) {
			$class[] = "required";
		}
?>
<select<?php if (count($class)) { ?> class="<?=implode(" ",$class)?>"<?php } ?> name="<?=$this->Key?>" tabindex="<?=$this->TabIndex?>" id="<?=$this->ID?>">
	<?php if ($this->Settings["allow-empty"] != "No") { ?>
	<option<?php if ($is_group_based_perm && $module_access_level) { ?> data-access-level="<?=$module_access_level?>"<?php } ?>></option>
	<?php } ?>
	<?php foreach ($list as $option) { ?>
	<option value="<?=Text::htmlEncode($option["value"])?>"<?php if ($this->Value == $option["value"]) { ?> selected="selected"<?php } ?><?php if ($option["access_level"]) { ?> data-access-level="<?=$option["access_level"]?>"<?php } ?>><?=Text::htmlEncode(Text::trimLength(strip_tags($option["description"]), 100))?></option>
	<?php } ?>
</select>
<?php
	}
?>