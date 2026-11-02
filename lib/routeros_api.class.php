<?php
















class RouterosAPI
{
    var $debug     = false;
    var $connected = false;
    var $port      = 8728;
    var $ssl       = false;
    var $timeout   = 3;
    var $attempts  = 5;
    var $delay     = 3;

    var $socket;
    var $error_no;
    var $error_str;


    public function isIterable($var)
    {
        return $var !== null
                && (is_array($var)
                || $var instanceof Traversable
                || $var instanceof Iterator
                || $var instanceof IteratorAggregate
                );
    }








    public function debug($text)
    {
        if ($this->debug) {
            echo $text . "\n";
        }
    }









    public function encodeLength($length)
    {
        if ($length < 0x80) {
            $length = chr($length);
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            $length = chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            $length = chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            $length = chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length >= 0x10000000) {
            $length = chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }

        return $length;
    }











    public function connect($ip, $login, $password)
    {
        for ($ATTEMPT = 1; $ATTEMPT <= $this->attempts; $ATTEMPT++) {
            $this->connected = false;
            $PROTOCOL = ($this->ssl ? 'ssl://' : '' );
            $context = stream_context_create(array('ssl' => array('ciphers' => 'ADH:ALL', 'verify_peer' => false, 'verify_peer_name' => false)));
            $this->debug('Connection attempt #' . $ATTEMPT . ' to ' . $PROTOCOL . $ip . ':' . $this->port . '...');
            $this->socket = @stream_socket_client($PROTOCOL . $ip.':'. $this->port, $this->error_no, $this->error_str, $this->timeout, STREAM_CLIENT_CONNECT,$context);
            if ($this->socket) {
                socket_set_timeout($this->socket, $this->timeout);
                $this->write('/login', false);
                $this->write('=name=' . $login, false);
                $this->write('=password=' . $password);
                $RESPONSE = $this->read(false);
                if (isset($RESPONSE[0])) {
                    if ($RESPONSE[0] == '!done') {
                        if (!isset($RESPONSE[1])) {

                            $this->connected = true;
                            break;
                        } else {

                            $MATCHES = array();
                            if (preg_match_all('/[^=]+/i', $RESPONSE[1], $MATCHES)) {
                                if ($MATCHES[0][0] == 'ret' && strlen($MATCHES[0][1]) == 32) {
                                    $this->write('/login', false);
                                    $this->write('=name=' . $login, false);
                                    $this->write('=response=00' . md5(chr(0) . $password . pack('H*', $MATCHES[0][1])));
                                    $RESPONSE = $this->read(false);
                                    if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                                        $this->connected = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
                fclose($this->socket);
            }
            sleep($this->delay);
        }

        if ($this->connected) {
            $this->debug('Connected...');
        } else {
            $this->debug('Error...');
        }
        return $this->connected;
    }







    public function disconnect()
    {

        if( is_resource($this->socket) ) {
            fclose($this->socket);
        }
        $this->connected = false;
        $this->debug('Disconnected...');
    }









    public function parseResponse($response)
    {
        if (is_array($response)) {
            $PARSED      = array();
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, array('!fatal','!re','!trap'))) {
                    if ($x == '!re') {
                        $CURRENT =& $PARSED[];
                    } else {
                        $CURRENT =& $PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCHES = array();
                    if (preg_match_all('/[^=]+/i', $x, $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret') {
                            $singlevalue = $MATCHES[0][1];
                        }
                        $CURRENT[$MATCHES[0][0]] = (isset($MATCHES[0][1]) ? $MATCHES[0][1] : '');
                    }
                }
            }

            if (empty($PARSED) && !is_null($singlevalue)) {
                $PARSED = $singlevalue;
            }

            return $PARSED;
        } else {
            return array();
        }
    }









    public function parseResponse4Smarty($response)
    {
        if (is_array($response)) {
            $PARSED      = array();
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, array('!fatal','!re','!trap'))) {
                    if ($x == '!re') {
                        $CURRENT =& $PARSED[];
                    } else {
                        $CURRENT =& $PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCHES = array();
                    if (preg_match_all('/[^=]+/i', $x, $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret') {
                            $singlevalue = $MATCHES[0][1];
                        }
                        $CURRENT[$MATCHES[0][0]] = (isset($MATCHES[0][1]) ? $MATCHES[0][1] : '');
                    }
                }
            }
            foreach ($PARSED as $key => $value) {
                $PARSED[$key] = $this->arrayChangeKeyName($value);
            }
            return $PARSED;
            if (empty($PARSED) && !is_null($singlevalue)) {
                $PARSED = $singlevalue;
            }
        } else {
            return array();
        }
    }









    public function arrayChangeKeyName(&$array)
    {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                $tmp = str_replace("-", "_", $k);
                $tmp = str_replace("/", "_", $tmp);
                if ($tmp) {
                    $array_new[$tmp] = $v;
                } else {
                    $array_new[$k] = $v;
                }
            }
            return $array_new;
        } else {
            return $array;
        }
    }









    public function read($parse = true)
    {
        $RESPONSE     = array();
        $receiveddone = false;
        while (true) {


            $BYTE   = ord(fread($this->socket, 1));
            $LENGTH = 0;





            if ($BYTE & 128) {
                if (($BYTE & 192) == 128) {
                    $LENGTH = (($BYTE & 63) << 8) + ord(fread($this->socket, 1));
                } else {
                    if (($BYTE & 224) == 192) {
                        $LENGTH = (($BYTE & 31) << 8) + ord(fread($this->socket, 1));
                        $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                    } else {
                        if (($BYTE & 240) == 224) {
                            $LENGTH = (($BYTE & 15) << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        } else {
                            $LENGTH = ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        }
                    }
                }
            } else {
                $LENGTH = $BYTE;
            }

            $_ = "";


            if ($LENGTH > 0) {
                $_      = "";
                $retlen = 0;
                while ($retlen < $LENGTH) {
                    $toread = $LENGTH - $retlen;
                    $_ .= fread($this->socket, $toread);
                    $retlen = strlen($_);
                }
                $RESPONSE[] = $_;
                $this->debug('>>> [' . $retlen . '/' . $LENGTH . '] bytes read.');
            }


            if ($_ == "!done") {
                $receiveddone = true;
            }

            $STATUS = socket_get_status($this->socket);
            if ($LENGTH > 0) {
                $this->debug('>>> [' . $LENGTH . ', ' . $STATUS['unread_bytes'] . ']' . $_);
            }

            if ((!$this->connected && !$STATUS['unread_bytes']) || ($this->connected && !$STATUS['unread_bytes'] && $receiveddone)) {
                break;
            }
        }

        if ($parse) {
            $RESPONSE = $this->parseResponse($RESPONSE);
        }

        return $RESPONSE;
    }













    public function write($command, $param2 = true)
    {
        if ($command) {
            $data = explode("\n", $command);
            foreach ($data as $com) {
                $com = trim($com);
                fwrite($this->socket, $this->encodeLength(strlen($com)) . $com);
                $this->debug('<<< [' . strlen($com) . '] ' . $com);
            }

            if (gettype($param2) == 'integer') {
                fwrite($this->socket, $this->encodeLength(strlen('.tag=' . $param2)) . '.tag=' . $param2 . chr(0));
                $this->debug('<<< [' . strlen('.tag=' . $param2) . '] .tag=' . $param2);
            } elseif (gettype($param2) == 'boolean') {
                fwrite($this->socket, ($param2 ? chr(0) : ''));
            }

            return true;
        } else {
            return false;
        }
    }










    public function comm($com, $arr = array())
    {
        $count = count($arr);
        $this->write($com, !$arr);
        $i = 0;
        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                switch ($k[0]) {
                    case "?":
                        $el = "$k=$v";
                        break;
                    case "~":
                        $el = "$k~$v";
                        break;
                    default:
                        $el = "=$k=$v";
                        break;
                }

                $last = ($i++ == $count - 1);
                $this->write($el, $last);
            }
        }

        return $this->read();
    }






    public function __destruct()
    {
        $this->disconnect();
    }
}



