<?php
$user = $_SERVER['PHP_AUTH_USER']; 
if ($_POST['code']) {
    file_put_contents("/tmp/pyractice_preview_$user.py", $_POST['code']);
    $result = 'failed to run code';
    if ($_GET['task']) {
        if (strpos($_GET['task'], '/') === FALSE && strpos($_GET['task'], "$user-") === 0) {
            if (file_exists("/opt/pypractice/upload/task-submission/$_GET[task]")) {
                $result = shell_exec(
                    'timeout -k 12s 10s '.
                    '/opt/python-latest/bin/python3 /opt/pypractice/testtask.py '.
                    "/opt/pypractice/upload/task-submission/$_GET[task] ".
                    "/tmp/pyractice_preview_$user.py 2>&1");
            } else if (file_exists("/opt/pypractice/upload/tasks/$_GET[task]")) {
                $result = shell_exec(
                    'timeout -k 12s 10s '.
                    '/opt/python-latest/bin/python3 /opt/pypractice/testtask.py '.
                    "/opt/pypractice/upload/tasks/$_GET[task] ".
                    "/tmp/pyractice_preview_$user.py 2>&1");
            }
        }
    }
    unlink("/tmp/pyractice_preview_$user.py");
    die($result);
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Python Practice Preview</title>
        <script type="text/javascript" src="ace.js"></script>
        <script type="text/javascript" src="js-yaml.js"></script>
        <script type="text/javascript" src="marked.js"></script>
        <style type="text/css">
        #wrapper { padding:1em; border-radius:1em; background:white; }
        body { background: #dddad0; }
        pre#timer { border: 1px solid black; }
        #editor { border:thin solid grey; min-height:5em; width:100%; font-size:100%; }
        td { padding:0.5ex; }
        tr.failed { background-color:#fdd; }
        tr.passed { background-color:#dfd; }
        </style>
        <script type="text/javascript">

<?php

$problem = null;
$task = "";
if ($_GET['task']) {
    if (strpos($_GET['task'], '/') === FALSE && strpos($_GET['task'], "$user-") === 0) {
        $task = basename(substr($_GET['task'], strlen("$user-")), '.yaml');
        if (file_exists("/opt/pypractice/upload/task-submission/$_GET[task]")) {
            $problem = file_get_contents("/opt/pypractice/upload/task-submission/$_GET[task]");
        } else if (file_exists("/opt/pypractice/upload/tasks/$_GET[task]")) {
            $problem = file_get_contents("/opt/pypractice/upload/tasks/$_GET[task]");
        }
    }
}
?>

function setText(text) {
    document.getElementById("timer").innerHTML = text;
}

function sendcode() {
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            var data = this.responseText;
            if (data.indexOf('{') < 0) {
                setText(data);
                return;
            }
            data = JSON.parse(data.substr(data.indexOf('{')));
            var res = document.getElementById("result");
            while(res.hasChildNodes()) res.removeChild(res.lastChild);
            var tr = res.insertRow();
            tr.insertCell();
            tr.insertCell().appendChild(document.createTextNode("correct"));
            tr.insertCell().appendChild(document.createTextNode(Math.floor(data["score"]*100/data['tests'].length)+'%'));
            for(var i=0; i<data['tests'].length; i+=1) {
                var test = data['tests'][i];
                tr = res.insertRow();
                tr.className = test.passed ? 'passed' : 'failed';
                tr.insertCell().appendChild(document.createTextNode(test.passed ? "✓": "✗"));
                tr.insertCell().appendChild(document.createTextNode(test.case));
                tr.lastChild.style.whiteSpace = 'pre-wrap';
                tr.insertCell().appendChild(document.createTextNode(test.message ? test.message : ''));
            }
            setText('ready');
        }
    }
    xhr.open("POST", document.location.href, true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send('code='+encodeURIComponent(editor.getValue()));
}

function connect() {
    <?php if ($problem) { ?>
    var user = "<?=$user?>";
    var task = "<?=$task?>";
    
    var problem = jsyaml.safeLoad(<?=json_encode($problem)?>);


    document.getElementById("content").innerHTML = "<h1>"+task+".py</h1><p>"+marked(problem.description)+"</p><div id='editor'></div><input id=\"send\" type=\"button\" onclick=\"sendcode()\" value=\"Submit Code\"></input><table id=\"result\"></table>";
    editor = ace.edit("editor");
    editor.setTheme("ace/theme/pycharm");
    editor.getSession().setMode("ace/mode/python");
    editor.setOptions({maxLines: Infinity});
    setText('ready');

    <?php } else { ?>

    setText('failed to load requested exercise');
    
    <?php } ?>
}
        </script>
    </head>
<body onLoad="connect()">
    <p>Note: the preview does not correctly handle parameterized problems, but the final version does.</p>
    <div id="wrapper">
        <div id="content"></div>
        <pre id="timer"></pre>
    </div>
</body>
</html>
