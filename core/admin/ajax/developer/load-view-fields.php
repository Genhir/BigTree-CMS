<?php
	namespace BigTree;

	/**
	 * @global string $table
	 * @global string $type
	 */

	if (isset($_GET["table"])) {
		$table = $_GET["table"];
	}

	if (isset($_GET["type"])) {
		$type = $_GET["type"];
	}

	$used = [];
	$unused = [];
	$table_fields = [];

	if ($table) {
		$table_description = SQL::describeTable($table);

		foreach ($table_description["columns"] as $column => $details) {
			$table_fields[] = $column;
		}
	}
	
	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			$used[] = $key;
		}

		// Figure out the fields we're not using so we can offer them back.
		foreach ($table_fields as $field) {
			if (!in_array($field, Module::$ReservedColumns) && !in_array($field, $used)) {
				$unused[] = [
					"field" => $field, 
					"title" => ucwords(str_replace("_", " ", $field))
				];
			}
		}		
	}

	// Add the ability for someone to create a custom view field
	$unused[] = ["field" => "— Custom —", "title" => ""];

	if (count($table_fields)) {
		$parser_placeholder = Text::translate('PHP code to transform $value (which contains the column value.)', true);
?>
<fieldset id="fields"<?php if ($type == "images" || $type == "images-grouped") { ?> style="display: none;"<?php } ?>>
	<label><?=Text::translate("Fields")?></label>
	
	<div class="form_table">
		<header></header>
		<div class="labels">
			<span class="developer_view_title"><?=Text::translate("Title")?></span>
			<span class="developer_view_parser"><?=Text::translate("Parser")?></span>
			<span class="developer_resource_action"><?=Text::translate("Delete")?></span>
		</div>
		<ul id="sort_table">
			<?php
				// If we're loading an existing data set.
				if (isset($fields)) {
					foreach ($fields as $key => $field) {
						$used[] = $key;
			?>
			<li id="row_<?=$key?>">
				<input type="hidden" name="fields[<?=$key?>][width]" value="<?=$field["width"]?>" />
				<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[<?=$key?>][title]" value="<?=$field["title"]?>" /></section>
				<section class="developer_view_parser"><input type="text" name="fields[<?=$key?>][parser]" value="<?=htmlspecialchars($field["parser"])?>" class="parser" placeholder="<?=$parser_placeholder?>" /></section>
				<section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>
			</li>
			<?php
					}			
				// Otherwise we're loading a new data set based on a table.
				} else {
					foreach ($table_fields as $key) {
						if (!in_array($key, Module::$ReservedColumns)) {
			?>
			<li id="row_<?=$key?>">
				<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[<?=$key?>][title]" value="<?=htmlspecialchars(ucwords(str_replace("_"," ",$key)))?>" /></section>
				<section class="developer_view_parser"><input type="text" name="fields[<?=$key?>][parser]" value="" class="parser" placeholder="<?=$parser_placeholder?>" /></section>
				<section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>
			</li>
			<?php
						}
					}
				}
			?>
		</ul>
	</div>
</fieldset>
<fieldset class="last">
	<label><?=Text::translate("Actions <small>(click to deselect, drag bottom tab to rearrange)</small>")?></label>
	<div class="developer_action_list">
		<ul>
			<?php
				$used_actions = [];

				if (!empty($actions)) {
					foreach ($actions as $key => $action) {
						if ($action != "on") {
							$data = json_decode($action, true);
							$key = $data["route"];
							$class = $data["class"];
						} else {
							$class = "icon_$key";
							if ($key == "feature" || $key == "approve") {
								$class .= " icon_".$key."_on";
							}
						}

						$used_actions[] = $key;
			?>
			<li>
				<input class="custom_control" type="checkbox" name="actions[<?=$key?>]" checked="checked" value="<?=htmlspecialchars($action)?>" />
				<a href="#" class="action active">
					<span class="<?=$class?>"></span>
				</a>
				<div class="handle"><?php if ($action != "on") { ?><span class="edit"></span><?php } ?></div>
			</li>
			<?php
					}
				}

				foreach (ModuleView::$CoreActions as $key => $action) {
					if (!in_array($key, $used_actions) && (in_array($action["key"], $table_fields) || defined("BIGTREE_MODULE_DESIGNER_VIEW"))) {
						$checked = false;

						if (isset($actions[$key]) || (!isset($actions) && !defined("BIGTREE_MODULE_DESIGNER_VIEW")) || (defined("BIGTREE_MODULE_DESIGNER_VIEW") && ($key == "edit" || $key == "delete"))) {
							$checked = true;
						}
			?>
			<li>
				<input class="custom_control" type="checkbox" name="actions[<?=$key?>]" value="on" <?php if ($checked) { ?>checked="checked" <?php } ?>/>
				<a href="#" class="action<?php if ($checked) { ?> active<?php } ?>">
					<span class="<?=$action["class"]?>"></span>
				</a>
				<div class="handle"></div>
			</li>
			<?php
					}
				}
			?>
		</ul>
		<a href="#" class="button add_action"><?=Text::translate("Add")?></a>
	</div>
</fieldset>

<script>
	(function() {
		var CustomAction = false;
		var FieldSelect;

		function hooks() {
			$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		}

		// Hook removal of fields to add them back to the field select dropdown
		$(".form_table").on("click",".icon_delete",function() {
			var title_field = $(this).parents("li").find("section").find("input");
			var title = title_field.val();
			var key = title_field.attr("name").substr(7);

			key = key.substr(0, key.length - 8);
			FieldSelect.addField(key, title);

			$(this).parents("li").remove();

			return false;
		});

		// Hook action boxes
		$(".developer_action_list").on("click",".action",function() {
			if ($(this).hasClass("active")) {
				$(this).removeClass("active");
				$(this).prev("input").prop("checked",false);
			} else {
				$(this).addClass("active");
				$(this).prev("input").prop("checked",true);
			}

			return false;
		}).on("click",".edit",function() {
			CustomAction = $(this).parents("li");
			var json = $.parseJSON(CustomAction.find("input").val());

			BigTreeDialog({
				title: "<?=Text::translate("Edit Custom Action", true)?>",
				content: '<fieldset>' +
							'<label><?=Text::translate("Action Name")?></label>' +
							'<input type="text" name="name" value="' + htmlspecialchars(json.name) + '" />' +
						'</fieldset>' +
						'<fieldset>' +
							'<label><?=Text::translate("Action Image Class <small>(i.e. icon_preview)</small>")?></label>' +
							'<input type="text" name="class" value="' + htmlspecialchars(json.class) + '" />' +
						'</fieldset>' +
						'<fieldset>' +
							'<label><?=Text::translate("Action Route")?></label>' +
							'<input type="text" name="route" value="' + htmlspecialchars(json.route) + '" />' +
						'</fieldset>' +
						'<fieldset class="last">' +
							'<label><?=Text::translate("Link Function <small>(if you need more than simply /route/id/)</small>")?></label>' +
							'<input type="text" name="function" value="' + htmlspecialchars(json.function) + '" />' +
						'</fieldset>',
				icon: "edit",
				callback: function(data) {
					CustomAction.load("<?=ADMIN_ROOT?>ajax/developer/add-view-action/", data);
				}
			});
		}).sortable({ axis: "x", containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });

		// Custom action adding
		$(".add_action").click(function() {
			BigTreeDialog({
				title: "<?=Text::translate("Add Custom Action", true)?>",
				content: '<fieldset>' +
							'<label><?=Text::translate("Action Name")?></label>' +
							'<input type="text" name="name" />' +
						'</fieldset>' +
						'<fieldset>' +
							'<label><?=Text::translate("Action Image Class <small>(i.e. icon_preview)</small>")?></label>' +
							'<input type="text" name="class" />' +
						'</fieldset>' +
						'<fieldset>' +
							'<label><?=Text::translate("Action Route")?></label>' +
							'<input type="text" name="route" />' +
						'</fieldset>' +
						'<fieldset class="last">' +
							'<label><?=Text::translate("Link Function <small>(if you need more than simply /route/id/)</small>")?></label>' +
							'<input type="text" name="function" />' +
						'</fieldset>',
				icon: "add",
				alternateSaveText: "<?=Text::translate("Add", true)?>",
				callback: function(data) {
					var li = $('<li>');

					li.load("<?=ADMIN_ROOT?>ajax/developer/add-view-action/", data);
					$(".developer_action_list li:first-child").before(li);
				}
			});

			return false;
		});

		FieldSelect = BigTreeFieldSelect({
			selector: ".form_table header",
			elements: <?=json_encode($unused)?>,
			callback: function(el, field_select) {
				var title = el.title;
				var key = el.field;

				if (title) {
					var li = $('<li id="row_' + key + '">');

					li.html('<section class="developer_view_title">' +
								'<span class="icon_sort"></span>' +
								'<input type="text" name="fields[' + key + '][title]" value="' + title + '" />' +
							'</section>' +
							'<section class="developer_view_parser">' +
								'<input type="text" class="parser" name="fields[' + key + '][parser]" value="" placeholder="<?=$parser_placeholder?>"/>' +
							'</section>' +
							'<section class="developer_resource_action">' +
								'<a href="#" class="icon_delete"></a>' +
							'</section>');

					$("#sort_table").append(li);

					field_select.removeCurrent();
					hooks();
				} else {
					BigTreeDialog({
						title: "<?=Text::translate("Add Custom Column", true)?>",
						content: '<fieldset>' +
									'<label><?=Text::translate("Column Key <small>(must be unique)</small>")?></label>' +
									'<input type="text" name="key" />' +
								'</fieldset>' +
								'<fieldset class="last">' +
									'<label><?=Text::translate("Column Title")?></label>' +
									'<input type="text" name="title" />' +
								'</fieldset>',
						icon: "add",
						alternateSaveText: "Add",
						callback: function(data) {
							var key = htmlspecialchars(data.key);
							var title = htmlspecialchars(data.title);

							var li = $('<li id="row_' + key + '">');
							li.html('<section class="developer_view_title">' +
										'<span class="icon_sort"></span>' +
										'<input type="text" name="fields[' + key + '][title]" value="' + title + '" />' +
									'</section>' +
									'<section class="developer_view_parser">' +
										'<input type="text" class="parser" name="fields[' + key + '][parser]" value="" placeholder="<?=$parser_placeholder?>" />' +
									'</section>' +
									'<section class="developer_resource_action">' +
										'<a href="#" class="icon_delete"></a>' +
									'</section>');
							$("#sort_table").append(li);

							hooks();
						}
					});
				}
			}
		});

		hooks();

	})();
</script>
<?php
	} else {
?>
<p><?=Text::translate("Please choose a table to populate this area.")?></p>
<?php
	}
?>