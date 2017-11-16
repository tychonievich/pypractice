This project is currently in a alpha stage of development.

The eventual design is expected to work as follows:

-   A Python daemon (`autotester.py`) watches

    -   A `upload/tasks/` directory for YAML files defining tasks.
        New entries are loaded using `testmaker.py`.
    -   An `upload/submission/` directory for Python files solving tasks.
        New entries are tested, using the tests from `testmaker.py` which use `modwrap.py` to sandbox the code.
    
    Test results are written to a file and then moved to the `upload/result/` directory.

-   PHP and JavaScript

    -   handle user authentication
    -   text editing
    -   websockets
    -   display results

-   A vibe.d server

    -   backend for web sockets
    -   interfaces between python and web front-end using inotify
    -   implements in-memory database with append-only file persistence

Checklist:

| Status | Feature |
|:------:|:--------|
| done | grader (re)loads tasks dynamically |
| done | socket (re)loads tasks dynamically |
| under-tested | socket determines recommendations |
| done | php displays results tables |
| done | php allows submissions |
| done | php blocks double submissions |
| done | grader handles parameterization |
| done | socket handles parameterization |
| done | user can finish with assignment |
| pending | implicit testing of functions ignores output |
| pending | admin views |
| pending | dynamic addition of users |
| done | checkoff interface |
| done | test case writing interface |
| done | test case preview interface |
| pending | test case review by super-users |
