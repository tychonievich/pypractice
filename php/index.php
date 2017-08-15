<!DOCTYPE html>
<html>
<head>
    <title>Python Practice</title>
    <script type="text/javascript" src="ace.js"></script>
    <script type="text/javascript">//<!--
<?php
$user = $_SERVER['PHP_AUTH_USER'];
$token = bin2hex(openssl_random_pseudo_bytes(4)) . " " . date(DATE_ISO8601);
mkdir("/tmp/sessions");
file_put_contents("/tmp/sessions/$user", "$token");
?>

var socket;
var user = "<?=$user;?>";
var token = "<?=$token;?>";
var editor;
var task;
function connect()
{
    setText("connecting "+user+"...");
    var content = document.getElementById("content");
    socket = new WebSocket(getBaseURL() + "/ws");
    socket.onopen = function() {
        setText("connected. waiting for timer...");
        socket.send(JSON.stringify({user:user, session:token, 
            action:"status"}));
    }
    socket.onmessage = function(message) {
        var data = JSON.parse(message.data);
        var kind = data["."];
        delete data["."];
        if (kind == 'error') {
            console.log(data.private);
            setText('ERROR: ' + data.public);
        } else if (kind == "status") {
            var table = document.createElement("table");
            var head = document.createElement("thead");
            table.appendChild(head);
            head = head.insertRow();
            var th = document.createElement("th"); head.appendChild(th); th.innerText = "Topic";
            th = document.createElement("th"); head.appendChild(th); th.innerText = "Recommendation";
            var body = document.createElement("tbody");
            table.appendChild(body);
            for(var i=0; i<5; i+=1) {
                var begin = ["done", "check", "practice", "wait", "missing"][i];
                for(var key in data) {
                    if (data[key].startsWith(begin)) {
                        var tr = body.insertRow();
                        tr.insertCell().innerText = key;
                        tr.insertCell().innerText = data[key];
                        // tr.addEventListener('click', function(){socket.send(JSON.stringify({user:user, session:token, action:"practice", topic:key}));});
                        tr.setAttribute("onclick", 'socket.send(JSON.stringify({user:"'+user+'", session:"'+token+'", action:"practice", topic:"'+key+'"}))');
                    }
                }
            }
            content.innerHTML = '';
            content.appendChild(table);
        } else if (kind == "task") {
            task = data.task;
            document.getElementById("content").innerHTML = "<h1>"+data.task+".py</h1><p>"+data.desc+"</p><div id='editor'></div><input type=\"button\" onclick=\"sendcode()\" value=\"Submit Code\"></input><table id=\"result\"></table>";
            editor = ace.edit("editor");
            editor.setTheme("ace/theme/pycharm");
            editor.getSession().setMode("ace/mode/python");
            editor.setOptions({maxLines: Infinity});
        } else if (kind == "result") {
            var res = document.getElementById("result");
            while(res.hasChildNodes()) res.removeChild(res.lastChild());
            var tr = res.insertRow();
            tr.insertCell();
            tr.insertCell().appendChild(document.createTextNode("correct"));
            tr.insertCell().appendChild(document.createTextNode(Math.floor(data["score"]*100)+'%'));
            for(var i=0; i<data['tests'].length; i+=1) {
                var test = data['tests'][i];
                tr = res.insertRow();
                tr.className = test.passed ? 'passed' : 'failed';
                tr.insertCell().appendChild(document.createTextNode(test.passed ? "&#x2713;": "&#2717;"));
                tr.insertCell().appendChild(document.createTextNode(test.case));
                tr.insertCell().appendChild(document.createTextNode(test.message ? test.message : 'Failed'));
            }
        } else {
            setText(kind + ": " + message.data);
        }
    }
    socket.onclose = function() {
        setText("connection closed.");
    }
    socket.onerror = function() {
        setText("Error!");
    }
}

function closeConnection()
{
    socket.close();
    setText("closed.");
}

function sendcode() {
    socket.send(JSON.stringify({user:user, session:token, 
        action:"submit",
        task:task,
        code:editor.getValue(),
    }));
}

function setText(text)
{
    document.getElementById("timer").innerHTML += "\n"+text;
}

function getBaseURL()
{
    var wsurl = "wss://" + window.location.hostname+':1110' // not ':'+window.location.port
    return wsurl;
}
    //--></script>
    <style>
        pre#timer {
            border: 1px solid black;
        }
        #editor {
            border:thin solid grey;
            min-height:5em;
            width:100%;
            font-size:100%;
        }
    </style>
</head>
<body onLoad="connect()">
    <p>The following box should show a running counter, updated by the server:</p>
    <pre id="timer"></pre>
    <button type="button" onclick="closeConnection()">Close connection</button>
    <div id="content"></div>
</body>
</html>
<?php

?>
