<?php
/*
* Zuppy Web Socket Server V1.0
* Developed by Abel Akponine
* Github: https://github.com/abelakponine
* Instagram: @kingabel.a
*/
namespace websocket;
use \SocketConfig as WSConfig;
set_time_limit(0);

/** Web Socket Class **/
class ZuppyWS extends WSConfig  {
	private static $socket;
	private static $nodes;
	private static $clients;
	private static $loop;
	private static $maxreadbytes;
	private static $readtype;
	// Database setup
	private static $dbhost = "127.0.0.1:3306";
	private static $db = "testDB";
	private static $user = "testdb";
	private static $pass = "test#zuppy";
	private static $conn;

	/** Constructor for socket class **/
	public function __construct($host=null, $port=null, $maxreadbytes = 65536, $readtype = 0){
		self::$maxreadbytes = $maxreadbytes;
		self::$readtype = $readtype;
		if($host !== null && $port !== null){
			self::sethost($host);
			self::setport($port);
		}
		self::$conn = mysqli_connect(self::$dbhost,self::$user,self::$pass) or die(mysqli_connect_error());
		if (self::$conn){
			mysqli_select_db(self::$conn,self::$db) or die(mysqli_error(self::$conn));
		}
		self::$socket = socket_create(AF_INET,SOCK_STREAM, SOL_TCP); // master server
		self::$clients = [self::$socket]; // add master server to array list
		self::$loop = 'infinite';
	}

	/** Method to create sockect connection **/
	public static function createSocket(){
		$host = self::gethost();
		$port = self::getport();
		# bind socket address
		self::call(socket_bind(self::$socket, $host, $port));
		self::call(socket_listen(self::$socket,1));
		self::console_log("Socket Status:: Server listening on ".$host." @ port ".$port);
		self::synAck(self::$socket, self::$clients, self::$maxreadbytes, self::$readtype);
	}

