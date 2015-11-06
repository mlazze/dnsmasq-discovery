<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

function PDOadd_item($dbh, $MAC, $Hostname, $IP, $Lease) {
    $sth = $dbh->prepare('INSERT INTO Hosts (MAC,Hostname,IP,Lease) VALUES (?,?,?,?)');
    return $sth->execute(array($MAC, $Hostname, $IP, $Lease));
}

function PDOset_info($dbh,$field,$content) {
    $sth = $dbh->prepare('INSERT INTO Infos (Field, Content) VALUES(?,?)'); 
    return $sth->execute(array($field,$content));
}

function PDOget_info($dbh,$field) {
    $result = $dbh->query('SELECT * FROM Infos');

    foreach($result as $row) {
        if ($row["Field"]==$field)
            return $row["Content"];
    }
    return "";
}


function PDOget_items($dbh) {
    $result = $dbh->query('SELECT MAC, Hostname, IP, Lease FROM Hosts');
    $arr = array();
    foreach($result as $row) {
        $tmp = array();
        $tmp["MAC"] = $row["MAC"];   
        $tmp["Hostname"] = $row["Hostname"];   
        $tmp["IP"] = $row["IP"];   
        $tmp["Lease"] = $row["Lease"];   
        $arr[] = $tmp;
    }
    return $arr;
}

function PDOopenDB($dblocation) {
    return $db = new PDO('sqlite:'.$dblocation);
}

function PDOcreate_tables($dbh) {
    $dbh->exec("DROP TABLE Hosts");    
    $dbh->exec("DROP TABLE Domain");    
    $dbh->exec("DROP TABLE Infos");    
    $dbh->exec("CREATE TABLE Hosts (Id INTEGER PRIMARY KEY, MAC TEXT, Hostname TEXT, IP TEXT, Lease TEXT)");    
    $dbh->exec("CREATE TABLE Infos (Field TEXT PRIMARY KEY, Content TEXT)");    
    return;
}
?>
