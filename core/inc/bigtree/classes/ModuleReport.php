<?php
	/*
		Class: BigTree\ModuleReport
			Provides an interface for handling BigTree module reports.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 * @property-read ModuleInterface $Interface
	 * @property-read Module $Module
	 * @property-read ModuleForm $RelatedModuleForm
	 * @property-read ModuleView $RelatedModuleView
	 */
	
	class ModuleReport extends BaseObject
	{
		
		protected $ID;
		protected $Interface;
		protected $Module;
		
		public $Fields;
		public $Filters;
		public $Parser;
		public $Root;
		public $Table;
		public $Title;
		public $Type;
		public $View;
		
		/*
			Constructor:
				Builds a ModuleReport object referencing an existing database entry.

			Parameters:
				interface - An array of interface data
				module - The module for this report (passed by reference or passed as a module ID in the interface array)
		*/
		
		public function __construct(array $interface, ?Module &$module = null) {
			if (is_null($module) && !Module::exists($interface["module"])) {
				trigger_error("The module for this interface does not exist.", E_USER_ERROR);
			}
			
			$this->ID = $interface["id"];
			$this->Module = !is_null($module) ? $module : new Module($interface["module"]);
			$this->Interface = new ModuleInterface($interface, $this->Module);
			
			$this->Fields = is_array($this->Interface->Settings["fields"]) ? $this->Interface->Settings["fields"] : [];
			$this->Filters = is_array($this->Interface->Settings["filters"]) ? $this->Interface->Settings["filters"] : [];
			$this->Parser = $this->Interface->Settings["parser"];
			$this->Table = $interface["table"];
			$this->Title = $interface["title"];
			$this->Type = $this->Interface->Settings["type"];
			$this->View = $this->Interface->Settings["view"];
		}
		
		/*
			Function: create
				Creates a module report and the associated module action.

			Parameters:
				module - The module ID that this report relates to.
				title - The title of the report.
				table - The table for the report data.
				type - The type of report (csv or view).
				filters - The filters a user can use to create the report.
				fields - The fields to show in the CSV export (if type = csv).
				parser - An optional parser function to run on the CSV export data (if type = csv).
				view - A module view ID to use (if type = view).

			Returns:
				A ModuleReport object.
		*/
		
		public static function create(string $module, string $title, string $table, string $type, array $filters,
									  array $fields = [], string $parser = "", ?int $view = null): ModuleReport
		{
			$interface = ModuleInterface::create("report", $module, $title, $table, [
				"type" => $type,
				"filters" => $filters,
				"fields" => $fields,
				"parser" => $parser,
				"view" => $view
			]);
			
			return new ModuleReport($interface->Array);
		}
		
		/*
			Function: getRelatedModuleForm
				Returns the form for the same table as this report.

			Returns:
				A ModuleForm object or null.
		*/
		
		public function getRelatedModuleForm(): ?ModuleForm
		{
			foreach ($this->Module->Forms as $form) {
				if ($form->Table == $this->Table) {
					return $form;
				}
			}
			
			return null;
		}
		
		/*
			Function: getRelatedModuleView
				Returns the view for the same table as this report.

			Returns:
				A ModuleView object or null.
		*/
		
		public function getRelatedModuleView(): ?ModuleView
		{
			if (!empty($this->View) && isset($this->Module->Views[$this->View])) {
				return $this->Module->Views[$this->View];
			}
			
			foreach ($this->Module->Views as $view) {
				if ($view->Table == $this->Table) {
					return $view;
				}
			}
			
			return null;
		}
		
		/*
			Function: getResults
				Returns rows from the table that match the filters provided.

			Parameters:
				filter_data - The submitted filters to run.
				sort_field - The field to sort by (defaults to id)
				sort_direction - The direction to sort by (defaults to DESC)

			Returns:
				An array of rows from the report's table.
		*/
		
		public function getResults(array $filter_data, string $sort_field = "id",
								   string $sort_direction = "DESC"): array
		{
			$where = $items = $parsers = $poplists = [];
			$view = $this->RelatedModuleView;
			$form = $this->RelatedModuleForm;
			
			// Prevent SQL injection
			$sort_field = "`".str_replace("`", "", $sort_field)."`";
			$sort_direction = ($sort_direction == "ASC") ? "ASC" : "DESC";
			
			// Figure out if we have db populated lists and parsers
			if ($this->Type == "view") {
				foreach ($view->Fields as $key => $field) {
					if ($field["parser"]) {
						$parsers[$key] = $field["parser"];
					}
				}
			}
			
			if (is_array($form->Fields)) {
				foreach ($form->Fields as $key => $field) {
					if ($field["type"] == "list" && $field["settings"]["list_type"] == "db") {
						$poplists[$key] = [
							"description" => $form->Fields[$key]["settings"]["pop-description"],
							"table" => $form->Fields[$key]["settings"]["pop-table"]
						];
					}
				}
			}
			
			$query = "SELECT * FROM `".$this->Table."`";
			
			foreach ($this->Filters as $id => $filter) {
				if ($filter_data[$id]) {
					if ($filter["type"] == "search") {
						// Search field
						$where[] = "`$id` LIKE '%".SQL::escape($filter_data[$id])."%'";
					} elseif ($filter["type"] == "dropdown") {
						// Dropdown
						$where[] = "`$id` = '".SQL::escape($filter_data[$id])."'";
					} elseif ($filter["type"] == "boolean") {
						// Yes / No / Both
						if ($filter_data[$id] == "Yes") {
							$where[] = "(`$id` = 'on' OR `$id` = '1' OR `$id` != '')";
						} elseif ($filter_data[$id] == "No") {
							$where[] = "(`$id` = '' OR `$id` = '0' OR `$id` IS NULL)";
						}
					} elseif ($filter["type"] == "date-range") {
						// Date Range
						if ($filter_data[$id]["start"]) {
							$where[] = "`$id` >= '".SQL::escape($filter_data[$id]["start"])."'";
						}
						
						if ($filter_data[$id]["end"]) {
							$where[] = "`$id` <= '".SQL::escape($filter_data[$id]["end"])."'";
						}
					}
				}
			}
			
			if (count($where)) {
				$query .= " WHERE ".implode(" AND ", $where);
			}
			
			$query = SQL::query($query." ORDER BY $sort_field $sort_direction");
			
			while ($item = $query->fetch()) {
				$item = Link::decode($item);
				
				foreach ($item as $key => $value) {
					if ($poplists[$key]) {
						$item[$key] = SQL::fetchSingle("SELECT `".$poplists[$key]["description"]."` 
														FROM `".$poplists[$key]["table"]."` 
														WHERE id = ?", $value);
					}
					
					if ($parsers[$key]) {
						$item[$key] = Module::runParser($item, $value, $parsers[$key]);
					}
				}
				
				$items[] = $item;
			}
			
			// If the field we sort by was a poplist or parser, we need to resort.
			if (isset($parsers[$sort_field]) || isset($poplists[$sort_field])) {
				$sort_values = [];
				
				foreach ($items as $item) {
					$sort_values[] = $item[$sort_field];
				}
				
				if ($sort_direction == "ASC") {
					array_multisort($sort_values, SORT_ASC, $items);
				} else {
					array_multisort($sort_values, SORT_DESC, $items);
				}
			}
			
			// If there is a data parser we need to run it
			if (!empty($this->Parser) && function_exists($this->Parser)) {
				$items = call_user_func($this->Parser, $items);
			}
			
			return $items;
		}
		
		/*
			Function: save
				Saves the object's properties back to the database and updates InterfaceSettings.
		*/
		
		public function save(): ?bool
		{
			$this->Interface->Settings = [
				"type" => $this->Type,
				"filters" => array_filter((array) $this->Filters),
				"fields" => array_filter((array) $this->Fields),
				"parser" => $this->Parser,
				"view" => $this->View ?: null
			];
			$this->Interface->Table = $this->Table;
			$this->Interface->Title = $this->Title;
			$this->Interface->save();
			
			return true;
		}
		
		/*
			Function: update
				Updates the module report's properties and saves them back to the interface settings and database.

			Parameters:
				title - The title of the report.
				table - The table for the report data.
				type - The type of report (csv or view).
				filters - The filters a user can use to create the report.
				fields - The fields to show in the CSV export (if type = csv).
				parser - An optional parser function to run on the CSV export data (if type = csv).
				view - A module view ID to use (if type = view).
		*/
		
		public function update(string $title, string $table, string $type, array $filters, ?array $fields = null,
							   string $parser = "", ?int $view = null)
		{
			$this->Fields = $fields;
			$this->Filters = $filters;
			$this->Parser = $parser;
			$this->Table = $table;
			$this->Title = $title;
			$this->Type = $type;
			$this->View = $view;
			$this->save();
		}
		
	}
