<?php
	namespace BigTree;
	
	$id = intval($_POST["id"]);

	CSRF::verify();

	// Check security policy
	if ($_POST["password"] && !User::validatePassword($_POST["password"])) {
		$_SESSION["bigtree_admin"]["update_user"] = $_POST;
		$_SESSION["bigtree_admin"]["update_user"]["error"] = "password";
		Admin::growl("Users","Invalid Password","error");
		Router::redirect(ADMIN_ROOT."users/edit/$id/");
	}

	// Check permission level
	$error = false;
	$user = new User($id);

	// Don't let a user edit someone that has higher access levels than they do
	if ($user->Level > Auth::user()->Level) {
		$error = "level";
	}

	// Don't let a user change their own level
	if ($id == Auth::user()->ID) {
		$level = Auth::user()->Level;
	} else {
		$level = $_POST["level"];
	}

	if ($error === false) {
		$permission_data = json_decode($_POST["permissions"], true);
		$permissions = [
			"page" => $permission_data["Page"],
			"module" => $permission_data["Module"],
			"resources" => $permission_data["Resource"],
			"module_gbp" => $permission_data["ModuleGBP"]
		];

		$alerts = json_decode($_POST["alerts"], true);
		
		if (!$user->update($_POST["email"], $_POST["password"], $_POST["name"], $_POST["company"], $level,
						   $permissions, $alerts, $_POST["daily_digest"])) {
			$error = "email";
		}
	}

	if ($error !== false) {
		$_SESSION["bigtree_admin"]["update_user"] = $_POST;
		$_SESSION["bigtree_admin"]["update_user"]["error"] = $error;
		Admin::growl("Users","Update Failed","error");

		Router::redirect(ADMIN_ROOT."users/edit/$id/");
	}
	
	Admin::growl("Users","Updated User");
	
	Router::redirect(ADMIN_ROOT."users/");
	