<?php
@ini_set('zlib.output_compression',0);
@ini_set('output_buffering',0);
ini_set("display_errors", 1);
error_reporting(E_ALL);
include('sqlite.php');

header('Content-Type: text/HTML; charset=utf-8');
header( 'Content-Encoding: none; ' );

$logfile = "/tmp/skijdomain.log";
$basedir = "/var/www/html/";
$getvendorscript = $basedir."macprefixes/getvendorfrommac.sh";
$domain = "";
$hosts = array();
$tablesprinted=0;
$braddress=0;
$dblocation=$basedir.'db/clientsPDO.sqlite';
$latestupdate="";

function arr($arr) {
        echo "<pre>";
        print_r($arr);
        echo "</pre>";
}

function ping($host)
{
        exec(sprintf('fping -t70 -q %s', escapeshellarg($host)), $res, $rval);
        return $rval === 0;
}

function surroundWith($tag, $text) {
	$tagonly=current(explode(' ',$tag));
	return '<'.$tag.'>'.$text.'</'.$tagonly.'>';
}

function copiable($text) {
	return surroundWith('button class="copiable" data-clipboard-text="'.$text.'"',$text);
}

function findVendorByMac($mac) {
	global $getvendorscript;
	$mac = str_replace(' ', '', $mac);
	$res = exec($getvendorscript." ".$mac);
	if ($res=="")
		return "Unknown";
	return $res;
}

function formatHosts($el) {
    global $braddress;
    
    $el["MAC"] = strtoupper($el["MAC"]);
	if ($el["Lease"]=="0" || $el["Lease"] == "infinite") {
		$el["Lease"] = "Fixed";
	} else {
		$el["Lease"] .= "s";
	}
	$res = array();
	$res["MAC"] = copiable($el["MAC"]);
	$res["Vendor"] = copiable(findVendorByMac($el["MAC"]));
	$res["Hostname"] = copiable($el["Hostname"]);
	$res["IP"] = copiable($el["IP"]);
	$res["Ssh"] = '<a href="ssh://'.$el["Hostname"].'/">Ssh</a>';
        $res["Http"] = '<a href="http://'.$el["Hostname"].'/">Http</a>';
	$res["WoL"] = surroundWith('a onclick="wol(\''.$braddress.'\',\''.$el["MAC"].'\')"',"WoL");
	$res["More"] = isset($el["More"]) ? $el["More"] : "";
	$res["plainIP"] = $el["IP"];

	//disabled
	//$res["Lease"] = $el["Lease"];

	return $res;
}

function addMore($hosts, $ip,$value) {
	foreach($hosts as $k => $current) {
		if ($current["IP"]==$ip)
			$hosts[$k]["More"] = $value;
	}
	return $hosts;
}

function addMoreLink($hosts, $ip, $name, $url) {
	$link = surroundWith('a href="'.$url.'"',$name);
	return addMore($hosts, $ip, $link);
}

function createTable($hosts,$ping) {
	global $tablesprinted;
	$res="";
	$res.='<table id="leases'.$tablesprinted.'">';
	$tablesprinted+=1;
	if (isset($hosts[0])) {
		$res.= "<thead>";
		$res.= "<tr>";
		foreach($hosts[0] as $k => $v) {
			if ($k!="plainIP") {
				if ($k=="IP") {
					$res.= surroundWith("th class='sort-default' data-sort-method='dotsep'",$k);
				} else {
					$res.= surroundWith("th",$k);
				}
			}
		}
		$res.="</tr>";
		$res.="</thead>";
	}
	$res.="<tbody>";
	foreach($hosts as $current) {
		$up = $ping ? ping($current["plainIP"]) : false;
		$res.= "<tr class =".($up ? 'on' : 'off').">";
		foreach($current as $k => $v) {
                        if ($k!="plainIP") 
				$res.= surroundWith("td",$v);
                }
                $res.= "</tr>";
	}
	$res.="</tbody>";
	$res.= "</table>";
	return $res;
}

function createFooter() {
	return surroundWith("div class='footer'","Clicking on MAC, Hostname, IP copies to clipboard.<br /> Ssh and Http are links.");

}

function getBroadcast($ip, $subnet) {
    return long2ip(ip2long($ip) | ~ip2long($subnet));
}

function setupTable() {
	global $dblocation, $domain, $hosts, $braddress, $latestupdate;

    $dbh = PDOopenDB($dblocation);
    $hosts = PDOget_items($dbh);
    $domain = PDOget_info($dbh,"domain");
    $latestupdate = "Last Update:<br />".PDOget_info($dbh,"updated");
    $braddress = PDOget_info($dbh,"braddress");
	
	//addMore
	//Example: $hosts = addMore($hosts,"192.168.0.3","Mario");
	//Example: $hosts = addMoreLink($hosts,"192.168.0.3","WebServer","192.168.0.3:80");
	$hosts = addMoreLink($hosts,"192.168.0.4","NAS","http://192.168.0.4:1024");
	$hosts = addMoreLink($hosts,"192.168.0.5","Kodi","http://192.168.0.5:3128");

	$hosts = array_map("formathosts",$hosts);
}

function printHeader() {
	print '
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<link rel="icon" 
	      type="image/ico" 
	      href="/img/favicon.ico" />
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="js/pace.css">
	<link href="https://fonts.googleapis.com/css?family=Inconsolata" rel="stylesheet" type="text/css">
	<script src="https://cdn.rawgit.com/zenorocha/clipboard.js/master/dist/clipboard.min.js"></script>
	<script src="/js/tablesort.min.js"></script>
    <script src="/js/other.js"></script>
	<script src="/js/tablesort.dotsep.js"></script>
	<script src="/js/pace.min.js"></script>
	<title>Domain</title>
	</head>
	';
}

function printDiv($ping, $class, $previousclass) {
	global $latestupdate, $dblocation, $dnsmasqconffile, $domain, $hosts, $dnsmasqleasfile;

    ob_end_flush();
	if (isset($previousclass)) {
		print '
		<script type="text/javascript">
			var fileref=document.querySelector(".'.$previousclass.'")
	        	fileref.style.display = "none"
		</script>';
    }

	setupTable();

	echo '<div id="wrapper" class="'.($class?$class:"").'">';
	echo surroundWith('div class="title"',copiable($domain));
    echo surroundWith('div class="latest"',$latestupdate.'<br/><br/>'.surroundWith('a class="update" onclick="update()"',"Force Update"));
	echo createTable($hosts,$ping);
	echo createFooter();
	print 	'<script type="text/javascript">
				document.title = "'.$domain.'";
				new Clipboard(".copiable");
				var l0 = document.getElementById("leases0");
				var l1 = document.getElementById("leases1");
				var l2 = document.getElementById("leases2");
				if (l0 != null)
 					new Tablesort(l0);
				if (l1 != null)
 					new Tablesort(l1);
				if (l2 != null)
 					new Tablesort(l2);
			</script>';
	echo '</div>';
	
	ob_start();
	flush();

}

//actualdata

printHeader();
echo "<body>";
printDiv(false,"tempWOping",NULL);
printDiv(true,NULL,"tempWOping");
echo "</body>";


echo "</html>";
