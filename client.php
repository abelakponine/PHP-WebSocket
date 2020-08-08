<!Doctype html>
<head>
	<title>Zuppy Websocket | TestNet</title>
</head>
<body>
	<p><textarea id="input" name="text_input" style="width:360px;height:100px;">Type your message..</textarea></p>
	<button onclick="send(input.value)">Send Message</button> <button onclick="send('{{%disconnect%}}')">Disconnect Sever</button>

	<script src="websocket/zws.js" type="text/javascript"></script>
</body>
</html>