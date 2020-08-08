<?php
namespace home;
use \websocket\ZuppyWS;
require('websocket/class_loader.php');
	$host = "34.66.202.57";
	$port = 2022;
	// start socket server
	$zws = new ZuppyWS($host, $port,0,30000);
	$zws::createSocket();
?>