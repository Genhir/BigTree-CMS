<?php
	/*
		Class: BigTree\ModuleView
			Provides an interface for handling BigTree module views.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read string $FilterQuery
	 * @property-read int $ID
	 * @property-read ModuleInterface $Interface
	 * @property-read Module $Module
	 * @property-read ModuleForm $RelatedModuleForm
	 */
	
	class ModuleView extends BaseObject
	{
		
		protected $ID;
		protected $Interface;
		protected $Module;
		
		public $Actions;
		public $Description;
		public $EditURL;
		public $ExcludeFromSearch;
		public $Fields;
		public $PreviewURL;
		public $RelatedForm;
		public $Root;
		public $Settings;
		public $SortColumn = null;
		public $SortDirection = null;
		public $Table;
		public $Title;
		public $Type;
		
		public static $CoreActions = [
			"approve" => [
				"key" => "approved",
				"name" => "Approve",
				"class" => "icon_approve icon_approve_on"
			],
			"archive" => [
				"key" => "archived",
				"name" => "Archive",
				"class" => "icon_archive"
			],
			"feature" => [
				"key" => "featured",
				"name" => "Feature",
				"class" => "icon_feature icon_feature_on"
			],
			"edit" => [
				"key" => "id",
				"name" => "Edit",
				"class" => "icon_edit"
			],
			"delete" => [
				"key" => "id",
				"name" => "Delete",
				"class" => "icon_delete"
			]
		];
		public static $CoreTypes = [
			"searchable" => "Searchable List",
			"draggable" => "Draggable List",
			"nested" => "Nested Draggable List",
			"grouped" => "Grouped List",
			"images" => "Image List",
			"images-grouped" => "Grouped Image List"
		];
		public static $Plugins = [];
		
		/*
			Constructor:
				Builds a ModuleView object referencing an existing database entry.

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
			
			$this->Actions = $this->Interface->Settings["actions"];
			$this->Description = $this->Interface->Settings["description"];
			$this->ExcludeFromSearch = !empty($this->Interface->Settings["exclude_from_search"]);
			$this->Fields = array_filter((array) $this->Interface->Settings["fields"]);
			$this->PreviewURL = $this->Interface->Settings["preview_url"];
			$this->RelatedForm = $this->Interface->Settings["related_form"];
			$this->Settings = $this->Interface->Settings["settings"];
			
			if (!empty($this->Settings["sort"])) {
				list($sort_column, $sort_direction) = explode(" ", str_replace("`", "", $this->Settings["sort"]));
				$this->SortColumn = $sort_column;
				$this->SortDirection = $sort_direction;
			}
			
			$this->Table = $interface["table"];
			$this->Title = $interface["title"];
			$this->Type = $this->Interface->Settings["type"];
			
			// Apply Preview action if a Preview URL is set
			if ($this->PreviewURL) {
				$this->Actions["preview"] = "on";
			}
			
			// Get the edit link
			if (!empty($this->Actions["edit"])) {
				$module_root = defined("MODULE_ROOT") ? MODULE_ROOT : ADMIN_ROOT.$this->Module->Route."/";
				
				if (!empty($this->RelatedForm)) {
					foreach ($this->Module->Actions as $action) {
						if ($action->Interface == $this->RelatedForm && substr($action->Route, 0, 4) == "edit") {
							$this->EditURL = $module_root.$action->Route."/";
						}
					}
				}
				
				if (empty($this->EditURL)) {
					$this->EditURL = $module_root."edit/";
				}
			}
		}
		
		/*
			Function: allDependent
				Returns all module views that have a dependence on a given table.

			Parameters:
				table - Table name

			Returns:
				An array of ModuleView objects.
		*/
		
		public static function allDependent(string $table): array
		{
			$modules = DB::getAll("modules");
			$dependent_views = [];
			
			foreach ($modules as $module) {
				if (is_array($module["interfaces"])) {
					foreach ($module["interfaces"] as $interface) {
						if ($interface["type"] == "view" &&
							($interface["settings"]["type"] == "grouped" || $interface["settings"]["type"] == "images-grouped") &&
							$interface["settings"]["other_table"] == $table)
						{
							$dependent_views[] = new ModuleView($interface);
						}
					}
				}
			}
			
			return $dependent_views;
		}
		
		/*
			Function: cache
				Caches a single item in the view to the bigtree_module_view_cache table.
				Private method used by cacheAllData and cacheForAll
		*/
		
		private function cache(array $item, array $parsers, array $poplists, array $original_item): void
		{
			// If we have a filter function, ask it first if we should cache it
			if (!empty($this->Settings["filter"])) {
				if (!call_user_func($this->Settings["filter"], $item)) {
					return;
				}
			}
			
			// Stringify any columns that happen to be arrays (potentially from a pending record)
			foreach ($item as $key => $val) {
				if (is_array($val)) {
					$item[$key] = json_encode($val);
				}
			}
			
			// Setup the fields and VALUES to INSERT INTO the cache table.
			$status = "l"; // Live
			$pending_owner = 0;
			
			if ($item["bigtree_changes"]) {
				$status = "c"; // Changes Pending
			} elseif (isset($item["bigtree_pending"])) {
				$status = "p"; // Completely Pending
				$pending_owner = $item["bigtree_pending_owner"];
			} elseif (!empty($item["archived"]) || (isset($item["approved"]) && $item["approved"] != "on")) {
				$status = "i";
			}
			
			// Setup our array of insert values with what we know already
			$insert_values = [
				"view" => $this->ID,
				"id" => $item["id"],
				"status" => $status,
				"position" => isset($item["position"]) ? $item["position"] : 0,
				"approved" => isset($item["approved"]) ? $item["approved"] : "",
				"archived" => isset($item["archived"]) ? $item["archived"] : "",
				"featured" => isset($item["featured"]) ? $item["featured"] : "",
				"pending_owner" => $pending_owner
			];
			
			// Figure out which column we're going to use to sort the view.
			if ($this->Settings["sort"]) {
				$sort_field = SQL::nextColumnDefinition(ltrim($this->Settings["sort"], "`"));
			} else {
				$sort_field = false;
			}
			
			// Let's see if we have a grouping field.  If we do, let's get all that info and cache it as well.
			if (isset($this->Settings["group_field"]) && $this->Settings["group_field"]) {
				$value = $item[$this->Settings["group_field"]];
				
				// Check for a parser
				if (isset($this->Settings["group_parser"]) && $this->Settings["group_parser"]) {
					$value = Module::runParser($item, $value, $this->Settings["group_parser"]);
				}
				
				// Add the group field
				$insert_values["group_field"] = $value;
				
				// If there's a sort field for the group, add it
				if (is_numeric($value) && $this->Settings["other_table"] && $this->Settings["ot_sort_field"]) {
					$sort_field_value = SQL::fetchSingle("SELECT `".$this->Settings["ot_sort_field"]."` FROM `".$this->Settings["other_table"]."` WHERE id = ?", $value);
					$insert_values["group_sort_field"] = $sort_field_value;
				}
			}
			
			// Check for a nesting column
			if (!empty($this->Settings["nesting_column"])) {
				$insert_values["group_field"] = $item[$this->Settings["nesting_column"]];
			}
			
			// Group based permissions data
			if (!empty($this->Module->GroupBasedPermissions["enabled"]) &&
				$this->Module->GroupBasedPermissions["table"] == $this->Table
			) {
				$insert_values["gbp_field"] = $item[$this->Module->GroupBasedPermissions["group_field"]];
				$insert_values["published_gbp_field"] = $original_item[$this->Module->GroupBasedPermissions["group_field"]];
			}
			
			// Run parsers
			foreach ($parsers as $key => $parser) {
				$item[$key] = Module::runParser($item, $item[$key], $parser);
			}
			
			// Run database populated list hooks
			foreach ($poplists as $key => $pop) {
				$pop_description = SQL::fetchSingle("SELECT `".$pop["description"]."` FROM `".$pop["table"]."` WHERE id = ?", $item[$key]);
				
				if ($pop_description !== false) {
					$item[$key] = $pop_description;
				}
			}
			
			// Insert into the view cache
			if ($this->Type == "images" || $this->Type == "images-grouped") {
				$insert_values["column1"] = $item[$this->Settings["image"]];
			} else {
				$x = 1;
				
				foreach ($this->Fields as $field => $options) {
					$item[$field] = Link::decode($item[$field]);
					$insert_values["column$x"] = Text::htmlEncode(strip_tags($item[$field]));
					$x++;
				}
			}
			
			if (!empty($sort_field) && !empty($item[$sort_field])) {
				$insert_values["sort_field"] = $item[$sort_field];
			}
			
			SQL::insert("bigtree_module_view_cache", $insert_values);
		}
		
		/*
			Function: cacheAllData
				Grabs all the data from the view and does parsing on it based on automatic assumptions and manual parsers.
		*/
		
		public function cacheAllData(): bool
		{
			// See if we already have cached data.
			if (SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_module_view_cache WHERE view = ?", $this->ID)) {
				return false;
			}
			
			// Setup information on our parsers and populated lists.
			$form = $this->RelatedModuleForm;
			$parsers = [];
			$poplists = [];
			
			foreach ($this->Fields as $key => $field) {
				// Get the form field
				$form_field = isset($form->Fields[$key]) ? $form->Fields[$key] : false;
				
				if ($field["parser"]) {
					$parsers[$key] = $field["parser"];
				} elseif ($form_field && $form_field["type"] == "list" && $form_field["settings"]["list_type"] == "db") {
					$poplists[$key] = [
						"description" => $form_field["settings"]["pop-description"],
						"table" => $form_field["settings"]["pop-table"]
					];
				}
			}
			
			// See if we need to modify the cache table to add more fields.
			$field_count = count($this->Fields);
			$table_description = SQL::describeTable("bigtree_module_view_cache");
			$column_count = count($table_description["columns"]) - 13;
			
			if ($field_count > $column_count) {
				AuditTrail::track("config:schema", null, "update", "added column(s) to view cache");
			}

			while ($field_count > $column_count) {
				$column_count++;
				SQL::query("ALTER TABLE bigtree_module_view_cache ADD COLUMN column$column_count TEXT NOT NULL AFTER column".($column_count - 1));
			}

			// Paginate out for high record counts to avoid out of memory errors
			$record_count = SQL::fetchSingle("SELECT COUNT(*) FROM `".$this->Table."`");
			$total_pages = ceil($record_count / 1000);
			
			for ($page = 1; $page <= $total_pages; $page++) {
				$limit = ($page - 1) * 1000;
				
				// Get a page of records (and include their pending changes)
				$query = SQL::query("SELECT `".$this->Table."`.*, bigtree_pending_changes.changes AS bigtree_changes 
									 FROM `".$this->Table."` LEFT JOIN bigtree_pending_changes 
									 ON (bigtree_pending_changes.item_id = `".$this->Table."`.id 
									 AND bigtree_pending_changes.table = '".$this->Table."') 
									 ORDER BY `".$this->Table."`.id ASC LIMIT $limit, 1000");
			
				while ($item = $query->fetch()) {
					$original_item = $item;
					
					if ($item["bigtree_changes"]) {
						$changes = json_decode($item["bigtree_changes"],true);
						
						foreach ($changes as $key => $change) {
							$item[$key] = $change;
						}
					}	
					
					$this->cache($item, $parsers, $poplists, $original_item);
				}
			}

			return true;
		}
		
		/*
			Function: cacheForAll
				Caches a new database row for all Module Views that use the same table.

			Parameters:
				table - The table the row is in.
				id - The id of the row.
				pending - Whether this is actually a pending entry (defaults to false)
		*/
		
		public static function cacheForAll(string $table, string $id, bool $pending = false): void
		{
			if (!$pending) {
				$item = SQL::fetch("SELECT `$table`.*, bigtree_pending_changes.changes AS bigtree_changes 
									FROM `$table` LEFT JOIN bigtree_pending_changes 
									ON (bigtree_pending_changes.item_id = `$table`.id AND 
										bigtree_pending_changes.table = '$table') 
									WHERE `$table`.id = ?", $id);
				
				$original_item = $item;
				
				// Apply changes overtop existing values
				if ($item["bigtree_changes"]) {
					$changes = json_decode($item["bigtree_changes"], true);
					
					foreach ($changes as $key => $change) {
						$item[$key] = $change;
					}
				}
			} else {
				$pending_item = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);
				
				$item = json_decode($pending_item["changes"], true);
				$item["bigtree_pending"] = true;
				$item["bigtree_pending_owner"] = $pending_item["user"];
				$item["id"] = "p".$pending_item["id"];
				
				$original_item = $item;
			}

			$modules = DB::getAll("modules");

			foreach ($modules as $module) {
				if (!is_array($module["interfaces"])) {
					continue;
				}

				foreach ($module["interfaces"] as $interface) {
					if ($interface["type"] == "view" && $interface["table"] == $table) {
						$interface["module"] = $module["id"];
						$view = new ModuleView($interface);

						// Delete any existing cache data on this row
						SQL::delete("bigtree_module_view_cache", ["view" => $view->ID, "id" => $item["id"]]);
						
						// In case this view has never been cached, run the whole view, otherwise just this one.
						if (!$view->cacheAllData()) {
							$form = $view->RelatedModuleForm;
							$parsers = $poplists = [];
							
							foreach ($view->Fields as $key => $field) {
								$form_field = !empty($form->Fields[$key]) ? $form->Fields[$key] : false;
								
								if ($field["parser"]) {
									$parsers[$key] = $field["parser"];
								} elseif ($form_field && $form_field["type"] == "list" && $form_field["settings"]["list_type"] == "db") {
									$poplists[$key] = [
										"description" => $form_field["settings"]["pop-description"],
										"table" => $form_field["settings"]["pop-table"]
									];
								}
							}
							
							$view->cache($item, $parsers, $poplists, $original_item);
						}
					}
				}
			}
		}
		
		/*
			Function: calculateFieldWidths
				Calculates the field widths for the view for use when drawing a table. Updates $this->Actions

			Parameters:
				table_width - Table width (in pixels) to calculate column widths from (defaults to 888)
		*/
		
		public function calculateFieldWidths(int $table_width = 888): void
		{
			if (array_filter((array) $this->Fields)) {
				$first = current($this->Fields);
				
				// If we already have columns set we don't need to do the calculation
				if (empty($first["width"])) {
					$actions_width = count($this->Actions) * 40;
					$available = $table_width - $actions_width;
					$per_column = floor($available / count($this->Fields));
					
					// Set the widths
					foreach ($this->Fields as $key => $field) {
						$this->Fields[$key]["width"] = $per_column - 20;
					}
				}
			}
		}
		
		/*
			Function: clearCache
				Clears the cache of the view.
		*/
		
		public function clearCache(): void
		{
			SQL::delete("bigtree_module_view_cache", ["view" => $this->ID]);
		}
		
		/*
			Function: clearCacheForTable
				Clears all module view caches with the given table name.

			Parameters:
				table - A table to reset caches for.
		*/
		
		public static function clearCacheForTable(string $table): void
		{
			$modules = DB::getAll("modules");
			
			foreach ($modules as $module) {
				if (is_array($module["interfaces"])) {
					foreach ($module["interfaces"] as $interface) {
						if ($interface["type"] == "view" && $interface["table"] == $table) {
							SQL::delete("bigtree_module_view_cache", ["view" => $interface["id"]]);
						}
					}
				}
			}
		}
		
		/*
			Function: create
				Creates a module view.

			Parameters:
				module - The module ID that this view relates to.
				title - View title.
				description - Description.
				table - Data table.
				type - View type.
				settings - View settings array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.

			Returns:
				A ModuleView object.
		*/
		
		public static function create(int $module, string $title, string $description, string $table, string $type,
									  ?array $settings, ?array $fields, ?array $actions, ?int $related_form = null,
									  string $preview_url = "", bool $exclude_from_search = false): ModuleView
		{
			$interface = ModuleInterface::create("view", $module, $title, $table, [
				"description" => Text::htmlEncode($description),
				"type" => $type,
				"fields" => $fields ?: [],
				"settings" => $settings ?: [],
				"actions" => $actions ?: [],
				"preview_url" => $preview_url ? Link::encode($preview_url) : "",
				"related_form" => $related_form,
				"exclude_from_search" => $exclude_from_search
			]);
			
			$view = new ModuleView($interface->Array);
			$view->refreshNumericColumns();
			
			return $view;
		}
		
		/*
			Function: generateActionClass
				Returns the button class for the given action and item.

			Parameters:
				action - The action route for the item (edit, feature, approve, etc)
				item - The entry to check the action for.

			Returns:
				Class name for the <a> tag.

				For example, if the item is already featured, this returns "icon_featured icon_featured_on" for the "feature" action.
				If the item isn't already featured, it would simply return "icon_featured" for the "feature" action.
		*/
		
		public static function generateActionClass(string $action, array $item): string
		{
			$class = "";
			
			if (isset($item["bigtree_pending"]) && $action != "edit" && $action != "delete") {
				return "icon_disabled js-hook-disabled";
			}
			
			if ($action == "feature") {
				$class = "icon_feature js-hook-feature";
				
				if ($item["featured"]) {
					$class .= " icon_feature_on";
				}
			}
			
			if ($action == "edit") {
				$class = "icon_edit";
			}
			
			if ($action == "delete") {
				$class = "icon_delete js-hook-delete";
			}
			
			if ($action == "approve") {
				$class = "icon_approve js-hook-approve";
				
				if ($item["approved"]) {
					$class .= " icon_approve_on";
				}
			}
			
			if ($action == "archive") {
				$class = "icon_archive js-hook-archive";
				
				if ($item["archived"]) {
					$class .= " icon_archive_on";
				}
			}
			
			if ($action == "preview") {
				$class = "icon_preview";
			}
			
			return $class;
		}
		
		/*
			Function: getByTable
				Returns a ModuleView for a given table (if one exists).

			Parameters:
				table - A MySQL table name

			Returns:
				A ModuleView object or null.
		*/
		
		public static function getByTable(string $table): ?ModuleView
		{
			$modules = DB::getAll("modules");
			$view = null;
			$module_for_view = null;
			
			foreach ($modules as $module) {
				if (is_array($module["interfaces"])) {
					foreach ($module["interfaces"] as $interface) {
						if ($interface["type"] == "view" && $interface["table"] == $table) {
							$view = $interface;
							$view["module"] = $module["id"];
							
							break 2;
						}
					}
				}
			}
			
			if (!$view) {
				return null;
			}
			
			return new ModuleView($view);
		}
		
		/*
			Function: getData
				Looks up cached view data for the view.
			
			Parameters:
				sort - The sort direction, defaults to most recent.
				type - Whether to get only active entries, pending entries, or both.
				group - The group to get data for (defaults to all).
			
			Returns:
				An array of rows from bigtree_module_view_cache.
		*/
		
		public function getData(string $sort = "id DESC", string $type = "both", ?string $group = null): array
		{
			// Check to see if we've cached this table before.
			$this->cacheAllData();
			
			$where = "";
			
			if ($type == "active") {
				$where = "status != 'p' AND ";
			} elseif ($type == "pending") {
				$where = "status = 'p' AND ";
			}
			
			// If a group was passed add that filter
			if (!is_null($group)) {
				$where .= " AND group_field = '".SQL::escape($group)."'";
			}
			
			$results = SQL::fetchAll("SELECT * FROM bigtree_module_view_cache
									  WHERE $where view = ?".$this->FilterQuery."
									  ORDER BY $sort", $this->ID);
			
			// Assign them back to keys with the item id
			$items = [];
			
			foreach ($results as $item) {
				$items[$item["id"]] = $item;
			}
			
			return $items;
		}
		
		/*
			Function: getGroups
				Returns all groups in the view cache for the view.

			Returns:
				An array of groups.
		*/
		
		public function getGroups(): array
		{
			$groups = [];
			$query = "SELECT DISTINCT(group_field) FROM bigtree_module_view_cache WHERE view = ?";
			
			if (isset($this->Settings["ot_sort_field"]) && $this->Settings["ot_sort_field"]) {
				// We're going to determine whether the group sort field is numeric or not first.
				$is_numeric = true;
				$group_sort_fields = SQL::fetchAllSingle("SELECT DISTINCT(group_sort_field) FROM bigtree_module_view_cache
														  WHERE view = ?", $this->ID);
				foreach ($group_sort_fields as $value) {
					if (!is_numeric($value)) {
						$is_numeric = false;
					}
				}
				
				// If all of the groups are numeric we'll cast the sorting field as decimal so it's not interpretted as a string.
				if ($is_numeric) {
					$query .= " ORDER BY CAST(group_sort_field AS DECIMAL) ".$this->Settings["ot_sort_direction"];
				} else {
					$query .= " ORDER BY group_sort_field ".$this->Settings["ot_sort_direction"];
				}
			} else {
				$query .= " ORDER BY group_field";
			}
			
			$group_values = SQL::fetchAllSingle($query, $this->ID);
			
			// If there's another table, we're going to query it separately.
			if ($this->Settings["other_table"] && !$this->Settings["group_parser"] && count($group_values)) {
				$other_table_where = [];
				
				foreach ($group_values as $value) {
					$other_table_where[] = "id = ?";
					
					// We need to instatiate all of these as empty first in case the database relationship doesn't exist.
					$groups[$value] = "";
				}
				
				// Don't query up if we have no groups
				if ($this->Settings["ot_sort_field"]) {
					$sort_field = $this->Settings["ot_sort_field"];
					
					if ($this->Settings["ot_sort_direction"]) {
						$sort_direction = $this->Settings["ot_sort_direction"];
					} else {
						$sort_direction = "ASC";
					}
				} else {
					$sort_field = "id";
					$sort_direction = "ASC";
				}
				
				// Append the query to our parameter array
				array_unshift($group_values, "SELECT id,`".$this->Settings["title_field"]."` AS `title` 
											  FROM `".$this->Settings["other_table"]."` 
											  WHERE ".implode(" OR ", $other_table_where)." 
											  ORDER BY `$sort_field` $sort_direction");
				$group_search = call_user_func_array("BigTree\\SQL::fetchAll", $group_values);
				
				foreach ($group_search as $group) {
					$groups[$group["id"]] = $group["title"];
				}
				
			} else {
				// The title and value are the same
				foreach ($group_values as $value) {
					$groups[$value] = $value;
				}
			}
			
			return $groups;
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
		
		public function getFilterQuery(): string
		{
			if (!empty($this->Module->GroupBasedPermissions["enabled"]) &&
				$this->Module->GroupBasedPermissions["table"] == $this->Table
			) {
				$groups = $this->Module->UserAccessibleGroups;
				
				if (is_array($groups)) {
					$group_where = [];
					
					foreach ($groups as $group) {
						$group = SQL::escape($group);
						
						if ($this->Type == "nested" &&
							$this->Module->GroupBasedPermissions["group_field"] == $this->Settings["nesting_column"]
						) {
							$group_where[] = "`id` = '$group' OR `gbp_field` = '$group'";
						} else {
							$group_where[] = "`gbp_field` = '$group'";
						}
					}
					
					return " AND (".implode(" OR ", $group_where).")";
				}
			}
			
			return "";
		}
		
		/*
			Function: getRelatedModuleForm
				Returns the form for the same table as this view.
			
			Returns:
				A ModuleForm or null.
		*/
		
		public function getRelatedModuleForm(): ?ModuleForm
		{
			if ($this->RelatedForm && isset($this->Module->Forms[$this->RelatedForm])) {
				return $this->Module->Forms[$this->RelatedForm];
			}
			
			foreach ($this->Module->Forms as $form) {
				if ($form->Table == $this->Table) {
					return $form;
				}
			}
			
			return null;
		}
		
		/*
			Function: parseData
				Parses data and returns the parsed columns (runs parsers and populated lists).

			Parameters:
				items - An array of table rows to parse.

			Returns:
				An array of parsed rows for display in a View.
		*/
		
		public function parseData(array $items): array
		{
			$form = $this->RelatedModuleForm->Array;
			$parsed = [];
			
			foreach ($items as $item) {
				foreach ($this->Fields as $key => $field) {
					$value = $item[$key];
					
					// If we have a parser, run it.
					if ($field["parser"]) {
						$item[$key] = Module::runParser($item, $value, $field["parser"]);
					} else {
						$form_field = $form["fields"][$key];
						
						// If we know this field is a populated list, get the title they entered in the form.
						if ($form_field["type"] == "list" && $form_field["settings"]["list_type"] == "db") {
							
							$value = SQL::fetchSingle("SELECT `".$form_field["settings"]["pop-description"]."` 
													   FROM `".$form_field["settings"]["pop-table"]."` 
													   WHERE `id` = ?", $value);
						}
						
						$item[$key] = strip_tags($value);
					}
				}
				
				$parsed[] = $item;
			}
			
			return $parsed;
		}
		
		/*
			Function: refreshNumericColumns
				Updates the view's columns to designate whether they are numeric or not based on parsers, column type, and related forms.
		*/
		
		public function refreshNumericColumns(): void
		{
			if (array_filter((array) $this->Fields)) {
				$numeric_column_types = [
					"int",
					"float",
					"double",
					"double precision",
					"tinyint",
					"smallint",
					"mediumint",
					"bigint",
					"real",
					"decimal",
					"dec",
					"fixed",
					"numeric"
				];
				
				$form = $this->RelatedModuleForm;
				$table = SQL::describeTable($this->Table);
				
				foreach ($this->Fields as $key => $field) {
					$numeric = false;
					
					if (in_array($table["columns"][$key]["type"], $numeric_column_types)) {
						$numeric = true;
					}
					
					if ($field["parser"] || ($form->Fields[$key]["type"] == "list" && $form->Fields[$key]["list_type"] == "db")) {
						$numeric = false;
					}
					
					$this->Fields[$key]["numeric"] = $numeric;
				}
				
				$this->save();
			}
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			$this->Interface->Settings = [
				"description" => Text::htmlEncode($this->Description),
				"type" => $this->Type,
				"fields" => array_filter((array) $this->Fields),
				"settings" => (array) $this->Settings,
				"actions" => array_filter((array) $this->Actions),
				"preview_url" => $this->PreviewURL ? Link::encode($this->PreviewURL) : "",
				"related_form" => $this->RelatedForm ? intval($this->RelatedForm) : null,
				"exclude_from_search" => !empty($this->ExcludeFromSearch)
			];
			$this->Interface->Table = $this->Table;
			$this->Interface->Title = $this->Title;
			$this->Interface->save();
			
			return true;
		}
		
		/*
			Function: searchData
				Returns search results from the bigtree_module_view_cache table for this view.

			Parameters:
				page - The page of data to retrieve.
				query - The query string to search against.
				sort - The column and direction to sort.
				group - The group to pull information for.

			Returns:
				An array containing "pages" with the number of result pages and "results" with the results for the given page.
		*/
		
		public function searchData(int $page = 1, string $query = "", string $sort = "id DESC",
								   ?string $group = null): array
		{
			// Check to see if we've cached this table before.
			$this->cacheAllData();
			
			$search_parts = explode(" ", $query);
			$view_column_count = count($this->Fields);
			$per_page = !empty($this->Settings["per_page"]) ? $this->Settings["per_page"] : 15;
			$query = "SELECT * FROM bigtree_module_view_cache WHERE view = ?".$this->FilterQuery;
			
			if (!is_null($group)) {
				$query .= " AND group_field = '".SQL::escape($group)."'";
			}
			
			// Add all the pieces of the query to check against the columns in the view
			foreach ($search_parts as $part) {
				$part = SQL::escape($part);
				$query_parts = [];

				for ($x = 1; $x <= $view_column_count; $x++) {
					$query_parts[] = "column$x LIKE '%$part%'";
				}
				
				if (count($query_parts)) {
					$query .= " AND (".implode(" OR ", $query_parts).")";
				}
			}
			
			// Find how many pages are returned from this search
			$total = SQL::fetchSingle(str_replace("SELECT *", "SELECT COUNT(*)", $query), $this->ID);
			$pages = ceil($total / $per_page);
			$pages = $pages ? $pages : 1;
			
			// Get the correct column name for sorting
			if (strpos($sort, "`") !== false) { // New formatting
				$sort_field = SQL::nextColumnDefinition(substr($sort, 1));
				$sort_pieces = explode(" ", $sort);
				$sort_direction = end($sort_pieces);
			} else { // Old formatting
				list($sort_field, $sort_direction) = explode(" ", $sort);
			}
			
			// Figure out whether we need to cast the column we're sorting by as numeric so that 2 comes before 11
			if ($sort_field != "id") {
				$x = 0;
				
				if (!empty($this->Fields[$sort_field]["numeric"])) {
					$convert_numeric = true;
				} else {
					$convert_numeric = false;
				}
				
				foreach ($this->Fields as $field => $options) {
					$x++;
					if ($field == $sort_field) {
						$sort_field = "column$x";
					}
				}
				
				// If we didn't find a column, let's assume it's the default sort field.
				if (substr($sort_field, 0, 6) != "column") {
					$sort_field = "sort_field";
				}
				
				if ($convert_numeric) {
					$sort_field = "CONVERT(".$sort_field.",SIGNED)";
				}
			} else {
				$sort_field = "CONVERT(id,UNSIGNED)";
			}
			
			if (strtolower($sort) == "position desc, id asc") {
				$sort_field = "position DESC, id ASC";
				$sort_direction = "";
			} else {
				$sort_direction = (strtolower($sort_direction) == "asc") ? "ASC" : "DESC";
			}
			
			if ($page === "all") {
				$results = SQL::fetchAll($query." ORDER BY $sort_field $sort_direction", $this->ID);
			} else {
				$results = SQL::fetchAll($query." ORDER BY $sort_field $sort_direction
												  LIMIT ".(($page - 1) * $per_page).",$per_page", $this->ID);
			}
			
			return ["pages" => $pages, "results" => $results];
		}
		
		/*
			Function: uncacheForAll
				Removes a database row from all Module View caches with the given table.

			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
		*/
		
		public static function uncacheForAll(string $table, string $id): void
		{
			$modules = DB::getAll("modules");
			
			foreach ($modules as $module) {
				if (is_array($module["interfaces"])) {
					foreach ($module["interfaces"] as $interface) {
						if ($interface["type"] == "view" && $interface["table"] == $table) {
							SQL::query("DELETE FROM bigtree_module_view_cache
										WHERE `view` = ? AND `id` = ?", $interface["id"], $id);
						}
					}
				}
			}
		}
		
		/*
			Function: update
				Updates the module view and the associated module action's title.

			Parameters:
				title - View title.
				description - Description.
				table - Data table.
				type - View type.
				options - View options array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.
				exclude_from_search - Whether to exclude this view data from admin-side search
		*/
		
		public function update(string $title, string $description, string $table, string $type, ?array $options,
							   ?array $fields, ?array $actions, ?int $related_form = null,
							   string $preview_url = "", bool $exclude_from_search = false): void
		{
			$this->Actions = $actions ?: [];
			$this->Description = $description;
			$this->ExcludeFromSearch = $exclude_from_search;
			$this->Fields = $fields ?: [];
			$this->PreviewURL = $preview_url;
			$this->RelatedForm = $related_form;
			$this->Settings = $options ?: [];
			$this->Table = $table;
			$this->Title = $title;
			$this->Type = $type;
			
			// This method will automatically save
			$this->refreshNumericColumns();
			
			// Update related action titles
			foreach ($this->Module->Actions as $action) {
				if ($action->Interface == $this->ID) {
					$action->Name = Text::translate("View :view_title:", true, [":view_title:" => $title]);
					$action->save();
				}
			}
		}
		
	}
