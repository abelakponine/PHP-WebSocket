<?php
namespace home;
use \websocket\ZuppyWS;
require('websocket/class_loader.php');
	$host = "127.0.0.1";
	$port = 2022;
	// start socket server
	$zws = new ZuppyWS($host, $port,30000,0);
	$zws::createSocket();
?>
