<? 
/*
* CPMObjectEventHandler: BLInboundMessageCreate
* Package: RN
* Objects: BLDialogue\BLInboundMessage
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
 
class BLInboundMessageCreate implements RNCPM\ObjectEventHandler
{
	
	public static function apply( $run_mode, $action, $obj, $n_cycles )
	{
		
		if(RNCPM\ActionCreate == $action)
		{
			
			try
			{
				if($obj->ContactID)
                {
                    if($obj->OptOutIndicator)
                    {
                        $obj->ContactID->CustomFields->BLDialogue->sms_opt_out = 1;
                        $obj->ContactID->save();
                    }
                    else
                    {
                        if($obj->ContactID->CustomFields->BLDialogue->sms_opt_out)
                        {
                            $obj->ContactID->CustomFields->BLDialogue->sms_opt_out = 0;
                            $obj->ContactID->save();
                        }
                    }
                }
				if($obj->ConversationSID)
				{
					$BLOutboundMessage = RNCPHP\ROQL::queryObject( "SELECT BLDialogue.BLOutboundMessage from BLDialogue.BLOutboundMessage where ConversationSID='".$obj->ConversationSID."'")->next()->next();
					if($BLOutboundMessage)
					{
						if($BLOutboundMessage->CampaignID)
						$obj->CampaignID = $BLOutboundMessage->CampaignID;
						$obj->OutboundID = $BLOutboundMessage;
						$obj->save();
						return;
					}
				}
				if($obj->ContactID && $obj->FromNumber)
				{
					$contactID = $obj->ContactID->ID;
					$BLOutboundMessage = RNCPHP\ROQL::queryObject( "SELECT BLDialogue.BLOutboundMessage from BLDialogue.BLOutboundMessage where ContactID=".$contactID." and ContactToNumber='".$obj->FromNumber."'")->next()->next();
					
					if($BLOutboundMessage)
					{
						if($BLOutboundMessage->CampaignID)
						$obj->CampaignID = $BLOutboundMessage->CampaignID;
						$obj->OutboundID = $BLOutboundMessage;
						$obj->save();
						return;
					}
				}

				
			}
			catch(\Exception $ex)
			{
				
				echo $ex->getMessage();
				throw new RNCPM\CPMException($ex->getMessage(), 500);
			}
			
			
			
		}
			
	} // apply()

	
	
	/*********End*************/
	
} // class incident_name
 
 
/*
The Test Harness
*/
 
 
class BLInboundMessageCreate_TestHarness implements RNCPM\ObjectEventHandler_TestHarness
{
	static $co_invented = NULL;
 
	public static function setup()
	{
		try
        {
				$query = "select Contact from Contact";
		$result = RNCPHP\ROQL::queryObject($query)->next();
		$contact = $result->next();
				//$contact = RNCPHP\Contact::first();
				//echo "creating contact";
				$BlInboundMessage = new RNCPHP\BLDialogue\BLInboundMessage();
				$BlInboundMessage->ReceiveTime = time();
				$BlInboundMessage->ContactID = $contact;
				$BlInboundMessage->MessageBody = 'Event Handler Test Case Sample message';
				$BlInboundMessage->MessageType = 'Message Campaign';
				$BlInboundMessage->FromNumber = 'No Number';
				$BlInboundMessage->OptOutIndicator = 0;
				
				$BlInboundMessage->save();

				static::$co_invented = $BlInboundMessage;
			
			
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
		//$obj = RNCPHP\BLDialogue\BLInboundMessage::fetch(1);
		//return $obj;
		//return array();
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