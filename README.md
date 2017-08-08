This project is currently in a pre-alpha stage of development.

The eventual design is expected to work as follows:

-   A Python daemon (`autotester.py`) watches
    
    -   A `tasks/` directory for YAML files defining tasks.
    
        New entries are loaded using `testmaker.py`.
        
    -   An `upload/submission/` directory for Python files solving tasks.

        New entries are tested, using the tests from `testmaker.py` which use `modwrap.py` to sandbox the code.
    
    Test results are written to a file and then moved to the `upload/result/` directory.

-   A set of PHP scripts
    -   handle user authentication
    -   interact with an sqlite database to track user success
    -   let user write code for a task, uploading submissions to `upload/submission/`
    -   use javascript to check `upload/result/` for results

The Python part of this is reasonably complete.
A few updates to support more corner-case task tests and wrap more unsafe imports are expected, but it is workable as it stands.

The other parts are still under design.