	/** Syn-Ack method to connect clients to server **/
	public static function synAck($socket, $clients, $maxreadbytes, $readtype){

		while (self::$loop == 'infinite'){

			$waitingClients = $clients; // new clients list

			$wr = null;$ex = null;$tm = 10;

			if (socket_select($waitingClients, $wr, $ex, $tm) < 1){
				continue;
			}

			# first connect new clients
			if (in_array($socket, $waitingClients)){
				$newClient = socket_accept($socket); // accept new client
				$clients[] = $newClient; // add new client to global nodes list
				self::call(socket_recv($newClient, $header, 1024, 0)); // read client's header
				self::console_log("Socket Status:: Initiating handshake");
				if (self::handshake($newClient, $header)){
					//socket_set_nonblock($newClient);
					$master_node = array_search($socket, $waitingClients);
					unset($waitingClients[$master_node]);
					self::console_log("Socket Status:: Active | New client connected.");
				}
			}

			# read all clients with messages and broadcast
			foreach ($waitingClients as $waitingClient) {

				// $msg = self::socket_readAll($waitingClient,$maxreadbytes,$readtype,1);
				// $bytes = strlen($msg);

				// if (gettype(ord($msg[0])) == "integer" && $bytes > 0 && $msg !== false && $msg !== "PING"){
				
				if (($bytes = socket_recv($waitingClient, $msg, $maxreadbytes, $readtype)) > 0){

					/********* Perform all server side scriptings here *********/

					# do something with the message here [optional]
					if(self::unmask($msg) == '%disconnect%'){
						self::console_log("Disconnection requested!");
						socket_close($waitingClient);
					}
					else {

						$msg = json_decode(explode("{eof}", self::unmask($msg))[0]);

						if (!empty($msg)){

							if ($msg->hasAttachment == "no"){
								// save messages without attachments to database
								// messages with attachments are handle in Java server side Uploader class
								$sql = "INSERT INTO messages (Sender, Receiver, Data, Type) VALUES (
									'$msg->sender', '$msg->receiver', '$msg->message', '$msg->type')";
								$query = mysqli_query(self::$conn, $sql);

								if ($query){
									echo "\r\n*** Data Saved ***\r\n";
								}
							}
							// send unmasked message to all connected clients
							$msg->clients = count($clients);
							self::send($clients,$waitingClient,json_encode($msg));
						}
					}

					/********* Server side scripting ends *********/
				}
				else {
					# remove any disconnected client from clients list
					$findClient = array_search($waitingClient, $clients);
					unset($waitingClients[$findClient]);
					unset($clients[$findClient]);
					socket_close($waitingClient);
				}
			}
		}
		socket_close(self::$socket);
		self::console_log("Socket Status:: Socket connection closed!");
	}

	public static function cleanData($data){
		$i = 0;
		$output = "";
		for ($i;$i < strlen($data); $i++) { 
			if (ord($data[$i]) < 127 && ord($data[$i]) > 31){
				$output .= $data[$i];
			}
		}
		return $output;
	}
	/** Client - Server Handshake Method **/
	public static function handShake($client, $header){
		$headers = preg_split('/\r\n/', $header);
		// Construction server response
		$res = ['HTTP/1.1 101 Web Socket Protocol Handshake'];
		foreach ($headers as $hkey => $hvalue) {
			$match = explode(':', $hvalue);
			if (!empty($match[1])){
				if (count($match) == 2){
					$res[$match[0]] = trim($match[1]);
				}
				else if (count($match) < 2){
					$res[] = $match[0];
				}
			}
		}
		// remove unwanted headers
		unset($res['Sec-WebSocket-Extensions']);
		unset($res['Host']);
		// encryption
		$key = $res['Sec-WebSocket-Key'].self::getkey();
		$key = base64_encode(sha1($key, true));
		$res['Sec-WebSocket-Accept'] = $key;
		$response = "";
		foreach ($res as $rkey => $rvalue) {
			$response .= "$rkey: $rvalue\r\n";
		}
		self::call(socket_write($client, $response."\r\n", 1024));
		return true;
	}

	/** seal data method **/
	public static function seal($socketData) {
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($socketData);

		if ($length > 0){
			if($length <= 125)
				$header = pack('CC', $b1, $length);
			elseif($length > 125 && $length < 65536)
				$header = pack('CCn', $b1, 126, $length);
			elseif($length >= 65536)
				$header = pack('CCN', $b1, 127, $length);
			return $header.$socketData;
		}
	}

	/** Unmask data method **/
	public static function unmask($payload){

		if (!empty($payload)){

			$len = ord($payload[1]) & 127;
		
			if ($len == 126){
				$mask = substr($payload, 4, 4);
				$data = substr($payload, 8);
			}
			else if ($len == 127){
				$mask = substr($payload, 10, 4);
				$data = substr($payload, 14);
			}
			else {
				$mask = substr($payload, 2, 4);
				$data = substr($payload, 6);
			}
			$val = "";
			for ($i=0;$i<strlen($data);++$i){
				$val .= $data[$i] ^ $mask[$i%4];
			}
			return $val;
		}
	}
	
	/** Method to send messages to all connected clients **/
	public static function send($clients,$sender,$msg):void {
		foreach ($clients as $client) {
			socket_write($client, self::seal($msg), strlen(self::seal($msg)));
		}
	}

	/** Call method to debug errors on functions **/
	public static function call($func) {
		return $func or die(socket_strerror(socket_last_error()));
	}

	/** Method for logging errors and results, set $webconsole to true to display on browser console **/
	/** set dump to true to use the var_dump on arrays or json **/
	public static function console_log($input, $dump=false, $webconsole=false):void {
		if ($webconsole == true){
			echo @"<script>console.log(\"".html_entity_decode("\r\n&ensp;$input \r\n&ensp;&raquo; Time: ".date('h:i:s'))."\");</script> \r\n";
		}
		else {
			echo html_entity_decode("\r\n&ensp;$input \r\n&ensp;&raquo; Time: ".date('h:i:s'))."\r\n";
		}
		if ($dump==true){
			echo "\r\n&ensp;**** Var Dump **** \n";
			@var_dump($input);
			echo "\r\n";
		}
	}
}
?>
