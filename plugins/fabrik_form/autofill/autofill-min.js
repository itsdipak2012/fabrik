/*! Fabrik */
var Autofill=my.Class({options:{observe:"",trigger:"",cnn:0,table:0,map:"",editOrig:!1,fillOnLoad:!1,confirm:!0,autofill_lookup_field:0},constructor:function(a){this.options=$.append(this.options,a),this.attached=[],this.setupDone=!1,this.setUp(Fabrik.getBlock("form_"+this.options.formid)),Fabrik.addEvent("fabrik.form.elements.added",function(a){this.setUp(a)}.bind(this)),Fabrik.addEvent("fabrik.form.element.added",function(a,b,c){this.element&&c.strElement===this.element.strElement&&(this.element=!1,this.setupDone=!1,this.setUp(a))}.bind(this))},getElement:function(a){var b=!1,c=this.form.formElements.get(this.options.observe);if(!c){var d=0,e=Object.keys(this.form.formElements);e.each(function(e){e.contains(this.options.observe)&&(b=this.form.formElements.get(e),this.attached.contains(b.options.element)||this.attached.push(b.options.element),("null"===typeOf(a)||a===d)&&(c=b),d++)}.bind(this))}return c},setUp:function(a){if(!this.setupDone&&"null"!==typeOf(a)){try{this.form=a}catch(b){return}var c=this.getElement();if(!c)return!1;var d=function(){this.lookUp()}.bind(this);if(this.element=c,""===this.options.trigger)if(this.element){var e=this.element.getBlurEvent();this.form.dispatchEvent("",this.element.options.element,e,function(a){this.element=a,this.lookUp()}.bind(this))}else fconsole("autofill - couldnt find element to observe");else this.form.dispatchEvent("",this.options.trigger,"click",d);if(this.options.fillOnLoad){var f=""===this.options.trigger?this.element.strElement:this.options.trigger;this.form.dispatchEvent("",f,"load",d)}this.setupDone=!0}},lookUp:function(){if(this.options.confirm!==!0||confirm(Joomla.JText._("PLG_FORM_AUTOFILL_DO_UPDATE"))){Fabrik.loader.start("form_"+this.options.formid,Joomla.JText._("PLG_FORM_AUTOFILL_SEARCHING")),this.element||(this.element=this.getElement(0));{var a=this.element.getValue(),b=this.options.formid,c=this.options.observe;new Request.JSON({evalScripts:!0,data:{option:"com_fabrik",format:"raw",task:"plugin.pluginAjax",plugin:"autofill",method:"ajax_getAutoFill",g:"form",v:a,formid:b,observe:c,cnn:this.options.cnn,table:this.options.table,map:this.options.map,autofill_lookup_field:this.options.autofill_lookup_field},onCancel:function(){Fabrik.loader.stop("form_"+this.options.formid)}.bind(this),onFailure:function(){Fabrik.loader.stop("form_"+this.options.formid),window.alert(this.getHeader("Status"))},onError:function(a,b){Fabrik.loader.stop("form_"+this.options.formid),fconsole(a+" "+b)}.bind(this),onSuccess:function(a){Fabrik.loader.stop("form_"+this.options.formid),this.updateForm(a)}.bind(this)}).send()}}},updateForm:function(a){var b=this.element.getRepeatNum();0===Object.keys(a).length&&window.alert(Joomla.JText._("PLG_FORM_AUTOFILL_NORECORDS_FOUND")),jQuery.each(a,function(a,c){var d=a.substr(a.length-4,4);if("_raw"===d){a=a.replace("_raw","");var e=a;this.tryUpdate(a,c)||("object"==typeof c?(c=$H(c),c.each(function(b,c){d=a+"_"+c,this.tryUpdate(d,b)}.bind(this))):(a+=b?"_"+b:"_0",this.tryUpdate(a,c)||(a="join___"+this.element.options.joinid+"___"+a,!this.tryUpdate(e,c,!0))))}}.bind(this)),this.options.editOrig===!0&&(this.form.getForm().getElement("input[name=rowid]").value=a.__pk_val),Fabrik.trigger("fabrik.form.autofill.update.end",[this,a])},tryUpdate:function(a,b,c){if(c=c?!0:!1){var d=Object.keys(this.form.formElements).filter(function(b){return b.contains(a)});if(d.length>0)return d.each(function(a){var c=this.form.formElements.get(a);c.update(b),c.element.trigger(c.getBlurEvent(),new Event.Mock(c.element,c.getBlurEvent()))}.bind(this)),!0}else{var e=this.form.formElements.get(a);if("null"!==typeOf(e))return typeOf("null"!==e.options.displayType)&&"auto-complete"===e.options.displayType&&(e.activePopUp=!0),e.update(b),e.element.trigger(e.getBlurEvent(),new Event.Mock(e.element,e.getBlurEvent())),!0}return!1}});