/*! Fabrik */
var fabrikFusiongraph=my.Class({options:{legend:!1,label:"",aChartKeys:{},axis_label:"",json:{},chartType:"Column3D",xticks:[]},constructor:function(a,b,c){this.el=a,this.options=$.append(this.options,c),this.json=b,this.render()},render:function(){switch(this.options.chartType){case"Column3D":this.graph=new Plotr.BarChart(this.el,this.options);break;case"PieChart":this.graph=new Plotr.PieChart(this.el,this.options);break;case"LineChart":this.graph=new Plotr.LineChart(this.el,this.options)}this.graph.addDataset(this.json),this.graph.render(),"1"===this.options.legend&&this.graph.addLegend(this.el)}});