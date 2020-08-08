<?php

class SocketConfig {
	private static $host = "127.0.0.1";
	private static $port = 2023;
	private static $key = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

	protected static function gethost(){
		return self::$host;
	}
	public static function getport(){
		return self::$port;
	}
	public static function getkey(){
		return self::$key;
	}
	protected static function sethost($host){
		self::$host = $host;
	}
	public static function setport($port){
		self::$port = $port;
	}
}
?>