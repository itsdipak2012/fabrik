/*! Fabrik */
var FbNotes=my.Class(FbElement,{options:{rowid:0,id:0},constructor:function(a,b){this.plugin="notes",this.parent(a,b),this.setUp()},setUp:function(){if(0!==this.options.rowid){this.element.getElement(".button").addEvent("click",function(a){this.submit(a)}.bind(this)),this.field=this.element.getElement(".fabrikinput");var a=this.element.getElement("div");a.makeResizable({modifiers:{x:!1,y:"height"},handle:this.element.getElement(".noteHandle")}),this.element.getElement(".noteHandle").setStyle("cursor","all-scroll")}},submit:function(a){a.stop();var b=this.field.get("value");if(""!==b){Fabrik.loader.start(this.element);var c={option:"com_fabrik",format:"raw",task:"plugin.pluginAjax",plugin:"notes",method:"ajax_addNote",element_id:this.options.id,v:b,rowid:this.options.rowid,formid:this.form.id};this.myAjax=new Request.JSON({url:"",data:c,onSuccess:function(a){if(Fabrik.loader.stop(this.element),this.options.j3){var b=this.element.getElement("div"),c=new Element("div",{"class":"row-fluid"}),d=new Element("div",{"class":"span12"}).set("html",a.label).inject(c);d.inject(b)}else{var e=this.element.getElement("ul"),f="oddRow"+e.getElements("li").length%2;new Element("li",{"class":f}).set("html",a.label).inject(e)}this.field.value=""}.bind(this),onError:function(a){Fabrik.loader.stop(this.element),alert(a)},onFailure:function(){Fabrik.loader.stop(this.element),alert("ajax failed")},onCancel:function(){Fabrik.loader.stop(this.element)}}).send()}},cloned:function(a){Fabrik.trigger("fabrik.notes.update",this),this.parent(a)}});