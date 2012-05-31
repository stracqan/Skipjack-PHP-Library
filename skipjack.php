<?php
/**
 * 
 * Skipjack
 * 
 * // usage for simple transaction
 * <usage>
 *      $sj = new Skipjack();
 *      $sj->setDeveloper(true); // use the development server address
 * 
 * 		$itemCost = $sj->cleanItemCost(YOUR_AMOUNT); //added by: Angelo Stracquatanio to clean the item cost.  See function summary for reason why
 *		
 *      $sj->addFields(array(
 *              'OrderNumber' => '5',
 *              'ItemNumber' => 'i5',
 *              'ItemDescription' => 'Test Item',
 *              'ItemCost' => '5.50',
 *              'Quantity' => '1',
 *              'Taxable' => '0',
 *              'AccountNumber' => '4445999922225',
 *              'Month' => '12',
 *              'Year' => '2010',
 *              'TransactionAmount' => $itemCost
 *      ));
 *  
 *      if($sj->process() && $sj->isApproved()) {
 *              echo "Transaction approved!";
 *      } else {
 *              echo "Transaction declined!\n";
 *              echo $sj->getErrors();
 *      }
 * </usage>
 * 
 * // usage for recurring payments, added by: Angelo Stracquatanio
 * <usage>
 *      $sj = new Skipjack('recurring');
 *      $sj->setDeveloper(true); // use the development server address
 * 
 * 		$itemCost = $sj->cleanItemCost(YOUR_AMOUNT); //clean the item cost.  See function summary for reason why
 *		$startDate = $sj->getStartDate(); //See function summary for reason why we're building the date
 *
 *      $sj->addFields(array(
 *              'rtOrderNumber' => uniqid(),
 *				'rtName' => 'John Doe',
 *				'rtEmail'  => 'john@example.com',
 *				'rtAddress1' => '8320',
 *				'rtCity' => 'Awesomeville',
 *				'rtState' => 'NY',
 *				'rtPostalCode' => '85284',
 *				'rtAccountNumber' => '4445999922225',
 *				'rtExpMonth' => '06',
 *				'rtExpYear' => '2012',
 *				'rtItemNumber' => '1',
 *				'rtItemDescription' => DESCRIPTION,
 *				'rtAmount' => $itemCost,
 *				'rtStartingDate' => $startDate,
 *				'rtFrequency' => '3', //3 means monthly from page 112 in int. guide
 *				'rtTotalTransactions' => '24' //they recommend not going over 3 years, so I set the max to 2 years
 *      ));
 *  
 *      if($sj->processRecurringPymnts() && $sj->isApproved()) {
 *              echo "Transaction approved!";
 *      } else {
 *              echo "Transaction declined!\n";
 *              echo $sj->getErrors();
 *      }
 * </usage>
 * 
 * Used to connect to the Skipjack API and submit credit card orders that need
 * to be authorized.
 * 
 * @author      Steven Vondruska, Bret Kuhns Extended by: Angelo Stracquatanio
 * @link        http://imgserver.skipjack.com/imgServer/5293710/skipjack_integration_guide.pdf
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @access      public
 *
 * Notes:
 * *This file was exended on 5/30/12 by Angelo Stracquatanio to include recurring payments and cleanup functions
 *
 *
 */
class Skipjack {
        var $fields = array();          // Fields to be posted to the API
        var $response = array();        // Reponse array returned after query
        var $errors = array();          // Any errors that might have populated
		var $error;
		var $fullResponse;
        
        // REPLACE THESE VALUES WITH YOUR OWN GIVEN BY SKIPJACK
		var $serialNumber    = 'YOURSERIALNUMBER';
        var $devSerialNumber = 'YOURDEVNUMBER';
        
        var $DEVELOPER = false;
        
        // Required fields from pages 49-50 in API manual
        var $requiredFields = array(
                        'SerialNumber', 'DeveloperSerialNumber', 'OrderNumber', 'ItemNumber',
                        'ItemDescription', 'ItemCost', 'Quantity', 'Taxable', 'SJName',
                        'Email', 'StreetAddress', 'City', 'State', 'ZipCode', 
                        'ShipToPhone', 'OrderString', 'AccountNumber', 'Month', 'Year',
                        'TransactionAmount'
                );
				
