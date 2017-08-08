/* to do: implement these:
 * 
bad character: bg:#ffcccc
instance field: fg:#660E7A; bold
static field: fg:#660E7A; italic
static method: italic
block comment: fg:#808080; italic 
doc comment markup: bg:#E2ffE2
doc comment tag: underscode #808080; bold
doc comment tag value: fg:#3D3D3D; bold italic
doc comment text: fg:808080; italic
line comment: fg:808080; italic
identifiers constant: fg:#660E7A; bold italic
label: underscore #808080; bold
predefined symbol: italic
keyword: fg:#000080; bold
markup attribute: fg:#0000FF; bold
markup entity: fg:#0000FF; bold
markup tag: bg:#EfEfEf
@metadata: fg:#808000
number: fg:#0000FF
string invalid escape: fg:008000; bg:FFCCCC
string escape: fg:000080; bold
string text: fg:#008000; bold
template {% %}: bg:#F7FAFF
*/
ace.define("ace/theme/pycharm",["require","exports","module","ace/lib/dom"], function(require, exports, module) {
"use strict";

exports.isDark = false;
exports.cssText = ".ace-pycharm .ace_gutter {\
    background: #ebebeb;\
    border-right: 1px solid rgb(159, 159, 159);\
    color: rgb(136, 136, 136);\
}\
.ace-pycharm .ace_print-margin {\
    width: 1px;\
    background: #ebebeb;\
}\
.ace-pycharm {\
    background-color: #FFFFFF;\
    color: black;\
}\
.ace-pycharm .ace_fold {\
    background-color: rgb(60, 76, 114);\
}\
.ace-pycharm .ace_cursor {\
    color: black;\
}\
.ace-pycharm .ace_storage,\
.ace-pycharm .ace_keyword,\
.ace-pycharm .ace_variable {\
    color: #000080;\
    font-weight: bold;\
}\
.ace-pycharm .ace_constant.ace_buildin {\
color: rgb(88, 72, 246);\
}\
.ace-pycharm .ace_constant.ace_library {\
color: rgb(6, 150, 14);\
}\
.ace-pycharm .ace_function {\
color: rgb(60, 76, 114);\
}\
.ace-pycharm .ace_string {\
    color: #008000;\
    font-weight: bold;\
}\
.ace-pycharm .ace_comment {\
    color: #808080;\
    font-style: italic;\
}\
.ace-pycharm .ace_comment.ace_doc {\
    color: #808080;\
    font-style: italic;\
}\
.ace-pycharm .ace_comment.ace_doc.ace_tag {\
    color: #808080;\
    font-decoration: underline;\
    font-weight: bold;\
}\
.ace-pycharm .ace_constant.ace_numeric {\
color: darkblue;\
}\
.ace-pycharm .ace_tag {\
color: rgb(25, 118, 116);\
}\
.ace-pycharm .ace_type {\
color: rgb(127, 0, 127);\
}\
.ace-pycharm .ace_xml-pe {\
color: rgb(104, 104, 91);\
}\
.ace-pycharm .ace_marker-layer .ace_selection {\
background: rgb(181, 213, 255);\
}\
.ace-pycharm .ace_marker-layer .ace_bracket {\
margin: -1px 0 0 -1px;\
border: 1px solid rgb(192, 192, 192);\
}\
.ace-pycharm .ace_meta.ace_tag {\
color:rgb(25, 118, 116);\
}\
.ace-pycharm .ace_invisible {\
color: #ddd;\
}\
.ace-pycharm .ace_entity.ace_other.ace_attribute-name {\
color:rgb(127, 0, 127);\
}\
.ace-pycharm .ace_marker-layer .ace_step {\
background: rgb(255, 255, 0);\
}\
.ace-pycharm .ace_active-line {\
background: rgb(232, 242, 254);\
}\
.ace-pycharm .ace_gutter-active-line {\
background-color : #DADADA;\
}\
.ace-pycharm .ace_marker-layer .ace_selected-word {\
border: 1px solid rgb(181, 213, 255);\
}\
.ace-pycharm .ace_indent-guide {\
background: url(\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAACCAYAAACZgbYnAAAAE0lEQVQImWP4////f4bLly//BwAmVgd1/w11/gAAAABJRU5ErkJggg==\") right repeat-y;\
}";

exports.cssClass = "ace-pycharm";

var dom = require("../lib/dom");
dom.importCssString(exports.cssText, exports.cssClass);
});
