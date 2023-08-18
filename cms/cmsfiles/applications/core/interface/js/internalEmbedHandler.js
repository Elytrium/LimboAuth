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
	var _embedMaxWidth = null;
	var _initialHeight = 0;

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
			ips.utils.log( url );
			url.protocol = window.location.protocol;
		}

		// Set our origin
		_origin = url.protocol + '//' + url.host;
		ips.utils.log( "Origin in loader is " + _origin );

		// Hide the content
		_div.style.opacity = '0.00001';

		_initialHeight = ips.utils.getObjHeight( _div );

		// Do we have a specified maxWidth here?
		var maxWidthElem = document.querySelector('[data-embedInfo-maxSize]');

		if( maxWidthElem ){
			_embedMaxWidth = maxWidthElem.getAttribute('data-embedInfo-maxSize');
			maxWidthElem.style.maxWidth = (  maxWidthElem.getAttribute('data-embedInfo-maxSize').indexOf('%') == -1 ) ? parseInt( _embedMaxWidth ) + "px" : _embedMaxWidth + '%';
		}

		// Check for any truncated text
		var truncated = document.querySelectorAll('[data-truncate]');

		if( truncated ){
			for( var n = 0; n < truncated.length; n++ ){
				var size = parseInt( truncated[ n ].getAttribute('data-truncate') || 5 );
				clamp( truncated[ n ], size );
			}
		}

		ips.eventHandler.on( document.body, 'click', clickLink );

		// Set all links to open in a new tab/window
		var links = document.querySelectorAll('a');
		for( var i = 0; i < links.length; i++ ){
			links[i].setAttribute('target', '_blank');
		}

		startTimeout();
	};

	var _showEmbed = function () {
		document.body.className = document.body.className.replace('unloaded ', '');
		ips.utils.fadeIn( _div );
	};

	/**
	 * Starts our main loop, which posts messages to the parent frame as needed
	 *
	 * @returns {void}
	 */
	var startTimeout = function () {

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
					_showEmbed();
				}
			}

			currentSize = height;

			if( _posting ){
				if( _embedMaxWidth !== null ){
					_postMessage('dims', {
						height: height,
						width: _embedMaxWidth
					});
				} else {
					_postMessage('height', {
						height: height
					});
				}
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
	 * Handles link clicks, to check for dialogs. If one is found, the options are passed
	 * up to the parent to display.
	 *
	 * @returns {void}
	 */
	var clickLink = function (e) {
		var link = e.target.closest('a');

		if( link !== null ){
			if( link.hasAttributes() ){
				var output = {};
				var attrs = link.attributes;

				for( var i = attrs.length - 1; i > 0; i-- ){
					if( attrs[i].name !== 'class' && attrs[i].name !== 'title' ){
						output[ attrs[i].name ] = attrs[i].value;
					}
				}

				if( output['data-ipsdialog'] !== undefined ){
					e.preventDefault();

					_postMessage('dialog', {
						url: link.href,
						options: output
					});
				}
			}
		}
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
		},

		stop: function (data) {
			clearInterval( _timer );
			ips.eventHandler.off( window, 'message', windowMessage );
		},

		responsiveState: function (data) {
			if( data.currentIs === 'phone' ){
				document.body.className += ' ipsRichEmbed_phone';
			} else {
				document.body.className = document.body.className.replace( ' ipsRichEmbed_phone', '' );
			}
		}
	};

	/*******************************************************************************************/
	/* Boring stuff below */
	// Main message handler
	ips.eventHandler.on( window, 'message', windowMessage );

	function windowMessage (e) {
		if( e.origin !== _origin ){
			ips.utils.log( e.origin + 'does not equal' + _origin );
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
