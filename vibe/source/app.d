import vibe.d;
import vibe.data.json;
import vibe.core.file;
import std.functional : toDelegate;
import std.conv : to;
static import dyaml;

enum datadir = "/opt/pypractice/upload/"; // should contain at least sessions/ and tasks/ 
enum userlog = datadir ~ "users.log";
enum sublog = datadir ~ "submissions.log";



enum Role { student, ta, prof }

long timestamp() {
    import std.datetime : Clock;
    return Clock.currTime.toUnixTime;
}
string timestr() {
    import std.datetime : Clock;
    return Clock.currTime.toSimpleString;
}

struct User {
    string id;
    string name;
    Role role = Role.student;
    string picture;
    bool[string] mastered;
    Action[string] done; // indexed by task name; redoing task overwrites previous
    
    /**
     * returns recommendation[topic] for all topics, with five possible recommendations outlined below.
     * 
     * score/task = max(1, min(0, (time-5min)/30min))
     * weight = 1/min(1 + correct attempts in last 24hr, 10)
     * status = status * (1-weight) + score * (weight)
     * status drops 0.04 per hour (<1/24hr)
     * 
     * "done" if topic in mastered
     * "prereqs needed" if set of open problems < 50
     * "wait" if status > 1 and weight < 0.5
     * "checkoff" if status > 2 -and- all scores > 0.5 in last 48 hours -and- last score > 0.75 in last 24 hours
     * "practice" otherwise
     */
    string[string] status() shared {
        enum long min1 = 60, min5 = 5*60, min30 = 30*60, day = 24*60*60, day2 = 2*24*60*60;
        
        long now = timestamp;
        string[string] ans;
        foreach(topic, ref _; topics) {
            if (topic in mastered) { ans[topic] = "done"; continue; }
            if (!readyFor(topic)) { ans[topic] = "missing prereqs"; continue; }
            
            float[2][] attempts;
            foreach(k,ref v; done)
                if (v.times[0] > 0 && v.times[1] > v.times[0] && v.task in topics[topic])
                    attempts ~= [
                        cast(float)(v.times[0]), // time finished
                        cast(float)
                            (v.result < 1 ? -1 // didn't pass all test cases = failed
                            :v.times[1] - v.times[0] < min5 ? 1 // 5 min = aced
                            :v.times[1] - v.times[0] > min30 + min5 ? 0 // 35 min = timed out
                            :(v.times[1] - v.times[0] - min5) / cast(float)min30 // linear in between
                            ), // score -- based on time right now, may need to rethink later
                    ];
            if (attempts.length == 0) { ans[topic] = "practice"; continue; }
            
            { import std.algorithm.sorting : sort; sort(attempts); }
            float status = 0;
            bool ready = attempts[$-1][0] > now-day && attempts[$-1][1] < 0.75;
            float weight = 1.0;
            foreach(i,v; attempts) {
                if (v[0] > now-day2 && v[1] < 0.5) ready = false;
                float cor24 = 1.0f;
                foreach_reverse(v2; attempts[0..i]) 
                    if (v2[0] > now-day && cor24 > 0) cor24 += v2[1];
                status += v[1] / cor24;
                if (v[0] > now-day && v[1] > 0) weight += v[1];
                // if (v[1] < 0 && status > 1) status = 1;
            }
            if (ready && status > 2) ans[topic] = "it looks like you've mastered this topic!";//"check off with TA";
            else if (status > 1 && weight > 2) ans[topic] = "wait and revisit to build long-term memory";
            else ans[topic] = "practice";
        }
        return ans;
    }
    bool readyFor(string topic) shared {
        int good = 0, bad = 0;
        foreach(task, ref _; topics[topic])
            if (tasks[task].ready(this)) good += 1;
            else bad += 1;
        return good > bad || good > 50;
    }
    string[] suggested(string topic) shared {
        import std.array;
        // if readyFor(topic), list of ready tasks minus done tasks (up to half of list)
        // else, list of all tasks minus done tasks (up to half of list)
        int good = 0, bad = 0;
        foreach(task, ref _; topics[topic])
            if (tasks[task].ready(this)) good += 1;
            else bad += 1;
        if (good > bad || good > 50) {
            Appender!(string[]) ans;
            foreach(task, ref _; topics[topic])
                if (tasks[task].ready(this))
                    if (task !in done) ans ~= task;
            if (ans.data.length < 50 && ans.data.length <= good/2)
                foreach(task, ref _; topics[topic])
                    if (tasks[task].ready(this))
                        if (task in done) ans ~= task;
            return ans.data;
        } else {
            Appender!(string[]) ans;
            foreach(task, ref _; topics[topic])
                if (task !in done) ans ~= task;
            if (ans.data.length < 50 && ans.data.length <= good/2)
                foreach(task, ref _; topics[topic])
                    if (task in done) ans ~= task;
            return ans.data;
        }
    }
}
struct Task {
    string[] topics;
    string description;
    immutable(Param)[] parameters; 
    
