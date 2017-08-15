This project is currently in a alpha stage of development.

The eventual design is expected to work as follows:

-   A Python daemon (`autotester.py`) watches
    
    -   A `upload/tasks/` directory for YAML files defining tasks.
    
        New entries are loaded using `testmaker.py`.
        
    -   An `upload/submission/` directory for Python files solving tasks.

        New entries are tested, using the tests from `testmaker.py` which use `modwrap.py` to sandbox the code.
    
    Test results are written to a file and then moved to the `upload/result/` directory.

-   A set of PHP scripts
    -   handle user authentication
    -   use javascript for text editing and websockets

-   A vibe.d server
    -   backend for web sockets
    -   interfaces between python and web front-end using inotify
    -   implements in-memory database with append-only file persistence

A first draft of each is completed.  Many ideas are not yet achieved:

-   parameterized tasks are implemented in Python, but not D (shared opAssign is the problem)
-   the user interface is ugly and lacks the ability to return to the main page after submission
-   tracking completion is implemented in D, but untested
-   implicitly-exact function-style python tests should only check retval, not output (see type_nums.yaml)
-   ...
