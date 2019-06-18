<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$feed = new Feed($_GET["id"]);
	$feed->delete();

	Admin::growl("Developer","Deleted Feed");
	Router::redirect(DEVELOPER_ROOT."feeds/");
	