/*! Fabrik */
var elementElement=my.Class({options:{plugin:"chart",excludejoined:0,value:"",highlightpk:0},constructor:function(a,b){this.options=$.append(this.options,b),this.el=a,this.setOptions(b),this.ready()?this.setUp():this.cnnperiodical=setInterval(function(){this.getCnn.call(this,!0)},500)},ready:function(){return"null"===typeOf(document.id(this.options.conn))?!1:"undefined"===typeOf(FabrikAdmin.model.fields.fabriktable)?!1:0===Object.getLength(FabrikAdmin.model.fields.fabriktable)?!1:-1===Object.keys(FabrikAdmin.model.fields.fabriktable).indexOf(this.options.table)?!1:!0},getCnn:function(){this.ready()&&(this.setUp(),clearInterval(this.cnnperiodical))},setUp:function(){var a=this.el;this.el=document.id(this.el),"null"===typeOf(this.el)&&fconsole("element didnt find me, ",a);var b=this.el.parent().find("button");"null"!==typeOf(b)&&(b.addEvent("mousedown",function(a){a.stop(),this.addPlaceHolder()}.bind(this)),b.addEvent("click",function(a){a.stop()})),FabrikAdmin.model.fields.fabriktable[this.options.table].registerElement(this)},addPlaceHolder:function(){var a=this.el.parent().find("select");this.insertTextAtCaret(this.el,"{"+a.get("value")+"}")},getOpts:function(){return{calcs:this.options.include_calculations,showintable:this.options.showintable,published:this.options.published,excludejoined:this.options.excludejoined,highlightpk:this.options.highlightpk}},cloned:function(a,b){this.el=a;var c=this.options.table.split("-");c.pop(),this.options.table=c.join("-")+"-"+b,this.setUp()},getSelectionBoundary:function(a,b){var c,d,e,f,g,h=b?"selectionStart":"selectionEnd";if("number"==typeof a[h])return a[h];if(document.selection&&document.selection.createRange){a.focus();var i=document.selection.createRange();if(i)return"Text"===document.selection.type&&i.collapse(!!b),c=a.value,d=a.createTextRange(),e=a.createTextRange(),f=0,g=i.getBookmark(),d.moveToBookmark(g),c.indexOf("\r\n")>-1?(d.text=" ",e.setEndPoint("EndToStart",d),f=e.text.length-1,document.execCommand("undo")):(e.setEndPoint("EndToStart",d),f=e.text.length),f}return 0},offsetToRangeCharacterMove:function(a,b){return b-(a.value.slice(0,b).split("\r\n").length-1)},setSelection:function(a,b,c){var d=a.createTextRange(),e=this.offsetToRangeCharacterMove(a,b);d.collapse(!0),b===c?d.move("character",e):(d.moveEnd("character",this.offsetToRangeCharacterMove(a,c)),d.moveStart("character",e)),d.select()},insertTextAtCaret:function(a,b){var c=this.getSelectionBoundary(a,!1),d=c+b.length,e=a.value;a.value=e.slice(0,c)+b+e.slice(c),this.setSelection(a,d,d)}});