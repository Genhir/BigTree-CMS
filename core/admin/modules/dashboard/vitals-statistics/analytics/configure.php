<?php
	namespace BigTree;
	
	/**
	 * @global GoogleAnalytics\API $analytics
	 */
?>
<div class="container">
	<?php
		if ($analytics->Settings["token"]) {
			$profiles = $analytics->getProfiles();
	?>
	<form method="post" action="<?=MODULE_ROOT?>set-profile/" class="module">
		<section>
			<fieldset>
				<label for="ga_field_profile"><?=Text::translate("Choose A Profile From The List Below")?></label>
				<?php
					if (count($profiles->Results)) {
				?>
				<select id="ga_field_profile" name="profile">
					<?php foreach ($profiles->Results as $profile) { ?>
					<option value="<?=$profile->ID?>"><?=$profile->WebsiteURL?> &mdash; <?=$profile->Name?></option>
					<?php } ?>
				</select>
				<?php
					} else {
				?>
				<p class="error_message"><?=Text::translate("No profiles were found in your Google Analytics account.")?></p>
				<?php  	
					}
				?>
			</fieldset>
		</section>
		<footer>
			<input type="submit" value="<?=Text::translate("Set Profile")?>" class="blue" id="set_button" />
			<a href="#" class="button" id="ga_disconnect"><?=Text::translate("Disconnect")?></a>
		</footer>
	</form>
	
	<?php
		} else {
			$auth_url = $analytics->AuthorizeURL.
				"?client_id=".urlencode($analytics->ClientID).
				"&redirect_uri=".urlencode($analytics->ReturnURL).
				"&response_type=code".
				"&scope=".urlencode($analytics->Scope).
				"&approval_prompt=force".
				"&access_type=offline";
	?>
	<form method="get" action="<?=MODULE_ROOT?>set-token/" class="module">	
		<section>
			<p><?=Text::translate("To connect Google Analytics you will need to login to your Google Analytics account by clicking the Authenticate button below. Once you have logged in you will be taken to a screen with a code in a box. Copy that code into the field that appears below to allow BigTree to access your Google Analytics information.")?></p>
			<fieldset>
				<input type="text" name="code" placeholder="<?=Text::translate("Enter Code Here")?>" />
			</fieldset>
		</section>
		<footer>
			<a href="<?=$auth_url?>" class="button" id="google_button" target="_blank"><?=Text::translate("Authenticate")?></a>
			<input type="submit" class="button blue" id="profile_button" value="<?=Text::translate("Save Code")?>" style="display: none;" />
		</footer>
	</form>
	<?php
		}
	?>		
</div>
<script>
	$("#google_button").click(function() {
		$(this).hide();
		$("#profile_button").show();
	});
	
	$("#ga_disconnect").click(function() {
		BigTreeDialog({
			title: "<?=Text::translate("Disconnect Google Analytics")?>",
			content: "<p><?=Text::translate("Are you sure you want to disconnect your Google Analytics account?<br>This will remove all analytics data and can not be undone.")?></p>",
			icon: "delete",
			alternateSaveText: "<?=Text::translate("Disconnect")?>",
			callback: function() {
				window.location.href = "<?=MODULE_ROOT?>disconnect/";
			}
		});

		return false;
	});
</script>