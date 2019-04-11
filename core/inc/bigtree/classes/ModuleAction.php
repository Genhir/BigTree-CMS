<?php
	/*
		Class: BigTree\ModuleAction
			Provides an interface for handling BigTree module actions.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 */
	
	class ModuleAction extends BaseObject
	{
		
		protected $ID;
		protected $OriginalRoute;
		
		public $Icon;
		public $InNav;
		public $Interface;
		public $Level;
		public $Module;
		public $Name;
		public $Position;
		public $Route;
		
		public static $Table = "bigtree_module_actions";
		
		/*
			Constructor:
				Builds a ModuleAction object referencing an existing database entry.

			Parameters:
				action - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($action = null)
		{
			if ($action !== null) {
				// Passing in just an ID
				if (!is_array($action)) {
					$action = SQL::fetch("SELECT * FROM bigtree_module_actions WHERE id = ?", $action);
				}
				
				// Bad data set
				if (!is_array($action)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $action["id"];
					
					$this->Icon = $action["class"];
					$this->InNav = $action["in_nav"] ? true : false;
					$this->Interface = $action["interface"] ?: false;
					$this->Level = $action["level"];
					$this->Module = $action["module"];
					$this->Name = $action["name"];
					$this->Position = $action["position"];
					$this->Route = $this->OriginalRoute = $action["route"];
				}
			}
		}
		
		/*
			Function: create
				Creates a module action.

			Parameters:
				module - The module to create an action for.
				name - The name of the action.
				route - The action route.
				in_nav - Whether the action is in the navigation.
				icon - The icon class for the action.
				interface - Related module interface.
				level - The required access level.
				position - The position in navigation.

			Returns:
				A ModuleAction object.
		*/
		
		public static function create(int $module, string $name, string $route, bool $in_nav, string $icon,
									  ?int $interface, int $level = 0, int $position = 0): ModuleAction
		{
			// Get a clean unique route
			$route = SQL::unique("bigtree_module_actions", "route", Link::urlify($route), ["module" => $module], true);
			
			// Create
			$id = SQL::insert("bigtree_module_actions", [
				"module" => $module,
				"name" => Text::htmlEncode($name),
				"route" => $route,
				"in_nav" => ($in_nav ? "on" : ""),
				"class" => $icon,
				"level" => intval($level),
				"interface" => $interface ?: null,
				"position" => $position
			]);
			
			AuditTrail::track("bigtree_module_actions", $id, "created");
			
			return new ModuleAction($id);
		}
		
		/*
			Function: delete
				Deletes the module action and the related interface (if no other action is using it).
		*/
		
		public function delete(): ?bool
		{
			// If this action is the only one using the interface, delete it as well
			if ($this->Interface) {
				$interface_count = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_module_actions
													 WHERE interface = ?", $this->Interface);
				
				if ($interface_count == 1) {
					SQL::delete("bigtree_module_interfaces", $this->Interface);
					AuditTrail::track("bigtree_module_interfaces", $this->Interface, "deleted");
				}
			}
			
			// Delete the action
			SQL::delete("bigtree_module_actions", $this->ID);
			AuditTrail::track("bigtree_module_actions", $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: existsForRoute
				Checks to see if an action exists for a given route and module.

			Parameters:
				module - The module to check.
				route - The route of the action to check.

			Returns:
				true if an action exists, otherwise false.
		*/
		
		public static function existsForRoute(int $module, string $route): bool
		{
			return SQL::exists("bigtree_module_actions", ["module" => $module, "route" => $route]);
		}
		
		/*
			Function: getByInterface
				Returns the module action for a given module interface.
				Prioritizes edit action over add.
		
			Parameters:
				interface - The ID of an interface, interface array or interface object.

			Returns:
				A module action entry or false if none exists for the provided interface.
		*/
		
		public static function getByInterface($interface): ?ModuleAction
		{
			if (is_object($interface)) {
				$id = $interface->ID;
			} elseif (is_array($interface)) {
				$id = $interface["id"];
			} else {
				$id = $interface;
			}
			
			$action = SQL::fetch("SELECT * FROM bigtree_module_actions WHERE interface = ? ORDER BY route DESC", $id);
			
			return $action ? new ModuleAction($action) : null;
		}
		
		/*
			Function: getUserCanAccess
				Determines whether the logged in user has access to the action or not.

			Returns:
				true if the user can access the action, otherwise false.
		*/
		
		public function getUserCanAccess(): bool
		{
			return Auth::user()->canAccess($this);
		}
		
		/*
			Function: lookup
				Returns a ModuleAction for the given module and route.

			Parameters:
				module - The module to lookup an action for.
				route - The route of the action.

			Returns:
				An array containing the action and additional commands or false if lookup failed.
		*/
		
		public static function lookup(int $module, array $route): ?array
		{
			// For landing routes.
			if (!count($route)) {
				$route = [""];
			}
			
			$commands = [];
			
			while (count($route)) {
				$action = SQL::fetch("SELECT * FROM bigtree_module_actions 
									  WHERE module = ? AND route = ?", $module, implode("/", $route));
				
				// If we found an action for this sequence, return it with the extra URL route commands
				if (!empty($action)) {
					return ["action" => new ModuleAction($action), "commands" => array_reverse($commands)];
				}
				
				// Otherwise strip off the last route as a command and try again
				$commands[] = array_pop($route);
			}
			
			return null;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			if (empty($this->ID)) {
				$action = static::create($this->Module, $this->Name, $this->Route, $this->InNav, $this->Icon,
										 $this->Interface, $this->Level, $this->Position);
				$this->ID = $action->ID;
			} else {
				// Make sure route is unique and clean
				$this->Route = Link::urlify($this->Route);
				
				if ($this->Route != $this->OriginalRoute) {
					$this->Route = SQL::unique("bigtree_module_actions", "route", $this->Route,
											   ["module" => $this->Module], true);
					$this->OriginalRoute = $this->Route;
				}
				
				SQL::update("bigtree_module_actions", $this->ID, [
					"name" => Text::htmlEncode($this->Name),
					"route" => $this->Route,
					"class" => $this->Icon,
					"in_nav" => $this->InNav ? "on" : false,
					"level" => $this->Level,
					"position" => $this->Position,
					"interface" => $this->Interface ?: null
				]);
				
				AuditTrail::track("bigtree_module_actions", $this->ID, "updated");
			}
			
			return true;
		}
		
		/*
			Function: update
				Updates the module action.

			Parameters:
				name - The name of the action.
				route - The action route.
				in_nav - Whether the action is in the navigation.
				icon - The icon class for the action.
				interface - Related module interface.
				level - The required access level.
				position - The position in navigation.
		*/
		
		public function update(string $name, string $route, bool $in_nav, string $icon, ?int $interface, int $level,
							   int $position): void
		{
			$this->Name = $name;
			$this->Route = $route;
			$this->InNav = $in_nav;
			$this->Icon = $icon;
			$this->Interface = $interface;
			$this->Level = $level;
			$this->Position = $position;
			
			$this->save();
		}
		
	}
	