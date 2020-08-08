<?php
namespace home;
use \websocket\ZuppyVS;
require('websocket/class_loader.php');
	$host = "34.66.202.57";
	$port = 2026;
	// start socket server
	$zws = new ZuppyVS($host, $port, 30000);
	$zws::createSocket();
?>