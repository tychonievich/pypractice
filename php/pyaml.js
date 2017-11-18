var eol = /\r?\n|\r/g;
var plain_safe = /^[^\u0000-\u001f\u007f-\u00a0\ud800-\ue000\u2028\u2029\ufeff\s\-?:,\[\]{}#&*!|>'"%@`][^\u0000-\u001f\u007f-\u00a0\ud800-\ue000\u2028\u2029\ufeff,\[\]{}:#]*$/;

/**
 * 0: not inline
 * 1: inline, but not inside an inline
 * 2: inline always
 */
function pretty_yaml_inline(value) {
	if (Array.isArray(value)) {
		var ans = 2;
		for(var i=0; i<value.length; i+=1)
			if (pretty_yaml_inline(value[i])) ans = 1;
			else return 0;
		return ans;
	} else if (value == null) {
		return 2;
	} else if (typeof value == 'object') {
		var ans = 2;
		for(var k in value)
			if (pretty_yaml_inline(value[k]) == 2) ans = 1;
			else return 0;
		return ans;
	} else if (typeof value == 'string') {
		return value.indexOf('\n') < 0 ? 2 : 0;
	} else {
		return 2;
	}
}



function pretty_yaml(value, indent='', within) {
	if (Array.isArray(value)) {
		if (pretty_yaml_inline(value)) {
			var ans = '';
			for(var i=0; i<value.length; i+=1) ans += (i==0?'[':', ')+pretty_yaml(value[i], false);
			return ans + ']';
			// return JSON.stringify(value);
		}
		var ans = '';
		for(var i=0; i<value.length; i+=1)
			ans += (i > 0 || within != 'array' ? '\n'+indent : '') + '- ' + pretty_yaml(value[i], indent + '  ', 'array');
		return ans;
	} else if (value == null) {
		return 'null';
	} else if (typeof value == 'object') {
		if (pretty_yaml_inline(value)) {
			var ans = '';
			for(var k in value) ans += (ans==''?'{':', ')+pretty_yaml(k,false)+': '+pretty_yaml(value[k], false);
			return ans + '}';
			// return JSON.stringify(value);
		}
		if (pretty_yaml_inline(value)) return JSON.stringify(value);
		var ans = '';
		for(var k in value) {
			ans += (ans != '' || within != 'array' ? '\n'+indent : '')+pretty_yaml(k, false)+': '+pretty_yaml(value[k], indent+'  ', 'object');
		}
		return ans;
	} else if (typeof value == 'string') {
		if (typeof indent == 'string' && value.indexOf('\n') >= 0) {
			if (eol.test(value[value.length-1]))
				return '|\n'+indent + value.substr(0, value.length-1).replace(eol, '\n'+indent);
			else
				return '|-\n'+indent + value.replace(eol, '\n'+indent);
		} else if (plain_safe.test(value)) { return value;
		} else return JSON.stringify(value);
		// FIXME: only quote strings if necessary
	} else {
		return JSON.stringify(value);
	}
}


console.log(pretty_yaml({hi:3, there:[1,2,3], I:[{x:1,y:2},null,'yes'], want:"some\nmore\n  things", so:[[[1],[2]],[[3],[4]]], "wh\nat":[{is:1,to:[2,3]},{another:'thing"about'}]}));
