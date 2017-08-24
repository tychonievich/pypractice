'''
This file is intended as a tester for tasks:
it takes a task.yaml and a task.py and tells if the task passes the task.
It is not intended to be used in production, as it is not efficient and does not implement timeouts.

If you get complaints about not being able to import yaml, try running

````bash
pip3 install PyYAML
````

The testing harness adds a comment to the first line of each submission specifying the random parameters, like

````python
# {"x": 3, "num": -1000}
````

If you omit this line, it will assume there are not parameters
'''


from sys import argv
from os.path import exists
import autotester, testmaker, yaml, json

if len(argv) != 3 or not argv[1].endswith('.yaml') or not argv[2].endswith('.py'):
    print('USAGE: python3', argv[0], 'path/to/task.yaml', 'path/to/implementation.py')
    exit(1)
if not exists(argv[1]):
    print('No such file or directory:', argv[1])
    exit(2)
if not exists(argv[2]):
    print('No such file or directory:', argv[2])
    exit(3)


with open(argv[1]) as f: t = testmaker.Tester(yaml.safe_load(f))
print('loaded task', argv[1])

params = {}
with open(argv[2]) as f: line1 = f.readline()
if line1.startswith('#'):
    try:
        params = ast.literal_eval(line1[1:].strip())
        if type(params) is not dict: params = {}
    except:
        params = {}

result = t.test(argv[2], params)

data = {'score':0, 'tests':[]}
if isinstance(result, BaseException):
    data['tests'].append({
        'passed':False, 
        'case':'Code check', 
        'message':result.__class__.__name__+': '+str(result),
        'details':repr(result),
    })
else:
    for case in result:
        data['tests'].append({
            'passed':case[0], 
            'case':case[1], 
            'message':case[2],
            'details':repr(case[3:]),
        })
        if case[0]: data['score'] += 1

print(json.dumps(data, indent=2))
