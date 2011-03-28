<?php
class ServerListModel extends NObject{

	public $servers = array();
	public $url = 'http://www.dplogin.com/serverlist.php';
	
	public function servers(){
		
		$lines = file($this->url);
		$count = count($lines);
		// delete last 6 lines
		unset($lines[$count]);
		unset($lines[$count-1]);
		unset($lines[$count-2]);
		unset($lines[$count-3]);
		unset($lines[$count-4]);
		unset($lines[$count-5]);
		// ---------------------
		foreach ($lines as $server) {
			$this->servers[] = trim($server);
		}
		
		/*
		foreach($servers as $num=>$server){
			list($ip,$port) = explode(":",$server);
			echo "<script>load_info('$ip','$port');</script>";
		}
		
		/* use $servers array */		
	}
	
}