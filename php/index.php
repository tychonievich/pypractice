<!DOCTYPE html>
<html>
<head>
    <title>Python Practice</title>
    <script type="text/javascript" src="ace.js"></script>
    <script type="text/javascript">//<!--
<?php
$user = $_SERVER['PHP_AUTH_USER'];
$token = bin2hex(openssl_random_pseudo_bytes(4)) . " " . date(DATE_ISO8601);
mkdir("/opt/pypractice/upload/sessions");
file_put_contents("/opt/pypractice/upload/sessions/$user", "$token");
?>

var socket;
var user = "<?=$user;?>";
var token = "<?=$token;?>";
var editor;
var task;
var done;

function connect()
{
    setText("connecting "+user+"...");
    var content = document.getElementById("content");
    socket = new WebSocket(getBaseURL() + "/ws");
    socket.onopen = function() {
        setText("connected. waiting for update from server");
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
        } else if (kind == "view") {
            var table = document.createElement("table");
            var head = document.createElement("thead");
            table.appendChild(head);
            head = head.insertRow();
            var th = document.createElement("th"); head.appendChild(th); th.innerText = "Topic";
            th = document.createElement("th"); head.appendChild(th); th.innerText = "Recommendation";
            th = document.createElement("th"); head.appendChild(th); th.innerText = "Check Off";
            var body = document.createElement("tbody");
            table.appendChild(body);
            for(var i=0; i<5; i+=1) {
                var begin = ["done", "check", "practice", "wait", "missing"][i];
                for(var key in data) {
                    if (data[key].startsWith(begin)) {
                        var tr = body.insertRow();
                        tr.insertCell().innerText = key;
                        tr.insertCell().innerText = data[key];
                        tr.insertCell().innerHTML = '<input type="button" value="'+(begin == 'done' ? 'Unc' : 'C')+'heck off mastery of '+key+'" onclick="setText(\'sending check-off to server...\'); socket.send(JSON.stringify({user:\''+user+'\', session:\''+token+'\', action:\''+(begin == 'done' ? 'un' : '')+'checkoff\', student:\''+data['.student']+'\', topic:\''+key+'\'}))">';
                    }
                }
            }
            content.innerHTML = '<img src="pics.php?filename='+data['.student']+'.jpg"/><p>You are viewing '+data['.name']+' ('+data['.student']+').  In addition to the options below, you may <input type="button" value="return to the students list" onclick="setText(\'requesting student list from server...\'); socket.send(JSON.stringify({user:\''+user+'\', session:\''+token+'\', action:\'status\'}))"> or <input type="button" value="log in as this student" onclick="setText(\'asking server to treat you as a student...\'); socket.send(JSON.stringify({user:\''+user+'\', session:\''+token+'\', asuser:\''+data['.student']+'\', action:\'status\'}))"> </p>';
            content.appendChild(table);
            setText('ready');
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
                        tr.setAttribute("onclick", 'setText("requesting task definition from server..."); socket.send(JSON.stringify({user:"'+user+'", session:"'+token+'", action:"practice", topic:"'+key+'"}))');
                    }
                }
            }
            content.innerHTML = '';
            content.appendChild(table);
            setText('ready');
        } else if (kind == "task") {
            done = false;
            task = data.task;
            document.getElementById("content").innerHTML = "<h1>"+task.replace(/[^\-]*-/, '')+".py</h1><p>"+data.desc+"</p><div id='editor'></div><input id=\"send\" type=\"button\" onclick=\"sendcode()\" value=\"Submit Code\"></input><table id=\"result\"></table>";
            editor = ace.edit("editor");
            editor.setTheme("ace/theme/pycharm");
            editor.getSession().setMode("ace/mode/python");
            editor.setOptions({maxLines: Infinity});
            setText('ready');
        } else if (kind == "result") {
            var res = document.getElementById("result");
            while(res.hasChildNodes()) res.removeChild(res.lastChild);
            var tr = res.insertRow();
            tr.insertCell();
            tr.insertCell().appendChild(document.createTextNode("correct"));
            tr.insertCell().appendChild(document.createTextNode(Math.floor(data["score"]*100)+'%'));
            for(var i=0; i<data['tests'].length; i+=1) {
                var test = data['tests'][i];
                tr = res.insertRow();
                tr.className = test.passed ? 'passed' : 'failed';
                tr.insertCell().appendChild(document.createTextNode(test.passed ? "✓": "✗"));
                tr.insertCell().appendChild(document.createTextNode(test.case));
                tr.lastChild.style.whiteSpace = 'pre-wrap';
                tr.insertCell().appendChild(document.createTextNode(test.message ? test.message : ''));
            }
            if (data.score >= 1) {
                done = true;
                document.getElementById('send').value = "Return to menu";
            }
            setText('ready');
        } else if (kind == "students") {
            var table = document.createElement("table");
            var head = document.createElement("thead");
            table.appendChild(head);
            head = head.insertRow();
            var th = document.createElement("th"); head.appendChild(th); th.appendChild(document.createTextNode("ID"));
            th = document.createElement("th"); head.appendChild(th); th.appendChild(document.createTextNode("Name"));
            var body = document.createElement("tbody");
            table.appendChild(body);
            for(var i=0; i<data.students.length; i+=1) {
                var tr = body.insertRow();
                tr.insertCell().innerText = data.students[i][0];
                tr.insertCell().innerText = data.students[i][1];
                tr.setAttribute("onclick", 'setText("requesting student summary from server..."); socket.send(JSON.stringify({user:"'+user+'", session:"'+token+'", action:"view", student:"'+data.students[i][0]+'"}))');
            }
            content.innerHTML = '';
            content.appendChild(table);
            setText('ready');
        } else {
            setText(kind + ": " + message.data);
        }
    }
    socket.onclose = function() {
        setText("connection closed; reload page to make a new connection.");
    }
    socket.onerror = function() {
        setText("error connecting to server");
    }
}

function closeConnection()
{
    socket.close();
    setText("connection closed; reload page to make a new connection.");
}

function sendcode() {
    setText('code sent; awaiting reply from server...');
    if (done)
        socket.send(JSON.stringify({user:user, session:token, 
            action:"status",
        }));
    else
        socket.send(JSON.stringify({user:user, session:token, 
            action:"submit",
            task:task,
            code:editor.getValue(),
        }));
}

function setText(text)
{
    if (socket && socket.readyState >= socket.CLOSING) document.getElementById("timer").innerHTML = "connection closed; reload page to make a new connection.";
    else document.getElementById("timer").innerHTML = text;
}

function getBaseURL()
{
    var wsurl = "wss://" + window.location.hostname+':1110' // not ':'+window.location.port
    return wsurl;
}
    //--></script>
    <style>
        #wrapper { 
            padding:1em; border-radius:1em; background:white;
        }
        body { background: #dddad0; }
        pre#timer {
            border: 1px solid black;
        }
        #editor {
            border:thin solid grey;
            min-height:5em;
            width:100%;
            font-size:100%;
        }
        td { padding:0.5ex; }
        tr.failed { background-color:#fdd; }
        tr.passed { background-color:#dfd; }
    </style>
</head>
<body onLoad="connect()">
    <div id="wrapper">
        <div id="content"></div>
        <pre id="timer"></pre>
    </div>
</body>
</html>
<?php

?>
