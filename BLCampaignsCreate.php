<? 
/*
* CPMObjectEventHandler: BLCampaignsCreate
* Package: RN
* Objects: BLDialogue\BLCampaigns
* Actions: Create,Update
* Version: 1.2
*/
 
// This object procedure binds to v1_1 of the Connect PHP API
use RightNow\Connect\v1_2 as RNCPHP;
 
// This object procedure binds to the v1 interface of the process
// designer
use \RightNow\CPM\v1 as RNCPM;
/*
Campaign create incident, when incident creating, will trigger this handler and send sms.
*/
 
/**
* An Object Event Handler must provide two classes:
* - One with the same name as the CPMObjectEventHandler tag
* above that implements the ObjectEventHandler interface.
* - And one of the same name with a "_TestHarness" suffix
* that implements the ObjectEventHandler_TestHarness interface.
*
* Each method must have an implementation.
*/
 
class BLCampaignsCreate implements RNCPM\ObjectEventHandler
{
	public static $apiUrl = 'https://dialogue.blueleap.com';
	public static function apply( $run_mode, $action, $obj, $n_cycles )
	{
		$actionToPerform = '';
		date_default_timezone_set("UTC");
		try
		{
			if(RNCPM\ActionCreate == $action)
			{
				if($obj->StatusOrExceptionReason == 'SchedulePending')
				{
					self::scheduleCampaign($obj,'schedule');
					
				}
				else if($obj->StatusOrExceptionReason == 'Executing')
				{
					self::executeCampaign($obj);
				}
				
				
			}
			else if(RNCPM\ActionUpdate == $action)
			{
				if($obj->StatusOrExceptionReason == 'UpdatePending')
				{	
					self::scheduleCampaign($obj,'update');
					
				}
				else if($obj->StatusOrExceptionReason == 'CancelPending')
				{
					self::scheduleCampaign($obj,'cancel');
				}
				else if($obj->StatusOrExceptionReason == 'Executing')
				{
					self::executeCampaign($obj);
					
				}
				
				
				
			}
		}
		catch(\Exception $ex)
		{
			$obj->StatusOrExceptionCode ='ble1016';
			$obj->StatusOrExceptionReason = 'Failed';
			$obj->StatusOrExceptionDescription = 'General script failure - '.$ex->getMessage();
			$obj->save();
			echo $ex->getMessage();
		}
		return;
		
			
	} // apply()

