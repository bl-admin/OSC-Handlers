<? 
/*
* CPMObjectEventHandler: BL_WeChatImg_StoreCreate
* Package: OracleServiceCloud
* Objects: WeChatImage\BL_WeChatImg_Store
* Actions: Create
* Version: 1.2
*/
 
// This object procedure binds to v1_2 of the Connect PHP API
use RightNow\Connect\v1_2 as RNCPHP;
 
// This object procedure binds to the v1 interface of the process
// designer
use \RightNow\CPM\v1 as RNCPM;
/*
* Attach image with incident 
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
 
class BL_WeChatImg_StoreCreate implements RNCPM\ObjectEventHandler
{
	public static function apply( $run_mode, $action, $obj, $n_cycles )
	{
		
		if(RNCPM\ActionCreate == $action)
		{
            try
            {
                $incidentId = self::getIncidentId();
                self::attachImageToIncident($incidentId,$obj);
                //$obj->destroy();
                // delete $obj
            }
            catch(\Exception $ex)
            {
                throw new RNCPM\CPMException($ex->getMessage(), 1, 0);
            }
			
			
			
		}
			
	} // apply()
	/*
    * fecthes IncidentID from Report
    */
    public static function getIncidentId()
    {
		
        $filters = new RNCPHP\AnalyticsReportSearchFilterArray;;
		// $filter= new RNCPHP\AnalyticsReportSearchFilter;
        // $filter->Name = trim("Session ID");
        // $filter->Values = array( $obj->SessionID );
        // $filters[] = $filter;
		$report = RNCPHP\AnalyticsReport::fetch(202772);
		$result = $report->run($report->run(0, $filters)->count()-1,$filters,1);
		$resultArr = $result->next();
		
		
		return $resultArr['Incident ID'];
    }

    public static function attachImageToIncident($incidentId,$obj)
    {
		
		try
		{
			$incident = RNCPHP\Incident::fetch($incidentId);
			
			if(count($incident->FileAttachments)==0)
			$incident->FileAttachments = new RNCPHP\FileAttachmentIncidentArray();
			$imageInfo = pathinfo($obj->AWS_Link);
			$fattach = new RNCPHP\FileAttachmentIncident();
		// Initialize session and set URL.
		load_curl();
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $obj->AWS_Link);

		// Set so curl_exec returns the result instead of outputting it.
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		// Get the response and close the channel.
		$imageData = curl_exec($ch);
		curl_close($ch);

			
		
			
			
			$extension = explode('?',$imageInfo['extension']);
			$ext = $extension[0];
			$fattach->ContentType = "image/".$ext;

			$fp = $fattach->makeFile();



			fwrite($fp,$imageData);
			fclose($fp);
			
			$fattach->FileName = $imageInfo['filename'].'.'.$ext;
			$fattach->Name = $imageInfo['filename'].'.'.$ext;
			$incident->FileAttachments[] = $fattach;
			
			$incident->save(RNCPHP\RNObject::SuppressAll);
		
		
		}
		catch(\Exception $ex)
            {
                echo $ex->getTraceAsString();
            }
        

    }

	
	/*********End*************/
	
} // class BL_WeChatImg_StoreCreate
 
 
/*
The Test Harness
*/
 
 
class BL_WeChatImg_StoreCreate_TestHarness implements RNCPM\ObjectEventHandler_TestHarness
{
	static $inc_invented = NULL;
 
	public static function setup()
	{
	// For this test, create a new
	// inc as expected.	
		return;
	}
 
	public static function fetchObject( $action, $object_type )
	{
	// Return the object that we
	// want to test with.
	// You could also return an array of objects
	// to test more than one variation of an object.
	
		 $obj = RNCPHP\WeChatImage\BL_WeChatImg_Store::fetch(11);
		return $obj;
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
		return;
	}		
}