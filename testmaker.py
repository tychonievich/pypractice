import modwrap, yaml

'''
The main goal of this over testmaker.py is

parse task/whatever.yaml into a Test object
    
    description:    string
    solution:       string that can be exec'd
    func:           function name or None for module test
    imports:        list of permitted module names
    recursive:      True
    loops:          False
    ban:            list of strings to ban at tokenization
    ast:            list of [predicate(ast) -> None or raise Exception]
    re:             list of regular expressions that need to search() positively
    ban_re:         list of regular expressions that need to not search() positively
    constraints:    list of predicates, each either
        - string s -- used as eval(s, {'retval':, 'output':, 'args':, 'kwargs':, 'input':}) -> True/False
        - dict {rule:..., message:...} -- rule is eval'd as above, custom error message on failure
    args:           list of lists
    cases:          list of dicts {args:[], kwargs:{}, inputs:[], outputs:[], retval:any, predicate:code}
    random:         dict of {name:{range:[min,max], exclude:[], include:[]}}
    
    
handle random parameterization
    add parameters as globals (prepended with ___ to avoid collisions)
    solution:
        replace $x$ with ___x
        compile to a code object using wrapped builtins like user code
        exec user and solution, compare results
    outputs:
        if none, skip check
        if list, use as-is
        if string, replace $x$ with ___x and eval(...) using wrapped builtins
    retval:
        if none, skip check
        if str and replacing $x$ with ___x changes it, eval()
        else, use as-is
    predicate:
        the string body of a function to be called with ___x globals and (outputs, retval) arguments
        hence, always replaced, exec'd, and then called
    constraints:
        always replaced and eval'd

could also wrap modules in functions
    - reduces two cases to one
    - changes which variables end up in globals()
    - changes legality of bare return

'''

def req_recursion(node):
    '''An ast predicate tshat requires a recursive function somewhere in the tree'''
    # recursion occurs when a node invokes itself
    # that requires a depth-first traveral, not just a random walk
    import ast, _ast
    fstack = []
    recursive = set()
    def visit(node, dep=0):
        if isinstance(node, _ast.FunctionDef): fstack.append(node.name)
        if isinstance(node, _ast.Call) and isinstance(node.func, _ast.Name) and node.func.id in fstack:
            recursive.add(node.func.id)
        for n in ast.iter_child_nodes(node):
            visit(n, dep+2)
        if isinstance(node, _ast.FunctionDef): fstack.pop()
    visit(node)
    if len(recursive) == 0:
        raise ValueError('required a recursive function')
def no_loops(node):
    '''An ast predicate that requires no loops (for, while, or async for)'''
    import ast, _ast
    for f in ast.walk(node):
        if isinstance(f, (_ast.AsyncFor, _ast.For, _ast.While)):
            raise ValueError('use of loops prohibited')
def ban(*names):
    '''An ast predicate maker to prohibit particular variable, module, and function names (slightly more forgiving that banning lexemes)'''
    def ans(node):
        '''An ast predicate to prohibit particular variable, module, and function names (slightly more forgiving that banning lexemes)'''
        import ast, _ast
        for f in ast.walk(node):
            if isinstance(node, _ast.Name) and node.id in names:
                raise ValueError('use of name "'+node.id+'" prohibited')
            elif isinstance(node, _ast.Attribute) and node.attr in names:
                raise ValueError('use of name "'+node.id+'" prohibited')
                
    return ans
    

def ex_msg(ex):
    '''turns an exception into a one-line and multi-line message.
    Skips the top-level invocation, which is always from the testing harness.'''
    msg = ''
    tr = ex.__traceback__.tb_next
    line = None
    fname = None
    while tr is not None:
        msg += '  File "'+tr.tb_frame.f_code.co_filename+'", line '+str(tr.tb_lineno)+'\n'
        line = tr.tb_lineno
        fname = tr.tb_frame.f_code.co_filename
        tr = tr.tb_next
    msg += ex.__class__.__name__+': ' + str(ex)
    # -1 to line to move past hidden parameterization comment
    if fname is not None and fname.endswith('testmaker.py'):
        smsg = 'test harness raised '+ex.__class__.__name__+':\n  '+str(ex)
    else:
        smsg = 'raised '+ex.__class__.__name__+(' on line '+str(line-1) if line is not None else '')+': '+str(ex)
    return smsg, msg