    bool ready(const ref shared User me) shared {
        int missing = 0;
        foreach(topic; topics) if (topic !in me.mastered) missing += 1;
        return missing <= 1;
    }
    shared(Parameterization) parameterize() shared {
        shared Parameterization ans;
        foreach(shared const ref p; parameters)
            ans.pairs ~= p.pick;
        return ans;
    }
}
struct Action {
    string task;
    long[2] times;
    float result;
    shared Parameterization params;
    this(string task, long[2] times, float result, shared _nif[] p) shared {
        this.task = task;
        this.times[] = times[];
        this.result = result;
        this.params.pairs ~= p;
    }
}
struct Picker(T) {
    T[2] range;
    T[] include;
    T[] exclude;
    T pick() const {
        import std.random;
        if (range[1] <= range[0]
        || (include.length > 0 && uniform(cast(T)0, cast(T)(range[1]-range[0]-exclude.length+include.length)) < include.length))
            return include[uniform(0, include.length)];
        bool again = true;
        T ans;
        while(again) {
            again = false;
            ans = uniform(range[0], range[1]);
            foreach(i; exclude) if (i == ans) { again = true; break; }
        }
        return ans;
    }
}
struct Param {
    string name;
    bool integer;
    union {
        Picker!long i;
        Picker!double f;
    }
    void pick(ref Json ans) const {
        ans[name] = integer ? Json(i.pick) : Json(f.pick);
    }
    shared(_nif) pick() const {
        if (integer) return shared(_nif)(name, i.pick);
        else return shared(_nif)(name, f.pick);
    }
    this(in string n, in long[2] r, in long[] i, in long[] e) immutable {
        this.name = n;
        this.integer = true;
        this.i = immutable Picker!(long)(r, i.idup, e.idup);
    }
    this(in string n, in double[2] r, in double[] i, in double[] e) immutable {
        this.name = n;
        this.integer = false;
        this.f = immutable Picker!(double)(r, i.idup, e.idup);
    }
}
/**
 * named integer/float : just needed to permit shared map assignment
 */
struct _nif {
	string name;
	bool integer;
	union { long i; double f; }
	this(string n, in long i) { name = n; integer = true; this.i = i; }
	this(string n, in double f) { name = n; integer = false; this.f = f; }
	this(string n, in long i) shared { name = n; integer = true; this.i = i; }
	this(string n, in double f) shared { name = n; integer = false; this.f = f; }
	this(string n, in Json v) { name = n; value(v); }
	Json value() const shared { return integer ? Json(i) : Json(f); }
	void value(in Json v) { 
		integer = v.type == Json.Type.int_; 
		if (integer) i = v.get!long;
		else f = v.get!double;
	}
}
struct Parameterization {
	_nif[] pairs;
	
	Json toJson() const shared {
		Json ans = Json.emptyObject;
		foreach(const ref n; pairs)
			ans[n.name] = n.value;
		return ans;
	}
	static Parameterization fromJson(in Json src) {
		Parameterization ans;
		foreach(string k, const Json v; src) {
			ans.pairs ~= _nif(k, v);
		}
		return ans;
	}
    string toString() shared const {
        import std.array;
        Appender!string ans;
        char pre = '{';
        foreach(const ref n; pairs) {
            ans ~= pre; pre = ',';
            ans ~= '"'; ans ~= n.name; ans ~= `":`;
            if (n.integer) ans ~= to!string(n.i);
            else ans ~= to!string(n.f);
        }
        if (pre == '{') return "{}";
        ans ~= '}';
        return ans.data;
    }
    string removeDollars(string s) shared const {
        import std.array : replace;
        foreach(const ref n; pairs)
            s = s.replace("$"~n.name~"$", n.value.toString.replace(`-`,`−`));
        return s.replace("+ −", "− ");
    }
}



shared string[string] session_key;
shared User[string] users;
shared Task[string] tasks;
shared bool[string][string] topics; // topics['loops'] = ['task':true, 'task2':true, ...]

/**
 * The log is an append-only replay log of all actions taken.
 * Each line is a JSON object with a field ".".
 * Other information is dependent on the particular kind:
 * 
 * user
 *  id!
 *  name?
 *  role?
 *  picture? (base-64 encoded)
 *  mastered? -- gets added
 *  unmaster? -- gets removed
 * 
 * action
 *  user!
 *  task!
 *  start?
 *  stop?
 *  result?
 *  params
 * 
 * Tasks (and topics) are stored separately in individual YAML files; see readTask.
 */
