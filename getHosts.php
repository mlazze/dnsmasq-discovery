<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);
include('sqlite.php');

$basedir = "/var/www/html/";
$dnsmasqconffile = $basedir."dnsmasq/dnsmasq.conf";
$dnsmasqleasfile = $basedir."dnsmasq/dnsmasq.leases";
$ddwrt = "ddwrt";
$ddwrtuser = "root";
$localfiledir = $basedir."dnsmasq/";
$logfile = "/tmp/skijdomain.log";
$dblocation=$basedir.'db/clientsPDO.sqlite';

function getFiles() {
	//always works
	global $ddwrt, $ddwrtuser, $localfiledir, $logfile;
	exec("scp -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ".$ddwrtuser."@".$ddwrt.":/tmp/dnsmasq.* ".$localfiledir." > ".$logfile." 2>&1");	
	exec ("date >> ".$logfile);
	//works if static webpages are kept on router
	//global $dnsmasqconffile, $dnsmasqleasfile;
	//exec("wget -qO ".$dnsmasqconffile." http://ddwrt/user/dnsmasq.htm >> /tmp/parsednsmasq.log");
	//exec("wget -qO ".$dnsmasqleasfile." http://ddwrt/user/dnsmasql.htm >> /tmp/parsednsmasq.log");
}

function arr($arr) {
        echo "<pre>";
        print_r($arr);
        echo "</pre>";
}

function findToArr($startingArray, $string) {
        $res=array();
        $startingArray = array_map("trim", $startingArray);
        foreach($startingArray as $current) {
                $pieces = explode ("=",$current);
                if ($pieces[0]==$string)
                        $res[]=$pieces[1];
        }
        return $res;
}

function convertArr($el) {
	$res = explode(",",$el);
	$res["MAC"] = $res[0];
	$res["Hostname"] = $res[1];
	$res["IP"] = $res[2];
	$res["Lease"] = $res[3];
	unset($res[0]);
	unset($res[1]);
	unset($res[2]);
	unset($res[3]);
	return $res;
}

function convertArrLeases($el) {
	$res = explode(" ",$el);
	$res["MAC"] = $res[1];
	$res["Hostname"] = $res[3];
	$res["IP"] = $res[2];
	$res["Lease"] = $res[0];
	unset($res[0]);
	unset($res[1]);
	unset($res[2]);
	unset($res[3]);
	unset($res[4]);
	return $res;
}

function unique_multidim_array($array, $key){
    $temp_array = array();
    $i = 0;
    $key_array = array();
    
    foreach($array as $val){
        if(!in_array($val[$key],$key_array)){
            $key_array[$i] = $val[$key];
            $temp_array[$i] = $val;
        }
        $i++;
    }
    return $temp_array;
}

function getBroadcast($ip, $subnet) {
        return long2ip(ip2long($ip) | ~ip2long($subnet));
}

function addToDB() {
	global $dblocation, $dnsmasqconffile, $dnsmasqleasfile;

	//parse dnsmasq.conf file
	getFiles();

	
	$pattern = "/\=/";
	$res = preg_grep($pattern, file($dnsmasqconffile));
	$domain = findToArr($res,"domain")[0];
	$temp = explode(",",findToArr($res,"dhcp-range")[0]);
    $braddress = getBroadcast($temp[1],$temp[3]);
	$hosts = array_map("convertArr", findToArr($res,"dhcp-host"));
	
	//parse dnsmasq.leases file
	$leases = array_map("convertArrLeases", file($dnsmasqleasfile));
	
	//merge hosts, format and unique them
	$hosts = array_merge($hosts,$leases);
    $hosts = unique_multidim_array($hosts,"IP");

    //get cuyrrent time
    date_default_timezone_set('Europe/Rome');
    $date = date('d/m/Y H:i:s', time());

    $dbh = PDOopenDB($dblocation);
    PDOcreate_tables($dbh);

    foreach($hosts as $host) {
        PDOadd_item($dbh,$host["MAC"],$host["Hostname"], $host["IP"], $host["Lease"]);
    }
    PDOset_info($dbh,"domain",$domain);
    PDOset_info($dbh,"updated",$date);
    PDOset_info($dbh,"braddress",$braddress);

    
    
    $dbh = NULL;
	
}

addToDB();
?>
