
testers = {}

# task/name.yaml
def newtask(path):
    global testers
    import testmaker, yaml
    with open(path) as f: p = yaml.safe_load(f)
    t = testmaker.Tester(p)
    name = path.rsplit('/', 1)[-1].rsplit('.', 1)[0]
    testers[name] = t
    print('loaded new task', name, 'from', path)

# submission/mst3k/name/20170720T110423/name.py
# end with name.py so that error messages make more sense
# result/mst3k/name/20170720T110423/name.csv
def newcode(path):
    global testers
    import ast, csv, os
    dest = path.replace('submission/', 'result/', 1).rsplit('/', 1)[0]
    name = path.rsplit('/', 1)[-1].rsplit('.', 1)[0]

    # the following case prevents re-running the same test
    if os.path.exists(os.path.join(dest, name+'.csv')):
        return
    
    result = None
    if name not in testers:
        y = path.split('submission/',1)[0]+'task/'+name+'.yaml'
        if os.path.exists(path.split('submission/',1)[0]+'task/'+name+'.yaml'):
            print('reloading', name)
            newtask(y)
        else:
            result = SystemError('No tests found for "'+name+'"')
    if result is None:
        with open(path) as f: params = f.readline()
        try:
            assert params.startswith('#')
            params = ast.literal_eval(params[1:].strip())
            assert type(params) is dict
        except:
            result = SystemError('Testing harness error: '+params[1:].strip())
        if type(params) is dict:
            result = testers[name].test(path, params)

    # because vibe's DirectoryWatcher does not have a close_write listener,
    # we need to make the entire file elsewhere and the move it to the final destination
    tmploc = '.tmp_' + dest.replace('/', '_') + name + '.csv'
    endloc = os.path.join(dest, name + '.csv')
    os.makedirs(dest, mode=0o777, exist_ok=True)
    with open(tmploc, 'w') as f:
        w = csv.writer(f)
        w.writerow(['passed', 'test', 'message', 'diagnostics'])
        if isinstance(result, BaseException):
            w.writerow(['False', 'Code check', result.__class__.__name__+': '+str(result), repr(result)])
        else:
            for case in result:
                w.writerow(case[0:3] + (repr(case[3:]),))
    os.rename(tmploc, endloc)

class PIDWrap:
    import os
    count = 0
    limit = max(1,len(os.sched_getaffinity(0))) * 10
    objs = set()
    
    def __init__(self, limit, func, args=(), kargs={}):
        self.limit = limit
        self.func = func
        self.args = args
        self.kargs = kargs
        self._status = 'pending'
        self.status() # starts if there are processes to spare
        PIDWrap.objs.add(self)
    def begin(self):
        import resource, time
        self.pid = os.fork()
        if self.pid == 0:
            used = resource.getrusage(resource.RUSAGE_SELF)
            resource.setrlimit(resource.RLIMIT_CPU, (used.ru_utime+self.limit, -1))
            self.func(*self.args, **self.kargs)
            quit()
        else:
            self.killat = time.time() + 10*self.limit
            self._status = 'running'
            PIDWrap.count += 1
    def status(self):
        import time
        if self._status == 'pending' and PIDWrap.count < PIDWrap.limit:
            self.begin()
        if self._status == 'running':
            ans = os.waitid(os.P_PID, self.pid, os.WEXITED | os.WNOHANG)
            if ans is None: 
                if time.time() >= self.killat:
                    os.kill(self.pid, 9)
                    os.waitpid(self.pid, 0)
                    self._status = 'wallclock timeout'
                else:
                    self._status = 'running'
            elif ans.si_status == 24: self._status = 'cpu timeout'
            elif ans.si_status != 0: self._status = 'crashed'
            else: self._status = 'finished'
            if self._status != 'running': PIDWrap.count -= 1
        return self._status


if __name__ == "__main__":

    import pyinotify as pin # to notice when new files are ready for testing
    import sys, os.path

    wm = pin.WatchManager()
    mask = pin.IN_CLOSE_WRITE | pin.IN_MOVED_TO | pin.IN_CREATE
    watched = wm.add_watch(os.path.dirname(sys.argv[0])+'/upload', mask, rec=True)

    class EventHandler(pin.ProcessEvent):
        '''Given a multiprocessing pool and a list,
        for each file event, requests the pool handle file
        and puts the request handler in the list'''
        def newfile(self, path):
            if 'submission/' in path:
                PIDWrap(1, newcode, (path,))
            elif 'task/' in path:
                newtask(path) # PIDWrap(1, newtask, (path,))
            else:
                pass # print('unexpected path name:', path)
        def process_default(self, event):
            if event.dir:
                wm.add_watch(event.pathname, mask, rec=True)
            elif not event.mask&(pin.IN_CREATE | pin.IN_IGNORED):
                # to-do: handle problem definition files and submissions differently
                self.newfile(event.pathname)
            # else ignore
            

    handler = EventHandler()
    notifier = pin.Notifier(wm, handler)
    
    for d, sds, fns in os.walk('upload/task'):
        for fn in fns:
            if fn.endswith('.yaml'):
                handler.newfile(os.path.join(d,fn))
    for d, sds, fns in os.walk('upload/submission'):
        for fn in fns:
            if fn.endswith('.py'):
                handler.newfile(os.path.join(d,fn))
    
    
    while True:
        evts = False
        if PIDWrap.count == 0:
            evts = notifier.check_events() # block
        else:
            evts = notifier.check_events(timeout=100) # 100 ms = 0.1 sec
        if evts:
            notifier.read_events() # runs handler for all events in queue
            notifier.process_events()
        for obj in tuple(PIDWrap.objs):
            if obj.status() not in ['running', 'pending']:
                # print(obj.args, obj.status())
                PIDWrap.objs.remove(obj)