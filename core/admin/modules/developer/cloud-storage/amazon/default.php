<?php
	namespace BigTree;
	
	/**
	 * @global CloudStorage\Amazon $amazon
	 */
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/amazon/update/" class="module">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<div class="alert">
				<p><?=Text::translate("To enable usage of Amazon S3 for all BigTree uploads enter your access keys below.<br />Please note that this change is not retroactive -- only future uploads will be stored on Amazon S3.")?></p>
			</div>
			<fieldset>
				<label for="amazon_field_region">AWS Region <small>(if you are unsure of the region, leave the default)</small></label>
				<select id="amazon_field_region" name="region">
					<?php
						foreach ($amazon->AvailableRegions as $code => $name) {
					?>
					<option value="<?=$code?>"<?php if ($code == $amazon->Region) { ?> selected="selected"<?php } ?>><?=$name?></option>
					<?php
						}
					?>
				</select>
			</fieldset>
			<fieldset>
				<label for="amazon_field_key"><?=Text::translate("Access Key ID")?></label>
				<input id="amazon_field_key" type="text" name="key" value="<?=Text::htmlEncode($amazon->Key)?>" />
			</fieldset>
			<fieldset>
				<label for="amazon_field_secret"><?=Text::translate("Secret Access Key")?></label>
				<input id="amazon_field_secret" type="text" name="secret" value="<?=Text::htmlEncode($amazon->Secret)?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>