/*! Fabrik */
FbCheckBox=my.Class(FbElementList,{type:"checkbox",constructor:function(a,b){this.plugin="fabrikcheckbox",this.parent(a,b),this._getSubElements()},watchAddToggle:function(){var a=this.getContainer(),b=a.getElement("div.addoption"),c=a.getElement(".toggle-addoption");if(this.mySlider){var d=b.clone(),e=a.getElement(".fabrikElement");b.parent().destroy(),e.adopt(d),b=a.getElement("div.addoption"),b.setStyle("margin",0)}this.mySlider=new Fx.Slide(b,{duration:500}),this.mySlider.hide(),c.addEvent("click",function(a){a.stop(),this.mySlider.toggle()}.bind(this))},getValue:function(){if(!this.options.editable)return this.options.value;var a=[];return this.options.editable?(this._getSubElements().each(function(b){b.checked&&a.push(b.get("value"))}),a):this.options.value},numChecked:function(){return this._getSubElements().filter(function(a){return a.checked}).length},update:function(a){if(this.getElement(),"string"===typeOf(a)&&(a=""===a?[]:JSON.decode(a)),!this.options.editable){if(this.element.innerHTML="",""===a)return;return void jQuery.each(a,function(a,b){this.element.innerHTML+=this.options.data[b]+"<br />"}.bind(this))}this._getSubElements(),jQuery.each(this.subElements,function(b,c){var d=!1;jQuery.each(a,function(a,b){b===c.value&&(d=!0)}.bind(this)),c.checked=d}.bind(this))},cloned:function(a){this.options.allowadd===!0&&this.options.editable!==!1&&(this.watchAddToggle(),this.watchAdd()),this.parent(a)}});