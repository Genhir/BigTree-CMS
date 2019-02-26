<?php
	namespace BigTree;
	
	/**
	 * @global Template $template
	 */
	
	$cached_types = FieldType::reference(true);
	$types = $cached_types["templates"];
?>
<script>
	(function() {
		var CurrentField = false;
		var ResourceCount = <?=count($template->Fields)?>;

		BigTreeFormValidator("form.module");
		$(".form_table").on("click",".icon_settings",function(ev) {
			ev.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			CurrentField = $(this).attr("name");
			
			BigTreeDialog({
				title: "<?=Text::translate("Field Settings", true)?>",
				url: "<?=ADMIN_ROOT?>ajax/developer/load-field-settings/",
				post: { template: "true", type: $("#type_" + CurrentField).val(), data: $("#settings_" + CurrentField).val() },
				icon: "edit",
				callback: function(data) {
					$("#settings_" + CurrentField).val(JSON.stringify(data));
				}
			});
			
		}).on("click",".icon_delete",function(ev) {
			ev.preventDefault();
			BigTreeDialog({
				title: "<?=Text::translate("Delete Resource", true)?>",
				content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this resource?", true)?></p>',
				icon: "delete",
				alternateSaveText: "<?=Text::translate("OK", true)?>",
				callback: $.proxy(function() { $(this).parents("li").remove(); },this)
			});
		});
		
		$(".add_resource").click(function(ev) {
			ev.preventDefault();
			ResourceCount++;
			
			var li = $('<li>').html('<section class="developer_resource_id">' +
										'<span class="icon_sort"></span>' +
										'<input type="text" name="resources[' + ResourceCount + '][id]" value="" />' +
									'</section>' +
									'<section class="developer_resource_title">' +
										'<input type="text" name="resources[' + ResourceCount + '][title]" value="" />' + 
									'</section>' +
									'<section class="developer_resource_subtitle">' +
										'<input type="text" name="resources[' + ResourceCount + '][subtitle]" value="" />' +
									'</section>' +
									'<section class="developer_resource_type">' +
										'<select name="resources[' + ResourceCount + '][type]" id="type_' + ResourceCount + '">' +
											'<optgroup label="<?=Text::translate("Default", true)?>">' +
												<?php foreach ($types["default"] as $id => $field_type) { ?>
												'<option value="<?=$id?>"><?=$field_type["name"]?></option>' +
												<?php } ?>
											'</optgroup>' +
											<?php if (count($types["custom"])) { ?>
											'<optgroup label="<?=Text::translate("Custom", true)?>">' +
												<?php foreach ($types["custom"] as $id => $field_type) { ?>
												'<option value="<?=$id?>"><?=$field_type["name"]?></option>' +
												<?php } ?>
											'</optgroup>' +
											<?php } ?>
										'</select>' +
										'<a href="#" tabindex="-1" class="icon_settings" name="' + ResourceCount + '"></a>' +
										'<input type="hidden" name="resources[' + ResourceCount + '][settings]" value="" id="settings_' + ResourceCount + '" />' +
									'</section>' +
									'<section class="developer_resource_action right">' +
										'<a href="#" tabindex="-1" class="icon_delete"></a>' +
									'</section>');	
			$("#resource_table").append(li)
								.sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
			
			BigTreeCustomControls(li);
		});
		
		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });

	})();
</script>