function encrypt($string, $key=128) {
	$result = '';
	for($i=0, $k= strlen($string); $i<$k; $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)+ord($keychar));
		$result .= $char;
	}
	return base64_encode($result);
}
function decrypt($string, $key=128) {
	$result = '';
	$string = base64_decode($string);
	for($i=0, $k=strlen($string); $i< $k ; $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)-ord($keychar));
		$result .= $char;
	}
	return $result;
}




function formatInterval($dtm){
$val_convert = $dtm;
$new_format = str_replace("s", "", str_replace("m", "m ", str_replace("h", "h ", str_replace("d", "d ", str_replace("w", "w ", $val_convert)))));
return $new_format;
}

function formatDTM($dtm){
if(substr($dtm, 1,1) == "d" || substr($dtm, 2,1) == "d"){
    $day = explode("d",$dtm)[0]."d";
    $day = str_replace("d", "d ", str_replace("w", "w ", $day));
    $dtm = explode("d",$dtm)[1];
}elseif(substr($dtm, 1,1) == "w" && substr($dtm, 3,1) == "d" || substr($dtm, 2,1) == "w" && substr($dtm, 4,1) == "d"){
    $day = explode("d",$dtm)[0]."d";
    $day = str_replace("d", "d ", str_replace("w", "w ", $day));
    $dtm = explode("d",$dtm)[1];
}elseif (substr($dtm, 1,1) == "w" || substr($dtm, 2,1) == "w" ) {
    $day = explode("w",$dtm)[0]."w";
    $day = str_replace("d", "d ", str_replace("w", "w ", $day));
    $dtm = explode("w",$dtm)[1];
}


if(strlen($dtm) == "2" && substr($dtm, -1) == "s"){
    $format = $day." 00:00:0".substr($dtm, 0,-1);
}elseif(strlen($dtm) == "3" && substr($dtm, -1) == "s"){
    $format = $day." 00:00:".substr($dtm, 0,-1);

}elseif(strlen($dtm) == "2" && substr($dtm, -1) == "m"){
    $format = $day." 00:0".substr($dtm, 0,-1).":00";
}elseif(strlen($dtm) == "3" && substr($dtm, -1) == "m"){
    $format = $day." 00:".substr($dtm, 0,-1).":00";

}elseif(strlen($dtm) == "2" && substr($dtm, -1) == "h"){
    $format = $day." 0".substr($dtm, 0,-1).":00:00";
}elseif(strlen($dtm) == "3" && substr($dtm, -1) == "h"){
    $format = $day." ".substr($dtm, 0,-1).":00:00";


}elseif(strlen($dtm) == "4" && substr($dtm, -1) == "s" && substr($dtm,1,-2) == "m"){
    $format = $day." "."00:0".substr($dtm, 0,1).":0".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,1,-3) == "m"){
    $format = $day." "."00:0".substr($dtm, 0,1).":".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,2,-2) == "m"){
    $format = $day." "."00:".substr($dtm, 0,2).":0".substr($dtm, 3,-1);
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm,2,-3) == "m"){
    $format = $day." "."00:".substr($dtm, 0,2).":".substr($dtm, 3,-1);


}elseif(strlen($dtm) == "4" && substr($dtm, -1) == "s" && substr($dtm,1,-2) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":00:0".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,1,-3) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":00:".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,2,-2) == "h"){
    $format = $day." ".substr($dtm, 0,2).":00:0".substr($dtm, 3,-1);
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm,2,-3) == "h"){
    $format = $day." ".substr($dtm, 0,2).":00:".substr($dtm, 3,-1);


}elseif(strlen($dtm) == "4" && substr($dtm, -1) == "m" && substr($dtm,1,-2) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":0".substr($dtm, 2,-1).":00";
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "m" && substr($dtm,1,-3) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":".substr($dtm, 2,-1).":00";
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "m" && substr($dtm,2,-2) == "h"){
    $format = $day." ".substr($dtm, 0,2).":0".substr($dtm, 3,-1).":00";
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "m" && substr($dtm,2,-3) == "h"){
    $format = $day." ".substr($dtm, 0,2).":".substr($dtm, 3,-1).":00";


}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm,3,-2) == "m" && substr($dtm,1,-4) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":0".substr($dtm, 2,-3).":0".substr($dtm, 4,-1);
}elseif(strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm,3,-3) == "m" && substr($dtm,1,-5) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":0".substr($dtm, 2,-4).":".substr($dtm, 4,-1);
}elseif(strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm,4,-2) == "m" && substr($dtm,1,-5) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":".substr($dtm, 2,-3).":0".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm,4,-3) == "m" && substr($dtm,1,-6) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":".substr($dtm, 2,-4).":".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm,4,-2) == "m" && substr($dtm,2,-4) == "h"){
    $format = $day." ".substr($dtm, 0,2).":0".substr($dtm, 3,-3).":0".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm,4,-3) == "m" && substr($dtm,2,-5) == "h"){
    $format = $day." ".substr($dtm, 0,2).":0".substr($dtm, 3,-4).":".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm,5,-2) == "m" && substr($dtm,2,-5) == "h"){
    $format = $day." ".substr($dtm, 0,2).":".substr($dtm, 3,-3).":0".substr($dtm, 6,-1);
}elseif(strlen($dtm) == "9" && substr($dtm, -1) == "s" && substr($dtm,5,-3) == "m" && substr($dtm,2,-6) == "h"){
    $format = $day." ".substr($dtm, 0,2).":".substr($dtm, 3,-4).":".substr($dtm, 6,-1);

}else{
    $format = $dtm;
}
return $format;
}


function randN($length) {
	$chars = "23456789";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randUC($length) {
	$chars = "ABCDEFGHJKLMNPRSTUVWXYZ";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}
function randLC($length) {
	$chars = "abcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randULC($length) {
	$chars = "ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randNLC($length) {
	$chars = "23456789abcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randNUC($length) {
	$chars = "23456789ABCDEFGHJKLMNPRSTUVWXYZ";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randNULC($length) {
	$chars = "23456789ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

?>