bool readLogLine(R)(ref R range) {
    import std.base64;
    try {
        Json data = range.parseJson;
        if (data["."] == "user") {
            shared User ans;
            if (data["id"].get!string in users) ans = users[data["id"].get!string];
            else ans.id = data["id"].get!string;

            if ("name" in data) ans.name = data["name"].get!string;
            if ("role" in data) {
                auto tmp = data["role"].get!string;
                if (tmp == "student") ans.role = Role.student;
                else if (tmp == "ta") ans.role = Role.ta;
                else if (tmp == "prof") ans.role = Role.prof;
                else { logError("unknown role: "~tmp); return false; }
            }
            if ("picture" in data) ans.picture = data["picture"].get!string;
            if ("mastered" in data) ans.mastered[data["mastered"].get!string] = true;
            if ("unmaster" in data) ans.mastered.remove(data["unmastered"].get!string);
            
            users[data["id"].get!string] = ans;
            return true;
        } else if (data["."] == "action") {
            shared Action act;
            if (data["user"].get!string !in users)
                users[data["user"].get!string] = User.init;
            // FIXME: add parameterization
            shared User* user = &users[data["user"].get!string];
            if (data["task"].get!string in user.done) {
                shared Action* a = data["task"].get!string in user.done;
                act.task = a.task;
                act.times[] = a.times[];
                act.result = a.result;
                act.params = a.params;
            } else
                act.task = data["task"].get!string;
            if ("start" in data) act.times[0] = data["start"].get!long;
            if ("stop" in data) act.times[1] = data["stop"].get!long;
            if ("result" in data)  act.result = data["result"].to!float;
            if ("param" in data) {act.params.pairs.length = 0; act.params.pairs ~= Parameterization.fromJson(data["param"]).pairs;}
            
            user.done[data["task"].get!string] = act;
            return true;
        } else {
            logError("unknown action kind: " ~ to!string(range));
            return false;
        }
    } catch(JSONException ex) {
        logError("log parsing exception: " ~ to!string(range));
        return false;
    }
}

void readTask(string path, string name) {
    try {
        auto node = dyaml.Loader(path).load;
        foreach(k,v; topics) if (name in v) v.remove(name);
        shared string[] tp;
        foreach(string topic; node["topics"]) {
            tp ~= topic;
            topics[topic][name] = true;
        }
        immutable(Param)[] p;
        if ("random" in node) {
            foreach(string k,ref dyaml.Node v; node["random"]) {
                if (("range" in v) ? v["range"][0].isInt : v["include"][0].isInt) {
                    long[2] range;
                    long[] include, exclude;
                    range[0] = ("range" in v) ? v["range"][0].get!long : 0;
                    range[1] = ("range" in v) ? v["range"][1].get!long : 0;
                    if ("include" in v) foreach(long i; v["include"]) include ~= i;
                    if ("exclude" in v) foreach(long i; v["exclude"]) exclude ~= i;
                    p ~= immutable Param(k, range, include, exclude);
                } else {
                    double[2] range;
                    double[] include, exclude;
                    range[0] = ("range" in v) ? v["range"][0].get!double : 0;
                    range[1] = ("range" in v) ? v["range"][1].get!double : 0;
                    if ("include" in v) foreach(double i; v["include"]) include ~= i;
                    if ("exclude" in v) foreach(double i; v["exclude"]) exclude ~= i;
                    p ~= immutable Param(k, range, include, exclude);
                }
            }
        }
        tasks[name] = shared Task(tp, node["description"].get!string, p);
    } catch(dyaml.YAMLException ex) {}
}


shared static this() {
    
    {   // initially load log; don't do this threaded...
        import std.stdio;
        foreach(string line; File(userlog, "r").lines)
            readLogLine(line);
        logInfo("Users: " ~ to!string(users.keys));
    } {
        import std.file;
        foreach(string path; dirEntries(datadir ~ "tasks", SpanMode.shallow)) 
            if (path[$-4..$] == "yaml") {
                auto name = path[datadir.length+6..$-5];
                if (name[0] != '.') readTask(path, name);
            }
        logInfo("Tasks: " ~ to!string(tasks.keys));
        logInfo("Topics: " ~ to!string(topics));
    }
        

    auto settings = new HTTPServerSettings;
    settings.port = 1110;
    settings.hostName = "archimedes.cs.virginia.edu";
    settings.bindAddresses = ["::1", "127.0.0.1", "128.143.63.34"];
    settings.tlsContext = createTLSContext(TLSContextKind.server);
    settings.tlsContext.useCertificateChainFile("server.cer");
    settings.tlsContext.usePrivateKeyFile("server.key");

    auto router = new URLRouter;
    router.get("/ws", handleWebSockets(&handleWebSocketConnection));

    runTask(toDelegate(&trackSessions));
    runTask(toDelegate(&trackTasks));

    listenHTTP(settings, router);
}