def case_str(case):
    ans = ''
    if case.get('args',()):
        ans = ', '.join(repr(_) for _ in case['args'])
    if case.get('kwargs',{}):
        if len(ans) > 0: ans += ', '
        ans += ', ',join(_k+'='+repr(_v) for _k,_v in case['kwargs'].items())
    if case.get('inputs',()):
        if len(ans) > 0: ans += '\n'
        ans += '\n'.join('input '+str(_+1)+': '+str(case['inputs'][_]) for _ in range(len(case['inputs'])))
    if ans.startswith('input 1:') and 'input 2:' not in ans:
        ans = ans[9:]
    return ans

def compare_result(wanted, got):
    '''A flexible comparison function
    w == g
    w is same/sub type of exception as g
    casting g to type(w) makes w == g work
    abs(w-g) < 1e-6
    w.search(g)
    if re.match("/.+/", w), treat w as regex and w.search(g)
    w(g)
    w is None and type(g) is str
    all(compare_result(w,g) for w,g in zip(w,g))
    '''
    if wanted == got: return True
    if wanted is None and got == ['']: return True
    if isinstance(wanted, BaseException):
        return isinstance(got, type(wanted))
    try:
        if type(got) is str and 'search' in dir(wanted) and wanted.search(got) is not None:
            return True
    except: pass
    try:
        if abs(wanted - got) < 1e-6: return True
    except: pass
    if type(got) is str and wanted is None: return True
    try:
        if type(wanted) in (tuple, list) and len(wanted) == len(got):
            return all(compare_result(w,g) for w,g in zip(wanted, got))
    except: pass
    if type(wanted) is str and type(got) is str:
        if wanted.strip() == got.strip(): return True
        if len(wanted) > 2 and wanted[0] == '/' and wanted[-1] == '/':
            import re
            if re.search(wanted[1:-1], got) is not None: return True
    if callable(wanted):
        try:
            if wanted(got): return True
        except: pass
    try:
        if type(wanted)(got) == wanted: return True
    except: pass
    return False

def run(exe, funcname=None, inputs=None, args=(), kwargs={}, params=None):
    co,tree,gl,io = exe
    if params: gl.update(params)
    if inputs is not None: io.reset(inputs)
    exec(co, gl)
    if funcname:
        if funcname not in gl:
            raise NameError('no function named '+funcname)
        elif not callable(gl[funcname]):
            raise ValueError(funcname+' is not a function')
        retval = gl[funcname](*args, **kwargs)
    else:
        retval = None
    return retval, io.outputs

def asfunc(src, header):
    if 'return' not in src:
        src = src.strip()
        src = src.rsplit('\n', 1)
        src = ''.join(src[:-1])+'\nreturn '+src[-1]
    return header.strip(' :')+':\n    ' + src.strip().replace('\n', '\n    ')

