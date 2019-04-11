<?php
	/*
		Class: BigTree\Twitter\Tweet
			A Twitter object that contains information about and methods you can perform on a tweet.
	*/

	namespace BigTree\Twitter;

	use stdClass;

	class Tweet
	{

		/** @var API */
		protected $API;

		public $Content;
		public $FavoriteCount;
		public $Favorited;
		public $Hashtags = [];
		public $ID;
		public $IsRetweet;
		public $Language;
		public $LinkedContent;
		public $Media = [];
		public $Mentions = [];
		public $OriginalTweet;
		public $Place;
		public $RetweetCount;
		public $Retweeted;
		public $Source;
		public $Symbols = [];
		public $Timestamp;
		public $URLs = [];
		public $User;

		/*
			Constructor:
				Creates a tweet object from Twitter data.

			Parameters:
				tweet - Twitter data
				api - Reference to the BigTree\Twitter\API class instance
		*/

		function __construct($tweet, API &$api)
		{
			$this->API = $api;
			isset($tweet->text) ? $this->Content = $tweet->text : false;
			isset($tweet->full_text) ? $this->Content = $tweet->full_text : false;
			isset($tweet->favorite_count) ? $this->FavoriteCount = $tweet->favorite_count : false;
			isset($tweet->favorited) ? $this->Favorited = $tweet->favorited : false;
			
			if (isset($tweet->entities->hashtags)) {
				if (is_array($tweet->entities->hashtags)) {
					foreach ($tweet->entities->hashtags as $hashtag) {
						$this->Hashtags[] = $hashtag->text;
					}
				}
			}
			
			$this->ID = $tweet->id;
			isset($tweet->retweeted_status) ? ($this->IsRetweet = $tweet->retweeted_status ? true : false) : false;
			isset($tweet->lang) ? $this->Language = $tweet->lang : false;
			isset($this->Content) ? $this->LinkedContent = preg_replace('/(^|\s)#(\w+)/','\1<a href="http://twitter.com/search?q=%23\2" target="_blank">#\2</a>',preg_replace('/(^|\s)@(\w+)/','\1<a href="http://www.twitter.com/\2" target="_blank">@\2</a>',preg_replace("@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@",'<a href="\0" target="_blank">\0</a>', $this->Content))) : false;
			
			if (isset($tweet->entities->media)) {
				if (is_array($tweet->entities->media)) {
					foreach ($tweet->entities->media as $media) {
						$m = new stdClass;
						$m->DisplayURL = $media->display_url;
						$m->ID = $media->id;
						$m->ExpandedURL = $media->expanded_url;
						$m->SecureURL = $media->media_url_https;
						foreach ($media->sizes as $size => $info) {
							$size_key = ucwords($size);
							$m->Sizes = new stdClass;
							$m->Sizes->$size_key = new stdClass;
							$m->Sizes->$size_key->Height = $info->h;
							$m->Sizes->$size_key->Width = $info->w;
							$m->Sizes->$size_key->SecureURL = $media->media_url_https.":".$size;
							$m->Sizes->$size_key->URL = $media->media_url.":".$size;
						}
						$m->Type = $media->type;
						$m->URL = $media->media_url;
						$this->Media[] = $m;
					}
				}
			}
			
			if (isset($tweet->entities->user_mentions)) {
				if (is_array($tweet->entities->user_mentions)) {
					foreach ($tweet->entities->user_mentions as $mention) {
						$this->Mentions[] = new User($mention,$api);
					}
				}
			}
			
			$tweet->retweeted_status ? $this->OriginalTweet = new Tweet($tweet->retweeted_status,$api) : false;
			isset($tweet->place) ? $this->Place = new Place($tweet->place,$api) : false;
			isset($tweet->retweet_count) ? $this->RetweetCount = $tweet->retweet_count : false;
			isset($tweet->retweeted) ? $this->Retweeted = $tweet->retweeted : false;
			isset($tweet->source) ? $this->Source = $tweet->source : false;
			
			if (isset($tweet->entities->symbols)) {
				if (is_array($tweet->entities->symbols)) {
					foreach ($tweet->entities->symbols as $symbol) {
						$this->Symbols[] = $symbol->text;
					}
				}
			}
			
			isset($tweet->created_at) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($tweet->created_at)) : false;
			
			if (isset($tweet->entities->url)) {
				if (is_array($tweet->entities->url)) {
					foreach ($tweet->entities->urls as $url) {
						$this->URLs[] = (object) array(
							"URL" => $url->url,
							"ExpandedURL" => $url->expanded_url,
							"DisplayURL" => $url->display_url
						);
					}
				}
			}
			
			isset($tweet->user) ? $this->User = new User($tweet->user,$api) : false;
		}

		/*
			Function: __toString
				Returns the Tweet's content when this object is treated as a string.
		*/

		function __toString(): string
		{
			return $this->Content;
		}

		/*
			Function: delete
				Deletes the tweet from Twitter.
				The authenticated user must own the tweet.

			Returns:
				True if successful.
		*/

		function delete(): bool
		{
			return $this->API->deleteTweet($this->ID);
		}

		/*
			Function: favorite
				Favorites the tweet.

			Returns:
				A BigTree\Twitter\Tweet object if successful.
		*/

		function favorite(): ?Tweet
		{
			return $this->API->favoriteTweet($this->ID);
		}

		/*
			Function: retweet
				Causes the authenticated user to retweet the tweet.

			Returns:
				True if successful.
		*/

		function retweet(): bool
		{
			return $this->API->retweetTweet($this->IsRetweet ? $this->OriginalTweet->ID : $this->ID);
		}

		/*
			Function: retweets
				Returns retweets of the tweet.

			Returns:
				An array of BigTree\Twitter\Tweet objects.
		*/

		function retweets(): array
		{
			// We know how many retweets the tweet has already, so don't bother asking Twitter if it's 0.
			if (!$this->RetweetCount) {
				return [];
			}

			if ($this->OriginalTweet) {
				$response = $this->API->call("statuses/retweets/".$this->OriginalTweet->ID.".json");
			} else {
				$response = $this->API->call("statuses/retweets/".$this->ID.".json");
			}

			$tweets = [];
			
			foreach ($response as $tweet) {
				$tweets[] = new Tweet($tweet,$this->API);
			}

			return $tweets;
		}

		/*
			Function: retweeters
				Returns a list of Twitter user IDs for users who retweeted this tweet.

			Returns:
				An array of Twitter IDs
		*/

		function retweeters(): ?array
		{
			$id = $this->IsRetweet ? $this->OriginalTweet->ID : $id = $this->ID;
			$response = $this->API->call("statuses/retweeters/ids.json",array("id" => $id));

			if ($response->ids) {
				return $response->ids;
			}

			return null;
		}

		/*
			Function: unfavorite
				Unfavorites the tweet.

			Returns:
				A BigTree\Twitter\Tweet object if successful.
		*/

		function unfavorite(): ?Tweet
		{
			return $this->API->unfavoriteTweet($this->ID);
		}
		
	}