void trackSessions() {
    DirectoryWatcher sessions = watchDirectory(datadir ~ "sessions");
    DirectoryChange[] changes;
    while(sessions.readChanges(changes))
        foreach(change; changes) {
            if (change.type == DirectoryChangeType.modified)
                session_key[change.path.head.name] = readFileUTF8(change.path);
        }
}
void trackTasks() {
    DirectoryWatcher sessions = watchDirectory(datadir ~ "tasks");
    DirectoryChange[] changes;
    while(sessions.readChanges(changes))
        foreach(change; changes) {
            auto name = change.path.head.name;
            if (name[0] == '.' || name[$-4..$] != "yaml") continue;
            name = name[0..$-5];
            if (change.type == DirectoryChangeType.removed)
                try {
                    tasks.remove(name);
                    foreach(k,v; topics) if (name in v) v.remove(name);
                    logInfo("Removed task " ~ name);
                } catch (Exception ex) {
                    logError("Task error: " ~ to!string(ex));
                }
            else
                try {
                    readTask(change.path.toString, name);
                    logInfo("Updated task " ~ name);
                } catch (Exception ex) {
                    logError("Task error: " ~ to!string(ex));
                }
        }
}

void recordAction(T)(T message) {
    if ("." !in message) message["."] = "action";
    appendToFile(userlog, serializeToJsonString(message)~"\n");
}

