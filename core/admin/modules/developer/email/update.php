<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$email_setting = new Setting("bigtree-internal-email-service");

	if ($_POST["service"] == "smtp") {
		$email_setting->Value["settings"]["smtp_host"] = $_POST["smtp_host"];
		$email_setting->Value["settings"]["smtp_port"] = $_POST["smtp_port"];
		$email_setting->Value["settings"]["smtp_security"] = $_POST["smtp_security"];
		$email_setting->Value["settings"]["smtp_user"] = $_POST["smtp_user"];
		$email_setting->Value["settings"]["smtp_password"] = $_POST["smtp_password"];		
	} elseif ($_POST["service"] == "mandrill") {
		$email_setting->Value["settings"]["mandrill_key"] = $_POST["mandrill_key"];
	} elseif ($_POST["service"] == "mailgun") {
		$email_setting->Value["settings"]["mailgun_key"] = $_POST["mailgun_key"];
		$email_setting->Value["settings"]["mailgun_domain"] = $_POST["mailgun_domain"];
	} elseif ($_POST["service"] == "postmark") {
		$email_setting->Value["settings"]["postmark_key"] = $_POST["postmark_key"];
	} elseif ($_POST["service"] == "sendgrid") {
		$email_setting->Value["settings"]["sendgrid_api_user"] = $_POST["sendgrid_api_user"];
		$email_setting->Value["settings"]["sendgrid_api_key"] = $_POST["sendgrid_api_key"];
	}
	
	$email_setting->Value["service"] = $_POST["service"];
	$email_setting->Value["settings"]["bigtree_from"] = $_POST["bigtree_from"];
	$email_setting->Value["settings"]["bigtree_from_name"] = $_POST["bigtree_from_name"];
	
	$email_setting->save();

	Utils::growl("Developer","Updated Email Service");
	Router::redirect(DEVELOPER_ROOT);
	