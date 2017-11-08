#!/bin/bash

function wss_start {
    # newgrp instructors # execs so lose context
    ps -A | grep ' 'pypractice_wss -q && return
    cd /opt/pypractice/vibe
    if [ "$(ls -t source | head -1)" -nt pypractice_wss ]; then dub build -b release >>buildlog 2>>buildlog; fi
    date >> ../upload/run.log
    nohup bash ../restart_on_segfault.sh ./pypractice_wss >>../upload/run.log 2>>../upload/run.log </dev/null &
}

function tester_start {
    cd /opt/pypractice
    if [ "$(ps -A u | grep 'python3 autotester.py' -c)" -ge 2 ]; then return; fi
    nohup python3 autotester.py >> upload/run.log 2>> upload/run.log </dev/null &
}

tester_start
wss_start
