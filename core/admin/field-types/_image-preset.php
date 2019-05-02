<?php
	namespace BigTree;
	
	/**
	 * @global array $presets
	 */
	
	$preset_data = isset($settings) ? $presets[$settings["preset"]] : $presets[$_POST["id"]];
	
	// Translate repeated strings
	$text_centered_subcrop = Text::translate("Create Centered Sub-Crop", true);
	$text_thumbnail_crop = Text::translate("Create Thumbnail of Crop", true);
	$text_color_mode = Text::translate("Switch Color Mode", true);
	$text_remove = Text::translate("Remove");
	$text_thumbnail = Text::translate("Thumbnail");
	$text_subcrop = Text::translate("Sub-Crop");
?>
<fieldset>
	<label for="<?=$image_options_prefix?>settings_field_min_width"><?=Text::translate("Minimum Width <small>(numeric value in pixels)</small>")?></label>
	<input id="<?=$image_options_prefix?>settings_field_min_width" type="text" name="min_width" value="<?=htmlspecialchars($preset_data["min_width"])?>" disabled="disabled" />
</fieldset>

<fieldset>
	<label for="<?=$image_options_prefix?>settings_field_min_height"><?=Text::translate("Minimum Height <small>(numeric value in pixels)</small>")?></label>
	<input id="<?=$image_options_prefix?>settings_field_min_height" type="text" name="min_height" value="<?=htmlspecialchars($preset_data["min_height"])?>" disabled="disabled" />
</fieldset>

<fieldset>
	<label for="<?=$image_options_prefix?>settings_field_preview_prefix"><?=Text::translate("Preview Prefix <small>(for forms)</small>")?></label>
	<input id="<?=$image_options_prefix?>settings_field_preview_prefix" type="text" name="preview_prefix" value="<?=htmlspecialchars($preset_data["preview_prefix"])?>" disabled="disabled" />
</fieldset>

<fieldset>
	<label><?=Text::translate('Create Hi-Resolution Retina Images <small><a href=":doc_link:" target="_blank">(learn more)</a></small>', false, [":doc_link:" => "https://www.bigtreecms.org/docs/dev-guide/field-types/retina-images/"])?></label>
	<input id="<?=$image_options_prefix?>settings_field_retina" type="checkbox" name="retina" <?php if ($preset_data["retina"]) { ?>checked="checked" <?php } ?> disabled="disabled" />
	<label for="<?=$image_options_prefix?>settings_field_retina" class="for_checkbox"> <?=Text::translate("When Available")?></label>
</fieldset>

<h4><?=Text::translate("Crops")?> <a href="#" class="add_crop icon_small icon_small_add" style="display: none;"></a></h4>

