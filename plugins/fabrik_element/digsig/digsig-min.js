/*! Fabrik */
var FbDigsig=my.Class(FbElement,{constructor:function(a,b){if(this.parent(a,b),this.plugin="digsig",this.options.editable===!0){if("null"===typeOf(this.element))return void fconsole("no element found for digsig");var c={defaultAction:"drawIt",lineTop:"100",output:"#"+this.options.sig_id,canvas:"#"+this.element.id+"_oc_pad",drawOnly:!0};jQuery("#"+this.element.id).signaturePad(c).regenerate(this.options.value)}else jQuery("#"+this.options.sig_id).signaturePad({displayOnly:!0}).regenerate(this.options.value)},getValue:function(){return this.options.value},addNewEvent:function(a,b){return"load"===a?(this.loadEvents.push(b),void this.runLoadEvent(b)):void("change"===a&&(this.changejs=b))}});