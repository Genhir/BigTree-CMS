<?php
	namespace BigTree;
	
	/**
	 * @global string $access_level
	 * @global ModuleForm $form
	 * @global int $id
	 * @global array $item
	 * @global Module $module
	 * @global string $table
	 * @global callable $run_publish_hook
	 */
	
	include "_setup.php";
	
	if ($item["approved"]) {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now unapproved.";
			
			if (is_numeric($id)) {
				SQL::update($table, $id, ["approved" => ""]);
				$run_publish_hook(["approved" => ""]);
			} else {
				$form->updatePendingEntryField(substr($id, 1), "approved", "");
			}
		}
	} else {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now approved.";
			
			if (is_numeric($id)) {
				SQL::update($table, $id, ["approved" => "on"]);
				$run_publish_hook(["approved" => "on"]);
			} else {
				$form->updatePendingEntryField(substr($id, 1), "approved", "on");
			}
		}
	}
	
	include "_recache.php";
?>
BigTree.growl("<?=$module->Name?>","<?=Text::translate($message, true)?>");