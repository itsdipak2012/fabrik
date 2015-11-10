/**
 * Visualizations Repeating Groups Helper
 *
 * @copyright: Copyright (C) 2005-2015, fabrikar.com - All rights reserved.
 * @license:   GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

/**
 * This deals with viz admin settings when viz parameters can have repeating groups.
 * It's still not the same code base as the form and table code so refactoring at some
 * point would be good.
 * Not here we clone the actual /admin/com_fabrik/elements/ js classes
 * where as for forms and tables we have a controller class - called admin.js in the plugin folder
 * to deal with cloning
 */

var RepeatParams = my.Class({

	constructor:function(el, opts){
		this.opts = opts;
		this.el = $(el);
		this.counter = this.el.getElements('.repeatGroup').length - 1;
		//addButton
		this.el.getElement('.addButton').addEvent('click', function(e){
			new Event(e).stop();
			var div = this.el.getElements('.repeatGroup').pop();
			//var c = div.clone();
			var newc = this.counter + 1;
			var id = div.id.replace('-' + this.counter, '-' + newc);
			var c = new Element('div', {'class':'repeatGroup', 'id':id}).set('html', div.innerHTML);
			c.inject(div, 'after');
			this.counter = newc;
			//update params ids
			if (this.counter != 0){
				jQuery.each(c.getElements('input[name^=params], select[name^=params]'), function(key, i){
					var newPlugin = false;
					var newid = '';
					var oldid = i.id;
					if (i.id !== '') {
						var a = i.id.split('-');
						a.pop();
						var newid = a.join('-') + '-' + this.counter;
						i.id = newid;
					}

					if (Fabrik.adminElements.has(oldid)){
						var plugin = Fabrik.adminElements.get(oldid);
						newPlugin = new CloneObject(plugin, true, []);
						try{
							newPlugin.cloned(newid, this.counter);
						}catch(err){
							fconsole('no clone method available for ' + i.id);
						}
					}
					if (newPlugin !== false){
						Fabrik.adminElements.set(i.id, newPlugin);
					}
				}.bind(this));

				jQuery.each(c.getElements('img[src=components/com_fabrik/images/ajax-loader.gif]'), function(key, i){
					i.id = i.id.replace('-0_loader', '-'+this.counter+'_loader');
				}.bind(this));
			}


			this.watchDeleteParamsGroup();
		}.bind(this));
		this.watchDeleteParamsGroup();
	},

	watchDeleteParamsGroup:function(){
		var dels = this.el.parent().find('.delete');
		if (typeOf(dels) !== 'null'){
				dels.each(function(del){
				del.removeEvents();
				del.addEvent('click', function(e){
					e = new Event(e);
					//var divs = this.el.getElements('.repeatGroup');
					var divs = this.el.parent().find('.repeatGroup');
					if (divs.length -1 > this.opts.repeatMin){
						$(e.target).closest('.repeatGroup').remove();
					}
					e.stop();
					this.watchDeleteParamsGroup();
				}.bind(this));
			}.bind(this));
		}
	}
});