<?php
	namespace BigTree;
	
	$method = $_SESSION["bigtree_admin"]["upgrade_method"];	
?>
<form method="post" action="<?=DEVELOPER_ROOT?>upgrade/install/">
	<input type="hidden" name="type" value="<?=htmlspecialchars($_GET["type"])?>" />
	<div class="container">
		<div class="container_summary"><h2><?=Text::translate("Upgrade BigTree")?></h2></div>
		<section>
			<div class="alert">
				<span></span>
				<p><?=Text::translate("<strong>Login Failed:</strong> Please enter the correct :update_method: username and password below.", false, [":update_method:" => $method])?></p>
			</div>
			<fieldset>
				<label for="login_field_username"><?=Text::translate(":update_method: Username", false, [":update_method:" => $method])?></label>
				<input id="login_field_username" type="text" name="username" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label for="login_field_password"><?=Text::translate(":update_method: Password", false, [":update_method:" => $method])?></label>
				<input id="login_field_password" type="password" name="password" autocomplete="off" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="blue" value="<?=Text::translate("Install", true)?>" />
		</footer>
	</div>
</form>