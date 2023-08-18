/*
 * IPS external widgets loader
 *
 * https://www.invisioncommunity.com
 */
( function () {
	"use strict";

	var _baseURL = '';
	var _widgets = {};
	var _scriptAttributes = {};

	/**
	 * Initialization
	 */
	var initialize = function () {
		// Get our script attributes
		var thisScript = getOurScript();
		_scriptAttributes = getAttributesFrom( thisScript );

		// Set our base URL
		_baseURL = _scriptAttributes.src.replace( 'external.js', 'external.php' );

		// Main message handler
		eventHandler.on( window, 'message', function (e) {
			try {
				var pmData = JSON.parse( e.data );
			} catch (err) {
				return;
			}

			if( typeof pmData.method == 'undefined' || typeof pmData.widgetID == 'undefined' || typeof pmData.blockID == 'undefined' ){
				return;
			}

			// Ensure this is a valid frame
			if( typeof _widgets[ pmData.widgetID ] == 'undefined' ){
				return;
			}

			if( pmData.method && typeof messageEvents[ pmData.method ] != 'undefined' ){
				messageEvents[ pmData.method ].call( this, pmData );
			}
		});

		// We need to find the IPS widgets on the page
		var widgets = document.getElementsByClassName('ipsExternalWidget');

		if( !widgets.length ) {
			utils.log("No widgets found.");
			return;
		}

		for( var i = 0; i < widgets.length; i++ ){
			createWidget( widgets[i] );
		};

		while( widgets.length ){
			widgets[0].parentElement.removeChild( widgets[0] );
		}
	};

	/**
	 * Handles events from the iframe
	 *
	 */
	var messageEvents = {
		/**
		 * iframe tells us we're ready; if we need to send styles, do that now
		 *
		 * @param 	{object} 	data 	Data object
		 * @returns {void}
		 */
		iframeReady: function (data) {
			var thisWidgetInfo = _widgets[ data.widgetID ];

			utils.log( thisWidgetInfo );

			// Do we need to send probed styles?
			if( thisWidgetInfo.useStyles ){
				_postMessage( data.widgetID, 'probedStyles', {
					text: thisWidgetInfo['text'],
					font: thisWidgetInfo['font'],
					link: thisWidgetInfo['link']
				});
			}
		},

		/**
		 * iframe has sent a size; resize it to fit
		 *
		 * @param 	{object} 	data 	Data object
		 * @returns {void}
		 */
		iframeSize: function (data) {
			var block = document.getElementById( 'ipsFrame_' + data.widgetID );
			block.style.height = parseInt( data.size ) + 'px';
		},

		/**
		 * Link clicked inside iframe; open it in parent
		 *
		 * @param 	{object} 	data 	Data object
		 * @returns {void}
		 */
		goToLink: function (data) {
			window.location = data.link;
		}
	};

	/**
	 * Creates a widget in the page, replacing the element in the param
	 *
	 * @param 	{element} 	widget
	 * @returns {void}
	 */
	var createWidget = function (widget) {
		var widgetAttributes = getAttributesFrom( widget );

		// Make sure we have a block ID
		if( typeof widgetAttributes.id == 'undefined' || typeof widgetAttributes['data-blockid'] == 'undefined' ){
			utils.error("Widget class found but no block ID available.");
			return;
		}

		var widgetID = widgetAttributes.id.replace( 'block_', '' );
		var blockID = widgetAttributes['data-blockid'];
		var blockParams = widgetAttributes['data-blockparams'];
		var externalRef = btoa( window.location.href ); // This is required to let community know this is external AND where it is (for redirects and stuff)

		_widgets[ widgetID ] = {
			blockID: blockID
		};

		// Create the iframe to replace this
		var iframe;

		iframe = document.createElement('iframe');
		iframe.src = _baseURL + '?widgetid=' + widgetID + '&blockid=' + blockID + `&externalref=${externalRef}` + ( blockParams ? '&' + blockParams : '' );
		iframe.id = 'ipsFrame_' + widgetID;
		iframe.style.width = '100%';
		iframe.style.border = '0';
		iframe.style.opacity = '1';
		iframe.style.overflow = 'hidden';

		// Do we need to probe for some styles
		if( typeof widgetAttributes['data-inheritstyle'] != 'undefined' ){
			var styles = _createStyleProbes( widget );

			_widgets[ widgetID ]['useStyles'] = true;
			_widgets[ widgetID ]['link'] = styles.link;
			_widgets[ widgetID ]['text'] = styles.text;
			_widgets[ widgetID ]['font'] = styles.font;
		}

		// Now insert the iframe 
		widget.parentElement.insertBefore( iframe, widget );
	};

	/**
	 * Create elements and probe for some key styles that we'll share with the iframes
	 *
	 * @returns {void}
	 */
	var _createStyleProbes = function (element) {
		var styles = {};

		//=====
		// First, a simple link
		var a = document.createElement('a');
		utils.insertBefore( a, element );

		styles['link'] = utils.getStyle( a, 'color' );
		a.parentNode.removeChild( a );

		//=====
		// Next create a paragraph
		var p = document.createElement('p');
		utils.insertBefore( p, element );

		styles['font'] = utils.getStyle( p, 'font-family' );
		styles['text'] = utils.getStyle( p, 'color' );
		p.parentNode.removeChild( p );

		return styles;
	};

	/**
	 * Returns the attributes on the given element
	 *
	 * @param 	{element} 	element
	 * @returns {object} 	The attributes in key/value pairs
	 */
	var getAttributesFrom = function (element) {
		if( typeof element == 'undefined' ){
			return;
		}

		var attributes = {};

		if( element.hasAttributes() ){
			var _attr = element.attributes;

			for( var i = 0; i < _attr.length; i++ ){
				attributes[ _attr[ i ].name.toLowerCase() ] = _attr[ i ].value;
			}
		}

		return attributes;
	};

	/**
	 * Finds and returns our script
	 *
	 * @param 	{element} 	element
	 * @returns {object} 	The attributes in key/value pairs
	 */
	var getOurScript = function () {
		return document.getElementById('ipsWidgetLoader');
	};

	/**
	 * Posts a message to the iframes
	 *
	 * @param	{number} 	[pageNo]	Page number to load
	 * @returns {void}
	 */
	var _postMessage = function (iframeID, method, data) {
		var iframe = document.getElementById( 'ipsFrame_' + iframeID );
		iframe.contentWindow.postMessage( JSON.stringify( utils.extendObj( data || {}, { 
			method: method,
			widgetID: iframeID
		})), '*' );
	};

	var utils = {
		/*
		 * Cross-browser 'dom ready' event handler to init the page
		 */
		/*! contentloaded.min.js - https://github.com/dperini/ContentLoaded - Author: Diego Perini - License: MIT */
		contentLoaded: function (b,i) {
			var j=false,k=true,a=b.document,l=a.documentElement,f=a.addEventListener,h=f?'addEventListener':'attachEvent',n=f?'removeEventListener':'detachEvent',g=f?'':'on',c=function(d){if(d.type=='readystatechange'&&a.readyState!='complete')return;(d.type=='load'?b:a)[n](g+d.type,c,false);if(!j&&(j=true))i.call(b,d.type||d)},m=function(){try{l.doScroll('left')}catch(e){setTimeout(m,50);return}c('poll')};if(a.readyState=='complete')i.call(b,'lazy');else{if(!f&&l.doScroll){try{k=!b.frameElement}catch(e){}if(k)m()}a[h](g+'DOMContentLoaded',c,false);a[h](g+'readystatechange',c,false);b[h](g+'load',c,false)}
		},

		/**
		 * Extend an object with another object
		 *
		 * @param 	{object} 	originalObj 	Original object
		 * @param	{object} 	newObj 			New object
		 * @returns {void}
		 */
		extendObj: function (originalObj, newObj) {
			for( var i in newObj ){
				if( newObj.hasOwnProperty(i) ){
					originalObj[i] = newObj[i];
				}
			}

			return originalObj;
		},

		/**
		 * Log to console if supported
		 *
		 * @param	{string} 	message 	Message to log
		 * @returns {void}
		 */
		log: function (message) {
			if( window.console ){
				console.log( message );
			}
		},

		/**
		 * Error to console if supported
		 *
		 * @param	{string} 	message 	Message to log
		 * @returns {void}
		 */
		error: function (message) {
			if( window.console ){
				console.error( message );
			}
		},

		/**
		 * A simple method to insert an element before another element
		 *
		 * @param	{element} 	elem 		The new element
		 * @param 	{element} 	existing	The existing element, before which the new will be inserted
		 * @returns {void}
		 */
		insertBefore: function (elem, existing) {
			existing.parentNode.insertBefore( elem, existing );
		},

		/**
		 * Returns a style property value for the given element and style
		 *
		 * @param	{element} 	elem 		Element whose style is to be fetched
		 * @param 	{string} 	style		Property to fetch
		 * @returns {mixed}
		 */
		getStyle: function (elem, style) {
			return window.getComputedStyle( elem, null ).getPropertyValue( style );
		}
	};

	/**
	 * Event handling
	 *
	 */
	// http://www.anujgakhar.com/2013/05/22/cross-browser-event-handling-in-javascript/
	var eventHandler = {
		on: function (el, ev, fn) {
			if( window.addEventListener ){
				el.addEventListener( ev, fn, false );
			} else if( window.attachEvent ){
				el.attachEvent( 'on' + ev, fn );
			} else {
				el[ 'on' + ev ] = fn;
			}
		},

		off: function (el, ev, fn) {
			if( window.removeEventListener ){
				el.removeEventListener( ev, fn, false );
			} else if( window.detachEvent ) {
				el.detachEvent( 'on' + ev, fn );
			} else {
				elem[ 'on' + ev ] = null;
			}
		},

		stop: function (ev) {
			var e = ev || window.event;
			e.cancelBubble = true;
			if( e.stopPropagation ){
				e.stopPropagation();
			}
		}
	};

	// Once the DOM is ready we'll initialize our widgets
	utils.contentLoaded( window, function () {
		initialize();
	});
}() );