	public static function scheduleCampaign($obj,$action)
	{
					
					$query = "select BLDialogue.BLAccount from BLDialogue.BLAccount";
					$result = RNCPHP\ROQL::queryObject($query)->next();
					$BLAccount = $result->next();
					$tokenResponse = self::getToken($obj,$BLAccount->BLAccountSID,$BLAccount->BLAccountPWD);
					$tokenArr = json_decode($tokenResponse,true);
					$BLAccount->BLToken = $tokenArr['token'];
					$campaignResponse = self::scheduleCampaignServer($obj,$BLAccount,$action);
					if(strlen($campaignResponse))
						{
							
							if(($campaignResult = json_decode($campaignResponse)))
							{
								
								
								$obj->StatusOrExceptionDescription = $campaignResult->StatusMessage;
								$obj->StatusOrExceptionReason = $campaignResult->RequestStatus;
								$obj->StatusOrExceptionCode = $campaignResult->RequestStatusCode;
								
								
								
							}
							else
							{
								// garbage response no json
								$obj->StatusOrExceptionCode = 'ble1014';
								$obj->StatusOrExceptionReason = 'Failed';
								$obj->StatusOrExceptionDescription = 'Garbage response from API not a json object.';
								
							}
						}
						else
						{
							// no response from API
							$obj->StatusOrExceptionCode = 'ble1013';
							$obj->StatusOrExceptionReason = 'Failed';
							$obj->StatusOrExceptionDescription = 'No response from API.';
							// sms send failed, set status failed and add proper reason
						}
						$obj->save();
	}
	public static function executeCampaign($obj)
	{
		$obj->StartTime = time();
		//$query = "select MarketingSettings.ContactLists.* from Contact limit 1";
		$query = "SELECT Contact FROM Contact where MarketingSettings.ContactLists.NamedIDList=".$obj->ContactListID;
		//$query = "SELECT Contact FROM Contact where MarketingSettings.ContactLists.NamedIDList=2408";
		$result = RNCPHP\ROQL::queryObject($query)->next();
				
		$obj->OptOutCount = 0;
		$obj->SMSCreateCount = 0;
		$obj->SMSFailedCount = 0;
		$obj->AudienceCount=0;
		$campaign = RNCPHP\BLDialogue\BLCampaigns::fetch($obj->ID);

		while($contact = $result->next())
		{
			$campaign = RNCPHP\BLDialogue\BLCampaigns::fetch($obj->ID);
			if($campaign->StatusOrExceptionReason == 'Executing')
			{
				if($contact->CustomFields->BLDialogue->sms_opt_out)
				{
					$obj->OptOutCount++;
				}
				$optedOut = false;
				if($contact->CustomFields->BLDialogue->sms_opt_out && $obj->OptOutOverride)
					$optedOut = false;
				else if($contact->CustomFields->BLDialogue->sms_opt_out)
					$optedOut = true;
						
				if(self::saveBlOutboundMessage($contact,$obj,$optedOut))
				{
					$obj->SMSCreateCount++;
				}
				else
				{
					$obj->SMSFailedCount++;
				}
						
						
				$obj->AudienceCount++;
			}
			else
			{
				break;
			}
			
					
					
					
		}
		if($campaign->StatusOrExceptionReason == 'Executing')
		{
			$obj->StatusOrExceptionCode = 'bls1029';
			$obj->StatusOrExceptionReason = 'Finished';
			$obj->StatusOrExceptionDescription = 'Campaign has finished processing';
		}
				
				
		$obj->FinishTime = time();
		$obj->save();
				
				
				
	
			
	}
	public static function saveBlOutboundMessage($contact,$BlCampaigns,$optedOut)
	{
		
		try{
			$messageBody = self::getMessageBody($BlCampaigns->MessageBody,$contact);
			$BlOutboundMessage = new RNCPHP\BLDialogue\BLOutboundMessage();
			$BlOutboundMessage->MessageBody = $messageBody;
			$BlOutboundMessage->AlphaNumeric = $BlCampaigns->AlphaNumeric;
			$BlOutboundMessage->ContactID = $contact;
			//$BlOutboundMessage->MessageSID = $messageCount;
			$BlOutboundMessage->AgentID = $BlCampaigns->AgentID;
			$BlOutboundMessage->RunTime = $BlCampaigns->RunTime;
			$BlOutboundMessage->ContactGMToffset = $BlCampaigns->ContactGMToffset;
			$BlOutboundMessage->MessageType = $BlCampaigns->MessageType;
			$roql_result_set = RNCPHP\ROQL::query( "SELECT Phones.Number from Contact where Phones.PhoneType.ID = 1 and  ID=".$contact->ID)->next()->next();
			
			if(($number = self::formatNumber($roql_result_set['Number']))===false)
			{
				$number = $roql_result_set['Number'];
				$BlOutboundMessage->StatusOrExceptionCode = 'ble1020';
				$BlOutboundMessage->StatusOrExceptionReason ='Failed';
				$BlOutboundMessage->StatusOrExceptionDescription = 'You have attempted to send an SMS with a number that is not a valid mobile number';
				// if(($number = self::formatNumber($contact->Phones[0]->Number))===false)
				// {
				// 	$number = $contact->Phones[1]->Number?$contact->Phones[1]->Number:$contact->Phones[0]->Number;
				// 	$BlOutboundMessage->StatusOrExceptionCode = 'ble1020';
				// 	$BlOutboundMessage->StatusOrExceptionReason ='Failed';
				// }
			}
			if($number == "dnp") 
			{
				$number = $roql_result_set['Number'];
			}
			
			
			$BlOutboundMessage->ContactToNumber = $number;
			//$BlOutboundMessage->ContactFirstName = $contact->Name->First;
			//$BlOutboundMessage->ContactLastName = $contact->Name->Last;
			//$BlOutboundMessage->ContactCountry = $contact->Address->Country->Name;
			//$BlOutboundMessage->ContactState = $contact->Address->StateOrProvince->LookupName;
			$BlOutboundMessage->CampaignID = $BlCampaigns->ID;
			$BlOutboundMessage->OptOutOverride = $BlCampaigns->OptOutOverride;
			//$BlOutboundMessage->MessageDirection = 'Outbound';
			//print_r($BlOutboundMessage);exit;
			if($optedOut)
			{
				$BlOutboundMessage->StatusOrExceptionCode = 'BLE1019';
				$BlOutboundMessage->StatusOrExceptionReason ='Failed';
				$BlOutboundMessage->StatusOrExceptionDescription = 'Customer has opted out. SMS channel not available.';
			}
			
			//echo 'code - '.$BlOutboundMessage->StatusOrExceptionCode.' Number - '.$BlOutboundMessage->ContactToNumber;
			$BlOutboundMessage->save();
			
			return true;
			
		}
		catch(\Exception $ex)
		{
			
			echo $ex->getMessage();
			return false;
		}
		

	}
	function scheduleCampaignServer($BLCampaign,$BLAccount,$action)
	{
		try
		{
			
			$url = self::$apiUrl.'/api/v1/campaigns/';
						
			$headers = array(
				"Cache-Control: no-cache",
				"Pragma: no-cache",
				"Authorization: ".$BLAccount->BLToken
				);
			
				
			$context = [
				
				"BLAccountSID"=>$BLAccount->BLAccountSID,
				"Action"=>$action,
				
				"CustomerDomain"=>$BLAccount->CustomerDomain1,
				
				"RunTime"=> $BLCampaign->RunTime?gmdate("m/d/Y h:i:s a", $BLCampaign->RunTime):null,
				"CampaignID"=>$BLCampaign->ID,
				
				];
			
			$postargs = http_build_query($context);
			
			// Get the curl session object
			load_curl();
			$session = curl_init();
			curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($session, CURLOPT_URL, $url);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($session, CURLOPT_POST, true);
			curl_setopt($session, CURLOPT_POSTFIELDS, $postargs);
			curl_setopt($session, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($session, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt($session, CURLOPT_FOLLOWLOCATION, 1);
			// Do the POST and then close the session
			$response = curl_exec($session);
			$httpcode = curl_getinfo($session);
			curl_close($session);
			
			return $response;
						
		}
		catch(\Exception $ex)
		{
			echo $ex->getMessage();
			self::apiRequestTimeout($BLCampaign);
			//throw new RNCPM\CPMException($ex->getMessage(), 500);
			
		}
	}
	function getToken($BLCampaign,$username,$password)
	{
		try{
			$headers = array(
				"Cache-Control: no-cache",
				"Pragma: no-cache",
				'Accept: application/json',
				'Content-Type: apploication/json',
				"Authorization: Basic ".base64_encode($username.":".$password)
				);
			load_curl();
			$session = curl_init();
			curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($session, CURLOPT_URL,self::$apiUrl.'/api/v1/token');
			curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($session, CURLOPT_POST, false);
			curl_setopt($session, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($session, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt($session, CURLOPT_FOLLOWLOCATION, 1);
			$response = curl_exec($session);
			curl_close($session);
			return $response;
		}
		catch(\Exception $ex)
		{
			echo $ex->getMessage();
			self::apiRequestTimeout($BLCampaign);
			//throw new RNCPM\CPMException($ex->getMessage(), 500);
		}
		
	}
	function apiRequestTimeout($BLCampaign)
	{
		
		$BLCampaign->StatusOrExceptionCode = 'ble1015';
		$BLCampaign->StatusOrExceptionReason = 'Failed';
		$BLCampaign->StatusOrExceptionDescription = 'API request timeout.';
		$BLCampaign->save();
		
	}
	function getStatusReasonObj($type,$name)
	{
		$query = "select ".$type." from ".$type." where Name ='".$name."'";
		$result = RNCPHP\ROQL::queryObject($query)->next();
		return $result->next();
	}
	
	function getMessageBody($messageBody,$contact)
	{
		
		// if($BlCampaigns->MergeFirstName)
		// {
		// 	$messageBody = str_replace('[FIRSTNAME]',$contact->Name->First,$messageBody);
			
		// }
		// if($BlCampaigns->MergeStudentID)
		// {
		// 	$messageBody = str_replace('[STUDENTID]',$data->CustomFields->c->student_id,$messageBody);
		// }
		$pattern = "!(?<=[[])[^]]+(?=[]])!";
    	preg_match_all($pattern,$messageBody,$match);
		$replace = [];
		$i=0;
		foreach($match[0] as $field)
		{
			try
			{
				$query = 'select Contact.'.$field.' from Contact where ID='.$contact->ID;
				$result = RNCPHP\ROQL::query($query)->next()->next();
				$replace[$i]=end($result);
				
			}
			catch(\Exception $ex)
			{
				$replace[$i] = '';
			}
			$match[0][$i] = '['.$field.']';
			$i++;
			

		}
		$messageBody = str_replace($match[0],$replace,$messageBody);
		
		return $messageBody;
	}
	function formatNumber($number)
	{
		$phoneNumber = preg_replace( '/[^0-9+]/', '', $number);
		$query = "select BLDialogue.BLAccount from BLDialogue.BLAccount";
		$result = RNCPHP\ROQL::queryObject($query)->next();
		$BLAccount = $result->next();
		if(substr($phoneNumber,0,1)=='+')
		{
			return $phoneNumber;
		}
		else if(substr($phoneNumber, 0, strlen($BLAccount->LocalCountryCode))==$BLAccount->LocalCountryCode)
		{
			return '+'.$phoneNumber;
		}
		
		if($BLAccount->LocalCountryCode == "61")
		{
			
		
			
			if(substr($phoneNumber, 0, 4)=="0011")
			{
				return str_replace("0011",'+',$phoneNumber);
			}
			// else if((strlen($phoneNumber)==11 || strlen($phoneNumber)==12) && substr($phoneNumber,0,1)!='+')
			// {
			// 	return '+'.$phoneNumber;
			// }
			else if(strlen($phoneNumber)==8)
			{
				return false;
			}
			// else if((strlen($phoneNumber)==10 && (!in_array(substr($phoneNumber, 0, 2),["04","05"]))) || (strlen($phoneNumber)==9 && (!in_array(substr($phoneNumber, 0, 1),["4","5"]))) )
			// {
			// 	return false;
			// }
			else if((strlen($phoneNumber)==10 && in_array(substr($phoneNumber, 0, 2),["04","05"])) || (strlen($phoneNumber)==9 && in_array(substr($phoneNumber, 0, 1),["4","5"]) ))
			{
				$phoneNumber = strlen($phoneNumber)==10?substr($phoneNumber,1):$phoneNumber;
				return '+61'.$phoneNumber;
			}
			else if((strlen($phoneNumber)==10 && in_array(substr($phoneNumber, 0, 2),["02","03","07","08"])) || (strlen($phoneNumber)==9 && in_array(substr($phoneNumber, 0, 2),["2","3","7","8"])) )
			{
				return false;
			}
			else if(strlen($phoneNumber)==9)
			{
				return '+61'.$phoneNumber;
			}

		}
		else
		{
			if(substr($phoneNumber,0,1)=='0')
			$phoneNumber = substr($phoneNumber,1);
			
			return '+'.$BLAccount->LocalCountryCode.$phoneNumber; 
			
			
		}
		
		
		return false;
		
	}


	
	/*********End*************/
	
} // class incident_name
 
 
/*
The Test Harness
*/
 
 
class BLCampaignsCreate_TestHarness implements RNCPM\ObjectEventHandler_TestHarness
{
	static $co_invented = NULL;
 
	public static function setup()
	{
		try
        {
			$query = "SELECT MarketingSettings.ContactLists.NamedIDList.ID FROM Contact limit 1";
			
			$result = RNCPHP\ROQL::query($query)->next()->next();
			if(isset($result['ID']))
			{
				$contact = RNCPHP\Contact::first();
				//echo "creating contact";
				$BlCampaign = new RNCPHP\BLDialogue\BLCampaigns();
				$BlCampaign->CampaignName = 'Event Handler Test Case';
				//$BlCampaign->RunTime = strtotime('2017-12-12 12:00:00' );
				$BlCampaign->ContactListID = $result['ID'];
				$BlCampaign->OptOutOverride = 0;
				$BlCampaign->AlphaNumeric = 0;
				//$BlCampaign->MergeStudentID = 0;
				//$BlCampaign->MergeFirstName = 0;
				$BlCampaign->AgentID = $contact;
				$BlCampaign->MessageBody = 'Event Handler Test Case Sample message';
				$BlCampaign->MessageType = 'Message Campaign';
				$BlCampaign->StatusOrExceptionCode = 'bls1022';
				$BlCampaign->StatusOrExceptionReason = 'Executing';
				$BlCampaign->StatusOrExceptionDescription = 'Camapign is being processed';
			
				$BlCampaign->save();

				static::$co_invented = $BlCampaign;
			}
			
        }
        catch(RNCPHP\ConnectAPIErrorFatal $err )
        {
            echo "Error in test harness save contact : ".$err->getCode();
        }
        return;
	
	}
 
	public static function fetchObject( $action, $object_type )
	{
	// Return the object that we
	// want to test with.
	// You could also return an array of objects
	// to test more than one variation of an object.
	//$obj = RNCPHP\BLDialogue\BLCampaigns::fetch(289);
	//return $obj;
       // return static::$co_invented?static::$co_invented: array();
	   return array();
	}
 
	public static function validate( $action, $object )
	{
		return(1);
	}
 
	public static function cleanup()
	{
		// Destroy every object invented
		// by this test.
		// Not necessary since in test
		// mode and nothing is committed,
		// but good practice if only to
		// document the side effects of
		// this test.
		if(static::$co_invented)
		{
			static::$co_invented->destroy();
        	static::$co_invented = NULL;
		}
		
        return;
	}		
}