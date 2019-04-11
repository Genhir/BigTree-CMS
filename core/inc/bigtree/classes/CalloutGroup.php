<?php
	/*
		Class: BigTree\CalloutGroup
			Provides an interface for handling BigTree callout groups.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 */
	
	class CalloutGroup extends BaseObject
	{
		
		protected $ID;
		
		public $Callouts;
		public $Name;
		
		public static $Table = "bigtree_callout_groups";
		
		/*
			Constructor:
				Builds a CalloutGroup object referencing an existing database entry.

			Parameters:
				group - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($group = null) {
			if ($group !== null) {
				// Passing in just an ID
				if (!is_array($group)) {
					$group = SQL::fetch("SELECT * FROM bigtree_callout_groups WHERE id = ?", $group);
				}
				
				// Bad data set
				if (!is_array($group)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $group["id"];
					$this->Name = $group["name"];
					$this->Callouts = array_filter((array) (is_string($group["callouts"]) ? json_decode($group["callouts"], true) : $group["callouts"]));
				}
			}
		}
		
		/*
			Function: create
				Creates a callout group.

			Parameters:
				name - The name of the group.
				callouts - An array of callout IDs to assign to the group.

			Returns:
				A BigTree\CalloutGroup object.
		*/
		
		public static function create(string $name, array $callouts = []): CalloutGroup {
			// Order callouts alphabetically by ID
			sort($callouts);
			
			// Insert group
			$id = SQL::insert("bigtree_callout_groups", [
				"name" => Text::htmlEncode($name),
				"callouts" => $callouts
			]);
			
			AuditTrail::track("bigtree_callout_groups", $id, "created");
			
			return new CalloutGroup($id);
		}
		
		/*
			Function: delete
				Deletes a callout group.

			Parameters:
				id - The id of the callout group.
		*/
		
		public function delete(): ?bool {
			SQL::delete("bigtree_callout_groups", $this->ID);
			AuditTrail::track("bigtree_callout_groups", $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool {
			$this->Callouts = (array) $this->Callouts;
			sort($this->Callouts);
			
			$sql_data = [
				"name" => Text::htmlEncode($this->Name),
				"callouts" => $this->Callouts
			];
			
			if (empty($this->ID)) {
				$this->ID = SQL::insert("bigtree_callout_groups", $sql_data);
				AuditTrail::track("bigtree_callout_groups", $this->ID, "created");
			} else {
				SQL::update("bigtree_callout_groups", $this->ID, $sql_data);
				AuditTrail::track("bigtree_callout_groups", $this->ID, "updated");
			}
			
			return true;
		}
		
		/*
			Function: update
				Updates the callout group's name and callout list properties and saves them back to the database.

			Parameters:
				name - Name string.
				callouts - An array of callout IDs to assign to the group.
		*/
		
		public function update(string $name, array $callouts): ?bool {
			$this->Name = $name;
			$this->Callouts = $callouts;
			
			return $this->save();
		}
		
	}
