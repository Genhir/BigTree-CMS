<?php
	/*
		Class: BigTree\PaymentGateway\AuthorizeNet
			Provides an Authorize.Net implementation of the PaymentGateway Provider.
	*/
	
	namespace BigTree\PaymentGateway;
	
	use BigTree\cURL;
	
	class AuthorizeNet extends Provider
	{
		
		protected $APILogin;
		protected $DefaultParameters;
		protected $Environment;
		protected $PostURL;
		protected $TransactionKey;
		
		/*
			Constructor:
				Prepares an environment for Authorize.Net payments.
		*/
		
		public function __construct()
		{
			parent::__construct();
			
			$this->APILogin = $this->Settings["authorize-api-login"];
			$this->TransactionKey = $this->Settings["authorize-transaction-key"];
			$this->Environment = $this->Settings["authorize-environment"];
			
			if ($this->Environment == "test") {
				$this->PostURL = "https://test.authorize.net/gateway/transact.dll";
			} else {
				$this->PostURL = "https://secure.authorize.net/gateway/transact.dll";
			}
			
			$this->DefaultParameters = [
				"x_delim_data" => "TRUE",
				"x_delim_char" => "|",
				"x_relay_response" => "FALSE",
				"x_url" => "FALSE",
				"x_version" => "3.1",
				"x_method" => "CC",
				"x_login" => $this->APILogin,
				"x_tran_key" => $this->TransactionKey
			];
		}
		
		// Implements Provider::authorize
		public function authorize(float $amount, float $tax, string $card_name, string $card_number, int $card_expiration,
						   int $cvv, array $address, ?string $description = "", ?string $email = "",
						   ?string $phone = "", ?string $customer = ""): ?string
		{
			return $this->charge($amount, $tax, $card_name, $card_number, $card_expiration, $cvv, $address, $description,
								 $email, $phone, $customer, "AUTH_ONLY");
		}
		
		/*
			Function: call
				Sends an API call to Authorize.Net.
		*/
		
		public function call(array $params): ?array
		{
			$count = 0;
			$possibilities = ["", "approved", "declined", "error"];
			$this->Unresponsive = false;
			
			// Get the default parameters
			$params = array_merge($this->DefaultParameters, $params);
			
			// Set the cURL options
			$curl_options = [
				CURLOPT_HEADER => 0,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query($params)
			];
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = cURL::request($this->PostURL, false, $curl_options);
				
				if ($response) {
					$r = explode("|", $response);
					
					return [
						"status" => $possibilities[$r[0]],
						"message" => $r[3],
						"authorization" => $r[4],
						"avs" => $r[5],
						"cvv" => $r[39],
						"transaction" => $r[6],
						"cc_last_4" => substr($r[50], -4, 4)
					];
				}
				
				$count++;
			}
			
			$this->Unresponsive = true;
			
			return null;
		}
		
		// Implements Provider::capture
		public function capture(string $transaction, ?float $amount = null): ?string
		{
			$params = [
				"x_type" => "PRIOR_AUTH_CAPTURE",
				"x_trans_id" => $transaction
			];
			
			// Default is to capture the whole transaction
			if ($amount) {
				$params["x_amount"] = $this->formatCurrency($amount);
			}
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["transaction"];
			$this->Message = $response["message"];
			
			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return null;
			}
		}
		
		// Implements Provider::charge
		public function charge(float $amount, float $tax, string $card_name, string $card_number, int $card_expiration,
						int $cvv, array $address, ?string $description = "", ?string $email = "", ?string $phone = "",
						?string $customer = "", ?string $action = null): ?string
		{
			
			if (!$action) {
				$action = "AUTH_CAPTURE";
			}
			
			// Clean up the amount and tax.
			$amount = $this->formatCurrency($amount);
			$tax = $this->formatCurrency($tax);
			
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);
			
			// Split the card name into first name and last name.
			$first_name = substr($card_name, 0, strpos($card_name, " "));
			$last_name = trim(substr($card_name, strlen($first_name)));
			
			// Build request parameters
			$params = [
				"x_type" => $action,
				"x_first_name" => $first_name,
				"x_last_name" => $last_name,
				"x_address" => trim($address["street"]." ".$address["street2"]),
				"x_city" => $address["city"],
				"x_state" => $address["state"],
				"x_zip" => $address["zip"],
				"x_country" => $address["country"],
				"x_phone" => $phone,
				"x_email" => $email,
				"x_cust_id" => $customer,
				"x_customer_ip" => $_SERVER["REMOTE_ADDR"],
				"x_card_num" => $card_number,
				"x_exp_date" => $card_expiration,
				"x_card_code" => $cvv,
				"x_amount" => $amount,
				"x_tax" => $tax,
				"x_description" => $description
			];
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["transaction"];
			$this->Message = $response["message"];
			$this->Last4CC = $response["cc_last_4"];
			
			// Get a common AVS response.
			if ($response["avs"] == "A") {
				$this->AVS = "Address";
			} elseif ($response["avs"] == "W" || $response["avs"] == "Z") {
				$this->AVS = "Zip";
			} elseif ($response["avs"] == "X" || $response["avs"] == "Y") {
				$this->AVS = "Both";
			} else {
				$this->AVS = false;
			}
			
			// Get a common CVV response, either it passed or it didn't.
			if ($response["cvv"] == "2" || $response["cvv"] == "8" || $response["cvv"] == "A" || $response["cvv"] == "B") {
				$this->CVV = true;
			} else {
				$this->CVV = false;
			}
			
			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return null;
			}
		}
		
		// Implements Provider::refund
		public function refund(string $transaction, ?string $card_number = "", ?float $amount = 0.0): ?string
		{
			// Setup request params
			$params = [
				"x_type" => "CREDIT",
				"x_trans_id" => $transaction,
				"x_card_num" => $card_number
			];
			
			if ($amount) {
				$params["x_amount"] = $this->formatCurrency($amount);
			}
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["transaction"];
			$this->Message = $response["message"];
			
			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return null;
			}
		}
		
		// Implements Provider::void
		public function void(string $authorization): ?string
		{
			$params = [
				"x_type" => "VOID",
				"x_trans_id" => $authorization
			];
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["transaction"];
			$this->Message = $response["message"];
			
			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return null;
			}
		}
		
	}