<fieldset>
	<div class="image_attr" id="<?=$image_options_prefix?>pop_crop_list">
		<ul>
			<li><?=Text::translate("Prefix:")?></li><li><?=Text::translate("Width:")?></li><li><?=Text::translate("Height:")?></li>
		</ul>
		<?php
			$crop_count = 0;
			$crop_thumb_count = 0;
			$crop_sub_count = 0;
			
			if (is_array($preset_data["crops"])) {
				foreach ($preset_data["crops"] as $crop) {
					// In case a crop was added but no options were set
					if (is_array($crop) && $crop["width"] && $crop["height"]) {
						$crop_count++;
		?>
		<ul>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][width]" value="<?=htmlspecialchars($crop["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][height]" value="<?=htmlspecialchars($crop["height"])?>" disabled="disabled" />
			</li>
			<li class="actions">
				<a href="#<?=$crop_count?>" title="<?=$text_centered_subcrop?>" class="subcrop disabled"></a>
				<a href="#<?=$crop_count?>" title="<?=$text_thumbnail_crop?>" class="thumbnail disabled"></a>
				<input type="hidden" name="crops[<?=$crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="<?=$text_color_mode?>" class="disabled color_mode<?php if ($crop["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#<?=$crop_count?>" title="<?=$text_remove?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
						if (is_array($crop["thumbs"])) {
							foreach ($crop["thumbs"] as $thumb) {
								// In case a thumb was added and a prefix or width/height were missing - require prefix here because it'll replace the crop otherwise
								if (is_array($thumb) && $thumb["prefix"] && ($thumb["width"] || $thumb["height"])) {
									$crop_thumb_count++;
		?>
		<ul class="image_attr_thumbs_<?=$crop_count?>">
			<li class="thumbed">
				<span class="icon_small icon_small_picture" title="<?=$text_thumbnail?>"></span>
				<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][prefix]" value="<?=htmlspecialchars($thumb["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][width]" value="<?=htmlspecialchars($thumb["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][height]" value="<?=htmlspecialchars($thumb["height"])?>" disabled="disabled" />
			</li>
			<li class="actions">
				<span class="icon_small icon_small_up disabled"></span>
				<input type="hidden" name="crops[<?=$crop_count?>][thumbs][<?=$crop_thumb_count?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
				<a href="#" title="<?=$text_color_mode?>" class="disabled color_mode<?php if ($thumb["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#" title="<?=$text_remove?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
								}
							}
						}

						if (is_array($crop["center_crops"])) {
							foreach ($crop["center_crops"] as $center_crop) {
								// In case a sub crop was added and a prefix or width/height were missing - require prefix here because it'll replace the crop otherwise
								if (is_array($center_crop) && $center_crop["prefix"] && $center_crop["width"] && $center_crop["height"]) {
									$crop_sub_count++;
		?>
		<ul class="image_attr_thumbs_<?=$crop_count?>">
			<li class="thumbed">
				<span class="icon_small icon_small_crop" title="<?=$text_subcrop?>"></span>
				<input type="text" class="image_attr_thumbs" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][prefix]" value="<?=htmlspecialchars($center_crop["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][width]" value="<?=htmlspecialchars($center_crop["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][height]" value="<?=htmlspecialchars($center_crop["height"])?>" disabled="disabled" />
			</li>
			<li class="actions">
				<span class="disabled icon_small icon_small_up"></span>
				<input type="hidden" name="crops[<?=$crop_count?>][center_crops][<?=$crop_sub_count?>][grayscale]" value="<?=$center_crop["grayscale"]?>" />
				<a href="#" title="<?=$text_color_mode?>" class="disabled color_mode<?php if ($center_crop["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#" title="<?=$text_remove?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
								}
							}
						}
					}
				}
			}
		?>
	</div>
</fieldset>

<h4><?=Text::translate("Thumbnails")?> <a href="#" class="add_thumb icon_small icon_small_add" style="display: none;"></a></h4>
<p class="error_message" style="display: none;" id="<?=$image_options_prefix?>thumbnail_dialog_error"><?=Text::translate("You must enter a height or width for each thumbnail.")?></p>
<fieldset>
	<div class="image_attr" id="<?=$image_options_prefix?>pop_thumb_list">
		<ul>
			<li><?=Text::translate("Prefix:")?></li><li><?=Text::translate("Width:")?></li><li><?=Text::translate("Height:")?></li>
		</ul>
		<?php
			// Keep a count of thumbs
			$thumb_count = 0;
			if (is_array($preset_data["thumbs"])) {
				foreach ($preset_data["thumbs"] as $thumb) {
					// Make sure a width or height was entered or it's pointless
					if (is_array($thumb) && ($thumb["width"] || $thumb["height"])) {
						$thumb_count++;
		?>
		<ul>
			<li>
				<input type="text" name="thumbs[<?=$thumb_count?>][prefix]" value="<?=htmlspecialchars($thumb["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="thumbs[<?=$thumb_count?>][width]" value="<?=htmlspecialchars($thumb["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="thumbs[<?=$thumb_count?>][height]" value="<?=htmlspecialchars($thumb["height"])?>" disabled="disabled" />
			</li>
			<li class="actions for_thumbnail">
				<input type="hidden" name="thumbs[<?=$crop_count?>][grayscale]" value="<?=$thumb["grayscale"]?>" />
				<a href="#" title="<?=$text_color_mode?>" class="disabled color_mode<?php if ($thumb["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#<?=$crop_count?>" title="<?=$text_remove?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
					}
				}
			}
		?>
	</div>
</fieldset>

<h4><?=Text::translate("Center Crops <small>(automatically crops from the center of image)</small>")?> <a href="#" class="add_center_crop icon_small icon_small_add" style="display: none;"></a></h4>
<fieldset>
	<div class="image_attr" id="<?=$image_options_prefix?>pop_center_crop_list">
		<ul>
			<li><?=Text::translate("Prefix:")?></li><li><?=Text::translate("Width:")?></li><li><?=Text::translate("Height:")?></li>
		</ul>
		<?php
			// Keep a count of center crops
			$center_crop_count = 0;
			if (is_array($preset_data["center_crops"])) {
				foreach ($preset_data["center_crops"] as $crop) {
					// Make sure a width and height was entered or it's pointless
					if (is_array($crop) && ($crop["width"] && $crop["height"])) {
						$center_crop_count++;
		?>
		<ul>
			<li>
				<input type="text" name="center_crops[<?=$center_crop_count?>][prefix]" value="<?=htmlspecialchars($crop["prefix"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="center_crops[<?=$center_crop_count?>][width]" value="<?=htmlspecialchars($crop["width"])?>" disabled="disabled" />
			</li>
			<li>
				<input type="text" name="center_crops[<?=$center_crop_count?>][height]" value="<?=htmlspecialchars($crop["height"])?>" disabled="disabled" />
			</li>
			<li class="actions for_thumbnail">
				<input type="hidden" name="center_crops[<?=$center_crop_count?>][grayscale]" value="<?=$crop["grayscale"]?>" />
				<a href="#" title="<?=$text_color_mode?>" class="disabled color_mode<?php if ($crop["grayscale"]) { ?> gray<?php } ?>"></a>
				<a href="#<?=$center_crop_count?>" title="<?=$text_remove?>" class="disabled delete"></a>
			</li>
		</ul>
		<?php
					}
				}
			}
		?>
	</div>
</fieldset>
<script>
	try {
		<?=$image_options_prefix?>ImageOptions.updateCounts(<?=$crop_count?>,<?=$crop_thumb_count?>,<?=$thumb_count?>,<?=$center_crop_count?>,<?=$crop_sub_count?>);
	} catch (err) {}
</script>