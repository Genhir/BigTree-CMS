<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global ModuleForm $form
	 */

	$db_error = false;
	$is_group_based_perm = false;
	$list = array();
	$list_table = "";

	// Database populated list.
	if ($this->Settings["list_type"] == "db") {
		$list_table = $this->Settings["pop-table"];
		$list_title = $this->Settings["pop-description"];
		$list_sort = $this->Settings["pop-sort"];
		
		// If debug is on we're going to check if the tables exists...
		if ($bigtree["config"]["debug"] && !SQL::tableExists($list_table)) {
			$db_error = true;
		} else {
			$entries = SQL::fetchAll("SELECT `id`,`$list_title` AS `title` FROM `$list_table` ORDER BY $list_sort");
			
			// Check if we're doing module based permissions on this table.
			if ($bigtree["module"] && $bigtree["module"]["gbp"]["enabled"] && $form->Table == $bigtree["module"]["gbp"]["table"] && $this->Key == $bigtree["module"]["gbp"]["group_field"]) {
				$module = new Module($bigtree["module"]);
				$is_group_based_perm = true;
				
				if ($this->Settings["allow-empty"] != "No") {
					$module_access_level = Auth::user()->getAccessLevel($bigtree["module"]);
				}
				
				foreach ($entries as $entry) {
					// Find out whether the logged in user can access a given group, and if so, specify the access level.
					$access_level = Auth::user()->getGroupAccessLevel($module, $entry["id"]);
					
					if ($access_level) {
						$list[] = array("value" => $entry["id"],"description" => $entry["title"],"access_level" => $access_level);
					}
				}
			// We're not doing module group based permissions, get a regular list.
			} else {
				foreach ($entries as $entry) {
					$list[] = array("value" => $entry["id"],"description" => $entry["title"]);
				}
			}
		}
	// State List
	} elseif ($this->Settings["list_type"] == "state") {
		foreach (Field::$StateList as $a => $s) {
			$list[] = array(
				"value" => $a,
				"description" => $s
			);
		}
	// Country List
	} elseif ($this->Settings["list_type"] == "country") {
		foreach (Field::$CountryList as $c) {
			$list[] = array(
				"value" => $c,
				"description" => $c
			);
		}
	// Static List
	} else {
		$list = $this->Settings["list"];
	}

	// If we have a parser, send a list of the available items through it.
	if (isset($this->Settings["parser"]) && $this->Settings["parser"]) {
		$list = call_user_func($this->Settings["parser"],$list);
	}

	// If the table was deleted for a database populated list, throw an error.
	if ($db_error) {
?>
<p class="error_message">The table for this field no longer exists (<?=htmlspecialchars($list_table)?>).</p>
<?php
	// Draw the list.
	} else {
		$class = array();
		
		if ($is_group_based_perm) {
			$class[] = "gbp_select";
		}

		if ($this->Required) {
			$class[] = "required";
		}
?>
<select<?php if (count($class)) { ?> class="<?=implode(" ",$class)?>"<?php } ?> name="<?=$this->Key?>" tabindex="<?=$this->TabIndex?>" id="<?=$this->ID?>">
	<?php
		if ($this->Settings["allow-empty"] != "No") {
	?>
	<option<?php if ($is_group_based_perm && !empty($module_access_level)) { ?> data-access-level="<?=$module_access_level?>"<?php } ?>></option>
	<?php
		}
	
		foreach ($list as $option) {
	?>
	<option value="<?=Text::htmlEncode($option["value"])?>"<?php if ($this->Value == $option["value"]) { ?> selected="selected"<?php } ?><?php if ($option["access_level"]) { ?> data-access-level="<?=$option["access_level"]?>"<?php } ?>><?=Text::htmlEncode(Text::trimLength(strip_tags($option["description"]), 100))?></option>
	<?php
		}
	?>
</select>
<?php
	}
?>