<?php
// Class to get tide data from Weather Underground
// Copyright © 2018 Pathfinder Associates, Inc.
// Author Christopher Barlow
// version 1.8
// updated 12/29/2017 added LogRun, cache time

// Include the required Class file
include('PAI_Cache.php');
	

abstract class PAI_Tide_Abstract {

	const version = "1.8";
	abstract function getTide($zip);

}
	
class PAI_Tide extends PAI_Tide_Abstract {

	
	function getTide ($zip = "02840",$flush=false) {
		include ('WundergroundKey.php');
		if (is_null($zip)) {
			$zip="02840";
		}
		// create cache file for this zipcode
		$cfile = "wtide" . $zip;
		$pcache = new PAI_Cache();
		if ($flush) {$pcache->delete($cfile);}
		$f = $pcache->fetch($cfile);
		if (!$f) {
			// Fetch failed so now retrieve from weather api
			// tide conditions
			$url = "http://api.wunderground.com/api/"; 
			$url = $url . $wkey;
			$url = $url . "/forecast/tide/q/" . $zip . ".json";
			$f = file_get_contents($url);
			// then store in cache
			$pcache->store($cfile,$f,60*60*12);	//cache for 12 hours **or adj to midnight?
		}
		// decode the json from cache or api
		$json = json_decode($f);
		$almanac['cache'] = $pcache->setCache;

		// find the time zone and units
		$zone = new DateTimeZone($json->tide->tideInfo[0]->tzname);
		$almanac['city'] = $json->tide->tideInfo[0]->tideSite;
		$almanac['unit'] = $json->tide->tideInfo[0]->units;

		// Iterate through each tide->tideSummary object in the array
		//	and build array of type, dts, desc, value
		foreach($json->tide->tideSummary as $tide) {
		// Create the DateTime object
			$dt = DateTime::createFromFormat('U', $tide->utcdate->epoch);
			// Must specifically set TZ, else UTC is used
			$dt->setTimeZone($zone);
			switch ($tide->data->type) {
			   case "High Tide" :
				$type = "tide";
				break;
			   case "Low Tide" :
				$type = "tide";
				break;
			   case "Sunrise" :
				$type = "sun";
				break;
			   case "Sunset" :
				$type = "sun";
				break;
			   case "Moonrise" :
				$type = "sun";
				break;
			   case "Moonset" :
				$type = "sun";
				break;
			   case "First Quarter" :
				$type = "sun";
				break;
			   case "Last Quarter" :
				$type = "sun";
				break;
			   case "New Moon" :
				$type = "sun";
				break;
			   case "Half Moon" :
				$type = "sun";
				break;
			   case "Full Moon" :
				$type = "sun";
				break;
			}
			// Build array
			$almanac[$type][] = array($dt->format('D H:i'),$tide->data->type,$tide->data->height );
		}
		
		// now do forecast
		foreach($json->forecast->txt_forecast->forecastday as $fcst) {
			// Build array
			$almanac["fcst"][] = array($fcst->title,$fcst->fcttext);
		}
		$this->LogRun ($f);
		return $almanac;

	}
	function LogRun($f)
	{
		//now email
		$ip = $_SERVER['REMOTE_ADDR'] ;
		$to      = 'barlowcr@gmail.com';
		$subject = 'Tides run';
		$message = $ip . "=" . $f;
		$headers = 'From: cbarlow@pathfinderassociatesinc.com' . "\r\n" .
			'Reply-To: cbarlow@pathfinderassociatesinc.com' . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
		error_reporting(0);
		try {
			if (!mail($to, $subject, $message, $headers)) {
				throw new Exception('Mail failed');
			}
		} catch (Exception $e) {
			$checkmsg = 'Email failed: ' . $e->getMessage();
			//error_log($checkmsg);
		}
		error_reporting(E_ALL);
		return ;
	}

}
?>