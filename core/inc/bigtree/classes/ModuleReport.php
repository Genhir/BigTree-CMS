<?php
	/*
		Class: BigTree\ModuleReport
			Provides an interface for handling BigTree module reports.
	*/

	namespace BigTree;

	class ModuleReport extends ModuleInterface {

		protected $ID;
		protected $InterfaceSettings;

		public $Fields;
		public $Filters;
		public $Module;
		public $Parser;
		public $Title;
		public $Type;
		public $View;

		/*
			Constructor:
				Builds a ModuleReport object referencing an existing database entry.

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
				$this->InterfaceSettings = (array) @json_decode($interface["settings"],true);

				$this->Fields = $this->InterfaceSettings["fields"];
				$this->Filters = $this->InterfaceSettings["filters"];
				$this->Module = $interface["module"];
				$this->Parser = $this->InterfaceSettings["parser"];
				$this->Table = $interface["table"]; // We can't declare this publicly because it's static for the BaseObject class
				$this->Title = $interface["title"];
				$this->Type = $this->InterfaceSettings["type"];
				$this->View = $this->InterfaceSettings["view"];
			}
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
				The id of the report.
		*/

		static function createModuleReport($module,$title,$table,$type,$filters,$fields = "",$parser = "",$view = "") {
			$interface = BigTree\ModuleInterface::create("report",$module,$title,$table,array(
				"type" => $type,
				"filters" => $filters,
				"fields" => $fields,
				"parser" => $parser,
				"view" => $view ? $view : null
			));

			return $interface->ID;
		}

		/*
			Function: getRelatedModuleForm
				Returns the form for the same table as this report.

			Returns:
				A ModuleForm object or false.
		*/

		function getRelatedModuleForm() {
			$form = SQL::fetch("SELECT * FROM bigtree_module_interfaces WHERE `type` = 'form' AND `table` = ?", $this->Table);

			return $form ? new ModuleForm($form) : false;
		}

		/*
			Function: getRelatedModuleView
				Returns the view for the same table as this report.

			Returns:
				A ModuleView object or false.
		*/

		function getRelatedModuleView() {
			$view = SQL::fetch("SELECT * FROM bigtree_module_interfaces WHERE `type` = 'view' AND `table` = ?", $this->Table);

			return $view ? new ModuleView($view) : false;
		}

		/*
			Function: getResults
				Returns rows from the table that match the filters provided.

			Parameters:
				view - A view interface array.
				form - A form interface array.
				filter_data - The submitted filters to run.
				sort_field - The field to sort by (defaults to id)
				sort_direction - The direction to sort by (defaults to DESC)

			Returns:
				An array of rows from the report's table.
		*/

		function getResults($view, $form, $filter_data, $sort_field = "id", $sort_direction = "DESC") {
			$where = $items = $parsers = $poplists = array();

			// Prevent SQL injection
			$sort_field = "`".str_replace("`","",$sort_field)."`";
			$sort_direction = ($sort_direction == "ASC") ? "ASC" : "DESC";

			// Figure out if we have db populated lists and parsers
			if ($this->Type == "view") {
				foreach ($view["fields"] as $key => $field) {
					if ($field["parser"]) {
						$parsers[$key] = $field["parser"];
					}
				}
			}

			if (is_array($form["fields"])) {
				foreach ($form["fields"] as $key => $field) {
					if ($field["type"] == "list" && $field["options"]["list_type"] == "db") {
						$poplists[$key] = array(
							"description" => $form["fields"][$key]["options"]["pop-description"],
							"table" => $form["fields"][$key]["options"]["pop-table"]
						);
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
				$query .= " WHERE ".implode(" AND ",$where);
			}

			$query = SQL::query($query." ORDER BY $sort_field $sort_direction");

			while ($item = $query->fetch()) {
				$item = Link::decodeArray($item);

				foreach ($item as $key => $value) {
					if ($poplists[$key]) {
						$item[$key] = SQL::fetchSingle("SELECT `".$poplists[$key]["description"]."` 
														FROM `".$poplists[$key]["table"]."` 
														WHERE id = ?", $value);
					}

					if ($parsers[$key]) {
						$item[$key] = BigTree::runParser($item,$value,$parsers[$key]);
					}
				}

				$items[] = $item;
			}

			// If the field we sort by was a poplist or parser, we need to resort.
			if (isset($parsers[$sort_field]) || isset($poplists[$sort_field])) {
				$sort_values = array();

				foreach ($items as $item) {
					$sort_values[] = $item[$sort_field];
				}

				if ($sort_direction == "ASC") {
					array_multisort($sort_values,SORT_ASC,$items);
				} else {
					array_multisort($sort_values,SORT_DESC,$items);
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

		function save() {
			$this->InterfaceSettings = array(
				"type" => $this->Type,
				"filters" => $this->Filters,
				"fields" => $this->Fields,
				"parser" => $this->Parser,
				"view" => $this->View ?: null
			);

			parent::save();
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

		function update($title,$table,$type,$filters,$fields = "",$parser = "",$view = "") {
			$this->Fields = $fields;
			$this->Filters = $filters;
			$this->Parser = $parser;
			$this->Table = $table;
			$this->Title = $title;
			$this->Type = $type;
			$this->View = $view ?: null;

			$this->save();
		}
	}