class Tester:
    def __init__(self, obj):
        if 'random' in obj:
            self.params = tuple(obj['random'].keys())
        else:
            self.params = ()
        self.allow = obj.get('imports', ())
        self.banned = obj.get('ban', ())
        self.astchecks = (
            ((req_recursion,) if obj.get('recursive') is True else ()) +
            ((no_loops,) if obj.get('loops') is False else ()) +
            obj.get('ast', ())
            # to do: add re, ban_re, recursive: False, loops: True
        )
        if 'solution' in obj:
            self.solution = self.compile(obj['solution'], params=self.params)
        self.func = obj.get('func')
        self.exact = obj.get('exact', True)
        self.cases = []
        for case in obj.get('cases', ()):
            ans = {} #{'args':(), 'kwargs':{}, 'inputs':(), 'outputs':None, 'retval':None}
            ans.update(case)
            if type(ans.get('outputs')) is str:
                ans['outputs'] = self.compile(asfunc(ans['outputs'], 'def printed()'), params=self.params)
            if type(ans.get('predicate')) is str:
                ans['predicate'] = self.compile(asfunc(ans['predicate'], 'def predicate(retval, outputs)'), params=self.params)
            if 'name' not in ans: ans['name'] = case_str(ans)
            self.cases.append(ans)
        for args in obj.get('args', ()):
            ans = {'args':tuple(args)}
            if 'name' not in ans: ans['name'] = case_str(ans)
            self.cases.append(ans)
        for inputs in obj.get('inputs', ()):
            ans = {'inputs':tuple(inputs)}
            if 'name' not in ans: ans['name'] = case_str(ans)
            self.cases.append(ans)
        self.constraints = []
        for constraint in obj.get('constraints', ()):
            ans = {}
            if type(constraint) is str:
                ans['rule'] = self.compile(asfunc(constraint, 'def rule(retval, outputs)'), params=self.params)
            else:
                ans['rule'] = self.compile(asfunc(constraint['rule'], 'def rule(retval, outputs)'), params=self.params)
                if 'message' in constraint: ans['message'] = constraint['message']
            self.constraints.append(ans)

    def compile(self, src, filename='solution', mode='exec', params=()):
        for k in params:
            src = src.replace('$'+k+'$', '___'+k)
        return modwrap.safe_execable(filename, code=src, imports=self.allow, ban_tokens=self.banned, allow=tuple('___'+_ for _ in params))


    
    def test(self, filename, params={}):
        try:
            params = {'___'+k:v for k,v in params.items()}
            user = modwrap.safe_execable(filename, imports=self.allow, ban_tokens=self.banned)
            for check in self.astchecks:
                check(user[1]) # should throw an exception on violation
            
            if 0:
                if self.func:
                    
                    exec(co, gl)
                    if self.func not in gl:
                        raise NameError('no function named '+self.func)
                    elif not callable(gl[self.func]):
                        raise ValueError(self.func+' is not a function')
                    func = gl[self.func]
                else:
                    gl['__name__'] = '__main__'
            
            results = []
            for case in self.cases:
                try:
                    uo, go = [], []
                    try:
                        ur, uo = run(user, self.func, case.get('inputs'), case.get('args',()), case.get('kwargs',{}))
                    except BaseException as ex:
                        ur = ex
                    try:
                        gr, go = run(self.solution, self.func, case.get('inputs'), case.get('args',()), case.get('kwargs',{}), params)
                    except BaseException as ex:
                        gr = ex
                    
                    if isinstance(ur, BaseException) and not isinstance(gr, BaseException):
                        raise ur
                    
                    if len(user[3].inputs) > 0:
                        results.append((False, case['name'], 'inputs '+ str(user[3].inputs)+' unread', case, ur, uo))
                        continue
                    
                    # run constraints first (they can have specialized messages)
                    failed = False
                    for con in self.constraints:
                        r,o = run(con['rule'], 'rule', None, (ur, uo), {}, params)
                        if r is False:
                            results.append((False, case['name'], con.get('message', 'wrong result'), case, ur, uo))
                            failed = True
                    if failed: continue
                    
                    # if there is a predicate, use that
                    # if not but there is retval and/or outputs, use them, running them if needed
                    # else if exact, compare ur and gr, uo and go
                    if 'predicate' in case:
                        r,o = run(case['predicate'], 'predicate', None, (ur, uo), {}, params)
                        if r == False:
                            results.append((False, case['name'], case.get('message', 'wrong result'), case, ur, uo))
                            continue
                    elif 'retval' in case or 'outputs' in case:
                        if 'retval' in case and not compare_result(case['retval'], ur):
                            results.append((False, case['name'], case.get('message', 'returned wrong value'), case, ur, uo))
                            continue
                        if 'outputs' in case:
                            rv = case['outputs']
                            if type(rv) is tuple:
                                rv = run(rv, 'printed', params=params)[0]
                            if not compare_result(rv, uo):
                                results.append((False, case['name'], case.get('message', 'printed wrong text'), case, ur, uo))
                                continue
                    elif self.exact:
                        if not compare_result(gr, ur):
                            if isinstance(gr,BaseException) and not isinstance(ur,BaseException):
                                results.append((False, case['name'], 'expected to raise an Exception', case, ur, uo))
                            else:
                                results.append((False, case['name'], 'returned wrong value', case, ur, uo))
                            continue
                        if not compare_result(go, uo):
                            results.append((False, case['name'], 'printed wrong text', case, ur, uo))
                            continue

                    # if made it here, passed all tests
                    results.append((True, case['name'], None, case, ur, uo))

                except modwrap.TestPermissionError: raise
                except BaseException as ex:
                    if isinstance(case.get('retval'), BaseException):
                        results.append((True, u, None, case, ex, ['']))
                    else:
                        results.append((False,case['name'], ex_msg(ex)[0], case, ex, ['']))
            return results
        except BaseException as ex:
            return ex