void handleWebSocketConnection(scope WebSocket socket) {
    import std.algorithm.iteration : splitter;
    import core.time : dur;
    
    // to!string(socket.request.clientAddress)
    
    logInfo("Got new web socket connection.");
    while (socket.waitForData) {
        auto payload = socket.receiveText;
        try {
            Json data = parseJsonString(payload);
            auto user = data["user"].get!string; data.remove("user");
            auto session = data["session"].get!string; data.remove("session");
            auto action = data["action"].get!string; data.remove("action");
            if (user !in session_key || session_key[user] != session) {
                socket.send(serializeToJsonString(
                    [".":"reauthenticate"
                    ,"message":(user in session_key ? session_key[user][9..$] : "")
                    ]));
            } else if (user !in users) {
                socket.send(serializeToJsonString(
                [".":"error"
                ,"public":"user "~user~" not registered with system"
                ,"private":"user "~user~" not registered with system"
                ]));
            } else {
                string staff = null;
                // if (users[user].role != Role.student) {
                if (false) {
                    staff = user;
                    if ("asuser" in data) {
                        user = data["asuser"].get!string;
                        if (user !in users || users[user].role != Role.student) {
                            socket.send(serializeToJsonString(
                            [".":"error"
                            ,"public":"user "~user~" not registered with system"
                            ,"private":"attempted to send asuser="~user~", who is not registered with system"
                            ]));
                            continue;
                        }
                    }
                }
                switch(action) {
                    case "status": {
                        if (!staff) { // users[user].role == Role.student) {
                            auto ans = users[user].status;
                            ans["."] = "status";
                            socket.send(serializeToJsonString(ans));
                        } else {
                            Json tmp = Json.emptyObject;
                            tmp["."] = "students";
                            tmp["students"] = Json.emptyArray;
                            foreach(uid, const ref u; users) {
                                if (u.role == Role.student) {
                                    tmp["students"] ~= serializeToJson([[u.id, u.name]]);
                                }
                            }
                            socket.send(tmp.toString);
                        }
                    } break;
                    case "practice": { 
                        import std.random;
                        auto topic = data["topic"].get!string;
                        auto options = users[user].suggested(topic);
                        if (options.length > 0) {
                            auto now = timestamp;
                            auto task = options[uniform(0,options.length)];
                            shared param = tasks[task].parameterize;
                            recordAction(["user":Json(user), "task":Json(task), "start":Json(now), "param":param.toJson]);
                            auto a = shared Action(task, [now,0], 0.0f, param.pairs);
                            users[user].done[task] = a;
                            socket.send(serializeToJsonString(
                            [".":"task"
                            ,"task":task
                            ,"desc":param.removeDollars(tasks[task].description).filterMarkdown
                            ]));
                        } else {
                            socket.send(serializeToJsonString(
                                [".":"error"
                                ,"public":"no tasks for that topic defined"
                                ,"private":"unknown topic \""~topic~"\" " ~ data.toString
                                ]));
                        }
                    } break;
                    case "submit": {
                        // ensure the action has been begun
                        auto task = data["task"].get!string;
                        shared Action *act = task in users[user].done;
                        auto now = timestamp;
                        auto str = timestr;
                        if (act is null) {
                            socket.send(serializeToJsonString(
                                [".":"error"
                                ,"public":"submitted code to task you did not begin doing"
                                ,"private":"submitted to task \""~task~"\" " ~ data.toString
                                ]));
                            break;
                        }
                        // record end timestamp in action
                        recordAction(["user":Json(user), "task":Json(task), "stop":Json(now)]);
                        appendToFile(sublog, serializeToJsonString(["user":user, "when":str, "task":task, "code":data["code"].get!string])~"\n");
                        act.times[1] = now;
                        // make result directory
                        auto resdir = datadir~"result/"~user~"/";
                        { static import std.file; std.file.mkdirRecurse(resdir); }
                        // make directory watcher
                        auto watcher = watchDirectory(resdir);
                        // post code to tester directory
                        auto codedir = datadir~"submission/"~user~"/";
                        { static import std.file; std.file.mkdirRecurse(codedir); }
                        writeFile(codedir~task~".py", cast(immutable(ubyte)[])("# "~act.params.toString~"\n"~data["code"].get!string));
                        // wait for result to appear
                        DirectoryChange[2] _c; auto c = _c[0..0];
                        if (watcher.readChanges(c, dur!"seconds"(30))) {
                            foreach(change; c) {
                                // parse result to set action.result
                                Json result = parseJsonString(readFileUTF8(change.path));
                                // send result over socket
                                auto t = result["tests"];
                                act.result = result["score"].to!float / t.length;
                                recordAction(["user":Json(user), "task":Json(task), "result":Json(result["score"].to!double / t.length)]);
                                foreach(ref k; t) k.remove("details");
                                socket.send(serializeToJsonString(
                                    [".":Json("result")
                                    ,"score":Json(result["score"].to!double / t.length)
                                    ,"tests":t
                                    ]));
                            }
                        } else {
                            socket.send(serializeToJsonString(
                                [".":"error"
                                ,"public":"testing "~task~".py timed out"
                                ,"private":"directory watcher timed out after 30 seconds"
                                ]));
                        }
                    } break;
                    case "view": {
                        if (!staff) {
                            socket.send(serializeToJsonString(
                                [".":"error"
                                ,"public":"only staff may view other students"
                                ,"private":"attempted prohibited behavior: " ~ data.toString
                                ]));
                            break;
                        }
                        user = data["student"].get!string;
                        if (user !in users || staff) {
                            socket.send(serializeToJsonString(
                            [".":"error"
                            ,"public":"user "~user~" not registered with system"
                            ,"private":"attempted to view student="~user~", who is not registered with system"
                            ]));
                            break;
                        }
                        auto ans = users[user].status;
                        ans["."] = "view";
                        ans[".student"] = user;
                        ans[".name"] = users[user].name;
                        socket.send(serializeToJsonString(ans));
                    } break;
                    case "uncheckoff": goto case;
                    case "checkoff": {
                        if (!staff) {
                            socket.send(serializeToJsonString(
                                [".":"error"
                                ,"public":"only staff may check off students"
                                ,"private":"attempted prohibited behavior: " ~ data.toString
                                ]));
                            break;
                        }
                        user = data["student"].get!string;
                        if (user !in users || staff) {
                            socket.send(serializeToJsonString(
                            [".":"error"
                            ,"public":"user "~user~" not registered with system"
                            ,"private":"attempted to view student="~user~", who is not registered with system"
                            ]));
                            break;
                        }
                        appendToFile(userlog, serializeToJsonString([".":"user", "id":user, (action == "uncheckoff" ? "unmastered" : "mastered"):data["topic"].get!string, "ta":staff, "when":timestr])~"\n");
                        if (action == "checkoff") users[user].mastered[data["topic"].get!string] = true;
                        else users[user].mastered.remove(data["topic"].get!string);
                    } goto case "view";
                    default:
                        socket.send(serializeToJsonString(
                            [".":"error"
                            ,"public":"internal server error"
                            ,"private":"unknown action \""~action~"\" " ~ data.toString
                            ]));
                }
            }
        } catch (JSONException ex) {
            socket.send(serializeToJsonString(
                [".":"error"
                ,"public":"malformed request"
                ,"private":payload
                ]));
        }
    }
    logInfo("Client disconnected.");
}
