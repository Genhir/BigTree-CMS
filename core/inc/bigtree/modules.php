<?php
	/*
		Class: BigTreeModule
			Base class from which all BigTree module classes inherit from.
	*/

	use BigTree\JSON;
	use BigTree\Link;
	use BigTree\ModuleView;
	use BigTree\SQL;
	
	class BigTreeModule {

		public $NavPosition = "bottom";
		public $Table = "";

		/*
			Route Registry
				A module's route registry allows it to hook routes. Each registry entry array contains the following keys:
				
				"type"
					"admin" - matches patterns in the admin section
					"public" - matches patterns from the web root
					"template" - matches patterns in a given routed template (from the location of the page)
				
				"pattern"
					A pattern based on the route parameters from Slim - http://docs.slimframework.com/routing/params/
				
				"template"
					If the template type is used, this should be the ID of the template you're hooking.

				"file"
					The location, relative to SERVER_ROOT (or the routed template directory if "template" type is used), of the file to include.
				
				"function"
					A function to run (instead of including a file).
		*/
		
		static $RouteRegistry = [];


		/*
			Constructor:
				If you pass in a table name it will be used for all module functions.

			Parameters:
				table - The SQL table you want to perform queries on.
		*/

		function __construct($table = "") {
			if ($table) {
				$this->Table = $table;
			}
		}
		
		/*
			Function: add
				Adds an entry to the table.
			
			Parameters:
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well) - Optionally this can be a key/value array and the values field kept false
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
				enforce_unique - Check to see if this entry is already in the database (prevent duplicates, defaults to false)
				ignore_cache - If this is set to true, BigTree will not cache this entry in bigtree_module_view_cache - faster entry if you don't have an admin view (defaults to false)
			
			Returns:
				The "id" of the new entry.
			
			See Also:
				<delete>
				<save>
				<update>
		*/
		
		function add($fields, $values = [], $enforce_unique = false, $ignore_cache = false) {
			$insert_array = [];

			// Single column/value add
			if (is_string($fields)) {
				$insert_array[$fields] = $values;
				// Multiple columns / values
			} else {
				// If we didn't pass in values (=== []) then we're using a key => value array
				if ($values === []) {
					$insert_array = $fields;
					// Separate arrays for keys and values
				} else {
					foreach ($fields as $key) {
						$insert_array[$key] = current($values);
						next($values);
					}
				}
			}

			// Do auto IPL stuff
			$insert_array = Link::encode($insert_array);

			// Prevent Duplicates
			if ($enforce_unique) {
				$existing_parts = [];

				foreach ($insert_array as $key => $val) {
					$val = is_array($val) ? JSON::encode($val, true) : SQL::escape($val);
					$existing_parts[] = "`$key` = '$val'";
				}

				$existing_id = SQL::fetchSingle("SELECT id FROM `".$this->Table."` 
															 WHERE ".implode(" AND ", $existing_parts)." LIMIT 1");
				// If it's the same as an existing entry, return that entry's id
				if ($existing_id) {
					return $existing_id;
				}
			}
			
			// Add the entry and cache it.
			$id = SQL::insert($this->Table, $insert_array);

			if (!$ignore_cache) {
				ModuleView::cacheForAll($this->Table, $id);
			}

			return $id;
		}
		
		/*
			Function: approve
				Approves a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<unapprove>
		*/
		
		function approve($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}

			$this->update($item, "approved", "on");
			ModuleView::cacheForAll($this->Table, $item);
		}
		
		/*
			Function: archive
				Archives a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<unarchive>
		*/
		
		function archive($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}

			$this->update($item, "archived", "on");
			ModuleView::cacheForAll($this->Table, $item);
		}
		
		/*
			Function: delete
				Deletes an entry from the table.
			
			Parameters:
				item - The id of the entry to delete or the entry itself.
			
			See Also:
				<add>
				<save>
				<update>
		*/
		
		function delete($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}
			
			SQL::delete($this->Table, $item);
			SQL::delete("bigtree_pending_changes", ["table" => $this->Table, "item_id" => $item]);
			ModuleView::uncacheForAll($this->Table, $item);
		}
		
		/*
			Function: feature
				Features a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<unfeature>
		*/
		
		function feature($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}

			$this->update($item, "featured", "on");
			ModuleView::cacheForAll($this->Table, $item);
		}
		
		/*
			Function: fetch
				Protected function used by other table querying functions.
		*/
		
		protected function fetch($sortby = false, $limit = false, $where = false, $columns = false) {
			$query_columns = "*";
			if ($columns !== false) {
				if (is_array($columns)) {
					$query_columns = [];
					foreach ($columns as $column) {
						$query_columns[] = "`".str_replace("`", "", $column)."`";
					}
					$query_columns = implode(",", $query_columns);
				} else {
					$query_columns = "`".str_replace("`", "", $columns)."`";
				}
			}
			$query = "SELECT $query_columns FROM `".$this->Table."`";

			if ($where) {
				$query .= " WHERE $where";
			}
			
			if ($sortby) {
				$query .= " ORDER BY $sortby";
			}
			
			if ($limit) {
				$query .= " LIMIT $limit";
			}
			
			$items = [];
			$query = SQL::query($query);

			while ($item = $query->fetch()) {
				$items[] = $this->get($item);
			}
			
			return $items;
		}
		
		/*
			Function: get
				Gets a single entry from the table or translates an entry from the table.
				This method is called on each entry retrieved in every other function in this class so it can be used for additional data transformation overrides in your module class.
			
			Parameters:
				item - Either the ID of an item to pull from the table or a table entry to parse.
			
			Returns:
				A translated item from the table.
		*/
		
		function get($item) {
			if (!is_array($item)) {
				$item = SQL::fetch("SELECT * FROM `".$this->Table."` WHERE id = ?", $item);
			}
			
			if (!$item) {
				return false;
			}
			
			// Decode any JSON
			foreach ($item as $key => $value) {
				$array_value = @json_decode($value, true);

				if (is_array($array_value)) {
					$item[$key] = $array_value;
				}
			}
			
			return Link::decode($item);
		}
		
		/*
			Function: getAll
				Returns all items from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				columns - The columns to return (defaults to all)
		
			Returns:
				An array of items from the table.
		*/

		function getAll($order = false, $columns = false) {
			return $this->fetch($order, false, false, $columns);
		}
		
		/*
			Function: getAllPositioned
				Returns all entries from the table based on position.

			Parameters:
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
		*/
		
		function getAllPositioned($columns = false) {
			return $this->getAll("position DESC, id ASC", $columns);
		}
		
		/*
			Function: getApproved
				Returns approved entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getApproved($order = false, $limit = false, $columns = false) {
			return $this->getMatching("approved", "on", $order, $limit, false, $columns);
		}

		/*
			Function: getArchived
				Returns archived entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getArchived($order = false, $limit = false, $columns = false) {
			return $this->getMatching("archived", "on", $order, $limit, false, $columns);
		}
		
		/*
			Function: getBreadcrumb
				An optional function to override in your module class.
				Provides additional breadcrumb segments when <BigTreeCMS.getBreadcrumb> is called on a page with a template that uses this module.
			
			Parameters:
				page - The page data for the current page the user is on.
				routed_path - An array of routes used to land on the current file in your routed template directory. (equivalent to $bigtree["routed_path"])
				commands - An array of commands available in the current routed page (equivalent to $bigtree["commands"])
			
			Returns:
				An array of arrays with "title" and "link" key/value pairs.
		*/
		
		function getBreadcrumb($page, $routed_path = [], $commands = []) {
			return [];
		}
		
		/*
			Function: getByRoute
				Returns a table entry that has a `route` field matching the given value.
			
			Parameters:
				route - A string to check the `route` field for.
			
			Returns:
				An entry from the table if one is found.
		*/
		
		function getByRoute($route) {
			$item = SQL::fetch("SELECT * FROM `".$this->Table."` WHERE route = ?", $route);

			if (!$item) {
				return false;
			} else {
				return $this->get($item);
			}
		}
		
		/*
			Function: getFeatured
				Returns featured entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getFeatured($order = false, $limit = false, $columns = false) {
			return $this->getMatching("featured", "on", $order, $limit, false, $columns);
		}
		
		/*
			Function: getInfo
				Returns information about a given entry from the module.

			Parameters:
				entry - An entry from this module or an id

			Returns:
				An array of keyed information:
					"created_at" - A datestamp of the created date/time
					"updated_at" - A datestamp of the last updated date/time
					"creator" - The original creator of this entry (the user's ID)
					"last_updated_by" - The last user to update this entry (the user's ID)
					"status" - Whether this entry has pending changes "changed" or not "published"
		*/

		function getInfo($entry) {
			$info = [];

			if (is_array($entry)) {
				$entry = $entry["id"];
			}

			$base_query = "SELECT * FROM bigtree_audit_trail WHERE `table` = '".$this->Table."' AND entry = ?";
			$created = SQL::fetch($base_query." AND type = 'created'", $entry);
			$updated = SQL::fetch($base_query." AND type = 'updated' ORDER BY date DESC LIMIT 1", $entry);
			$changed = SQL::fetch($base_query." AND type = 'saved-draft' ORDER BY date DESC LIMIT 1", $entry);

			if ($created) {
				$info["created_at"] = $created["date"];
				$info["creator"] = $created["user"];
			}

			if ($updated) {
				$info["updated_at"] = $updated["date"];
				$info["last_updated_by"] = $updated["user"];
			}
			
			if ($changed && strtotime($changed) > strtotime($info["updated_at"])) {
				$info["status"] = "changed";
			} else {
				$info["status"] = "published";
			}

			return $info;
		}
		
		/*
			Function: getMatching
				Returns entries from the table that match the key/value pairs.
			
			Parameters:
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well)
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				exact - If you want exact matches for NULL, "", and 0, pass true, otherwise 0 = NULL = ""
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
		*/
		
		function getMatching($fields, $values, $sortby = false, $limit = false, $exact = false, $columns = false) {
			if (!is_array($fields)) {
				$search = [$fields => $values];
			} else {
				$search = array_combine($fields, $values);
			}

			$where = [];

			foreach ($search as $key => $value) {
				if (!$exact && ($value === "NULL" || !$value)) {
					$where[] = "(`$key` IS NULL OR `$key` = '' OR `$key` = '0')";
				} else {
					$where[] = "`$key` = '".SQL::escape($value)."'";
				}
			}
			
			return $this->fetch($sortby, $limit, implode(" AND ", $where), $columns);
		}
		
		/*
			Function: getNav
				An optional function to override in your module class.
				Provides additional navigation children when <BigTreeCMS.getNavByParent> is called on a page with a template that uses this module.
			
			Parameters:
				page - The page data for the current page the user is on.
			
			Returns:
				An array of arrays with "title" and "link" key/value pairs. Also accepts "children" for sending grandchildren as well.
		*/
		
		function getNav($page) {
			return [];
		}
		
		/*
			Function: getNonarchived
				Returns nonarchived entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getNonarchived($order = false, $limit = false, $columns = false) {
			return $this->getMatching("archived", "", $order, $limit, false, $columns);
		}
		
		/*
			Function: getPage
				Returns a page of entries from the table.
			
			Parameters:
				page - The page to return
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				perpage - The number of results per page (defaults to 15)
				where - Optional MySQL WHERE conditions
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				Array of entries from the table.
			
			See Also:
				<getPageCount>
		*/
		
		function getPage($page = 1, $order = "id ASC", $perpage = 15, $where = false, $columns = false) {
			// Backwards compatibility with old argument order
			if (!is_numeric($perpage)) {
				$saved = $perpage;
				$perpage = $where;
				$where = $saved;
			}

			// Don't try to hit page 0.
			if ($page < 1) {
				$page = 1;
			}

			return $this->fetch($order, (($page - 1) * $perpage).", $perpage", $where, $columns);
		}
		
		/*
			Function: getPageCount
				Returns the number of pages of entries in the table.
			
			Parameters:
				perpage - The number of results per page (defaults to 15)
				where - Optional MySQL WHERE conditions
		
			Returns:
				The number of pages.
			
			See Also:
				<getPage>
		*/
		
		function getPageCount($perpage = 15, $where = false) {
			// Backwards compatibility with old argument order
			if (!is_numeric($perpage)) {
				$saved = $perpage;
				$perpage = is_numeric($where) ? $where : 15;
				$where = $saved;
			}

			if ($where) {
				$query = "SELECT COUNT(*) FROM `".$this->Table."` WHERE $where";
			} else {
				$query = "SELECT COUNT(*) FROM `".$this->Table."`";
			}

			$pages = ceil(SQL::fetchSingle($query) / $perpage);

			if ($pages == 0) {
				$pages = 1;
			}

			return $pages;
		}
		
		/*
			Function: getPending
				Returns an entry from the table with pending changes applied.
			
			Parameters:
				id - The id of the entry in the table, or the id of the pending entry in bigtree_pending_changes prefixed with a "p"
			
			Returns:
				The entry from the table with pending changes applied.
		*/
		
		function getPending($id) {
			// Completely pending
			if (substr($id, 0, 1) == "p") {
				$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", substr($id, 1));
				$item = json_decode($pending["changes"], true);
				$item["id"] = $id;
			// Published with changes
			} else {
				$item = SQL::fetch("SELECT * FROM `".$this->Table."` WHERE id = ?", $id);
				$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE item_id = ? AND `table` = '".$this->Table."'", $id);
				if ($pending) {
					$changes = json_decode($pending["changes"], true);
					foreach ($changes as $key => $val) {
						$item[$key] = $val;
					}
				}

			}
			
			// Translate its roots and return it
			return $this->get($item);
		}
		
		/*
			Function: getRandom
				Returns a single (or many) random entries from the table.
			
			Parameters:
				count - The number of entries to return (if more than one).
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				If "count" is passed, an array of entries from the table. Otherwise, a single entry from the table.
		*/
		
		function getRandom($count = false, $columns = false) {
			$results = $this->fetch("RAND()", $count, false, $columns);

			if ($count === false) {
				return $results[0];
			}

			return $results;
		}

		/*
			Function: getRecent
				Returns an array of entries from the table that have passed.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getRecentFeatured>
		*/
		
		function getRecent($count = 5, $field = "date", $columns = false) {
			return $this->fetch("$field DESC", $count, "DATE(`$field`) <= '".date("Y-m-d")."'", $columns);
		}

		/*
			Function: getRecentFeatured
				Returns an array of entries from the table that have passed and are featured.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getRecent>
		*/
		
		function getRecentFeatured($count = 5, $field = "date", $columns = false) {
			return $this->fetch("$field DESC", $count, "featured = 'on' AND DATE(`$field`) <= '".date("Y-m-d")."'", $columns);
		}
		
		/*
			Function: getRelatedByTags
				Returns relevant entries from the table that match the given tags.
			
			Parameters:
				tags - An array of tags to match against.
				count - Number to return (defaults to all)
			
			Returns:
				An array of entries from the table sorted by most relevant to least.
		*/
		
		function getRelatedByTags($tags = [], $count = false) {
			$results = $relevance = [];

			foreach ($tags as $tag) {
				if (is_array($tag)) {
					$tag_id = $tag["id"];
				} else {
					$tag_id = SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?", $tag);
				}

				if ($tag_id) {
					$query = SQL::query("SELECT * FROM bigtree_tags_rel WHERE tag = '$tag_id' AND `table` = '".$this->Table."'");

					while ($relationship = $query->fetch()) {
						$id = $relationship["entry"];

						// If we already have this relationship, increase the relevance
						if (in_array($id, $results)) {
							$relevance[$id]++;
						} else {
							$results[] = $id;
							$relevance[$id] = 1;
						}
					}
				}
			}

			// Sort by most relevant
			array_multisort($relevance, SORT_DESC, $results);

			// If we asked for a certain number, only return that many
			if ($count !== false) {
				$results = array_slice($results, 0, $count);
			}

			// Parse result IDs into items 
			$items = [];

			foreach ($results as $result) {
				$items[] = $this->get($result);
			}

			return $items;
		}
		
		/*
			Function: getSitemap
				An optional function to override in your module class.
				Provides additional sitemap children when <BigTreeCMS.getNavByParent> is called on a page with a template that uses this module.
			
			Parameters:
				page - The page data for the current page the user is on.
			
			Returns:
				An array of arrays with "title" and "link" key/value pairs. Should not be a multi level array.
		*/
		
		function getSitemap($page) {
			return [];
		}
		
		/*
			Function: getTagsForItem
				Returns a list of tags the given table entry has been tagged with.
			
			Parameters:
				item - Either a table entry or the "id" of a table entry.
				full - Whether to return a full tag array or only the tag string (defaults to only the tag string)
			
			Returns:
				An array of tags (strings).
		*/
		
		public function getTagsForItem($item, $full = false) {
			if (!is_numeric($item)) {
				$item = $item["id"];
			}
			
			if ($full) {
				return SQL::fetchAll("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel
									  ON bigtree_tags_rel.tag = bigtree_tags.id
									  WHERE bigtree_tags_rel.`table` = ?
										AND bigtree_tags_rel.`entry` = ?
						  			  ORDER BY bigtree_tags.tag ASC", $this->Table, $item);
			}
			
			return SQL::fetchAllSingle("SELECT bigtree_tags.tag FROM bigtree_tags JOIN bigtree_tags_rel
										ON bigtree_tags_rel.tag = bigtree_tags.id
										WHERE bigtree_tags_rel.`table` = ?
										  AND bigtree_tags_rel.`entry` = ?
						  				ORDER BY bigtree_tags.tag ASC", $this->Table, $item);
		}

		/*
			Function: getUnarchived
				Returns entries that are not archived from the table.
				Equivalent to getNonarchived.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching> <getNonarchived>
		*/
		
		function getUnarchived($order = false, $limit = false, $columns = false) {
			return $this->getMatching("archived", "", $order, $limit, false, $columns);
		}

		/*
			Function: getUnapproved
				Returns unapproved entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getUnapproved($order = false, $limit = false, $columns = false) {
			return $this->getMatching("approved", "", $order, $limit, false, $columns);
		}
		
		/*
			Function: getUpcoming
				Returns an array of entries from the table that occur in the future.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getUpcomingFeatured>
		*/
		
		function getUpcoming($count = 5, $field = "date", $columns = false) {
			return $this->fetch("$field ASC", $count, "DATE(`$field`) >= '".date("Y-m-d")."'", $columns);
		}
		
		/*
			Function: getUpcomingFeatured
				Returns an array of entries from the table that occur in the future and are featured.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getUpcoming>
		*/
		
		function getUpcomingFeatured($count = 5, $field = "date", $columns = false) {
			return $this->fetch("$field ASC", $count, "featured = 'on' AND DATE(`$field`) >= '".date("Y-m-d")."'", $columns);
		}
		
		/*
			Function: save
				Saves the given entry back to the table.
			
			Parameters:
				item - A modified entry from the table.
				ignore_cache - If this is set to true, BigTree will not cache this entry in bigtree_module_view_cache - faster entry if you don't have an admin view (defaults to false)
							
			See Also:
				<add>
				<delete>
				<update>
		*/
		
		function save($item, $ignore_cache = false) {
			$id = $item["id"];
			unset($item["id"]);
			
			$keys = array_keys($item);
			$this->update($id, $keys, $item, $ignore_cache);
		}
		
		/*
			Function: search
				Returns an array of entries from the table with columns that match the search query.
			
			Parameters:
				query - A string to search for.
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max entries to return (defaults to all)
				split_search - If set to true, splits the query into parts and searches each part (defaults to false).
				case_sensitive - Case sensitivity (defaults to false / the collation of the database).
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
		*/
		
		function search($query, $order = false, $limit = false, $split_search = false, $case_sensitive = false, $columns = false) {
			$table_description = SQL::describeTable($this->Table);
			$where = [];

			if ($split_search) {
				$pieces = explode(" ", $query);

				foreach ($pieces as $piece) {
					if ($piece) {
						$piece = SQL::escape($piece);
						$where_piece = [];

						foreach ($table_description["columns"] as $field => $parameters) {
							if ($case_sensitive) {
								$where_piece[] = "`$field` LIKE '%$piece%'";
							} else {
								$where_piece[] = "LOWER(`$field`) LIKE '%".strtolower($piece)."%'";
							}
						}

						$where[] = "(".implode(" OR ", $where_piece).")";
					}
				}

				return $this->fetch($order, $limit, implode(" AND ", $where), $columns);
			} else {
				foreach ($table_description["columns"] as $field => $parameters) {
					if ($case_sensitive) {
						$where[] = "`$field` LIKE '%".SQL::escape($query)."%'";
					} else {
						$where[] = "LOWER(`$field`) LIKE '%".SQL::escape(strtolower($query))."%'";
					}
				}

				return $this->fetch($order, $limit, implode(" OR ", $where), $columns);
			}
		}
		
		/*
			Function: setPosition
				Sets the position of a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
				position - The position to set. BigTree sorts by default as position DESC, id ASC.
		*/
		
		function setPosition($item, $position) {
			if (is_array($item)) {
				$item = $item["id"];
			}

			$this->update($item, "position", $position);
			ModuleView::cacheForAll($this->Table, $item);
		}
		
		/*
			Function: unapprove
				Unapproves a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<approve>
		*/
		
		function unapprove($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}

			$this->update($item, "approved", "");
			ModuleView::cacheForAll($this->Table, $item);
		}
		
		/*
			Function: unarchive
				Unarchives a given entry.
			
			Parameters:
				item -  The "id" of an entry or an entry from the table.
			
			See Also:
				<archive>
		*/
		
		function unarchive($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}

			$this->update($item, "archived", "");
			ModuleView::cacheForAll($this->Table, $item);
		}
		
		/*
			Function: unfeature
				Unfeatures a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<feature>
		*/
		
		function unfeature($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}

			$this->update($item, "featured", "");
			ModuleView::cacheForAll($this->Table, $item);
		}
		
		/*
			Function: update
				Updates an entry in the table.
			
			Parameters:
				id - The "id" of the entry in the table.
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well) — Optionally this can be a key/value array and the values field kept false
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
				ignore_cache - If this is set to true, BigTree will not cache this entry in bigtree_module_view_cache - faster entry if you don't have an admin view (defaults to false)	
			
			See Also:
				<add>
				<delete>
				<save>
		*/
		
		function update($id, $fields, $values = [], $ignore_cache = false) {
			$update_fields = [];

			// Turn a key => value array into pairs
			if ($values === [] && is_array($fields)) {
				$update_fields = $fields;

			// Multiple columns to update
			} elseif (is_array($fields)) {
				foreach ($fields as $key) {
					$update_fields[$key] = current($values);
					next($values);
				}

			// Single column to update
			} else {
				$update_fields[$fields] = $values;
			}

			// Update
			SQL::update($this->Table, $id, $update_fields);

			if (!$ignore_cache) {
				ModuleView::cacheForAll($this->Table, $id);
			}
		}
	}