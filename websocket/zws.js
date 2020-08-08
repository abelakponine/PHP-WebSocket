/*
* Zuppy Web Socket PHP-JS V 1.0
* Created by Abel Akponine
* Java Script File
* Github: https://github.com/abelakponine
* Instagram: @kingabel.a
*/
var ws;
var host = "127.0.0.1";
var port = 2021;

function startSocket(){
	ws = new WebSocket(`ws://${host}:${port}/`);
	ws.onopen=(e)=>{
		console.log("Connection Open: state::",ws.readyState);
	};
	ws.onmessage=(e)=>{
		console.log(e);
	};
	ws.onerror=(e)=>{
		if (ws.readyState == 3){
			console.log("Disconnected unexpectedtly.");
			console.log("Trying to reconnect...");
			wsReconnect();
		}
	};
}
function wsReconnect(){
	return startSocket();
}
function send(data){
	data = data ?? "Please! write a message.\r\n";
	try { ws.send(data); }
	catch(e) { console.log(e); }
}

startSocket();