		// Required fields from pages 109-113 in API manual for recurring payments
        var $reqFldsRecurringPayments = array(
                        'szSerialNumber', 'szDeveloperSerialNumber', 'rtOrderNumber', 'rtName', 'rtEmail',
                        'rtAddress1', 'rtCity', 'rtState', 'rtPostalCode', 'rtPhone',
                        'rtAccountNumber', 'rtExpMonth', 'rtExpYear', 'rtItemNumber', 'rtItemDescription', 
                        'rtAmount', 'rtStartingDate', 'rtFrequency', 'rtTotalTransactions'
                );
				
        // recommended dummy values from page 50 in API manual
        var $dummyVals = array(
                        'SJName' => 'NA',
                        'Email'  => 'None',
                        'StreetAddress' => 'None',
                        'City' => 'None',
                        'State' => 'XX',
                        'ZipCode' => '00000',
                        'ShipToPhone' => '0000000000',
                        'OrderString' => '1~None~0.00~0~N~||'
                );
		
		// recommended dummy values from page 50 in API manual
        var $dummyValsReccurPymnts = array(
                        'rtName' => 'NA',
                        'rtEmail'  => 'None',
                        'rtAddress1' => 'None',
                        'rtCity' => 'None',
                        'rtState' => 'XX',
                        'rtPostalCode' => '00000',
                        'rtPhone' => '0000000000'
                );		
		
        var $authCodes = array(
                        0 => 'Source Unknown',
                        1 => 'STIP, Timeout Response',
                        2 => 'LCS Response',
                        3 => 'STIP, Issuer in Suppression',
                        4 => 'STIP Reponse, Issuer Unavailable',
                        5 => 'Issuer Aproval',
                        7 => 'Aquirer Approval, Base 1 Down',
                        8 => 'Aquirer Approval of Referral'
                );
        var $errorCodes = array(
                        "1"       => "Success (Valid Data)",
                        "-35" => "Invalid credit card number",
                        "-37" => "Error failed communication",
                        "-39" => "Error length serial number",
                        "-51" => "Invalid Billing Zip Code",
                        "-52" => "Invalid Shipto zip code",
                        "-53" => "Invalid expiration date",
                        "-54" => "Error length account number date",
                        "-55" => "Invalid Billing Street Address",
                        "-56" => "Invalid Shipto Street Address",
                        "-57" => "Error length transaction amount",
                        "-58" => "Invalid Name",
                        "-59" => "Error length location",
                        "-60" => "Invalid Billing State",
                        "-61" => "Invalid Shipto State",
                        "-62" => "Error length order string",
                        "-64" => "Invalid Phone Number",
                        "-65" => "Empty name",
                        "-66" => "Empty email",
                        "-67" => "Empty street address",
                        "-68" => "Empty city",
                        "-69" => "Empty state",
                        "-79" => "Error length customer name",
                        "-80" => "Error length shipto customer name",
                        "-81" => "Error length customer location",
                        "-82" => "Error length customer state",
                        "-83" => "Invalid Phone Number",
                        "-84" => "Pos error duplicate ordernumber",
                        "-91" => "Pos_error_CVV2",
                        "-92" => "Pos_error_Error_Approval_Code",
                        "-93" => "Pos_error_Blind_Credits_Not_Allowed",
                        "-94" => "Pos_error_Blind_Credits_Failed",
                        "-95" => "Pos_error_Voice_Authorizations_Not_Allowed"
                );
        
        
        /**
         * Constructor
         *
         * @param       String  $serial
         * @param       String  $developer
         * @return      Skipjack
         */
        function Skipjack($type = 'single', $serial = null, $developer = null) {
                
				if ($type === 'single')
				{
					if($serial != null) 
						$this->addField('SerialNumber', $serial);
					else 
						$this->addField('SerialNumber', $this->serialNumber);
					
					if($developer != null) 
						$this->addField('DeveloperSerialNumber', $developer);
					else 
						$this->addfield('DeveloperSerialNumber', $this->devSerialNumber);
				}
				else if ($type === 'recurring')
				{
					if($serial != null) 
						$this->addField('szSerialNumber', $serial);
					else 
						$this->addField('szSerialNumber', $this->serialNumber);
					
					if($developer != null) 
						$this->addField('szDeveloperSerialNumber', $developer);
					else 
						$this->addfield('szDeveloperSerialNumber', $this->devSerialNumber);
				}
        }
        
        
        /**
         * Add field to request, required field are:
         *   SJName (Billing Name), Email, StreetAddress, City, State, ZipCode, 
         *   ShipToPhone, AccountNumber (CC#), Month, Year, TransactionAmount, 
         *   OrderNumber, OrderString
         *
         * @param       String  $key
         * @param       String  $value
         * @return      void
         */
        function addField($key, $value) {
                if($value !== "" && $value !== "Submit") {
                        $this->fields[$key] = $value;
                }
        }
        
        
        /**
         * Allow array to be sent to object at once
         *
         * @param       Array(String => String) $array
         * @return      void
         */
        function addFields($array) {
                foreach($array as $key => $value) {
                        $this->addField($key, $value);
                }
        }
        
        
        /**
         * Determines if all required fields are in the fields array before
         * attempting to post to Skipjack. If a dummy value is found for a field,
         * then it used as a default and no error is thrown for that field. Returns
         * false if any errors are encountered.
         *
         * @access      private
         * @return      boolean
         */
        function __canPost() {
                $return = true;
                
                foreach($this->requiredFields as $field) {
                        if(!isset($this->fields[$field])) {
                                if(array_key_exists($field, $this->dummyVals)) {
                                        $this->addField($field, $this->dummyVals[$field]);
                                } else {
                                        $return = false;
                                        $this->errors[] = 'Required field not found: '.$field;
                                }
                        }
                }
                
                return $return;
        }
		
