<?php
	/*
		Class: BigTree\GoogleAnalytics\Property
			A Google Analytics object that contains information about and methods you can perform on a property.
	*/
	
	namespace BigTree\GoogleAnalytics;
	
	use BigTree\GoogleResultSet;
	use stdClass;
	
	class Property
	{
		
		/** @var API */
		protected $API;
		
		public $AccountID;
		public $CreatedAt;
		public $ID;
		public $Name;
		public $UpdatedAt;
		public $WebsiteURL;
		
		public function __construct(stdClass $property, API &$api)
		{
			$this->AccountID = $property->accountId;
			$this->API = $api;
			$this->CreatedAt = date("Y-m-d H:i:s", strtotime($property->created));
			$this->ID = $property->id;
			$this->Name = $property->name;
			$this->UpdatedAt = date("Y-m-d H:i:s", strtotime($property->updated));
			$this->WebsiteURL = $property->websiteUrl;
		}
		
		public function getProfiles($params): ?GoogleResultSet
		{
			return $this->API->getProfiles($this->AccountID, $this->ID, $params);
		}
		
	}
