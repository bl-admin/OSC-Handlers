<? 
/*
* CPMObjectEventHandler: BLOutboundMessageCreate
* Package: RN
* Objects: BLDialogue\BLOutboundMessage
* Actions: Create
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
 
class BLOutboundMessageCreate implements RNCPM\ObjectEventHandler
{
	public static $apiUrl = 'https://dialogue.blueleap.com';
	public static function apply( $run_mode, $action, $obj, $n_cycles )
	{
		
		if(RNCPM\ActionCreate == $action)
		{
		
			try
			{
				$impMsg = false;
				$query = "select BLDialogue.BLAccount from BLDialogue.BLAccount";
				$result = RNCPHP\ROQL::queryObject($query)->next();
				$BLAccount = $result->next();
				//$BLAccount = RNCPHP\BLDialogue\BLAccount::fetch(1);
				
				$obj->BLAccountSID = $BLAccount->BLAccountSID;
				$obj->CustomerDomain = $BLAccount->CustomerDomain1;
				// temp username and password
				// $obj->BLAccountSID = 'vu1';
				
				$tokenResponse = self::getToken($obj,$BLAccount->BLAccountSID,$BLAccount->BLAccountPWD);
				$tokenArr = json_decode($tokenResponse,true);
				
				$BLAccount->BLToken = $tokenArr['token'];
				
				
				date_default_timezone_set("UTC");
				// if not contactID then contactID => NC
				
				
				$obj->ConversationSID = $BLAccount->BLAccountSID."#".$obj->ContactID->ID."#".uniqid('',true);
				
				
				$obj->SendTime = time();
				if(strlen($obj->MessageBody) && strlen($obj->ContactToNumber) )
				{

					if(($obj->OptOutOverride) && !($obj->AlphaNumeric) && ($obj->ContactID->CustomFields->BLDialogue->sms_opt_out))
					{
						$obj->AlphaNumeric=1;
						$impMsg = true;

					}
					if($obj->StatusOrExceptionCode=="")
					{
						
						$smsResponse = self::sendSms($obj,$BLAccount->BLToken);
					
					
					
					// {
					// 	$tokenResponse = self::getToken($obj->BLAccountSID,$password);
					// 	$tokenArr = json_decode($tokenResponse,true);
					// 	$BLAccount->BLToken = $tokenArr['token'];
					// 	$smsResponse = self::sendSms($obj,$BLAccount->BLToken);
					// }
						if(strlen($smsResponse))
						{
							
							if(($smsResult = json_decode($smsResponse)))
							{
								
								
								$obj->StatusOrExceptionDescription = $smsResult->StatusMessage;
								$obj->StatusOrExceptionReason = $smsResult->RequestStatus;
								$obj->StatusOrExceptionCode = $smsResult->RequestStatusCode;
								if($smsResult->RequestStatusCode=='bls1002')
									$obj->StatusOrExceptionDescription = NULL;
								// set bls1018 if user was optout and optoutoverride was on
								if($impMsg===true && $smsResult->RequestStatusCode=='bls1002')
								{
									$obj->StatusOrExceptionDescription = 'Customer had opted out and message was delivered with an AlphaNumeric header.';
									$obj->StatusOrExceptionCode = 'bls1018';
								}
								
								
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
					}
				}
				else
				{
					// message body or contact number not available 
					$obj->StatusOrExceptionCode = 'ble1012';
					$obj->StatusOrExceptionReason ='Failed';
					$obj->StatusOrExceptionDescription = 'MessageBody or Contact Number is not available';
					// unable to send sms, invalid data
				}
				
				
				
			}
			catch(\Exception $ex)
			{
				$obj->StatusOrExceptionCode = 'ble1016';
				$obj->StatusOrExceptionReason = 'Failed';
				$obj->StatusOrExceptionDescription = 'General script failure - '.$ex->getMessage();
				echo $ex->getMessage();
				//throw new RNCPM\CPMException($ex->getMessage(), 500);
			}
			$obj->save();
			$BLAccount->save();
			
			
		}
			
	} // apply()
	public function sendSms($BlOutboundMessage,$token)
	{
		
		
		try
		{
			
			$url = self::$apiUrl.'/api/v1/send/';
						
			$headers = array(
				"Cache-Control: no-cache",
				"Pragma: no-cache",
				"Authorization: ".$token
				);
			
				
			$context = [
				"SendTime"=>date('YmdHis'),
				"BLAccountSID"=>$BlOutboundMessage->BLAccountSID,
				"ConversationSID"=>$BlOutboundMessage->ConversationSID,
				"MessageSID"=>$BlOutboundMessage->ConversationSID,
				"MessageType"=>$BlOutboundMessage->MessageType,
				"ContactToNumber"=>$BlOutboundMessage->ContactToNumber,
				"MessageBody"=>$BlOutboundMessage->MessageBody,
				//"AgentId"=>$BlOutboundMessage->AgentID,
				//"WindowId"=>1,
				"ContactID"=>$BlOutboundMessage->ContactID?$BlOutboundMessage->ContactID->ID:'NC',
				"IncidentID"=>$BlOutboundMessage->IncidentID,
				"AlphaNumeric"=>$BlOutboundMessage->AlphaNumeric?'Yes':'No',
				"OptOutOverride"=>$BlOutboundMessage->OptOutOverride?'Yes':'No',
				//"MessageSuffix"=>$BlOutboundMessage->MessageSuffix,
				"CustomerDomain"=>$BlOutboundMessage->CustomerDomain,
				//"CustomerSystem"=>$BlOutboundMessage->CustomerSystem,
				"CustomerSystemType"=>$BlOutboundMessage->CustomerSystemType,
				//"CustomerSystemVersion"=>$BlOutboundMessage->CustomerSystemVersion,
				"WindowID"=>$BlOutboundMessage->WindowID,
				"SessionID"=>$BlOutboundMessage->SessionID,
				//"GroupName"=>$BlOutboundMessage->GroupName,
				//"ReportingManager"=>$BlOutboundMessage->ReportingManager,
				//"ContactCountry"=>$BlOutboundMessage->ContactCountry,
				//"ContactState"=>$BlOutboundMessage->ContactState,
				//"ContactFirstName"=>$BlOutboundMessage->ContactFirstName,
				//"ContactLastName"=>$BlOutboundMessage->ContactLastName,
				//"ContactUserName"=>$BlOutboundMessage->ContactUserName,
				//"ContactGMToffset"=>$BlOutboundMessage->ContactGMToffset,
				"RunTime"=> $BlOutboundMessage->RunTime?gmdate("m/d/Y h:i:s a", $BlOutboundMessage->RunTime):null,
				"OutboundID"=>$BlOutboundMessage->ID,
				"CampaignID"=>$BlOutboundMessage->CampaignID,
				
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
			self::apiRequestTimeout($BlOutboundMessage);
			//throw new RNCPM\CPMException($ex->getMessage(), 500);
			
		}
	} // end sendSms()
    function getToken($BlOutboundMessage,$username,$password)
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
			self::apiRequestTimeout($BlOutboundMessage);
			//throw new RNCPM\CPMException($ex->getMessage(), 500);
		}
		
	}
	function getStatusReasonObj($type,$name)
	{
		$query = "select ".$type." from ".$type." where Name ='".$name."'";
		$result = RNCPHP\ROQL::queryObject($query)->next();
		return $result->next();
	}
	function apiRequestTimeout($BlOutboundMessage)
	{
		
		$BlOutboundMessage->StatusOrExceptionCode = 'ble1015';
		$BlOutboundMessage->StatusOrExceptionReason = 'Failed';
		$BlOutboundMessage->StatusOrExceptionDescription = 'API request timeout.';
		$BlOutboundMessage->save();
		
	}
	
	/*********End*************/
	
} // class incident_name
 
 
/*
The Test Harness
*/
 
 
class BLOutboundMessageCreate_TestHarness implements RNCPM\ObjectEventHandler_TestHarness
{
	static $co_invented = NULL;
	public static function setup()
	{
		try
        {
			$contact = RNCPHP\Contact::first();
            //echo "creating contact";
           	$BlOutboundMessage = new RNCPHP\BLDialogue\BLOutboundMessage();
            $BlOutboundMessage->ContactID = $contact;
			//$BlOutboundMessage->MessageSuffix = '';
			//$BlOutboundMessage->AgentID = $contact;
			//$BlOutboundMessage->MessageDirection = 'outbound';
			$BlOutboundMessage->ContactToNumber = '+918460841690';
			$BlOutboundMessage->AlphaNumeric = 0;
			//$BlOutboundMessage->RunTime = time();
			$BlOutboundMessage->MessageBody = 'Event Handler Auto Test';
			$BlOutboundMessage->MessageType = 'SMS';
			$BlOutboundMessage->OptOutOverride = 0;
			
           
        	$BlOutboundMessage->save();

            static::$co_invented = $BlOutboundMessage;
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
		//$obj = RNCPHP\BLDialogue\BLOutboundMessage::fetch(5173);
		//return $obj;
         return static::$co_invented?static::$co_invented: array();
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