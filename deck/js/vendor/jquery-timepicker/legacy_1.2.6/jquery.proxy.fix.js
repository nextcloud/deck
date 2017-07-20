/*
 * This is a fix to add the proxy function to jquery
 * can be used for when using older jquery library.
 *
 * Francois Gelinas
 * August 9, 2011
 *
 * Licensed using the jQuery license : MIT and GPL
 * http://jquery.org/license/
 */

(function( $, undefined ) {
	// Bind a function to a context, optionally partially applying any
	// arguments.
    if ( !$.proxy) {

        $.proxy = function( fn, context ) {
            if ( typeof context === "string" ) {
                var tmp = fn[ context ];
                context = fn;
                fn = tmp;
            }
            var slice = Array.prototype.slice;

            // Quick check to determine if target is callable, in the spec
            // this throws a TypeError, but we will just return undefined.
            if ( !jQuery.isFunction( fn ) ) {
                return undefined;
            }

            // Simulated bind
            var args = slice.call( arguments, 2 ),
                proxy = function() {
                    return fn.apply( context, args.concat( slice.call( arguments ) ) );
                };

            // Set the guid of unique handler to the same of original handler, so it can be removed
            proxy.guid = fn.guid = fn.guid || proxy.guid || jQuery.guid++;

            return proxy;
        }
        
    }
}(jQuery));