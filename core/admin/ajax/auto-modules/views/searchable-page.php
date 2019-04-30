<?php
	namespace BigTree;

	/**
	 * @global Module $module
	 * @global ModuleView $view
	 */

	// Grab View Data
	if (isset($_GET["view"])) {
		$view = new ModuleView($_GET["view"]);
	}
	
	if (isset($_GET["module"])) {
		$module = new Module($_GET["module"]);
	}

	$query = isset($_GET["search"]) ? $_GET["search"] : "";
	$page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
	$module_permission = $module->UserAccessLevel;
	$module_page = ADMIN_ROOT.$module->Route."/";

	// Figure out which column to sort by
	if (isset($_GET["sort"])) {
		$sort = $_GET["sort"]." ".$_GET["sort_direction"];
		
		// Append information to the end of an edit string so that we can return to the same set of search results after submitting a form.
		$edit_append = "?view_data=".base64_encode(json_encode([
			"view" => $view->ID, 
			"sort" => $_GET["sort"],
			"sort_direction" => $_GET["sort_direction"],
			"search" => $query,
			"page" => $page
		]));
	} else {
		if (isset($view->Settings["sort_column"])) {
			$sort = $view->Settings["sort_column"]." ".$view->Settings["sort_direction"];
		} elseif (isset($view->Settings["sort"])) {
			$sort = $view->Settings["sort"];
		} else {
			$sort = "id DESC";
		}
		
		// Same thing we were going to do above but omit the sort stuff.
		$edit_append = "?view_data=".base64_encode(json_encode([
			"view" => $view->ID, 
			"search" => $query, 
			"page" => $page
		]));
	}

	// Handle how many pages we have and get our results.
	$data = $view->searchData($page, $query, $sort, false);
	$pages = $data["pages"];
	$items = $data["results"];
	
	foreach ($items as $item) {
		// If it's straight from the db, it's published.
		if (!isset($item["status"])) {
			$item["status"] = "";
		}
		
		if ($item["status"] == "p") {
			$status = "Pending";
			$status_class = "pending";
		} elseif ($item["status"] == "c") {
			$status = "Changed";
			$status_class = "pending";
		} elseif ($item["status"] == "i") {
			$status = "Inactive";
			$status_class = "inactive";
		} else {
			$status = "Published";
			$status_class = "published";
		}

		$entry_permission = ($module_permission == "p") ? "p" : Auth::user()->getCachedAccessLevel($module, $item, $view->Table);
?>
<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
	<?php
		$x = 0;
		
		foreach ($view->Fields as $key => $field) {
			$x++;
			$value = $item["column$x"];
	?>
	<section class="view_column" style="width: <?=$field["width"]?>px;">
		<?=$value?>
	</section>
	<?php
		}
	?>
	<section class="view_status status_<?=$status_class?>"><?=Text::translate($status)?></section>
	<?php
		foreach ($view->Actions as $action => $data) {
			if ($data == "on") {
				if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $entry_permission != "p") {
					if ($action == "delete" && $item["pending_owner"] == Auth::user()->ID) {
						$class = "icon_delete js-hook-delete";
					} else {
						$class = "icon_disabled js-hook-disabled";
					}
				} else {
					$class = $view->generateActionClass($action, $item);
				}
				
				$action_title = ucwords($action);
				
				if ($action == "archive" && $item["archived"]) {
					$action_title = "Restore";
				} elseif ($action == "feature" && $item["featured"]) {
					$action_title = "Unfeature";
				} elseif ($action == "approve" && $item["approved"]) {
					$action_title = "Unapprove";
				}
				
				if ($action == "preview") {
					$link = rtrim($view->PreviewURL,"/")."/".$item["id"].'/" target="_preview';
				} elseif ($action == "edit") {
					$link = $view->EditURL.$item["id"]."/".$edit_append;
				} else {
					$link = "#".$item["id"];
				}
	?>
	<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>" title="<?=Text::translate($action_title, true)?>"></a></section>
	<?php
			} else {
				$data = json_decode($data,true);
				$link = $module_page.$data["route"]."/".$item["id"]."/";

				if ($data["function"]) {
					$link = call_user_func($data["function"],$item);
				}
	?>
	<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>" title="<?=Text::translate($data["name"], true)?>"></a></section>
	<?php
			}
		}
	?>
</li>
<?php
	}
?>
<script>
	BigTree.setPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>