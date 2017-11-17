<!DOCTYPE html>
<html>
    <head>
        <style type="text/css">
            .element { vertical-align:middle; border:thin solid rgba(0,0,0,0.125); border-radius:1ex; }
            .null { opacity:0.25; }
            code, a.button { border:thin solid rgba(255,127,0,0.125); background: rgba(255,127,0,0.0625); }
            .deletion { padding:0em; background: rgba(255,0,0,0.25); border:none; }
            .addition { padding:0em; }
            .element, .extender { display:inline-table; }
            .radio-wrapper + .radio-wrapper { margin-left: 1em; }
            input[type="submit"], textarea { vertical-align: middle; }
            a.plain, a.button { text-decoration: none; color:inherit; }
        </style>
        <script type="text/javascript" src="sform2.js"></script>
        <script type="text/javascript" src="js-yaml.js"></script> <!-- https://github.com/nodeca/js-yaml -->
        <script type="text/javascript" src="tv4.js"></script> <!-- https://github.com/geraintluff/tv4 -->
        <script type="text/javascript">

var message = '';

<?php
$user = $_SERVER['PHP_AUTH_USER'];

if ($_POST['problem']) {
    if ($_POST['filename'] && strpos($_POST['filename'], '/') === FALSE) {
        echo "console.log(".json_encode($_POST['problem']).");\n";
        mkdir('/opt/pypractice/upload/task-submission');
        chmod('/opt/pypractice/upload/task-submission', 0777);
        file_put_contents("/opt/pypractice/upload/task-submission/$user-$_POST[filename].yaml", $_POST['problem']);
        
        ?>message = 'file <?="$user-$_POST[filename].yaml"?> received';<?php
        
        unset($_POST['problem']);
    } else {
        echo "alert('cannot save to file name \"$user-$_POST[filename].yaml\"');\n";
    }
}
if ($_GET['revisit']) {
    if (strpos($_GET['revisit'], '/') === FALSE && strpos($_GET['revisit'], "$user-") === 0) {
        if (file_exists("/opt/pypractice/upload/task-submission/$_GET[revisit]")) {
            $_POST['problem'] = file_get_contents("/opt/pypractice/upload/task-submission/$_GET[revisit]");
        } else if (file_exists("/opt/pypractice/upload/tasks/$_GET[revisit]")) {
            $_POST['problem'] = file_get_contents("/opt/pypractice/upload/tasks/$_GET[revisit]");
        } else {
            echo "alert('could not find \"$_GET[revisit]\"');\n";
        }
    } else {
        echo "alert('cannot view file name \"$_GET[revisit]\"');\n";
    }
}
?>

var user = "<?=$user;?>";

