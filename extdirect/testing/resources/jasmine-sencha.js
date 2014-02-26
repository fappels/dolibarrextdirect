/*
 * [jasmine-sencha](http://github.com/CodeCatalyst/jasmine-sencha) v1.0.0
 * Copyright (c) 2013 [CodeCatalyst, LLC](http://www.codecatalyst.com/).
 * Open source under the [MIT License](http://en.wikipedia.org/wiki/MIT_License).
 */
(function(){
	"use strict";
	
	// Tweak Jasmine's "pretty print" logic to make it aware of Sencha classes.
	jasmine.PrettyPrinter.prototype.format = Ext.Function.createInterceptor(jasmine.PrettyPrinter.prototype.format, function (value) {
		if (value instanceof Ext.ClassManager.get('Ext.Base')) {
			this.emitScalar(Ext.ClassManager.getName(value));
			return false;
		}
	});
})();