		/**
		 * For Recurring Payments
         * Determines if all required fields are in the fields array before
         * attempting to post to Skipjack. If a dummy value is found for a field,
         * then it used as a default and no error is thrown for that field. Returns
         * false if any errors are encountered.
         *
         * @access      private
         * @return      boolean
         */
        function __canPostRecurrPymnts() {
                $return = true;
                
                foreach($this->reqFldsRecurringPayments as $field) {
                        if(!isset($this->fields[$field])) {
                                if(array_key_exists($field, $this->dummyValsReccurPymnts)) {
                                        $this->addField($field, $this->dummyValsReccurPymnts[$field]);
                                } else {
                                        $return = false;
                                        $this->errors[] = 'Required field not found: '.$field;
                                }
                        }
                }
                
                return $return;
        }
        
        
        /**
         * Process the order using information in Skipjack::fields. Returns false
         * when an error is encountered.
         *
         * @return      boolean
         */
        function process() {
                $post = '';
                $return = true;
                
                if($this->__canPost()) {
                        foreach($this->fields as $key=>$value) {
                                $post .= "$key=" . urlencode($value) . "&";
                        }
                        
                        if($this->DEVELOPER) {
                                $url = "https://developer.skipjackic.com/scripts/evolvcc.dll?AuthorizeAPI";
                        } else {
                                $url = "https://www.skipjackic.com/scripts/evolvcc.dll?AuthorizeAPI";
                        }
						
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, rtrim($post, "&"));
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        $response = curl_exec($ch);
						
                        if(curl_errno($ch) > 0) {
                                $this->errors[] = "Encountered Curl error number: ".curl_errno($ch) . $ch;
                                $return = false;
                        }
                        curl_close($ch);
                        
                        $response = explode("\r", $response);
                        $header = explode('","', $response[0]);
                        $data = explode('","', $response[1]);
                        
                        foreach($header as $i => $array) {
                                $this->response[str_replace(array("\r",'"'), "", $array)] = str_replace(array("\r",'"'), "", $data[$i]);
                        }
                } else {
                        $return = false;
                }
                
