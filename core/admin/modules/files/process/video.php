<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$video = null;
	$url = trim($_POST["video"]);
	$settings = DB::get("config", "media-settings");
	$preset = $settings["presets"]["default"];
	$folder_id = intval($_POST["folder"]);
	
	if (!ResourceFolder::exists($folder_id)) {
		Auth::stop("Folder does not exist.");
	}
	
	$folder = new ResourceFolder($folder_id);
	
	if ($folder->UserAccessLevel != "p") {
		Auth::stop("You do not have permission to create content in this folder.");
	}

	// YouTube
	if (strpos($url,"youtu.be") !== false || strpos($url,"youtube.com") !== false) {
		// Fix issues with URLs that contain timestamps.
		$parsed = parse_url($url);
		
		if ($parsed["query"]) {
			$get = explode("&", $parsed["query"]);
			
			foreach ($get as $index => $get_item) {
				if (strpos($get_item, "v=") !== 0) {
					unset($get[$index]);
				}
			}
			
			$url = $parsed["scheme"]."://".$parsed["host"].$parsed["path"];
			
			if (count($get)) {
				$url .= "?".implode("&", $get);
			}
		}
		
		// Try to grab the ID from the YouTube URL (courtesy of various Stack Overflow authors)
		$pattern =
			'%^# Match any youtube URL
			(?:https?://)?  # Optional scheme. Either http or https
			(?:www\.)?		# Optional www subdomain
			(?:				# Group host alternatives
			  youtu\.be/	# Either youtu.be,
			| youtube\.com  # or youtube.com
			  (?:			# Group path alternatives
				/embed/		# Either /embed/
			  | /v/			# or /v/
			  | .*v=		# or /watch\?v=
			  )				# End path alternatives.
			)				# End host alternatives.
			([\w-]{10,12})  # Allow 10-12 for 11 char youtube id.
			($|&).*			# if additional parameters are also in query string after video id.
			$%x';
		$result = preg_match($pattern, $url, $matches);

		// No ID match? Bad URL.
		if ($result === false) {
			Admin::growl("Files", "Invalid URL", "error");
			Router::redirect($_SERVER["HTTP_REFERER"]."?error=The URL you entered is not a valid YouTube URL.");
		// Got our YouTube ID
		} else {
			$youtube = new YouTube\API;
			$video_id = $matches[1];
			$oembed_data = json_decode(cURL::request("https://www.youtube.com/oembed?url=".urlencode("https://youtube.com/watch?v=".$video_id)), true);

			if (empty($oembed_data["html"])) {
				Admin::growl("Files", "Invalid URL", "error");
				Router::redirect($_SERVER["HTTP_REFERER"]."?error=The URL you entered is not a valid YouTube video URL.");
			}

			$video = [
				"service" => "YouTube",
				"id" => $video_id,
				"title" => $oembed_data["title"],
				"description" => null,
				"image" => $oembed_data["thumbnail_url"],
				"url" => "https://youtube.com/watch?v=".$video_id,
				"user_id" => null,
				"user_name" => $oembed_data["author_name"],
				"user_url" => $oembed_data["author_url"],
				"upload_date" => null,
				"height" => null,
				"width" => null,
				"duration" => null,
				"embed" => $oembed_data["html"]
			];

			if ($youtube->Connected) {
				// Try a higher authenticated version that gets us file dimensions, must own the file
				try {
					$response = $youtube->callUncached("videos", [
						"part" => "id,snippet,contentDetails,player,statistics,status,topicDetails,recordingDetails,fileDetails",
						"id" => $video_id
					]);
	
					if (isset($response->items) && count($response->items)) {
						if (!empty($response->items[0]->fileDetails->videoStreams[0])) {
							$video["height"] = $response->items[0]->fileDetails->videoStreams[0]->heightPixels;
							$video["width"] = $response->items[0]->fileDetails->videoStreams[0]->widthPixels;
						}
					}
				} catch (\Exception $e) {}

				// Now use the standard
				$video_data = $youtube->getVideo($video_id);

				// Try for max resolution first, then high, then default
				$source_image = $video_data->Images->Maxres ? $video_data->Images->Maxres : $video_data->Images->High;
				$source_image = $source_image ? $source_image : $video_data->Images->Default;

				$video["image"] = $source_image;
				$video["description"] = $video_data->Description;
				$video["user_id"] = $video_data->ChannelID;
				$video["upload_date"] = $video_data->Timestamp;
				$video["duration"] = ($video_data->Duration->Hours * 3600 + $video_data->Duration->Minutes * 60 + $video_data->Duration->Seconds);
			}
		}

	// Vimeo
	} elseif (strpos($url,"vimeo.com") !== false) {
		$url_pieces = explode("/",$url);
		$video_id = end($url_pieces);
		$json = json_decode(cURL::request("http://vimeo.com/api/v2/video/$video_id.json"), true);

		// Good video
		if (array_filter((array)$json)) {
			// Try to get the largest source image available
			$source_image = $json[0]["thumbnail_large"];
			$source_image = $source_image ? $source_image : $json[0]["thumbnail_medium"];
			$source_image = $source_image ? $source_image : $json[0]["thumbnail_small"];

			$video = [
				"service" => "Vimeo",
				"id" => $video_id,
				"title" => $json[0]["title"],
				"description" => $json[0]["description"],
				"image" => $source_image,
				"url" => $json[0]["url"],
				"user_id" => $json[0]["user_id"],
				"user_name" => $json[0]["user_name"],
				"user_url" => $json[0]["user_url"],
				"upload_date" => $json[0]["upload_date"],
				"height" => $json[0]["height"],
				"width" => $json[0]["width"],
				"duration" => $json[0]["duration"],
				"embed" => '<iframe src="https://player.vimeo.com/video/'.$video_id.'?byline=0&portrait=0" width="'.$json[0]["width"].'" height="'.$json[0]["height"].'" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>'
			];
		// No video :(
		} else {
			Admin::growl("Files", "Invalid URL", "error");
			Router::redirect($_SERVER["HTTP_REFERER"]."?error=The URL you entered is not a valid Vimeo video URL.");
		}
	// Invalid URL
	} else {
		Admin::growl("Files", "Invalid URL", "error");
		Router::redirect($_SERVER["HTTP_REFERER"]."?error=The URL you entered is not a valid video service URL.");
	}

	$extension = strtolower(pathinfo($video["image"], PATHINFO_EXTENSION));
	$file_name = SITE_ROOT."files/temporary/".Auth::user()->ID."/".$video["id"].".".$extension;
	FileSystem::copyFile($video["image"], $file_name);

	$min_height = intval($preset["min_height"]);
	$min_width = intval($preset["min_width"]);

	list($width, $height) = getimagesize($file_name);

	// Scale up content that doesn't meet minimums
	if ($width < $min_width || $height < $min_height) {
		$image = new Image($file_name);
		$image->upscale(null, $min_width, $min_height);
	}

	$field = new Field([
		"title" => $video["title"],
		"file_input" => [
			"tmp_name" => $file_name,
			"name" => $video["id"].".".$extension,
			"error" => 0
		],
		"settings" => [
			"directory" => "files/resources/",
			"preset" => "default"
		]
	]);

	$video["image"] = $field->processImageUpload();
	$resource = Resource::create($_POST["folder"], null, null, $type = "video", [], [], $video);
	Admin::growl("Files", "Created Video");

	$_SESSION["bigtree_admin"]["form_data"] = [
		"edit_link" => ADMIN_ROOT."files/folder/".intval(Router::$Commands[0])."/",
		"return_link" => ADMIN_ROOT."files/edit/file/".$resource->ID."/",
		"crop_key" => Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"])
	];
	
	if (is_array($bigtree["crops"]) && count($bigtree["crops"])) {
		Router::redirect(ADMIN_ROOT."files/crop/".intval(Router::$Commands[0])."/");
	} else {
		Router::redirect(ADMIN_ROOT."files/edit/file/".$resource->ID."/");
	}
	