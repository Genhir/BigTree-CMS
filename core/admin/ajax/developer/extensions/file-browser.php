<?php
	namespace BigTree;
	
	// See if we have cloud support
	$cloud_options = [];
	$directory = "";

	if (!$_POST["cloud_disabled"] || $_POST["cloud_disabled"] == "false") {
		$amazon = new CloudStorage\Amazon;
		$rackspace = new CloudStorage\Rackspace;
		$google = new CloudStorage\Google;

		if ($amazon->Active) {
			$cloud_options[] = ["class" => "amazon", "title" => "Amazon S3"];
		}

		if ($google->Active) {
			$cloud_options[] = ["class" => "google", "title" => "Google Cloud Storage"];
		}

		if ($rackspace->Active) {
			$cloud_options[] = ["class" => "rackspace", "title" => "Rackspace Cloud Files"];
		}

		if (count($cloud_options)) {
			array_unshift($cloud_options, ["class" => "server", "title" => Text::translate("Local Server")]);
		}
	}

	$location = !empty($_POST["location"]) ? $_POST["location"] : "server";
	$subdirectories = [];
	$files = [];
	$containers = [];
	
	// Get the post directory
	$postcontainer = !empty($_POST["container"]) ? $_POST["container"] : "";
	
	if ($_POST["location"] == "server") {
		$postdirectory = FileSystem::getSafePath($_POST["directory"]);
	} else {
		$postdirectory = $_POST["directory"];
	}

	// Local storage is being browsed
	if ($location == "server") {
		$directory = SERVER_ROOT.$postdirectory;

		if ($postdirectory && $postdirectory != ltrim($_POST["base_directory"], "/")) {
			$subdirectories[] = "..";
		}

		$o = opendir($directory);

		while ($r = readdir($o)) {
			if ($r != "." && $r != ".." && $r != ".DS_Store") {
				if (is_dir($directory.$r)) {
					$subdirectories[] = $r;
				} else {
					$files[] = $r;
				}
			}
		}
	} else {
		// If we're at ../ on the root of a container, go back to listing containers
		if ($_POST["directory"] == "../" && $postcontainer) {
			$postcontainer = false;
		}

		$cloud = false;

		if ($location == "amazon") {
			$cloud = new CloudStorage\Amazon;
		} elseif ($location == "rackspace") {
			$cloud = new CloudStorage\Rackspace;
		} elseif ($location == "google") {
			$cloud = new CloudStorage\Google;
		}

		if (!$postcontainer) {
			$containers = $cloud->listContainers();
		} else {
			$subdirectories[] = "..";
			$container = $cloud->getContainer($_POST["container"]);

			if (!$postdirectory) {
				$folder = $container["tree"];
			} else {
				$folder = $cloud->getFolder($container, $postdirectory);
			}

			foreach ($folder["folders"] as $name => $contents) {
				$subdirectories[] = $name;
			}

			foreach ($folder["files"] as $file) {
				$files[] = $file["name"];
			}

			// Give it a nice directory name
			$directory = $postcontainer."/".$postdirectory;
		}
	}
	
	if (count($cloud_options)) {
		$bucket_pane_height = 338 - 1 - (26 * count($cloud_options));
	} else {
		$bucket_pane_height = 338;
	}
?>
<div class="directory"><?=htmlspecialchars(Text::replaceServerRoot($directory, ""))?></div>
<div class="navigation_pane">
	<?php if (count($cloud_options)) { ?>
	<ul class="cloud_options">
		<?php foreach ($cloud_options as $option) { ?>
		<li>
			<a data-type="location" href="<?=$option["class"]?>"<?php if ($location == $option["class"]) { ?> class="active"<?php } ?>>
				<span class="icon_small icon_small_<?=$option["class"]?>"></span>
				<?=$option["title"]?>
			</a>
		</li>
		<?php } ?>
	</ul>
	<?php } ?>
	<ul style="height: <?=$bucket_pane_height?>px;">
		<?php
			foreach ($subdirectories as $d) {
		?>
		<li><a href="<?=$d?>"><span class="icon_small icon_small_folder"></span><?=$d?></a></li>
		<?php
			}
			foreach (array_filter((array) $containers) as $container) {
		?>
		<li>
			<a data-type="container" href="<?=$container["name"]?>" title="<?=$container["name"]?>">
				<span class="icon_small icon_small_export"></span>
				<?=$container["name"]?>
			</a>
		</li>
		<?php
			}
		?>
	</ul>
</div>
<div class="browser_pane">
	<ul>
		<?php
			foreach ($files as $file) {
				$parts = pathinfo($file);
				$ext = strtolower($parts["extension"]);
		?>
		<li class="file<?php if ($file == $_POST["file"]) { ?> selected<?php } ?>">
			<span class="icon_small icon_small_file_default icon_small_file_<?=$ext?>"></span>
			<p><?=$file?></p>
		</li>
		<?php
			}
		?>
	</ul>
	<input type="hidden" name="file" id="bigtree_foundry_file" value="<?=htmlspecialchars($_POST["file"])?>"/>
	<input type="hidden" name="directory" value="<?=ltrim($postdirectory, "/")?>" id="bigtree_foundry_directory" />
	<input type="hidden" name="container" value="<?=$postcontainer?>" id="bigtree_foundry_container"/>
	<input type="hidden" name="location" value="<?=$location?>" id="bigtree_foundry_location"/>
	<input type="submit" value="<?=Text::translate("Use Selected File", true)?>" class="button blue"/>
	<a href="#" class="button"><?=Text::translate("Cancel")?></a>
</div>