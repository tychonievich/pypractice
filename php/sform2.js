/**
 * A created-as-I-needed-it incomplete implementation of a web form based on 
 * [JSON Schema](http://json-schema.org/).
 * As you can see it is almost entirely free of comments, good variable names,
 * test cases, or any other good software development practice.
 * The subset of JSON Schema it covers is also more-or-less random, since I only
 * added those parts I needed to get one particular site working.
 * I have not tested it except in recent versions of Firefox and Chromium on Linux,
 * and then only insofar as a single example page counts as testing.
 * 
 * All that said, if you want it, have at it.  I hereby release it to the public domain.
 */

function nullElement(value) {
	var ans = document.createElement('label');
	ans.appendChild(document.createTextNode('(null)'));
	ans.classList.add('element');
	ans.classList.add('null');
	ans.getValue = function() { return null; }
	ans.setValue = function(value) { return this; }
	return ans;
}
function boolElement(value) {
	var ans = document.createElement('input');
	ans.type = 'checkbox';
	ans.classList.add('element');
	ans.classList.add('boolean');
	ans.checked = !!value;
	ans.getValue = function() { return this.checked; }
	ans.setValue = function(value) { this.checked = !!value; return this; }
	return ans;
}
function stringElement(value) {
	var ans = document.createElement('textarea');
	ans.rows = 1; ans.cols = 1; ans.style.resize = 'none';
	ans.onkeypress = function(event) {
		lines = this.value.split(/\n|\r\n?/g);
		this.rows = lines.length;
		this.cols = 1 + lines.map(function(x){return x.length;}).reduce(function(x,y){return x>y?x:y;});
	}
	ans.onkeyup = ans.onkeypress;
	ans.classList.add('element');
	ans.classList.add('string');
	if (value !== undefined) ans.value = value;
	ans.getValue = function() { return this.value; }
	ans.setValue = function(value) { this.value = String(value); this.onkeypress(); return this; }
	ans.onkeypress();
	return ans;
}
function numberElement(value) {
	var ans = document.createElement('input');
	ans.type = 'text'; ans.style.fontFamily = 'monospace';
	ans.value = value || 0;
	ans.size = ans.value.length + 1;
	ans.onkeypress = function(event) {
		this.value = this.value.replace(/[^\-\+\*/%\(\) xXeE0-9\.]/g, '');
		this.size = this.value.length + 1;
	}
	ans.onkeyup = ans.onkeypress;
	ans.classList.add('element');
	ans.classList.add('number');
	ans.getValue = function() { 
		try { return Number(eval(this.value)); }
		catch (e) { return null; }
	}
	ans.setValue = function(value) { 
		value = Number(value); if (!Number.isNaN(value)) this.value = value; 
		this.onkeypress(); return this;
	}
	ans.onkeypress();
	return ans;
}
function enumElement(schema, value) {
	var ans = document.createElement('div');
	ans.id = 'enum' + String(Math.random()).substr(2);
	ans.as_type = schema.type;
	schema.enum.forEach(function(val){
		var input = document.createElement('input');
		input.type = 'radio';
		input.name = ans.id;
		input.value = JSON.stringify(val);
		input.checked = (val == value);
		var wrap = document.createElement('label');
		wrap.classList.add('radio-wrapper');
		wrap.appendChild(input);
		wrap.appendChild(document.createTextNode(input.value));
		ans.appendChild(wrap);
	});

	ans.classList.add('element');
	ans.classList.add('enum');
	ans.getValue = function() {
		var elem = this.querySelector('input:checked');
		try{
			if (elem) elem = JSON.parse(elem.value);
			else return null;
			return elem;
		} catch (e) { return null; }
	}
	ans.setValue = function(value) { 
		for(var i=0; i<this.children.length; i+=1) {
			try {
				this.firstElementChild.firstElementChild.checked = (
					JSON.parse(this.firstElementChild.firstElementChild.value) == value);
			} catch(e) {}
		}
		return this;
	}
	return ans;
}
function arrayElement(items, value, minlen, maxlen) {
	var ans = document.createElement('ul');
	ans._minlen = minlen;
	ans._maxlen = maxlen;
	
	ans.addItem = function(schema, value, focus) {
		// console.log('addItem '+JSON.stringify(value));
		if (this._maxlen && this.children.length-1 >= this._maxlen) return;
		var li = document.createElement('li');
		li.appendChild(anyElement(schema, value)); // in-module function call
		// console.log("added "+li.innerHTML);
		var del = document.createElement('input');
		del.type = 'button'; del.value = '⌫';
		del.classList.add('deletion');
		del._minlen = minlen;
		del.onclick = function(event) {
			if (this._minlen && this.parentNode.parentNode.children.length-1 <= this._minlen) return;
			var gp = this.parentNode.parentNode;
			this.parentNode.remove();
			gp.dispatchEvent(new Event('change', {bubbles:true}));
		}
		li.appendChild(del);
		this.insertBefore(li, this.lastElementChild);
		if (focus) {
			if (li.firstElementChild.focus) li.firstElementChild.focus();
			if (li.firstElementChild.select) li.firstElementChild.select();
		}
	}
	
	var addSet = [{type:'null'}, {type:'boolean'}, {type:'number'}, {type:'string'}, {type:'array'}, {type:'object'}];
	if (items && items.type) addSet = [items];
	if (items && items.oneOf) addSet = items.oneOf;
	
	var extender = document.createElement('li');
	addSet.forEach(function(option){
		var adder = document.createElement('input');
		adder.type = 'button'; adder.value = '+ ' + (option.summary || option.description || (option.enum ? 'enum' : option.type) || 'item');
		adder.classList.add('addition');
		extender.appendChild(adder);
		adder.onclick = function(event) {
			this.parentNode.parentNode.addItem(option, undefined, true);
			this.dispatchEvent(new Event('change', {bubbles:true}));
		}
	});
	ans.appendChild(extender);
	
	if (value && value.length)
		for(var i=0; i<value.length; i+=1)
			ans.addItem(items, value[i]);
	while (minlen && ans.children.length-1 < minlen) ans.addItem(items);
	
	ans.classList.add('element');
	ans.classList.add('array');
	ans.getValue = function() {
		var ans = [];
		for(var i=0; i<this.children.length-1; i+=1) {
			var elem = this.children[i].querySelector('.element');
			if (elem) ans.push(elem.getValue());
			else ans.push(undefined);
		}
		return ans;
	}
	ans._items = items;
	ans.setValue = function(value) {
		if (!Array.isArray(value)) return;
		while(this.children.length > 1) this.removeChild(this.firstElementChild);
		for(var i=0; i<value.length; i+=1)
			this.addItem(this._items, value[i]);
		return this;
	}
	
	return ans;
}
function objectElement(schema, value, noExtensions) {
	var ans = document.createElement('dl');
	ans.schema = schema;
	ans.addKey = function(key, value) {
		// console.log("addKey "+key+": "+JSON.stringify(value));
		// find schema, if any
		var s = undefined;
		if (this.schema.properties && key in this.schema.properties) s = this.schema.properties[key];
		if (!s && this.schema.patternProperties)
			for(var pattern in this.schema.patternProperties)
				if (RegExp(pattern).test(key)) {
					s = this.schema.patternProperties[pattern];
					break;
				}
		// update existing element, if present
		for(var i=0; i<this.children.length; i+=1) if (this.children[i].tagName.toLowerCase() == 'dt')
			if (this.children[i].firstChild.nodeType == document.TEXT_NODE
			&& this.children[i].firstChild.wholeText == key) {
				// set value
				if (value === undefined) return false; // cannot set to undefined
				var elem = anyElement(s, value);
				if (!elem) return false;

				var dd = this.children[i].nextElementSibling;
				var adder = dd.querySelector('.addition');
				if (adder) adder.click();
				dd.querySelector('.element').replaceWith(elem);
				this.dispatchEvent(new Event('change', {bubbles:true}));
				return true;
			}
		if ((s === undefined) && noExtensions) return false;
		// add a new key-value pair
		
		
		var dt = document.createElement('dt');
		dt.appendChild(document.createTextNode(key));
		
		if (!this.schema.properties || !(key in this.schema.properties)) {
			var del = document.createElement('input');
			del.type = 'button'; del.value = '⌫';
			del.classList.add('deletion');
			del.onclick = function(event) {
				// TO DO: add confirmation if something is present
				this.parentNode.nextElementSibling.remove();
				var gp = this.parentNode.parentNode;
				this.parentNode.remove();
				gp.dispatchEvent(new Event('change', {bubbles:true}));
			}
			dt.appendChild(del);
		}
		
		var dd = document.createElement('dd');
		if (s && (s.summary || s.description))
			dd.innerHTML = (s.summary || s.description) + '<br/>';
		if (s && (this.schema.required && this.schema.required.includes(key) && s.type))
			dd.appendChild(anyElement(s, value));
		else {
			var holder = document.createElement('span');
			var addSet = [{type:'null'}, {type:'boolean'}, {type:'number'}, {type:'string'}, {type:'array'}, {type:'object'}];
			if (s && s.type) addSet = [s];
			if (s && s.oneOf) addSet = s.oneOf;
			addSet.forEach(function(option){
				var adder = document.createElement('input');
				adder.type = 'button'; adder.value = '+ ' + (option.enum ? 'enum' : option.type || 'item');
				adder.classList.add('addition');
				holder.appendChild(adder);
				adder.onclick = function(event) {
					var was = this.parentNode;
					var is = anyElement(option);
					was.replaceWith(is);
					if (is.focus) is.focus();
					if (is.select) is.select();
					var del = document.createElement('input');
					del.type = 'button'; del.value = '⌫';
					del.classList.add('deletion');
					del.onclick = function(event) {
						/*if (!this.previousElementSibling.getValue
						|| !this.previousElementSibling.getValue()
						|| window.confirm("Delete "+JSON.stringify(this.previousElementSibling.getValue())+'?'))*/ {
							this.previousElementSibling.replaceWith(was);
							var gp = this.parentNode;
							this.remove();
							gp.dispatchEvent(new Event('change', {bubbles:true}));
						}
					}
					if (is.nextSibling)
						is.parentNode.insertBefore(del, is.nextSibling)
					else
						is.parentNode.appendChild(del);
					is.dispatchEvent(new Event('change', {bubbles:true}));
				}
			});
			dd.appendChild(holder);
			if (addSet.length == 1 && (!this.schema.properties || !(key in this.schema.properties))) {
				// can remove entire key, and only one type possible, so just make it
				dd.lastElementChild.lastElementChild.click(); // by clicking the make button
				dd.lastElementChild.remove(); // and removing the value removal button
			}
		}

		var tail = this.querySelectorAll('dt.extender'); if (tail) tail = tail[tail.length-1];
		this.insertBefore(dt, tail);
		this.insertBefore(dd, tail);
		this.dispatchEvent(new Event('change', {bubbles:true}));
		return true;
	}
	
	if (ans.schema.patternProperties || !noExtensions) {
		var dt = document.createElement('dt');
		var ki = document.createElement('input'); ki.type = 'text';
		dt.appendChild(ki);
		dt.classList.add('extender');
		ans.appendChild(dt);

		var dd = document.createElement('dd');
		var create = document.createElement('input'); create.type = 'button'; create.value = 'add new key';
		create.onclick = function(event) {
			var keytext = this.parentNode.previousElementSibling.querySelector('input[type="text"]').value;
			if (!this.parentNode.parentNode.addKey(keytext))
				alert("Cannot add key "+JSON.stringify(keytext));
			else
				this.parentNode.previousElementSibling.querySelector('input[type="text"]').value = '';
		}
		dd.appendChild(create);
		dd.classList.add('extender');
		ans.appendChild(dd);
	}
	
	if (ans.schema.properties)
		for(var k in ans.schema.properties)
			if (value && k in value) ans.addKey(k, value[k]);
			else ans.addKey(k);
	//if (value)
		//for(var k in value)
			//if (!ans.schema.properties || !(k in ans.schema.properties))
				//ans.addKey(k, value[k]);
	
	ans.getValue = function() {
		var ans = {};
		for(var i=0; i<this.children.length; i+=1) if (this.children[i].tagName.toLowerCase() == 'dt') {
			if (this.children[i].firstChild.nodeType != document.TEXT_NODE) continue;
			var k = this.children[i].firstChild.wholeText;
			var value = this.children[i].nextElementSibling;
			if (value) value = value.querySelector('.element');
			if (value && value.getValue) {
				value = value.getValue();
				ans[k] = value;
			}
		}
		return ans;
	}
	ans.setValue = function(value) {
		if ((typeof value) != 'object') return;

		for(var i=0; i<this.children.length; i+=1) if (this.children[i].tagName.toLowerCase() == 'dt') {
			if (this.children[i].firstChild.nodeType != document.TEXT_NODE) continue;
			var k = this.children[i].firstChild.wholeText;
			if (this.schema.properties && k in this.schema.properties) {
				var deleters = this.children[i].nextElementSibling.querySelectorAll('.deletion');
				for(var j=0; j<deleters.length; j+=1) {
					deleters[j].click();
				}
				continue;
			} else {
				this.children[i].nextElementSibling.remove();
				this.children[i].remove();
			}
		}
		for(var k in value)
			this.addKey(k, value[k]);
		return this;
	}
	ans.classList.add('element');
	ans.classList.add('object');
	ans.setValue(value);
	return ans;
}

function anyElement(schema, value) {
	if (!schema) schema = {};
	if (schema.enum)
		return enumElement(schema, value);
	if (schema.type == 'null' || !schema.type && value === null) 
		return nullElement(value);
	if (schema.type == 'boolean' || !schema.type && typeof value == 'boolean')
		return boolElement(value);
	if (schema.type == 'number' || !schema.type && typeof value == 'number')
		return numberElement(value);
	if (schema.type == 'string' || !schema.type && typeof value == 'string')
		return stringElement(value);
	if (schema.type == 'array' || !schema.type && Array.isArray(value))
		return arrayElement(schema.items, value, schema.minItems, schema.maxItems);
	if (schema.type == 'object' || !schema.type && typeof value == 'object')
		// kludge: assume that if properties or patternProperties are given, no other properties may be added
		return objectElement(schema, value, !!(schema.properties || schema.patternProperties));
	
	return document.createTextNode('«'+JSON.stringify(schema)+','+JSON.stringify(value)+'»');
}
