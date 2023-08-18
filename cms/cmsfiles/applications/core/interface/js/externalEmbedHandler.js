/*
 * IPS embed handling
 * https://www.invisioncommunity.com
 */
( function () {
	"use strict";

	var _origin;
	var _div;
	var _posting = false;
	var _timer;
	var _timeout = 200; //ms
	var _embedId = '';
	var _failSafe = null;

	/**
	 * Init method, called when the document is ready
	 *
	 * @returns {void}
	 */
	function init() {
		// Check for postMessage and JSON support
		if( !window.postMessage || !window.JSON.parse ){
			return;
		}

		// Work out our URL
		_div = ips.utils.get('ipsEmbed');
		var url = ips.utils.parseURL( window.location );

		if( url.protocol == '' || url.protocol == ':' ){
			utils.log( url );
			url.protocol = window.location.protocol;
		}

		// Set our origin
		_origin = url.protocol + '//' + url.host;
		ips.utils.log( "Origin in loader is " + _origin );	

		// Hide the content
		_div.style.opacity = '0.00001';

		// But if we're in the 'top frame', don't bother showing loading
		try {
			if( window.self === window.top ){
				_div.style.opacity = 1;
				_div.parentNode.className = '';
			}
		} catch (err) {}

		// Start an emergency timeout, which we'll use to force-show the embed if the parent page
		// isn't talking to us for some reason. This will force the embed to eventually show, albeit at the wrong size,
		// instead of just being forever loading.
		var counter = 0;
		_failSafe = setInterval( function () {
			if( counter >= 6 ){ // approx 6 seconds
				ips.utils.log("Triggered failsafe timer");
				_div.parentNode.className = '';
				ips.utils.fadeIn( _div );
				clearInterval( _failSafe );
			}
			counter++;
		}, 1000 );
	};

	/**
	 * Starts our main loop, which posts messages to the parent frame as needed
	 *
	 * @returns {void}
	 */
	function startTimeout() {

		var currentSize = 0;
		var repeats = 0;

		ips.utils.log("Starting timeout...");

		// Main loop
		_timer = setInterval( function () {			
			// What we want to do here is make the loading process more pleasant and less jumpy.
			// To do that, we'll make the embed invisible until we've fetched the same height 6 times
			// in a row (approx 1 second). If that happens we'll assume it has finished loading, and then
			// we'll fade it in and start sending the recorded height to the parent for resizing.

			var height = ips.utils.getObjHeight( _div );

			if( !height )
			{
				return;
			}

			ips.utils.log("Determined height as " + height + ' for embed ID ' + _embedId );

			// If we HAVEN'T started posting our size
			if( !_posting ){
				if( height == currentSize ){
					repeats++;
				} else {
					// The height has changed, so reset our repeat counter
					repeats = 0;
				}

				if( repeats == 6 ){
					_posting = true;
					ips.utils.fadeIn( _div );
				}
			}

			currentSize = height;

			if( _posting ){
				_div.parentNode.className = '';

				_postMessage('height', {
					height: ( height + 10 )
				});
			}
		}, _timeout);
	};

	/**
	 * Posts a message to the iframe
	 *
	 * @param	{number} 	[pageNo]	Page number to load
	 * @returns {void}
	 */
	var _postMessage = function (method, obj) {
		// Send to parent window
		window.top.postMessage( JSON.stringify( ips.utils.extendObj( obj || {}, { 
			method: method,
			embedId: _embedId
		} ) ), _origin );
	};	

	/**
	 * Events sent to the iframe
	 */
	var messageEvents = {
		/**
		 * The parent is ready for messages
		 *
		 * @param 	{object} 	data 	Data from the iframe
		 * @returns {void}
		 */
		ready: function (data) {
			_embedId = data.embedId;
			_postMessage('ok');

			startTimeout();
			clearInterval( _failSafe ); // Stop our emergency timer
		},

		stop: function (data) {
			clearInterval( _timer );
			clearInterval( _failSafe ); // Stop our emergency timer
			eventHandler.off( window, 'message', windowMessage );
		}
	};

	/*******************************************************************************************/
	/* Boring stuff below */

	// Main message handler
	ips.eventHandler.on( window, 'message', windowMessage );

	function windowMessage (e) {
		if( e.origin !== _origin ){
			ips.utils.log( e.origin + ' does not equal ' + _origin );
			return;
		}

		try {
			var pmData = JSON.parse( e.data );
			var method = pmData.method;	
		} catch (err) {
			ips.utils.error("iframe: invalid data.");
			return;
		}			

		if( method && typeof messageEvents[ method ] != 'undefined' ){
			ips.utils.log("Called " + method );
			messageEvents[ method ].call( this, pmData );
		} else {
			ips.utils.log("Method " + method + " doesn't exist");
		}
	};

	
	ips.utils.contentLoaded( window, function () {
		init();
	});
})();