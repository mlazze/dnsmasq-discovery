<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);

$dnsmasqconffile = "/var/www/html/dnsmasq/dnsmasq.conf";
$dnsmasqleasfile = "/var/www/html/dnsmasq/dnsmasq.leases";
$ddwrt = "ddwrt";
$ddwrtuser = "root";
$localfiledir = "/var/www/html/dnsmasq/";
$logfile = "/tmp/skijdomain.log";
$getvendorscript = "/var/www/html/macprefixes/getvendorfrommac.sh";

function getFiles() {
	//always works
	global $ddwrt, $ddwrtuser, $localfiledir, $logfile;
	exec("scp -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ".$ddwrtuser."@".$ddwrt.":/tmp/dnsmasq.* ".$localfiledir." > ".$logfile." 2>&1 &");	

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

function getLatestUpdate() {
	global $dnsmasqleasfile;
	return "Last Update:<br />".date("F d Y H:i:s", filemtime($dnsmasqleasfile));
}

function ping($host)
{
        exec(sprintf('fping -t70 -q %s', escapeshellarg($host)), $res, $rval);
        return $rval === 0;
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
	$res["Lease"] = $el["Lease"];
	$res["plainIP"] = $el["IP"];
	return $res;
}

function createTable($hosts) {
	$res="";
	$res.='<table id="leases">';
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
		$up = ping($current["plainIP"]);
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

//parse dnsmasq.conf file
getFiles();
$pattern = "/\=/";
$res = preg_grep($pattern, file($dnsmasqconffile));

$domain = findToArr($res,"domain")[0];
$hosts = array_map("convertArr", findToArr($res,"dhcp-host"));

//parse dnsmasq.leases file
$leases = array_map("convertArrLeases", file($dnsmasqleasfile));

//merge hosts, format and unique them
$hosts = array_merge($hosts,$leases);
$hosts = array_map("formathosts",$hosts);
$hosts = unique_multidim_array($hosts,"IP");


?>
<html>
<head>
<title><?php echo $domain?></title>
<link rel="icon" 
      type="image/ico" 
      href="/img/favicon.ico" />
<link rel="stylesheet" href="style.css">
<link href='https://fonts.googleapis.com/css?family=Inconsolata' rel='stylesheet' type='text/css'>
<script src="https://cdn.rawgit.com/zenorocha/clipboard.js/master/dist/clipboard.min.js"></script>
<script src='/js/tablesort.min.js'></script>
<script src='/js/tablesort.dotsep.js'></script>
</head>
<body><?php
echo '<div id="wrapper">';
echo surroundWith('div class="title"',copiable($domain));
echo surroundWith('div class="latest"',getLatestUpdate());
echo createTable($hosts);
echo createFooter();
echo '</div>';
?>
</body>
<script type="text/javascript">
new Clipboard('.copiable');
new Tablesort(document.getElementById('leases'));
</script>
</html>