                return $return;
        }
		
		/**
         * Process the recurring payments order using information in Skipjack::fields. Returns false
         * when an error is encountered.
         *
         * @return      boolean
         */
        function processRecurringPymnts() {
                $post = '';
                $return = true;
                
                if($this->__canPostRecurrPymnts()) {
                        foreach($this->fields as $key=>$value) {
                                $post .= "$key=" . urlencode($value) . "&";
                        }
                        
                        if($this->DEVELOPER) {
                                $url = "https://developer.skipjackic.com/scripts/evolvcc.dll?SJAPI_RecurringPaymentAdd";
                        } else {
                                $url = "https://www.skipjackic.com/scripts/evolvcc.dll?SJAPI_RecurringPaymentAdd";
                        }
						
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, rtrim($post, "&"));
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                        $response = curl_exec($ch);
                        if(curl_errno($ch) > 0) {
                                $this->errors[] = "Encountered Curl error number: ".curl_errno($ch);
                                $return = false;
                        }
                        curl_close($ch);
                        
						//echo $response;
						
                        $response = explode("\r", $response);
                        $header = explode('","', $response[0]);
                        $data = explode('","', $response[1]);
						
						$this->response['szIsApproved'] = $header[1];
						
						//convert the status code of '0' to 1, which is the success variable for the regular transaction. SJ has no consistency...
						if ($header[1] === '0')
							$this->response['szIsApproved'] = 1;
						else
							$this->response['szIsApproved'] = $header[1];
						
						/*echo "";
						print_r($this->response);
						echo "";*/
                        
						//TO DO: pull out each var in the response.  The response description is on page 114 of integration guide
                        /*foreach($header as $i => $array) {
                                $this->response[str_replace(array("\r",'"'), "", $array)] = str_replace(array("\r",'"'), "", $data[$i]);
                        }*/
                } else {
                        $return = false;
                }
                
                return $return;
        }
        
        
        /**
         * Check the response for errors, returns false if errors found.
         *
         * @return      boolean
         */
        function checkForErrors() {
                $return = true;
                
                if(!$this->isApproved()) {
                        if($this->isCardDeclined()) {
                                $this->errors[] = $this->response['szAuthorizationDeclinedMessage'];
								//$this->errors[] = 'foo';
								//$this->error = "foo";
                                $return = false;
                        } else {
                                // this will run if there is an error with the information that you have provided to skipjack
                                $this->errors[] = $errorCodes[$this->response['szReturnCode']];
								//$this->errors[] = 'bar';
								//$this->error = "bar";
                                $return = false;
                        }
                }
                
                return $return;
        }
        
        
        /**
         * @return      boolean
         */
        function isApproved() {
                return ($this->response['szIsApproved'] == 1);
        }
        
        
        /**
         * @return      boolean
         */
        function isCardDeclined() {
                return !empty($this->response['szAuthorizationDeclinedMessage']);
        }
        
        
        /**
         * Returns the response auth code and associated string
         *
         * @return      Array(int => String)
         */
        function getAuthCode() {
                return array((int)$this->response['AUTHCODE'] => $this->authCodes[(int)$this->response['AUTHCODE']]);
        }
        
        
        /**
         * Set the developer variable. If set to true, development server is used.
         *
         * @param       boolean $val
         * @return      void
         */
        function setDeveloper($value) {
                $this->DEVELOPER = (bool)$value;
        }
        
        
        /**
         * @return      boolean
         */
        function errorsExist() {
                return (count($this->errors) > 0);
        }
        
        
        /**
         * @return      Array(String)
         */
        function getErrors() {
                return $this->errors;
        }
		
		/**
         * @return      Array(String)
         */
        function getResponse() {
                return $this->fullResponse;
        }
		
		/**
         * @return      Array(String)
         */
        function getFields() {
                return $this->fields;
        }
        
        
        /**
         * Reset the object's properties so multiple instantiations aren't required
         * for batch processing.
         *
         * @return      void
         */
        function reset() {
                $this->fields = array();
                $this->response = array();
                $this->errors = array();
        }
        
		/**
		* Added by: Angelo Stracquatanio
		* Date: 5/30/12
		*
		* Becuase Skipjack only accepts amounts with 2 decimal places and no commas because they're lazy,
		* this function cleans up the amount
		*
		* @param	string $value
		* @return 	string $cleanedValue
		*/
		function cleanItemCost($value) {
			
			$newStringWithStrippedCommas = preg_replace('#[^\w()/.%\-&]#', "", $value);
			$numberWithCorrectTwoDecimalPlacesFormat = number_format($newStringWithStrippedCommas, 2, '.', '');
			
			return $numberWithCorrectTwoDecimalPlacesFormat;
			
		}
		
		/**
		* Added by: Angelo Stracquatanio
		* Date: 5/30/12
		*
		* Another example of skipjack's lazyness.  They only accept 1-28 as valid start dates for
		* recurring payments, so this cleans the start date if it's the 29th, 30th, or 31st (page 107 of the doc)
		*
		* This builds the date off of today's date and format's it to their specifications: mm/dd/yyyy
		* page 112 of the integration guide
		*
		* @return 	string $cleanedDate
		*/
		function getStartDate() {
			
			$todaysDayValue = date("j"); 
			$todaysMonth = date("n"); 
			$todaysYear = date("Y"); 
				
			if ($todaysDayValue == '29' || $todaysDayValue = '30' || $todaysDayValue = '31')
				$todaysDayValue = '28';
				
			$startDate = $todaysMonth . "/" . $todaysDayValue . "/" . $todaysYear;
			
			return $startDate;
			
		}
        
}
?>