var schema = {
    type:'object',
    properties: {
        random: { 
            type:'object', 
            patternProperties:{
                '^[a-zA-Z]+$': { type:'object', properties:{
                    range: { type:'array', minItems:2, maxItems:2,  items: {type:'number'} },
                    include: {type:'array', items:{type:'number'}},
                    exclude: {type:'array', items:{type:'number'}},
                }}
            },
            summary:'Random parameters (which must be integers) can be referred to elsewhere by surrounding them with <code>$</code>; if <code>x</code> is a parameter and you type <code>repeated $x$ times</code> the student will see (e.g.) <code>repeated 3 times</code>',
        },
        description: { type:'string', summary:'Problem text to show student (may include markdown, like **<b>bold</b>**, *<i>italic</i>*, and `<code>code</code>`). Should specify program vs. function and if function, what function name.' },
        topics: { type:'array', items: {type:'string', enum:['list', 'for-while conversion', 'nested loops']}, minItems:1 },
        solution: { type:'string', summary:'Reference solution (in Python 3)' },
        func: { type:'string', summary:'The function to test; if blank, will run code as a program instead' },
        imports: { type:'array', items:{type:'string'}, summary:'Permitted imports; if empty, all imports are banned' },
        ban: { type:'array', items:{type:'string'}, summary:'Symbols that may not be used in solutions (single tokens: <code>if</code> and <code>(</code> can each be banned, but <code>if(</code> cannot)' },
        recursive: { type:'boolean', summary:'Require a recursive solution?', default:false },
        loops: { type:'boolean', summary:'Permit loops?', default:true },
        exact: { type:'boolean', summary:'Use reference solution to generate <code>retval</code> and <code>output</code> for test cases that do not specify them?', default:true },
        maychange: { type:'boolean', summary:'Allow modifying the contents of the arguments?', default:false },
        mustchange: { type:'boolean', summary:'Require student code to modify arguments the same way that the reference solution does?', default:false },
        constraints: { type:'array', items:{ oneOf:[
                {type:'string', summary:'rule with default message'}, // code
                {
                    type:'object',
                    properties: {
                        'rule': {type:'string'}, // code
                        'message': {type:'string'},
                    },
                    required: ['rule'],
                    summary: 'rule with custom message',
                }
            ]}, summary:'code run after every case before checking results; has access to variables <code>retval</code>, <code>output</code>, <code>args</code>, <code>kwargs</code>, and <code>input</code>; should result in a <code>bool</code>' },
        args: { type:'array', items:{type:'array'}, summary:'test case (argument only, checked by constraints and/or exact solution)' },
        inputs: { type:'array', items:{type:'array', items:{type:'string'}}, summary:'test case (input text only, checked by constraints and/or exact solution)' },
        cases: { type:'array', items:{
            type:'object',
            properties: {
                args:{type:'array'},
                kwargs:{type:'object'},
                inputs:{type:'array', items:{type:'string'}},
                outputs:{oneOf:[{type:'string', summary:'python expression generating list of outputs'}, {type:'array', summary:'list of outputs'}]}, // if string, is code Python will eval(...)
                retval:{},
                message:{type:'string', summary:'what to show on test failure'},
                name:{type:'string', summary:'how to identify this case'},
                predicate:{type:'string', summary:'the body of a Python function to be called with (outputs, retval) arguments'}, // code
            },
        }, summary:'full test cases' },
        // re:
        // ban_ban:
        // ast:
    },
    required: ['description', 'solution', 'topics'],
};
            function loader() {
                if (message) {
                    document.body.insertBefore(document.createTextNode(message), document.body.firstChild);
                }
                var thing = anyElement(schema);
                thing.id = 'form';
                thing.onkeyup = checker;
                thing.onchange = checker;
                document.body.insertBefore(thing, document.getElementById('warnings').nextSibling);
                <?php if ($_POST['problem']) { ?>
                document.getElementById('yaml').value = <?=json_encode($_POST['problem'])?>;
                reverse();
                <?php } else { ?>
                checker();
                <?php } ?>
            }
            function warn(msg) {
                if (msg) {
                    document.getElementById('warnings').innerText = msg;
                    document.getElementById('warnings').style.backgroundColor = 'pink';
                } else {
                    document.getElementById('warnings').innerText = '';
                    document.getElementById('warnings').style.backgroundColor = 'inherit';
                }
            }
            function checker() {
                var data = document.getElementById('form').getValue();
                document.getElementById('yaml').value = jsyaml.safeDump(data);
                yaml_validate();
            }
            function yaml_validate() {
                var lines = document.getElementById('yaml').value.split(/\n|\r\n?/g);
                if (document.getElementById('yaml').rows < lines.length)
                    document.getElementById('yaml').rows = lines.length;
                var wid = lines.map(function(x){return x.length;}).reduce(function(x,y){return x>y?x:y;});
                if (wid > 80) wid = 80;
                if (document.getElementById('yaml').cols <= wid)
                    document.getElementById('yaml').cols = 1 + wid;
                try {
                    var data = jsyaml.safeLoad(document.getElementById('yaml').value);
                } catch(e) {
                    warn(e);
                    return;
                }
                if (!data) { warn("invalid yaml entered"); return; }
                if (!tv4.validate(data, schema, true, true)) {
                    warn(tv4.error.dataPath+" "+tv4.error.message);
                } else {
                    warn();
                    return data;
                }
            }
            function reverse() {
                var data = yaml_validate();
                if (data)
                    document.getElementById('form').setValue(data);
            }

        </script>
    </head>
    <body onload="loader()">
        <p>New to the site? See <a href="#guidelines">the guidelines at the end of this page</a>.</p>
