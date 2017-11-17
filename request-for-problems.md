# Task specification

Tests are written in [YAML](http://yaml.org), a human-readable data specification language.
JSON is a valid subset of YAML, so feel free to use JSON if you are confused by YAML syntax.
And yes, I know we don't teach JSON or YAML in our core curriculum. I was never taught them either.
They are worth learning, even if not necessarily worth spending time teaching.

A task is defined as an object (the YAML/JSON equivalent of a map or dict) with both required and optional fields.
A minimal example looks like

````yaml
description: >
  Write a function named *next* that,
  given an integer *n*,
  returns the next larger integer.
topics: [function]
solution: |
  def next(n):
    return n+1
func: next
args:
  - [0]
  - [-1]
  - [1110]
  - [1881160119418754]
  - [-3155692597]
````

## Required Fields

-   A `description` string.  This may include embedded [Markdown](http://daringfireball.net/projects/markdown/syntax) for styling.
-   A list of `topics`.  For this pilot study, that should be `[lists]`.
-   A `solution`.  This must be valid Python code that solves the problem correctly.  It needn't be pretty.
-   A `func`tion name, unless the task is to write a program instead of a function.  This pilot study should only have functions, not programs, so always include a `func`tion name.
-   Some way of specifying test cases (see [Specifying test cases] below).

## Optional Fields

-   `exact: False`{.yaml} to indicate that output and return comparisons should not be performed by default.

-   `maychange: True`{.yaml} to give students permission to modify the contents of arguments if they so desire; without this modifying arguments is an automatic fail.

-   `mustchange: True`{.yaml} to have an implict constraint added that requires student code post-invocation arguments to be `==` to reference-code post-invocation arguments.

-   `random` to make a parameterized problem family.
    The value of `random` is an object describing one or more random variables;
    they may be used (offset with `$`s) in other parts of the problem.

    ````yaml
    random:
      b:
        range: [-2,3]
        include: [-1000, 10, 100]
        exclude: [0]
      num:
        range: [2,5]
    description: >
        Write a program that prints a sequence of numbers,
        starting with 0 and adding $b$ to it each time,
        for a total of $num$ times.
    solution: |
        x = 0
        for i in range($num$):
            print(x)
            x += $b$
    ````
    
    Each random variable must be defined with either `range` or `include` (or both) and may optionally also `exclude` some elements of the range.
    Only integer random variables are supported at this time.

-   a list of permitted `imports` (by default, all `import` statements are banned; any you want to permit need to be listed explicitly)

    ````yaml
    imports: [math, re]
    ````

-   `constraints` on code behavior, each taking the form of either a single python expression or the body of a python function with access to the variables `outputs` and `retval` (see [On `outputs`] below). May be paired with a `message` to present a particular message to the user if the constraint is violated.

    ````yaml
    constraints:
      - "type(retval) is str"
      - 
        rule: |
          if retval in outputs:
            return False
          else:
            return outputs.index(reval) > 0
        message: "You should print what is returned after the first input() is run"
    ````


-   constraints on souce code, including
    
    -   `ban: [prohibited, identifiers]`{.yaml} to prevent the use of particular identifiers (such as `abs`, `print`, or `sqrt`).

    -   `recursive: True`{.yaml} to require a recursive function in the source code.

    -   `loops: False`{.yaml} to prohibit the use of `while`{.python} and `for`{.python}.

    -   `ast: |` followed by a function that takes an `ast` node as its argument and either raises a `ValueError` with a user-centric message string or does not do so.
        
        Note: `ast` is currently untested and likely will not work properly.  If you need it for your problem, email Prof. Tyconievich with the code you want to work and he'll prioritize implementing it.
    
    ````yaml
    src:
      ban:
        - pow
        - log
      recursive: True
      loops: False
      ast: |
        def no_pow(node):
            import ast
            for f in ast.walk(node):
                if isinstance(f, ast.Pow):
                    raise ValueError("use of ** prohibited")
    ````

## Specifying test cases

Many different specification formats are supported.

### Available variables

Each test case specifies some subset of

-   `args`, a list of values to be passed to the `func`tion as positional arguments
-   `kwargs`, a dict of named values to be passed to the `func`tion as keyword aguments
-   `inputs`, a list of things to simulate the user typing in response to each `input(...)` command
-   `retval`, the value returned by the `func`tion
-   `outputs`, a list of things printed with `print` and `input` separated by inputs.
-   `predicate`, a function body with parameters `retval`, `outputs`, `args`, and `kwargs`. The `args` and `kwargs` passed to a `predicate` are the post-invocation values, to permit checking functions that are supposed to modify their arguments.

Each case may also specify

-   `name`, shown to the user to describe the test case,
    defaulting to a representation of `args`, `kwargs`, and `inputs`
-   `message`, shown to the user if the test fails, defaulting to 
    `"wrong result"` for `predicate`s and `constraints`, 
    `"returned wrong value"` for `retval`s, and 
    `"printed wrong text"` for `outputs`.

#### On `outputs`

`outputs` always has exactly one more element than `inputs` and is split around inputs,
so the following program

````python
print("Hi!")
ignore = input("Who are you? ")
print("Nice to meet you.")
````

would have, as its `outputs`, `["Hi!\nWho are you? ", "Nice to meet you.\n"]`{.python}
Outputs is always at least one element long, so even

````python    
pass
````

will have, as its `outputs`, `[""]`{.python}

#### On missing values

If `args`, `kwargs`, and/or `inputs` are not specified, they default to `[]`{.python} or `{}`{.python}.

If one of `retval` and `outputs` is specified but not the other, the other is not checked.
If neither `retval` nor `outputs` is specified and `exact: False`{.yaml} is not specified either,
then both `retval` and `outputs` must be identical to those produced by the `solution`.
If neither `retval` nor `outputs` is specified and `exact: False`{.yaml} is specified,
neither `retval` nor `outputs` are checked except as required by `constraints`.


### Specification formats

Test cases may be specified in any of the following ways:

#### Explicit test cases

Each test case may include any subset of case specification fields:

````yaml
cases:
  - args: [1, -14]
    inputs: [3]
    outputs: "[None]*2"
  - inputs: ['']
    args: [2, 3]
    outputs: "['/.*[Nn]ame.*[:?] $/', None]"
    message: "prompt must end '? ' or ': '"
    name: "(formatting)"
  - inputs: ['1,234', '1234']
    args: [0, 0]
    predicate: "outputs.length == 3"
    message: "you should keep asking for input until a number is typed"
````

#### Constraints

Constraints are applied on all cases, regardless of other `outputs`, `retval`, and/or `predicate` checks.

````yaml
constraints:
  - rule: "type(retval) is int"
    message: "must return an integer"
````

#### Just `args` or `outputs`

If you simply want to say "it should match the reference for these cases",
you can list `args` or `inputs` as top-level fields of the test case.

<table><thead><tr><th>This input</th><th>is the same as this input</th></tr></thead>
<tbody><tr><td>
````yaml
args:
  - [0, 0]
  - [5, 0]
````
</td><td>
````yaml
cases:
  - args: [0, 0]
  - args: [5, 0]
````
</td></tr><tr></td>
````yaml
inputs:
  - ['']
  - ['so wise!']
````
</td><td>
````yaml
cases:
  - inputs: ['']
  - inputs: ['so wise!']
````
</td></tr></tbody></table>

## Comparisons

When providing `outputs` or `retval`s, a flexible comparison function is used.
If `w` is the wanted value (specified in the YAML file) and `g` is the got value (produced by user code),
all of the following will register as a match:

-   `w == g`
-   `w` is same/sub type of exception as `g`
-   casting `g` to `type(w)` makes `w == g` work
-   `abs(w-g) < 1e-6`
-   if `search` is a member of `w`, `w.search(g)` -- intended for compiled `re` objects
-   if `re.match("/.+/", w)`, strip the `/`s and from `w`, compile the rest as a regex, and run `w.search(g)`
-   if `w` is callable, `w(g)`
-   `w is None and type(g) is str`
-   `all(compare_result(w,g) for w,g in zip(w,g))`

