<?php
class DPToolModel extends NObject{
	
	const SERVER_INFO = 'sv_info';
	const TECHNICAL_INFO = 'tc_info';
	
	// digital paintball server
	public $dps;
	// server & technical informations
	public $server = array();
	// server ip
	public $ip;
	// server port
	public $port = 0;
	
	

	public function run($ip,$port){
		
		if(is_numeric($port) && $this->validIp($ip)){
		
		try{
			$this->dps = new DigitalPaintServer($ip,$port);
			// set ip & port
			$this->ip = $ip;
			$this->port = $port;
						
			// is online?
			if($this->dps->is_online()){
				$this->setServerInfo();
				$this->setTechnicalInfo();
			}else{
				throw new Exception('Server is not active.');			
			}
			
		}catch(Exception $e){
			throw new Exception('Script error: ' . $e->getMessage());
		}	
		

		}else{
			throw new Exception('Wrong server IP or server PORT!');
		}
	}
	
	public function setServerInfo(){

		// server name
		$this->setServerName($this->dps->get_server_name());
		// server ip:port
		$this->setServerIp($this->ip .':'. $this->port);
		// server status (online,closed)
		//$this->setServerStatus($this->dps->is_online() ? 'yes' : 'no');
		// server ping
		//$this->setServerPing($this->dps->ping());
		// server pw
		$this->setServerHasPw($this->dps->status_cache['needpass'] ? 'yes' : 'no');
		// server map
		$this->setServerMap($this->dps->get_map());
		// server timeleft	
		$this->setServerTimeleft($this->dps->status_cache['TimeLeft']);
		// server players
		$this->setServerPlayers($this->dps->get_players());
		// server score
		$this->setServerScore($this->dps->get_scores());
		
	}
	
	public function getServerInfo(){
		return $this->server[self::SERVER_INFO];
	}
	
	/* SERVER_INFO SETTERS */
	
	public function setServerName($name){
		$this->server[self::SERVER_INFO]['name'] = $name;		
	}
	
	public function getServerName(){
		return $this->server[self::SERVER_INFO]['name'];
	}

	public function setServerIp($ip){
		$this->server[self::SERVER_INFO]['ip'] = $ip;		
	}
	
	public function getServerIp(){
		return $this->server[self::SERVER_INFO]['ip'];
	}

	public function setServerStatus($status){
		$this->server[self::SERVER_INFO]['status'] = $status;		
	}
	
	public function getServerStatus(){
		return $this->server[self::SERVER_INFO]['status'];
	}

	public function setServerPing($ping){
		$this->server[self::SERVER_INFO]['ping'] = (int) $ping;		
	}
	
	public function getServerPing(){
		return $this->server[self::SERVER_INFO]['ping'];
	}

	public function setServerHasPw($pw){
		$this->server[self::SERVER_INFO]['pw'] = $pw;		
	}
	
	public function getServerHasPw(){
		return $this->server[self::SERVER_INFO]['pw'];
	}

	public function setServerMap($map){
		$this->server[self::SERVER_INFO]['map'] = $map;		
	}
	
	public function getServerMap(){
		return $this->server[self::SERVER_INFO]['map'];
	}

	public function setServerTimeleft($timeleft){
		$this->server[self::SERVER_INFO]['timeleft'] = $timeleft;		
	}
	
	public function getServerTimeleft(){
		return $this->server[self::SERVER_INFO]['timeleft'];
	}
	
	public function setServerPlayers($players){
		$teams = Array('red'=>0,'blue'=>0,'purple'=>0,'yellow'=>0,'observer'=>0);	
		if(count($players) > 0){
			foreach($players as $player){
				$this->server[self::SERVER_INFO]['players'][]="<span class=\"color_".$player['team']."\" title=\"Ping: ".$player['ping']."ms; Team: ".$player['team']."; Kills: ".$player['score']."\" alt=\"".$this->dps->clean_funname($player['name'])."\">".$this->dps->clean_funname($player['name'])."[<b>".$player['score']."</b>]</span>";	
				switch($player['team']){
					case "red": $teams['red']++;break;	
					case "blue": $teams['blue']++;break;	
					case "purple": $teams['purple']++;break;	
					case "yellow": $teams['yellow']++;break;	
					case "observer": $teams['observer']++;break;	
				}
			}
			
			$this->server[self::SERVER_INFO]['teams'] = $teams;			
		}else{
			$this->server[self::SERVER_INFO]['players']= 'nobody';	
			$this->server[self::SERVER_INFO]['teams'] = $teams;			
		}
		$this->setServerTeams();
	}

	public function getServerPlayers(){
		return $this->server[self::SERVER_INFO]['players'];
	}
	
	public function setServerTeams(){
		$teams = null;
		foreach($this->server[self::SERVER_INFO]['teams'] as $color => $players){
			if($players>0){
				$teams[] = "<span class=\"color_".$color."\">".ucfirst($color).": <b>".$players."</b> ".($players>1 ? 'players' : 'player')."</span><br>";
			}
		}	
		$this->server[self::SERVER_INFO]['teams'] = (is_null($teams) ? 'no teams' : $teams);
	}

	public function getServerTeams(){
		return $this->server[self::SERVER_INFO]['teams'];
	}

	public function setServerScore($sc){
		foreach($sc as $color => $points){
			$scores[] = "<span class=\"color_".$color ."\">".ucfirst($color ).": <b>".$points."pt</b></span><br>";	
		}
			
		$this->server[self::SERVER_INFO]['score'] = (count($scores)==0 ? 'null' : $scores);	
	}

	public function getServerScore(){
		return $this->server[self::SERVER_INFO]['score'];
	}
	
	/* ----------------------------------------------------------------------------- */

	public function setTechnicalInfo(){
	
		/* 
		List technical informations 
		*/
		$sets = $this->dps->status_cache;
		
		$info['build'] = $this->dps->get_build();
		$info['sv_certificated'] = @$sets['sv_certificated'];
		$info['sv_login'] = $sets['sv_login'];
		$info['gamename'] = $sets['gamename'];
		$info['gameversion'] = $sets['gameversion'];
		$info['gamedate'] = $sets['gamedate'];
		$info['sv_login'] = $sets['sv_login'];
		$info['elim'] = $sets['elim'];
		$info['location'] = $sets['location'];
		$info['e-mail'] = $sets['e-mail'];
		$info['admin'] = $sets['admin'];
		$info['website'] = $sets['website'];
		$info['hostname'] = $sets['hostname'];
		$info['maxclients'] = $sets['maxclients'];
		$info['protocol'] = $sets['protocol'];
		$info['timelimit'] = $sets['timelimit'];	
		$info['fraglimit'] = $sets['fraglimit'];
		$info['version'] = $sets['version'];
		$info['gamedir'] = $sets['gamedir'];
		$info['game'] = $sets['game'];
		
		$this->server[self::TECHNICAL_INFO] = $info;
	}
	
	public function getTechnicalInfo(){
		return $this->server[self::TECHNICAL_INFO];
	}
	
	public function validIp($ip){
		
	  //first of all the format of the ip address is matched
	  if(preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/",$ip))
	  {
		//now all the intger values are separated
		$parts=explode(".",$ip);
		//now we need to check each part can range from 0-255
		foreach($parts as $ip_parts)
		{
		  if(intval($ip_parts)>255 || intval($ip_parts)<0)
		  return false; //if number is not within range of 0-255
		}
		return true;
	  }
	  else
		return false; //if format of ip address doesn't matches
	}

}