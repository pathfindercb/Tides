<?php
// Class to get tide data from DB
// Copyright © 2019 Pathfinder Associates, Inc.
// Author Christopher Barlow
// version 2.0
// updated 12/29/2017 added LogRun, cache time
// updated 07/19/2018 added zip to LogRun subject
// updated 04/28/2019 to use DB not weather.com

// Include the required Class file
include('PAI_Cache.php');
	

abstract class PAI_Tide_Abstract {

	const version = "2.1";
	abstract function getTide($zip);

}
	
class PAI_Tide extends PAI_Tide_Abstract {

//	const wkey = "d3473bad545feca5";	//your weather api key
	private $pdo;

	public function __construct ()
	{
		ini_set('max_execution_time', 300); //300 seconds = 5 minutes
		date_default_timezone_set('America/New_York');
		
		//open database so the pdo object is available to all functions
		if (! $this->opendb()) {
			return false;
		}
		
	}
	
	public function __destruct()
    {
        // close db 
		unset($this->pdo);
    }

	function getTide ($zip = "34236",$flush=false) {
		$now = time(); 
		if (is_null($zip)) {
			$zip="34236";
		}
		// create cache file for this zipcode
		$cfile = "wtide" . $zip;
		$pcache = new PAI_Cache();
		// if supposed to flush file then flush
		if ($flush) {$pcache->delete($cfile);}
		// read cache file
		$f = $pcache->fetch($cfile);
		// check if cache is empty
		if (!$f) {
			// Fetch failed so now retrieve tide, sunmoon, weather data
			$pcache->setCache = time();
			// first get port data
			$sql = "SELECT port, zip, lat, lng, stationid, zoneid, tzname FROM `ports` where zip = " . $zip ;
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
			$p = $stmt->fetch(PDO::FETCH_ASSOC);
			// check if failed
			if (!$p) {return;}
			
			// tide 
			$sql = "SELECT twhen, what, feet FROM `tides` where stationid = " . $p['stationid'] . " AND twhen > " . strtotime('today midnight') . " ORDER BY twhen LIMIT 20";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
			$t = $stmt->fetchALL(PDO::FETCH_ASSOC);

			//sunmoon
			$sql = "SELECT swhen, what FROM `sunmoon` where zip = " . $zip . " AND swhen > " . strtotime('today midnight'). " ORDER BY swhen LIMIT 20";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
			$s = $stmt->fetchALL(PDO::FETCH_ASSOC);
			// adjust for Eastern time since tide data already allows for daylight time
			// find the time zone and units
			$zone = new DateTimeZone($p['tzname']);
			foreach ($s as $rec){
				// Create the DateTime object
				$dt = DateTime::createFromFormat('U', $rec['swhen']);
				// Must specifically set TZ, else UTC is used
				$dt->setTimeZone($zone);
				$rec['swhen'] = $dt->format('D H:i');

			}
			
			//weather
			$url = "https://api.weather.gov/zones/marine/"; 
			$url = $url . $p['zoneid'];
			$url = $url . "/forecast";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // seconds
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('User-Agent: paitides'));
            $xmlString = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // make sure we got a valid response
            if ($httpCode != 200) {
                throw new \Exception('Received invalid HTTP response code: ' . $httpCode);
            }
            $xml = json_decode($xmlString);
			foreach ($xml->periods as $per ){
				$w[] = array("name"=>$per->name,"detailedForecast"=>$per->detailedForecast);
			}			
			
			//then build json
			$f = array ('Response' => array ( 
				'Title' => 'Tides', 
				'Version' => self::version, 
				'Created' => date('Y-m-d'),
				'Copyright' => 'Copyright 2019 Pathfinder Associates, Inc.',
				'Author' => 'Chris Barlow',
				'Cached' =>  gmdate('Y-m-d h:i:s A',$pcache->setCache),
				'Cache' =>  $pcache->setCache,
				'Port' => $p,
				'Tide' => $t,
				'Sun' => $s,
				'Fcst' => $w
			));
			
			// then store in cache
			$pcache->store($cfile,$f,60*60*12);	//cache for 12 hours **or adj to midnight?
		}
		// decode the json from cache or api
		$json = json_encode($f);
		$this->LogRun ($json,$zip);
		return $json;

	}
	function LogRun($f,$z)
	{
		//now email
		$ip = $_SERVER['REMOTE_ADDR'] ;
		$to      = 'barlowcr@gmail.com';
		$subject = 'Tides run ' . $z;
		$message = $ip . "=" . $f;
		$headers = 'From: cbarlow@pathfinderassociatesinc.com' . "\r\n" .
			'Reply-To: cbarlow@pathfinderassociatesinc.com' . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
		mail($to, $subject, $message, $headers);
		
		//error_log($sql,1,$to);
		
		return ;
	}

	function opendb() {
		//function to open PDO database and return PDO object
		if (isset($this->pdo)) {return true;}
		
		// first include file containing host, db, user, password so not in www folder
		if (file_exists("DBfolder.php")) {include ("DBfolder.php");}
		if (!isset($pfolder)) {$pfolder="";}
		require ($pfolder . 'DBconnect.php');
		$charset = 'utf8';
		$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
		$opt = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		];
		try {
			$this->pdo = new PDO($dsn, $user, $pass, $opt);
		} catch (PDOException $e) {
			$checkmsg = 'Connection failed: ' . $e->getMessage();
			error_log($checkmsg);
			return false;
		}
		return true;
}

}
?>