<h2>Previous submissions</h2>
<ul class="filelist">
<?php
$cnt = 0;
foreach(glob("/opt/pypractice/upload/task-submission/$user-*.yaml") as $path) {
    $name = basename($path);
    $when = date(DATE_COOKIE, filemtime($path));
    echo "<li><a class='plain' href='$_SERVER[SCRIPT_NAME]?revisit=$name'><code>$name</code></a> (modified $when) <a href='preview.php?task=$name' class='button'>preview student view</a></li>\n";
    $cnt += 1;
}
foreach(glob("/opt/pypractice/upload/tasks/$user-*.yaml") as $path) {
    $name = basename($path);
    $when = date(DATE_COOKIE, filemtime($path));
    echo "<li><a class='plain' href='$_SERVER[SCRIPT_NAME]?revisit=$name'><code>$name</code></a> (accepted) <a href='preview.php?task=$name' class='button'>preview student view</a></li>\n";
    $cnt += 1;
}
if ($cnt == 0) { echo '<li>You have not yet submitted a problem</li>'; }
?>
</ul>
<h2>Current proposal</h2>
        <form action="<?=$_SERVER['SCRIPT_NAME']?>" method="POST">
            <textarea id="yaml" onkeyup="yaml_validate()" onchange="reverse()" name="problem"></textarea>
            <table style="display:inline-table; vertical-align:middle;">
                <tr><td>Filename: <?=$user?>-<input type="text" name="filename" value="<?php
                
                if ($_GET['revisit']) echo basename(explode('-', $_GET['revisit'], 2)[1], '.yaml');
                
                ?>"></input>.yaml</td></tr>
                <tr><td><input type="submit"/> (can be edited and re-submitted after submission)</td></tr>
            </table>
        </form>
        <div id="warnings"></div>
<h2 id="guidelines">Guidelines</h2>
<p>
    To be accepted, a problem must meet the following criteria:
</p>
<ul>
    <li>Have a description suitable to show a student trying to solve the problem</li>
    <li>Have a (working) reference solution</li>
    <li>Have enough test cases (any mix of <code>args</code>, <code>inputs</code>, and <code>cases</code>) to detect the vast majority of incorrect implementations</li>
    <li>Be non-trivially distinct from any previously-accepted problem</li>
    <li>Take 5&ndash;10 minutes to solve by a competent but not amazing CS 111x alum</li>
    <li>Be on one (or more) of the topics provided</li>
    <li>Have no errors (neither an error message on this page, nor not-checked-here errors like invalid python code)</li>
</ul>
<p>There is an intent to pay $10 per accepted problem, up to 200 problems (in total, not per submitter), and funds to enable these payments have been received.  However, a process by which the payments may be made without scaring accounting has not yet been identified.  It may happen via gift cards or mailed checks or… TBD.</p>
<p>Both programs and functions are welcome.  We also welcome randomized problem families; problems with multiple acceptable results as checked by predicates and/or constraints; etc.</p>
<p>You can either edit the problem description directly as <a href="http://yaml.org/" target="_blank">YAML</a>, or use the form provided below the YAML box.  The two should update one another, but that is based on several hundred lines of raw JavaScript that I wrote in two days without much testing.  In the very likely event that there is a bug in that code, the YAML is what gets submitted, not the form.</p>


    </body>
</html>
