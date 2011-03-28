<?php
    /**
    * libdpserver v3.0
	* Copyright 2006-2008 sk89q
    * Written by sk89q
	* 
	* This program is free software; you can redistribute it and/or
	* modify it under the terms of the GNU General Public License
	* as published by the Free Software Foundation; either version 2
	* of the License, or (at your option) any later version.
	* 
	* This program is distributed in the hope that it will be useful,
	* but WITHOUT ANY WARRANTY; without even the implied warranty of
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	* GNU General Public License for more details.
	* 
	* You should have received a copy of the GNU General Public License
	* along with this program; if not, write to the Free Software
	* Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
    */
    
    class DPSException extends Exception {}
    class DPSServerListUnaccessibleException extends DPSException {}
    class DPSRCONPasswordUnsetException extends DPSException {}
    class DPSBadRCONPasswordException extends DPSException {}
    class DPSConnectionException extends DPSException {}
    class DPSTimeoutException extends DPSConnectionException {}
    class DPSPortUnreachable extends DPSConnectionException {}
    
    define("CHAR_ENDFORMAT", 133);
    define("CHAR_UNDERLINE", 134);
    define("CHAR_ITALICS", 135);
    define("CHAR_COLOR", 136);
    define("TEAM_RED", "red");
    define("TEAM_BLUE", "blue");
    define("TEAM_YELLOW", "yellow");
    define("TEAM_PURPLE", "purple");
    define("TEAM_OBSERVER", "observer");
    
    /**
     * <code>libdpserver</code> is an abstraction layer for
     * Digital Paint.
     *
     * @version 3.0
     * @author sk89q
     * @copyright Copyright (c) 2006-2008, sk89q
     */
    class DigitalPaintServer
    {
        /**
         * Server list URL to use.
         */
        private $server_list_url = "http://dplogin.com/serverlist.php";
        
        private $server_host = "127.0.0.1";
        private $server_port = 27910;
        private $rcon_connect_password;
        private $conn;
        private $udp_timeout = 3;
        
        public $status_cache = array();
        public $players_cache = array();
        public $server_list_cache = array();
        
        private $ping = 0;
        
        /**
         * Constructs the object.
         * @param string $server_host Server IP host
         * @param int $server_port Server port
         * @param string $rcon_connect_password RCON connect password
         * @param int $udp_timeout Timeout for UDP messages in seconds
         */
        public function DigitalPaintServer($server_host, $server_port = 27910, $rcon_connect_password = "", $udp_timeout = 3)
        {
            $this->server_host = $server_host;
            $this->server_port = $server_port;
            $this->rcon_connect_password = $rcon_connect_password;
            
            // "Open" a connection to the server through UDP and store
            // it in the library's instance
            $this->conn = fsockopen("udp://$server_host", $server_port, $errno, $errstr, 5);
            // UDP is connectionless, so we need to set this timeout
            // especially... defaults at three seconds
            stream_set_timeout($this->conn, $udp_timeout);
            $this->udp_timeout = $udp_timeout;
        }
        
        /**
         * Gets time in microseconds, function for PHP4.
         * @access private
         */
        private function microtime()
        {
            list($usec, $sec) = explode(" ", microtime());
            return ((float)$usec + (float)$sec);
        }
        
        /**
         * Sets the timeout for UDP requests.
         * @param int $udp_timeout Number of seconds to timeout
         */
        public function set_udp_timeout($udp_timeout)
        {
            stream_set_timeout($this->conn, $udp_timeout);
            $this->udp_timeout = $udp_timeout;
        }
        
        /**
         * Sets the RCON connect password for the library to access
         * the server, NOT the RCON password of the server.
         * @param int $rcon_connect_password RCON password
         */
        public function set_rcon_connect_password($rcon_connect_password)
        {
            $this->rcon_connect_password = $rcon_connect_password;
        }
        
        /**
         * Writes raw UDP data.
         * @access private
         * @param string $data Data to send
         */
        protected function send($data)
        {
            fwrite($this->conn, $data);
        }
        
        /**
         * Writes control packet.
         * @access private
         * @param string $data Data to send
         */
        protected function send_ctrl($data)
        {
            $this->send("\xFF\xFF\xFF\xFF$data\0");
        }
        
        /**
         * Gets the reply from the server.
         * @access private
         * @param int $length Length to read
         * @throws DPSPortUnreachable When the port is unreachable
         * @throws DPSTimeoutException When the operation times out
         * @return string Server reply
         */
        protected function get($length = 4096)
        {
            // Count time to detect a timeout
            $start = $this->microtime();
            $result = fread($this->conn, $length);
            $time = $this->microtime()-$start;
            $this->ping = $time;
            
            if($result === false)
            {
                throw new DPSPortUnreachable();
            }
            else if($time <= $this->udp_timeout)
            {
                return $result;
            }
            else
            {
                throw new DPSTimeoutException();
            }
        }
        
        /**
         * Gets the control reply from the server.
         * @access private
         * @param int $length Length to read
         * @return string Server reply
         */
        protected function get_ctrl($length = 4096)
        {
            return preg_replace("`^\xFF\xFF\xFF\xFFprint\n`", "", $this->get($length));
        }
        
        /**
         * Sends an RCON command and then returns it.
         * Will trigger error if no/bad RCON password was configured.
         * @param string $data Data to send
         * @throws DPSRCONPasswordUnsetException When the RCON password has not been provided
         * @throws DPSBadRCONPasswordException When a bad RCON password is used
         * @return mixed Server reply, string if port unreachable/valid reply, FALSE if bad password/no password
         */
        public function rcon($data)
        {
            if(!$this->rcon_connect_password)
            {
                throw new DPSRCONPasswordUnsetException();
            }
            
            $this->send_ctrl("rcon \"{$this->rcon_connect_password}\" $data");
            $result = $this->get_ctrl();
            
            if(trim($result) == "Bad rcon_password.")
            {
                throw new DPSBadRCONPasswordException();
            }
            
            return $result;
        }
        
        /**
         * Cleans a funname: removes coloring and changes characters to ASCII versions.
         * Written by jitspoe. Cleaner than the version I wrote =]
         * See http://dplogin.com/forums/index.php?topic=7579.msg82021#msg82021
         * @static
         * @param string $text Text to clean
         * @param bool $remove_formatting Remove color/italic/underline formattings
         * @return string Cleaned string
         */
        public function clean_funname($text, $remove_formatting = true)
        {
            $char_remap = "\0---_*t.N-\n#.>**[]@@@@@@<>.-*--- !\"#\$%&'()*+,-./".
                          "0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_".
                          "`abcdefghijklmnopqrstuvwxyz{|}~<(=)^!OUICCR#?>**".
                          "[]@@@@@@<>*X*--- !\"#\$%&'()*+,-./0123456789:;<=>?".
                          "@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`ABCDEFGHIJKLMNO".
                          "PQRSTUVWXYZ{|}~<";

            $outstr = "";
            $len = strlen($text);
            
            for($i = 0; $i < $len; $i++)
            {
                $c = ord($text[$i]);
                switch($c)
                {
                    case CHAR_COLOR:
                        if(!$remove_formatting)
                            $outstr .= $text[$i].$text[$i+1];
                        $i++;
                        break;
                    case CHAR_ITALICS:
                        if(!$remove_formatting)
                            $outstr .= $text[$i];
                    case CHAR_UNDERLINE:
                        if(!$remove_formatting)
                            $outstr .= $text[$i];
                    case CHAR_ENDFORMAT:
                        if(!$remove_formatting)
                            $outstr .= $text[$i];
                        break;
                    default:
                        $outstr .= $char_remap[$c];
                }
            }
            
            return $outstr;
        }
        
        /**
         * Converts a funname to HTML with coloring and formatting intact. 
         * Non-standard characters are converted to ASCII. Escapes HTML characters
         * and strips invalid color codes.
         * @static
         * @param string $text Text to convert
         * @param string $color Format with colors
         * @param string $italics Format with italics
         * @param string $underline Format with underlines
         * @return string Converted string
         */
        public function funname_to_html($text, $color = true, $italics = true, $underline = true)
        {
            // Change to ASCII first
            $text = DigitalPaintServer::clean_funname($text, false);
            
            // Have to fix XSS attacks by escaping HTML characters
            $text = str_replace("&", "&amp;", $text);
            $text = str_replace("<", "&lt;", $text);
            $text = str_replace(">", "&gt;", $text);
            $text = str_replace('"', "&quot;", $text);
            $text = str_replace("'", "&#39;", $text);
            
            // But now we need to undo it for color codes
            $text = str_replace(chr(CHAR_COLOR)."&lt;", chr(CHAR_COLOR)."<", $text);
            $text = str_replace(chr(CHAR_COLOR)."&gt;", chr(CHAR_COLOR).">", $text);
            $text = str_replace(chr(CHAR_COLOR)."&quot;", chr(CHAR_COLOR).'"', $text);
            $text = str_replace(chr(CHAR_COLOR)."&#39;", chr(CHAR_COLOR)."'", $text);
            $text = str_replace(chr(CHAR_COLOR)."&amp;", chr(CHAR_COLOR)."&", $text);
            
            $outstr = "";
            $end_tags = "";
            $len = strlen($text);
            
            for($i = 0; $i < $len; $i++)
            {
                $c = ord($text[$i]);
                switch($c)
                {
                    case CHAR_COLOR:
                        if($color)
                        {
                            $color = DigitalPaintServer::get_funname_color($text[$i+1]);
                            if($color)
                            {
                                $outstr .= '<span style="color: #'.$color.'">';
                                $end_tags .= '</span>';
                            }
                        }
                        $i++;
                        break;
                    case CHAR_ITALICS:
                        if($italics)
                        {
                            $outstr .= '<em>';
                            $end_tags .= '</em>';
                        }
                        break;
                    case CHAR_UNDERLINE:
                        if($underline)
                        {
                            $outstr .= '<span style="text-decoration: underline">';
                            $end_tags .= '</span>';
                        }
                        break;
                    case CHAR_ENDFORMAT:
                        break;
                    default:
                        $outstr .= chr($c);
                }
            }
            
            return $outstr.$end_tags;
        }
        
        /**
         * Converts a funname color to 6-digit hex without #.
         * @access private
         * @param string $code Color code
         * @return string Hex color
         */
        private function get_funname_color($code)
        {
            $colors = array('663A4D', 'AA7356', '', 'E2B171', 'CF8254', 'AC6245', 
                            '8E4A33', 'C1C126', '9C9C17', '588B7F', 'B99736', '8E6F22', 
                            '7D5A2A', 'C08FFF', '9453EA', '6E21D3', '242424', '343434', 
                            '4C4C4C', '656565', '818181', '9A9A9B', 'B7B7B6', 'D4D4D4', 
                            'EEEDED', 'FFFFFF', '2E344B', '', 'A8B8C6', '061E5B', '590806', 
                            'FFFFB4', 'F87A63', 'FD1B05', 'FD5C05', 'FD8505', 'FDBC05', 
                            'FAEF05', 'D2FD05', '9DFD05', '67FD05', '31FD05', '08FD11', 
                            '05FD45', '05FD7B', '05FDB0', '05FCE6', '05DEFD', '05A8FD', 
                            '0572FD', '053CFD', '0B0DFD', '3A05FD', '', '7005FD', 
                            'A505FD', 'DB05FD', 'FC05E8', 'FD05B3', '', '98B4AA', '', 
                            '2B6B5B', '035245', 'FE4321', '77302A', '77452A', '77522A', 
                            '77632A', '77732A', '6A772A', '5A772A', '48772A', '38772A', 
                            '2B772D', '2A773E', '2A774E', '2A7760', '2A7770', '2A6E77', 
                            '2A5D77', '2A4C77', '2A3B77', '2B2B77', '3A2A77', '4B2A77', 
                            '5C2A77', '6D2A77', '772A71', '772A61', '772A4F', 'FFFD95', 
                            'F89FE8', 'FEA791', '96CBFD', 'F0CAB0', 'B49C85', 'C3C383', 
                            '95BC55', '00DF93', '00B8D7', '8188A3', 'DA24F7', 'FF4BA0', 
                            '', 'FFB104', '7CFF78', '557DFE', 'FEFF62', 'E06FC8', 'F66E5C', 
                            '69ADFC', 'DCB08D', '987A5C', 'ABAA60', '7AA433', '00BB79', 
                            '0099B4', '6C7390', 'C100E2', 'ED1179', 'FF9000', '2FFF38', 
                            '2A4CFD', 'EADB1D', 'C250A4', 'E75643', '4590FA', 'B47D60', 
                            '7E5E3C', '847E38', '537F0F', '009661', '007D91', '535B79', 
                            'A100C1', 'C3005B', 'F96800', '00E005', '0000FF', 'D1B813', 
                            '993D80', 'C42821', '2972F9', '926043', '704C28', '656028', 
                            '385F0C', '007147', '005C6C', '424A69', '7A0098', '89003E', 
                            'C1440C', '009124', '00007E', 'AE8113', '6C2758', '841C18', 
                            '2653DE', '6C4F2A', '5C3E1E', '484D1D', '244A0F', '005534', 
                            '00414A', '2F3756', '4C0065', '580023', '7A2A06', '016219', 
                            '00004A', '9B6C00', '521D46', '5A1715', '1A318B', 'E75A00', 
                            'F26C00', 'F78100', 'FC9B00', 'FEB200', 'FFC900', 'FFDE13', 
                            '', 'FFEC1D', 'FFF929', 'FFFF3E', 'FFFF7C', 'FFFFB2', 'FFFFDB', 
                            'FFFFF7', '14235F', 'B57170', 'B59070', 'B5AB70', 'A6B570', 
                            '8BB570', '70B570', '70B57C', '70B59A', '6CB1AE', '709EB5', 
                            '7080B5', '7070B5', '8670B5', 'A370B5', 'B46EAD', 'B57094', 
                            '663A3A', '664B3A', '665D3A', '5A663A', '46663A', '3A663A', 
                            '3A663D', '3A6651', '386261', '3A5366', '3A4066', '3A3A66', 
                            '443A66', '583A66', '643A5F');
            
            return $colors[ord($code)-32];
        }
        
        /**
         * Tests if the server is online/reachable.
         * @return mixed True if online, false if timeout/unreachable, null if it is not a Digital Paint server
         */
        public function is_online()
        {
            $this->send_ctrl("status");
            
            try
            {
                $result = $this->get_ctrl();
            }
            catch(DPSConnectionException $e)
            {
                return false;
            }
            
            // Test if it's a DP server
            if(strpos($result, "DPPB2") || strpos($result, "Digital Paint"))
            {
                return true;
            }
            else
            {
                return null;
            }
        }
        
        /**
         * Tests if the server is on the server list.
         * @param bool $force_update Force redownload of the server list
         * @return bool True if it is
         */
        public function is_on_server_list($force_update = false)
        {
            if(!$this->server_list_cache || $force_update)
            {
                if(!$this->server_list_cache = @file_get_contents($this->server_list_url))
                {
                    throw new DPSServerListUnaccessibleException();
                }
            }
            
            return strpos($this->server_list_cache, "{$this->server_host}:{$this->server_port}") !== false;
        }
        
        /**
         * Pings a server and returns its ping in ms,
         * @return mixed Ping as an integer in ms, false if unreachable/timeout
         */
        public function ping()
        {
            // Get the connection challenge first
            $this->send_ctrl("ping");
            
            try
            {            
                $result = $this->get();
            }
            catch(DPSConnectionException $e)
            {
                return false;
            }
            
            return true;
        }
        
        /**
         * Checks if the server has a password.
         * No RCON required.
         * @return bool Has a password set
         */
        public function has_password_set()
        {
            // Get the connection challenge first
            $this->send_ctrl("getchallenge");
            $challenge = $this->get_ctrl()."\n";
            $challenge = explode(" ", $challenge);
            $challenge = trim($challenge[1]);
            
            // Request connection, but with an "old" build
            $this->send_ctrl('connect 34 '.rand(1025, 50000).' '.$challenge.' "\build\-999\name\libdpserver PW test"');
            $result = $this->get_ctrl();
            
            // Bad password reply?
            if(strpos($result, "Bad Password") !== false)
            {
                return true;
            }
            // Server accepting connect? BAD!
            else if($result == "client_connect")
            {
                // Shoot! It'll just have to time out.
                return false;
            }
            else
            {
                return false;
            }
        }
        
        /**
         * Gets the status the server returns and stores it to a cache.
         * If you've called a method to get some piece of info (i.e. map) that
         * uses status, then all further method calls will return cached data.
         * To get new data, call this method beforehand with true for the argument.
         * Note: If a player is downloading/still connecting, the team colors will
         * be off, and there is nothing we can do about it.
         * @param bool $force_update Forces an update of the cache with new information
         */
        public function update_status_cache($force_update = false)
        {
            if($force_update || count($this->status_cache) == 0)
            {
                $this->send_ctrl("status");
                $data = $this->get_ctrl();
                
                // Example data:
                // \TimeLeft\14:32\pr\!0\mapname\arenaball\_scores\Red:0 Blue:0
                // 0 204 "JoeJoe"
                $result = explode("\n", $data);
                $status = explode("\\", $result[0]);
                // Knock off the status data (so we can get the player list)
                array_shift($result);
                // Knock off the first blank entry
                array_shift($status);
                
                // Get the key/value statusness
                $info = array();
                
                // We skip two, so we set:
                // key = i
                // value = i+1
                for($i = 0; $i < count($status); $i += 2)
                {
                    $info[$status[$i]] = trim($status[$i+1]);
                }
                
                $this->status_cache = $info;
                
                // Get the player list
                $players = array();
                
                $index = 0;
                foreach($result as $line)
                {
                    $line = trim($line);
                    $line = explode(" ", $line, 3);
                    
                    if(@!$line[2]) continue;
                    
                    if(@strpos($info['pr']."!", "!$index!") !== false)
                        $team = TEAM_RED;
                    else if(@strpos($info['pb']."!", "!$index!") !== false)
                        $team = TEAM_BLUE;
                    else if(@strpos($info['py']."!", "!$index!") !== false)
                        $team = TEAM_YELLOW;
                    else if(@strpos($info['pp']."!", "!$index!") !== false)
                        $team = TEAM_PURPLE;
                    else
                        $team = TEAM_OBSERVER;
                    
                    $players[] = array('name' => substr($line[2], 1, -1),
                                       'ping' => $line[1],
                                       'score' => $line[0],
                                       'team' => $team);
                    
                    $index++;
                }
                
                $this->players_cache = $players;
            }
        }
        
        /**
         * Gets the status info of the server (server settings, etc.).
         * Uses the status data cache.
         * @return array Status information
         */
        public function get_status_info()
        {
            $this->update_status_cache();
            
            return $this->status_cache;
        }
        
        /**
         * Gets the build version of the server. Returns 0 if unknown.
         * Should recognize build 7, 10, 12, 14, 15, 16, 17, 18, 19, and beyond.
         * Uses the status data cache.
         * @return int Build number
         */
        public function get_build()
        {
            $this->update_status_cache();
            
            $build = 0;
            
            // Let's first try to get the build by checking the version string
            if(preg_match("#\\(([0-9]+)\\)$#", trim($this->status_cache['version']), $m))
            {
                return $m[1];
            }
            
            // No go? Let's do the nitty gritty and look at the game code versions
            if(preg_match("#v([0-9\\.]+)#", $this->status_cache['gameversion'], $m))
            {
                $game_code_ver = $m[1];
                
                // Incomplete list
                $known = array('1.901' => 18,
                               '1.900' => 17,
                               '1.831' => 17,
                               '1.83' => 16,
                               '1.82' => 15,
                               '1.81' => 14,
                               '1.802' => 12,
                               '1.774' => 10,
                               '1.771' => 10,
                               '1.77' => 7,
                               );
                
                if($known[$game_code_ver])
                {
                    return $known[$game_code_ver];
                }
            }
            
            return 0;
        }
        
        /**
         * Gets the players list (name, ping, score, team color).
         * Uses the status data cache.
         * @return array List of players
         */
        public function get_players()
        {
            $this->update_status_cache();
            
            return $this->players_cache;
        }
        
        /**
         * Gets the name of the server.
         * Uses the status data cache.
         * @return string Server name
         */
        public function get_server_name()
        {
            $this->update_status_cache();
            
            return $this->status_cache['hostname'];
        }
        
        /**
         * Gets the current map on the server.
         * Uses the status data cache.
         * @return string Name of map on server
         */
        public function get_map()
        {
            $this->update_status_cache();
            
            return $this->status_cache['mapname'];
        }
        
        /**
         * Gets the current team scores on the server.
         * Uses the status data cache.
         * @return array List of team scores
         */
        public function get_scores()
        {
            $this->update_status_cache();
            
            $data = explode(" ", $this->status_cache['_scores']);
            
            $scores = array();
            
            foreach($data as $team)
            {
                $team = explode(":", $team);
                
                // Set the keys to a TEAM_???? constant
                $scores[constant("TEAM_".strtoupper($team[0]))] = $team[1];
            }
            
            return $scores;
        }
        
        /**
         * Gets the server map rotation list (with vote points).
         * Requires RCON.
         * @return array List of maps, with keys 'points', 'mapname', and 'user_added'
         */
        public function rcon_get_map_rotation()
        {            
            $result = $this->rcon('sv maplist');
            
            $result = explode("\n", $result);
            
            $maps = array();
            
            foreach($result as $line)
            {
                if(preg_match("#^\\(?([0-9]+) (.*?)(\\)?)$#", trim($line), $m))
                {
                    $maps[] = array('points' => $m[1],
                                    'mapname' => $m[2],
                                    'user_added' => $m[3] ? true : false);
                }
            }
            
            return $maps;
        }
        
        /**
         * Gets a list of bots.
         * Requires RCON.
         * @return array List of bots
         */
        public function rcon_get_bots()
        {            
            $result = $this->rcon('sv listuserip');
            
            $result = explode("\n", $result);
            
            $bots = array();
            
            foreach($result as $line)
            {
                if(preg_match("#^ (.*?) \\[\\]$#", rtrim($line), $m))
                {
                    $bots[] = $m[1];
                }
            }
            
            return $bots;
        }
        
        /**
         * Adds a bot.
         * Requires RCON.
         * @param string $name Name of bot, or null for default
         * @return bool Success?
         */
        public function rcon_add_bot($name = null)
        {            
            $result = $this->rcon("sv addbot $name");
            
            if(preg_match("#increase Maxclients\.$#", trim($result)))
            {
                trigger_error("DigitalPaintServer: Failed to add bot: ".strip_tags(trim($result)." due to maxclients"), E_USER_WARNING);
                return false;
            }
            
            return true;
        }
        
        /**
         * Removes a bot/all bots.
         * Requires RCON.
         * @param string $name Name of bot to remove, null to remove all
         * @return bool Success?
         */
        public function rcon_remove_bot($name = null)
        {            
            if(!$name)
                $name = "all";
            
            $result = $this->rcon("sv removebot $name");
            
            if(!preg_match("#disconnected\.$#", trim($result)))
            {
                trigger_error("DigitalPaintServer: Failed to remove bot: ".strip_tags(trim($result)), E_USER_WARNING);
                return false;
            }
            
            return true;
        }
        
        /**
         * Sends a bot command to a bot/all bots.
         * Requires RCON.
         * @param string $name Name of bot to command, null to command all
         * @return bool Success?
         */
        public function rcon_bot_command($name = null)
        {            
            if(!$name)
                $name = "all";
            
            $result = $this->rcon("sv botcommand $name");
            
            if(preg_match("#not found$#", trim($result)))
            {
                trigger_error("DigitalPaintServer: Failed to command bot: ".strip_tags(trim($result)), E_USER_WARNING);
                return false;
            }
            
            return true;
        }
        /**
        
         * Gets the players list (client ID, name, score, ping, last message time, IP, port, qport, is_connecting, is_zombie).
         * Don't forget connecting and zombie users.
         * Uses RCON.
         * @since 3.0
         * @return array List of players, status information
         */
        function rcon_get_players()
        {
            $result = $this->rcon('status');
            
            if(!$result)
                return false;
            
            $result = explode("\n", $result);
            
            $players = array();
            
            foreach($result as $line)
            {
                // Parse the data
                if(preg_match('#^([0-9]+) +([0-9]+) +([^ ]+) +(.+?) +([0-9]+) +([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):([0-9]+) +([0-9]+)$#', trim($line), $m))
                {
                    list(, $client_id, $score, $ping, $name, $lastmsg, $host, $port, $qport) = $m;
                    
                    $info = array('client_id' => $client_id,
                                  'name' => $name,
                                  'score' => $score,
                                  'ping' => intval($ping),
                                  'last_msg' => $lastmsg,
                                  'ip' => $host,
                                  'port' => $port,
                                  'qport' => $qport,
                                  'is_connecting' => $ping == "CNCT",
                                  'is_zombie' => $ping == "ZMBI");
                    
                    $players[] = $info;
                }
            }
            
            return $players;
        }
        
        /**
         * Gets the players list  (name, ping, score, team color) with extended 
         * information (client ID, name, score, ping, last message time, IP, port, qport, is_connecting, is_zombie).
         * Don't forget connecting and zombie users.
         * Uses RCON, and will force an update of the status cache.
         * @return array List of players, extended information
         */
        public function rcon_get_extended_players()
        {
            $this->update_status_cache(true);
            
            $result = $this->rcon('status');
            
            $result = explode("\n", $result);
            
            $status = array();
            
            $players_cache_by_name = array();
            
            // Store the cache by name so we can match up additional stats
            foreach($this->players_cache as $player)
            {
                $players_cache_by_name[$player['name']] = $player;
            }
            
            $players = array();
            
            foreach($result as $line)
            {
                // Parse the data
                if(preg_match('#^([0-9]+) +([0-9]+) +([^ ]+) +(.+?) +([0-9]+) +([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):([0-9]+) +([0-9]+)$#', trim($line), $m))
                {
                    list(, $client_id, $score, $ping, $name, $lastmsg, $host, $port, $qport) = $m;
                    
                    $info = array('client_id' => $client_id,
                                  'name' => $name,
                                  'score' => $score,
                                  'ping' => intval($ping),
                                  'last_msg' => $lastmsg,
                                  'ip' => $host,
                                  'port' => $port,
                                  'qport' => $qport,
                                  'is_connecting' => $ping == "CNCT",
                                  'is_zombie' => $ping == "ZMBI");
                    
                    if($players_cache_by_name[$name])
                    {
                        $info = array_merge($info, $players_cache_by_name[$name]);
                    }
                    else
                    {
                        $info['team'] = null;
                    }
                    
                    $players[] = $info;
                }
            }
            
            return $players;
        }
        
        /**
         * Gets user information that the player provided when s/he connected (but
         * it updates if it changes during the game). Includes spectator (??),
         * build, password sent, hand, name, skin, rate, msg, field of view, and gender.
         * Requires RCON.
         * @param int $client_id Client ID number
         * @return array User information
         */
        public function rcon_get_user_information($client_id)
        {            
            $client_id = intval($client_id);
            
            $result = $this->rcon("dumpuser $client_id");
            
            $result = explode("\n", $result);
            
            // Knock off the header
            array_shift($result);
            array_shift($result);
            
            $info = array();
            
            foreach($result as $line)
            {
                $line = explode(" ", $line, 2);
                
                $value = trim($line[1]);
                
                if(!$value)
                    continue;
                
                $info[trim($line[0])] = $value;
            }
            
            return $info;
        }
        
        /**
         * Gets the value of a variable.
         * Requires RCON.
         * @param string $var Name of variable
         * @return string Value, or null if undefined
         */
        public function rcon_get_variable($var)
        {            
            $result = $this->rcon("$var"); // Not the best way? Command conflict?
            
            // Parse out what it returns
            // Example: "password" is "test"
            if(preg_match('#^"[^"]+" is "(.*)"$#', $result, $m))
            {
                return $m[1];
            }
            else
            {
                return null;
            }
        }
        
        /**
         * Sets the value of a variable.
         * Requires RCON.
         * @param string $var Name of variable
         * @param string $value Value to set variable to
         * @param bool $server_flag Set the server flag (appears in server information)
         * @return bool False if server returned that the variable was write protected
         */
        public function rcon_set_variable($var, $value, $server_flag = false)
        {            
            $flag = $server_flag ? ' s' : '';
            
            $result = $this->rcon("set $var \"$value\"$flag");
            
            return strpos($result, 'is write protected.') !== false;
        }
        
        /**
         * Unsets a variable.
         * Requires RCON.
         * @param string $var Name of variable
         * @return bool False if server returned that the variable was write protected
         */
        public function rcon_unset_variable($var)
        {            
            $result = $this->rcon("unset $var");
            
            return strpos($result, 'is write protected.') !== false;
        }
        
        /**
         * Bans a hostmask.
         * Requires RCON.
         * Will trigger an error if IP was not added successfully.
         * @param string $mask Host mask to ban
         * @param bool $write_ip Write the ban list to disk? (only if ip added successfully)
         * @return bool Added IP successfully
         */
        public function rcon_ban_hostmask($mask, $write_ip = true)
        {            
            $result = $this->rcon("sv addip \"$mask\"");
            
            if(trim($result) != "")
            {
                trigger_error("DigitalPaintServer: Failed to add IP: ".strip_tags(trim($result)), E_USER_WARNING);
                return false;
            }
            
            if($write_ip)
                $this->rcon_write_bans();
            
            return true;
        }
        
        /**
         * Unbans a hostmask.
         * Requires RCON.
         * @param string $mask Host mask to unban
         * @param bool $write_ip Write the ban list to disk? (only if ip removed successfully)
         * @return bool Removed IP successfully
         */
        public function rcon_unban_hostmask($mask, $write_ip = true)
        {            
            $this->rcon("sv removeip \"$mask\"");
            
            if(trim($result) != "")
                return false;
                
            if($write_ip)
                $this->rcon_write_bans();
            
            return true;
        }
        
        /**
         * Write ban list to disk.
         * Will trigger an error on failure.
         * Requires RCON.
         * @return bool Successful?
         */
        public function rcon_write_bans()
        {            
            $result = $this->rcon("sv writeip");
            
            if(!preg_match("#^Writing#", trim($result)))
            {
                trigger_error("DigitalPaintServer: Failed to write IP ban list to disk: ".strip_tags(trim($result)), E_USER_WARNING);
                return false;
            }
            
            return true;
        }
        
        /**
         * Kicks a user.
         * Requires RCON.
         * @param int $client_id Client ID of user to kick
         * @return bool Kicked successfully?
         */
        public function rcon_kick_user($client_id)
        {            
            $client_id = intval($client_id);
            
            $result = $this->rcon("kick $client_id");
            
            if(preg_match("#^Bad client slot|is not active#", trim($result)))
            {
                trigger_error("DigitalPaintServer: $result", E_USER_WARNING);
                return false;
            }
            
            return true;
        }
        
        /**
         * Gets the ban list.
         * Requires RCON.
         * @return array List of bans
         */
        public function rcon_list_banlist()
        {
            $result = $this->rcon("sv listip");
            
            $result = explode("\n", $result);
            
            // Knock off the header
            array_shift($result);
            
            $bans = array();
            
            foreach($result as $line)
            {
                $line = trim($line);
                
                if(preg_match("#^([0-9 ]{3})\.([0-9 ]{3})\.([0-9 ]{3})\.([0-9 ]{3})$#", $line))
                {
                    $bans[] = str_replace(" ", "", $line);
                }
            }
            
            return $bans;
        }
        
        /**
         * Removes temporary bans.
         * Requires RCON.
         */
        public function rcon_remove_temp_bans()
        {
            $this->rcon("sv removetbans");
        }
        
        /**
         * Sends a heartbeat.
         * Requires RCON.
         */
        public function rcon_heartbeat()
        {
            $this->rcon("sv heartbeat");
        }
        
        /**
         * Changes the map.
         * Requires RCON.
         * Triggers an error if map failed to change.
         * @param string $mapname Map name to change to
         * @param string $gamemode Game mode to change to
         * @return bool True if successful
         */
        public function rcon_new_map($mapname, $gamemode = "")
        {            
            $gamemode = $gamemode ? " $gamemode" : "";
            
            $result = $this->rcon("sv newmap $mapname$gamemode");
            
            if(trim($result) != "")
            {
                trigger_error("DigitalPaintServer: Failed to change map: ".strip_tags(trim($result)), E_USER_WARNING);
                return false;
            }
            
            return true;
        }
        
        /**
         * Kills the game, but will not actually close the 
         * server. Players will not be able to connect.
         * Requires RCON.
         */
        public function rcon_kill_server()
        {
            $this->rcon("killserver");
        }
        
        /**
         * Quits the server.
         * Requires RCON.
         */
        public function rcon_quit()
        {
            $this->rcon("quit");
        }
    }
?>