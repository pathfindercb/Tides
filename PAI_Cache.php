<?php
// Class to cache data to a file until ttl expires
// Copyright © 2017 Pathfinder Associates, Inc.
// Author Christopher Barlow
// version 2.1
// updated 01/17/2017

abstract class PAI_Cache_Abstract {

	const version = "2.1";
	abstract function fetch($key);
	abstract function store($key,$data,$ttl);
	abstract function delete($key);

}
	
class PAI_Cache extends PAI_Cache_Abstract {
	public $fromCache = false;
	public $setCache = 0;
	public $endCache = 0;

	// This is the function you store information with
	function store($key,$data,$ttl) {

		// Opening the file in read/write append mode
		$h = fopen($this->getFileName($key),'a+');
		if (!$h) throw new Exception('Could not write to cache');

		flock($h,LOCK_EX); // exclusive lock, will get released when the file is closed

		fseek($h,0); // go to the start of the file

		// truncate the file
		ftruncate($h,0);

		// Serializing along with the TTL
		$this->fromCache = false;
		$this->setCache = time();
		$this->endCache = time()+$ttl;
		$data = serialize(array($this->endCache,$this->setCache,$data));

		if (fwrite($h,$data)===false) {
		  throw new Exception('Could not write to cache');
		}
		fclose($h);

	}

	 // The function to fetch data returns false on failure
	 function fetch($key) {

		$filename = $this->getFileName($key);
		  
		if (!file_exists($filename)) return false;
		$h = fopen($filename,'r');

		if (!$h) return false;

		// Getting a shared lock 
		flock($h,LOCK_SH);

		$data = file_get_contents($filename);
		fclose($h);

		$data = @unserialize($data);
		if (!$data) {

			// If unserializing somehow didn't work out, we'll delete the file
			unlink($filename);
			return false;

		}

		if (time() > $data[0]) {

			// Unlinking when the file was expired
			unlink($filename);
			return false;

		}
		$this->fromCache = true;
		$this->setCache = $data[1];
		$this->endCache = $data[0];
		return $data[2];
	}

	function delete( $key ) {

		$filename = $this->getFileName($key);
		if (file_exists($filename)) {
			return unlink($filename);
		} else {
			return false;
		}

	}

	private function getFileName($key) {

		return ini_get('session.save_path') . '/pai_cache' . md5($key);

	}

}

?>
