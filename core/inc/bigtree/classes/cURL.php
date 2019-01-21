<?php
	/*
		Class: BigTree\cURL
			Provides an interface for making cURL requests.
	*/
	
	namespace BigTree;
	
	class cURL {
		
		public static $ResponseCode;
		
		/*
			Function: request
				Makes a request to a given URL and returns the response.
			
			Parameters:
				url - The URL to retrieve / POST to.
				post - A key/value pair array of things to POST (optional).
				options - A key/value pair of extra cURL options (optional).
				strict_security - Force SSL verification of the host and peer if true (optional, defaults to false).
				output_file - A file location to dump the output of the request to (optional, replaces return value).
			
			Returns:
				The string response from the URL.
		*/
		
		static function request(string $url, $post = null, array $options = [], bool $strict_security = false,
								?string $output_file = null) {
			// Strip out any hash
			list($url) = explode("#", $url);
			
			// Startup cURL and set the URL
			$handle = curl_init();
			curl_setopt($handle, CURLOPT_URL, $url);
			
			// Determine whether we're forcing valid SSL on the peer and host
			if (!$strict_security) {
				curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
			}
			
			// If we're returning to a file we setup a file pointer rather than waste RAM capturing to a variable
			if (!is_null($output_file)) {
				$file_pointer = fopen($output_file, "w");
				curl_setopt($handle, CURLOPT_FILE, $file_pointer);
			} else {
				curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
			}
			
			// Setup post data
			if (!is_null($post)) {
				// Use cURLFile for any file uploads
				if (function_exists("curl_file_create") && is_array($post)) {
					foreach ($post as &$post_field) {
						if (substr($post_field, 0, 1) == "@" && file_exists(substr($post_field, 1))) {
							$post_field = curl_file_create(substr($post_field, 1));
						}
					}
					
					unset($post_field);
				}
				
				curl_setopt($handle, CURLOPT_POSTFIELDS, $post);
			}
			
			// Any additional cURL options
			if (is_array($options) && count($options)) {
				foreach ($options as $key => $opt) {
					curl_setopt($handle, $key, $opt);
				}
			}
			
			// Get the output
			$output = curl_exec($handle);
			
			// Log response code for checking for failed HTTP codes
			static::$ResponseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			
			// Close connection
			curl_close($handle);
			
			// If we're outputting to a file, close the handle and return nothing
			if ($output_file) {
				fclose($file_pointer);
				
				return true;
			}
			
			return $output;
		}
		
	}