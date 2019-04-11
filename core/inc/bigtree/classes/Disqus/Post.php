<?php
	/*
		Class: BigTree\Disqus\Post
			A Disqus object that contains information about and methods you can perform on a forum post.
	*/
	
	namespace BigTree\Disqus;
	
	use stdClass;
	
	class Post
	{
		
		/** @var API */
		protected $API;
		
		public $Approved;
		public $Author;
		public $Content;
		public $ContentPlainText;
		public $Deleted;
		public $Dislikes;
		public $Edited;
		public $Flagged;
		public $Highlighted;
		public $ID;
		public $Likes;
		public $Media;
		public $ParentID;
		public $Points;
		public $Reports;
		public $Spam;
		public $ThreadID;
		public $Timestamp;
		public $UserScore;
		
		function __construct(stdClass $post, API &$api)
		{
			$this->API = $api;
			isset($post->isApproved) ? $this->Approved = $post->isApproved : false;
			isset($post->author) ? $this->Author = new User($post->author, $api) : false;
			isset($post->message) ? $this->Content = $post->message : false;
			isset($post->raw_message) ? $this->ContentPlainText = $post->raw_message : false;
			isset($post->isDeleted) ? $this->Deleted = $post->isDeleted : false;
			isset($post->dislikes) ? $this->Dislikes = $post->dislikes : false;
			isset($post->isEdited) ? $this->Edited = $post->isEdited : false;
			isset($post->isFlagged) ? $this->Flagged = $post->isFlagged : false;
			isset($post->isHighlighted) ? $this->Highlighted = $post->isHighlighted : false;
			isset($post->id) ? $this->ID = $post->id : false;
			isset($post->likes) ? $this->Likes = $post->likes : false;
			isset($post->media) ? $this->Media = $post->media : false;
			isset($post->parent) ? $this->ParentID = $post->parent : false;
			isset($post->points) ? $this->Points = $post->points : false;
			isset($post->numReports) ? $this->Reports = $post->numReports : false;
			isset($post->isSpam) ? $this->Spam = $post->isSpam : false;
			isset($post->thread) ? $this->ThreadID = $post->thread : false;
			isset($post->createdAt) ? $this->Timestamp = date("Y-m-d H:i:s", strtotime($post->createdAt)) : false;
			isset($post->userScore) ? $this->UserScore = $post->userScore : false;
		}
		
		private function _cacheBust(): void
		{
			$this->API->cacheBust("threadposts".$this->ThreadID);
			$this->API->cacheBust("post".$this->ID);
		}
		
		/*
			Function: approve
				Approves this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/
		
		function approve(): bool
		{
			$response = $this->API->call("posts/approve.json", ["post" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: highlight
				Highlights this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/
		
		function highlight(): bool
		{
			$response = $this->API->call("posts/highlight.json", ["post" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: remove
				Removes this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/
		
		function remove(): bool
		{
			$response = $this->API->call("posts/remove.json", ["post" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: report
				Reports/flags this post.

			Returns:
				true if successful.
		*/
		
		function report(): bool
		{
			$response = $this->API->call("posts/report.json", ["post" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: restore
				Restores this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/
		
		function restore(): bool
		{
			$response = $this->API->call("posts/restore.json", ["post" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: spam
				Marks this post as spam.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/
		
		function spam(): bool
		{
			$response = $this->API->call("posts/spam.json", ["post" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: unhighlight
				Unhighlights this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/
		
		function unhighlight(): bool
		{
			$response = $this->API->call("posts/unhighlight.json", ["post" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: vote
				Causes the authenticated user to vote on a post.

			Parameters:
				vote - Vote to cast (-1, 0, or 1)

			Returns:
				true if successful.
		*/
		
		function vote(int $vote = 0): bool
		{
			$response = $this->API->call("posts/vote.json", ["post" => $this->ID, "vote" => $vote], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
	}
