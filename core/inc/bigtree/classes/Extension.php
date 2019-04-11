<?php
	/*
		Class: BigTree\Extension
			Provides an interface for handling BigTree extensions.
	*/
	
	namespace BigTree;
	
	class Extension extends BaseObject
	{
		protected $LastUpdated;
		
		public $ID;
		public $Manifest;
		public $Name;
		public $Type;
		public $Version;
		
		public static $CacheInitialized = false;
		public static $RequiredFiles = [];
		public static $Table = "bigtree_extensions";
		
		/*
			Constructor:
				Builds a Extension object referencing an existing database entry.

			Parameters:
				extension - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($extension = null)
		{
			if ($extension !== null) {
				// Passing in just an ID
				if (!is_array($extension)) {
					$extension = SQL::fetch("SELECT * FROM bigtree_extensions WHERE id = ?", $extension);
				}
				
				// Bad data set
				if (!is_array($extension)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $extension["id"];
					$this->Manifest = array_filter((array) @json_decode($extension["manifest"], true));
					$this->Name = $extension["name"];
					$this->Type = $extension["type"];
					$this->Version = $extension["version"];
					
					$this->LastUpdated = $extension["last_updated"];
				}
			}
		}
		
		/*
			Function: delete
				Uninstalls the extension or package from BigTree and removes its related components and files.
		*/
		
		public function delete(): ?bool
		{
			// Prevent the whole directory from being deleted if this doesn't have an ID
			if (empty($this->ID)) {
				return false;
			}
			
			// Regular extension
			if ($this->Type == "extesion") {
				// Delete site files
				FileSystem::deleteDirectory(SITE_ROOT."extensions/".$this->ID."/");
				// Delete extensions directory
				FileSystem::deleteDirectory(SERVER_ROOT."extensions/".$this->ID."/");
				
				// Delete components
				foreach ($this->Manifest["components"] as $type => $list) {
					if ($type == "tables") {
						// Turn off foreign key checks since we're going to be dropping tables.
						SQL::query("SET SESSION foreign_key_checks = 0");
						
						// Drop all the tables the extension created
						foreach ($list as $table => $create_statement) {
							SQL::query("DROP TABLE IF EXISTS `$table`");
						}
						
						// Re-enable foreign key checks
						SQL::query("SET SESSION foreign_key_checks = 1");
					} else {
						// Remove other database entries
						foreach ($list as $item) {
							SQL::delete("bigtree_".$type, $item["id"]);
						}
					}
				}
			// Simple package
			} else {
				// Delete related files
				foreach ($this->Manifest["files"] as $file) {
					FileSystem::deleteFile(SERVER_ROOT.$file);
				}
				
				// Delete components
				foreach ($this->Manifest["components"] as $type => $list) {
					if ($type == "tables") {
						// Turn off foreign key checks since we're going to be dropping tables.
						SQL::query("SET SESSION foreign_key_checks = 0");
						
						// Remove all the tables the package added
						foreach ($list as $table) {
							SQL::query("DROP TABLE IF EXISTS `$table`");
						}
						
						// Re-enable key checks
						SQL::query("SET SESSION foreign_key_checks = 1");
					} else {
						// Remove all the bigtree components the package made
						foreach ($list as $item) {
							SQL::delete("bigtree_$type", $item["id"]);
						}
						
						// Modules might have their own directories
						if ($type == "modules") {
							foreach ($list as $item) {
								FileSystem::deleteDirectory(SERVER_ROOT."custom/admin/modules/".$item["route"]."/");
								FileSystem::deleteDirectory(SERVER_ROOT."custom/admin/ajax/".$item["route"]."/");
								FileSystem::deleteDirectory(SERVER_ROOT."custom/admin/images/".$item["route"]."/");
							}
						} elseif ($type == "templates") {
							foreach ($list as $item) {
								FileSystem::deleteDirectory(SERVER_ROOT."templates/routed/".$item["id"]."/");
							}
						}
					}
				}
			}
			
			// Delete extension entry
			SQL::delete("bigtree_extensions", $this->ID);
			
			// Track
			AuditTrail::track("bigtree_extensions", $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: initializeCache
				Initializes any extension plugins and caches them to the proper objects.
		*/
		
		public static function initializeCache(): void
		{
			global $bigtree;
			
			// Already done!
			if (static::$CacheInitialized) {
				return;
			}
			
			$extension_cache_file = SERVER_ROOT."cache/bigtree-extension-cache.json";
			
			// Handle extension cache
			if ($bigtree["config"]["debug"] || !file_exists($extension_cache_file)) {
				$plugins = [
					"cron" => [],
					"daily-digest" => [],
					"dashboard" => [],
					"interfaces" => [],
					"view-types" => []
				];
				
				$extension_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_extensions WHERE type = 'extension'");
				
				foreach ($extension_ids as $extension_id) {
					// Load up the manifest
					$manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/$extension_id/manifest.json"), true);
					if (!empty($manifest["plugins"]) && is_array($manifest["plugins"])) {
						foreach ($manifest["plugins"] as $type => $list) {
							foreach ($list as $id => $plugin) {
								$plugins[$type][$extension_id][$id] = $plugin;
							}
						}
					}
				}
				
				// If no longer in debug mode, cache it
				if (!$bigtree["config"]["debug"]) {
					file_put_contents($extension_cache_file, JSON::encode($plugins));
				}
			} else {
				$plugins = json_decode(file_get_contents($extension_cache_file), true);
			}
			
			Cron::$Plugins = $plugins["cron"];
			DailyDigest::$Plugins = $plugins["daily-digest"];
			Dashboard::$Plugins = $plugins["dashboard"];
			ModuleInterface::$Plugins = $plugins["interfaces"];
			ModuleView::$Plugins = $plugins["view-types"];
			
			static::$CacheInitialized = true;
		}
		
		/*
			Function: installFromManifest
				Installs an extension from a manifest.

			Parameters:
				manifest - Manifest array
				upgrade - Old manifest array (if doing an upgrade, otherwise leave null)

			Returns:
				An Extension object.
		*/
		
		public static function installFromManifest(array $manifest, ?array $upgrade = null): ?Extension
		{
			global $bigtree;
			
			// Initialize a bunch of empty arrays
			$bigtree["group_match"] = $bigtree["module_match"] = $bigtree["route_match"] = $bigtree["class_name_match"] = $bigtree["form_id_match"] = $bigtree["view_id_match"] = $bigtree["report_id_match"] = [];
			$extension = $manifest["id"];
			
			// Turn off foreign key checks so we can reference the extension before creating it
			SQL::query("SET foreign_key_checks = 0");
			
			// Upgrades drop existing modules, templates, etc -- we don't drop settings because they have user data
			if (is_array($upgrade)) {
				SQL::delete("bigtree_module_groups", ["extension" => $extension]);
				SQL::delete("bigtree_modules", ["extension" => $extension]);
				SQL::delete("bigtree_templates", ["extension" => $extension]);
				SQL::delete("bigtree_callouts", ["extension" => $extension]);
				SQL::delete("bigtree_field_types", ["extension" => $extension]);
				SQL::delete("bigtree_feeds", ["extension" => $extension]);
				
			// Import tables for new installs
			} else {
				foreach ($manifest["components"]["tables"] as $table_name => $sql_statement) {
					SQL::query("DROP TABLE IF EXISTS `$table_name`");
					SQL::query($sql_statement);
				}
			}
			
			// Import module groups
			foreach ($manifest["components"]["module_groups"] as &$group) {
				if (array_filter((array) $group)) {
					$bigtree["group_match"][$group["id"]] = ModuleGroup::create($group["name"]);
					// Update the group ID since we're going to save this manifest locally for uninstalling
					$group["id"] = $bigtree["group_match"][$group["id"]];
					SQL::update("bigtree_module_groups", $group["id"], ["extension" => $extension]);
				}
			}
			
			// Import modules
			foreach ($manifest["components"]["modules"] as &$module) {
				if (array_filter((array) $module)) {
					$group = ($module["group"] && isset($bigtree["group_match"][$module["group"]])) ? $bigtree["group_match"][$module["group"]] : null;
					
					// Find a unique route
					$route = SQL::unique("bigtree_modules", "route", $module["route"]);
					
					// Create the module
					$module_id = SQL::insert("bigtree_modules", [
						"name" => $module["name"],
						"route" => $route,
						"class" => $module["class"],
						"icon" => $module["icon"],
						"group" => $group,
						"gbp" => $module["gbp"],
						"extension" => $extension
					]);
					
					// Setup matches
					$bigtree["module_match"][$module["id"]] = $module_id;
					$bigtree["route_match"][$module["route"]] = $route;
					
					// Update the module ID since we're going to save this manifest locally for uninstalling
					$module["id"] = $module_id;
					
					// Create views
					foreach ($module["views"] as $view) {
						$view_object = ModuleView::create($module_id, $view["title"], $view["description"], $view["table"], $view["type"], Utils::arrayValue($view["options"]), Utils::arrayValue($view["fields"]), Utils::arrayValue($view["actions"]), $view["suffix"], $view["preview_url"]);
						$bigtree["view_id_match"][$view["id"]] = $view_object->ID;
					}
					
					// Create regular forms
					foreach ($module["forms"] as $form) {
						$form_object = ModuleForm::create($module_id, $form["title"], $form["table"], Utils::arrayValue($form["fields"]), $form["hooks"], $form["default_position"], ($form["return_view"] ? $bigtree["view_id_match"][$form["return_view"]] : false), $form["return_url"], $form["tagging"]);
						$bigtree["form_id_match"][$form["id"]] = $form_object->ID;
					}
					
					// Create reports
					foreach ($module["reports"] as $report) {
						$report_object = ModuleReport::create($module_id, $report["title"], $report["table"], $report["type"], Utils::arrayValue($report["filters"]), Utils::arrayValue($report["fields"]), $report["parser"], ($report["view"] ? $bigtree["view_id_match"][$report["view"]] : false));
						$bigtree["report_id_match"][$report["id"]] = $report_object->ID;
					}
					
					// Create actions
					foreach ($module["actions"] as $action) {
						// 4.1 and 4.2 compatibility
						if ($action["report"]) {
							$action["interface"] = $bigtree["report_id_match"][$action["report"]];
						} elseif ($action["form"]) {
							$action["interface"] = $bigtree["form_id_match"][$action["form"]];
						} elseif ($action["view"]) {
							$action["interface"] = $bigtree["view_id_match"][$action["view"]];
						}
						
						ModuleAction::create($module_id, $action["name"], $action["route"], $action["in_nav"], $action["class"], $action["interface"], $action["level"], $action["position"]);
					}
				}
			}
			
			// Import templates
			foreach ($manifest["components"]["templates"] as $template) {
				if (array_filter((array) $template)) {
					SQL::insert("bigtree_templates", [
						"id" => $template["id"],
						"name" => $template["name"],
						"module" => $bigtree["module_match"][$template["module"]],
						"resources" => $template["resources"],
						"level" => $template["level"],
						"routed" => $template["routed"],
						"extension" => $extension
					]);
				}
			}
			
			// Import callouts
			foreach ($manifest["components"]["callouts"] as $callout) {
				if (array_filter((array) $callout)) {
					SQL::insert("bigtree_callouts", [
						"id" => $callout["id"],
						"name" => $callout["name"],
						"description" => $callout["description"],
						"display_default" => $callout["display_default"],
						"display_field" => $callout["display_field"],
						"resources" => $callout["resources"],
						"level" => $callout["level"],
						"position" => $callout["position"],
						"extension" => $extension
					]);
				}
			}
			
			// Import Settings
			foreach ($manifest["components"]["settings"] as $setting) {
				if (array_filter((array) $setting)) {
					Setting::create($setting["id"], $setting["name"], $setting["description"], $setting["type"], $setting["options"], $setting["extension"], $setting["system"], $setting["encrypted"], $setting["locked"]);
					SQL::update("bigtree_settings", $setting["id"], ["extension" => $extension]);
				}
			}
			
			// Import Feeds
			foreach ($manifest["components"]["feeds"] as $feed) {
				if (array_filter((array) $feed)) {
					SQL::insert("bigtree_feeds", [
						"route" => $feed["route"],
						"name" => $feed["name"],
						"description" => $feed["description"],
						"type" => $feed["type"],
						"table" => $feed["table"],
						"fields" => $feed["fields"],
						"options" => $feed["options"],
						"extension" => $extension
					]);
				}
			}
			
			// Import Field Types
			foreach ($manifest["components"]["field_types"] as $type) {
				if (array_filter((array) $type)) {
					SQL::insert("bigtree_field_types", [
						"id" => $type["id"],
						"name" => $type["name"],
						"use_cases" => $type["use_cases"],
						"self_draw" => $type["self_draw"] ? "'on'" : null,
						"extension" => $extension
					]);
				}
			}
			
			// Upgrades don't drop tables, we run the SQL revisions instead
			if (is_array($upgrade)) {
				$old_revision = $upgrade["revision"];
				$sql_revisions = $manifest["sql_revisions"];
				
				// Go through all the SQL updates, we ksort first to ensure if the manifest somehow got out of order that we run the SQL update sequentially
				ksort($sql_revisions);
				
				foreach ($sql_revisions as $key => $statements) {
					if ($key > $old_revision) {
						foreach ($statements as $sql_statement) {
							SQL::query($sql_statement);
						}
					}
				}
				
				// Update the extension
				SQL::update("bigtree_extensions", $extension, [
					"name" => $manifest["title"],
					"version" => $manifest["version"],
					"manifest" => $manifest
				]);
				
			// Straight installs move files into place locally
			} else {
				// Make sure destination doesn't exist
				$destination_path = SERVER_ROOT."extensions/".$manifest["id"]."/";
				FileSystem::deleteDirectory($destination_path);
				
				// Move the package to the extension directory
				rename(SERVER_ROOT."cache/package/", $destination_path);
				FileSystem::setDirectoryPermissions($destination_path);
				
				// Create the extension
				SQL::insert("bigtree_extensions", [
					"id" => $extension,
					"type" => "extension",
					"name" => $manifest["title"],
					"version" => $manifest["version"],
					"manifest" => $manifest
				]);
			}
			
			// Re-enable foreign key checks
			SQL::query("SET foreign_key_checks = 1");
			
			// Empty view cache
			SQL::query("DELETE FROM bigtree_module_view_cache");
			
			// Move public files into the site directory
			$public_dir = SERVER_ROOT."extensions/".$manifest["id"]."/public/";
			$site_contents = file_exists($public_dir) ? FileSystem::getDirectoryContents($public_dir) : [];
			
			foreach ($site_contents as $file_path) {
				$destination_path = str_replace($public_dir, SITE_ROOT."extensions/".$manifest["id"]."/", $file_path);
				FileSystem::copyFile($file_path, $destination_path);
			}
			
			// Clear module class cache and field type cache.
			FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
			FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");
			
			return new Extension($manifest["id"]);
		}
		
	}
