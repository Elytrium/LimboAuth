window.ips = {};

/**
 * Event handling
 */
// http://www.anujgakhar.com/2013/05/22/cross-browser-event-handling-in-javascript/
window.ips.eventHandler = {
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

/**
 * Utilities
 */
window.ips.utils = {

	/**
	 * Log to console if supported
	 *
	 * @param	{string} 	message 	Message to log
	 * @returns {void}
	 */
	log: function (message) {
		if( window.console ){
			console.log( "(EMBED): " + message );
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
	 * Gets an element based on ID
	 *
	 * @param	{string} 	id 	Element ID
	 * @returns {element}
	 */
	get: function (id) {
		return document.getElementById( id );
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
	 * Returns parsed information about a URL
	 *
	 * @param 	{string} 	url 	URL to parse
	 * @returns {object}
	 */
	parseURL: function (url) {
		var elem = document.createElement('a');
		ips.utils.insertBefore( elem, document.body.firstChild );
		elem.href = url;

		var data = {
			protocol: elem.protocol,
			hostname: elem.hostname,
			port: elem.port,
			pathname: elem.pathname,
			search: elem.search,
			hash: elem.hash,
			host: elem.host
		};

		elem.parentNode.removeChild( elem );
		return data;
	},

	/**
	 * Returns the document scroll offset
	 *
	 * @returns {object}
	 */
	getScrollOffset: function () {
		var doc = document.documentElement;

		return {
			left: (window.pageXOffset || doc.scrollLeft) - (doc.clientLeft || 0),
			top: (window.pageYOffset || doc.scrollTop)  - (doc.clientTop || 0)
		};
	},

	/**
	 * Returns the offset relative to the document for an element
	 *
	 * @param	{element} 	element 	Element to get the offset for
	 * @returns {object}
	 */
	getOffset: function (element) {
		var p = {
			left: element.offsetLeft || 0,
			top: element.offsetTop || 0
		};

		while (element = element.offsetParent) {
			p.left += element.offsetLeft;
			p.top += element.offsetTop;
		}

		return p;
	},

	/**
	 * Returns the outer height of the element
	 *
	 * @returns {number}
	 */
	getObjHeight: function (elem) {
		return elem.offsetHeight || 0;
	},

	/**
	 * Returns the outer width of the element
	 *
	 * @returns {number}
	 */
	getObjWidth: function (elem) {
		return elem.offsetWidth || 0;
	},

	/**
	 * Fade the element in
	 *
	 * @returns void
	 */
	fadeIn: function (elem) {
		elem.style.opacity = 0;

		var last = +new Date();
		var tick = function() {
			elem.style.opacity = (parseFloat(elem.style.opacity) + (new Date() - last) / 400).toFixed(2);
			last = +new Date();

			if( parseFloat(elem.style.opacity) < 1 ){
				( window.requestAnimationFrame && requestAnimationFrame(tick) ) || setTimeout(tick, 16);
			} else if (parseFloat(elem.style.opacity) >= 1) {
				elem.style.opacity = 1;
			}
		};

		tick();
	},

	/**
	 * Returns the document height
	 *
	 * @returns {number}
	 */
	getDocHeight: function () {
		var D = document;

		return Math.max(
			D.body.scrollHeight, D.documentElement.scrollHeight,
			D.body.offsetHeight, D.documentElement.offsetHeight,
			D.body.clientHeight, D.documentElement.clientHeight
		);
	},

	/**
	 * Returns the viewport height
	 *
	 * @returns {number}
	 */
	getViewportHeight: function () {
		return Math.max( document.documentElement.clientHeight, window.innerHeight || 0 );
	},

	/**
	 * Returns attributes for an element
	 *
	 * @param	{element} 	element 	DOM element
	 * @returns {object}
	 */
	getAttributes: function (element) {
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
	},

	/**
	 * A simple method to insert an element after another element
	 *
	 * @param	{element} 	elem 		The new element
	 * @param 	{element} 	existing	The existing element, after which the new will be inserted
	 * @returns {void}
	 */
	insertAfter: function (elem, existing) {
		existing.parentNode.insertBefore( elem, existing.nextSibling );
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
	},

	/*
	 * Cross-browser 'dom ready' event handler to init the page
	 */
	/*! contentloaded.min.js - https://github.com/dperini/ContentLoaded - Author: Diego Perini - License: MIT */
	contentLoaded: function (b,i) {
		var j=false,k=true,a=b.document,l=a.documentElement,f=a.addEventListener,h=f?'addEventListener':'attachEvent',n=f?'removeEventListener':'detachEvent',g=f?'':'on',c=function(d){if(d.type=='readystatechange'&&a.readyState!='complete')return;(d.type=='load'?b:a)[n](g+d.type,c,false);if(!j&&(j=true))i.call(b,d.type||d)},m=function(){try{l.doScroll('left')}catch(e){setTimeout(m,50);return}c('poll')};if(a.readyState=='complete')i.call(b,'lazy');else{if(!f&&l.doScroll){try{k=!b.frameElement}catch(e){}if(k)m()}a[h](g+'DOMContentLoaded',c,false);a[h](g+'readystatechange',c,false);b[h](g+'load',c,false)}
	}
};

/* Polyfill for Element.closest, from https://developer.mozilla.org/en-US/docs/Web/API/Element/closest */
if (window.Element && !Element.prototype.closest) {
	Element.prototype.closest = function(s) {
		var matches = (this.document || this.ownerDocument).querySelectorAll(s);
		var	i;
		var el = this;
		
		do {
			i = matches.length;
			while (--i >= 0 && matches.item(i) !== el) {};
		} while ((i < 0) && (el = el.parentElement)); 

		return el;
	};
}

/**
  * TextOverflowClamp.js
  *
  * Updated 2014-05-08 to improve speed and fix some bugs.
  *
  * Updated 2013-05-09 to remove jQuery dependancy.
  * But be careful with webfonts!
  *
  * NEW!
  * - Support for padding.
  * - Support for nearby floated elements.
  * - Support for text-indent.
  */

// bind function support for older browsers without it
// https://developer.mozilla.org/en-US/docs/JavaScript/Reference/Global_Objects/Function/bind
if (!Function.prototype.bind) {
  Function.prototype.bind = function (oThis) {
    if (typeof this !== "function") {
      // closest thing possible to the ECMAScript 5 internal IsCallable function
      throw new TypeError("Function.prototype.bind - what is trying to be bound is not callable");
    }

    var aArgs = Array.prototype.slice.call(arguments, 1),
        fToBind = this,
        fNOP = function () {},
        fBound = function () {
          return fToBind.apply(this instanceof fNOP && oThis
                               ? this
                               : oThis,
                               aArgs.concat(Array.prototype.slice.call(arguments)));
        };

    fNOP.prototype = this.prototype;
    fBound.prototype = new fNOP();

    return fBound;
  };
}

// the actual meat is here
(function(w, d){
  var clamp, measure, text, lineWidth,
      lineStart, lineCount, wordStart,
      line, lineText, wasNewLine,
      ce = d.createElement.bind(d),
      ctn = d.createTextNode.bind(d),
      width, widthChild, newWidthChild;

  // measurement element is made a child of the clamped element to get it's style
  measure = ce('span');

  (function(s){
    s.position = 'absolute'; // prevent page reflow
    s.whiteSpace = 'pre'; // cross-browser width results
    s.visibility = 'hidden'; // prevent drawing
  })(measure.style);

  // width element calculates the width of each line
  width = ce('span');
  
  widthChild = ce('span');
  widthChild.style.display = 'block';
  widthChild.style.overflow = 'hidden';
  widthChild.appendChild(ctn("\u2060"));

  clamp = function (el, lineClamp) {
    var i;
    // make sure the element belongs to the document
    if(!el.ownerDocument || !el.ownerDocument === d) return;
    // reset to safe starting values
    lineStart = wordStart = 0;
    lineCount = 1;
    wasNewLine = false;
    //lineWidth = el.clientWidth;
    lineWidth = [];
    // get all the text, remove any line changes
    text = (el.textContent || el.innerText).replace(/\n/g, ' ');
    // create a child block element that accounts for floats
    for(i = 1; i < lineClamp; i++) {
      newWidthChild = widthChild.cloneNode(true);
      width.appendChild(newWidthChild);
      if(i === 1) {
        widthChild.style.textIndent = 0;
      }
    }
    widthChild.style.textIndent = '';
    // cleanup
    newWidthChild = void 0;
    // remove all content
    while(el.firstChild)
      el.removeChild(el.firstChild);
    // ready for width calculating magic
    el.appendChild(width);
    // then start calculating widths of each line
    for(i = 0; i < lineClamp - 1; i++) {
      lineWidth.push(width.childNodes[i].clientWidth);
    }
    // we are done, no need for this anymore
    el.removeChild(width);
    // cleanup the lines
    while(width.firstChild)
      width.removeChild(width.firstChild);
    // add measurement element within so it inherits styles
    el.appendChild(measure);
    // http://ejohn.org/blog/search-and-dont-replace/
    text.replace(/ /g, function(m, pos) {
      // ignore any further processing if we have total lines
      if(lineCount === lineClamp) return;
      // create a text node and place it in the measurement element
      measure.appendChild(ctn(text.substr(lineStart, pos - lineStart)));
      // have we exceeded allowed line width?
      if(lineWidth[lineCount - 1] <= measure.clientWidth) {
        if(wasNewLine) {
          // we have a long word so it gets a line of it's own
          lineText = text.substr(lineStart, pos + 1 - lineStart);
          // next line start position
          lineStart = pos + 1;
        } else {
          // grab the text until this word
          lineText = text.substr(lineStart, wordStart - lineStart);
          // next line start position
          lineStart = wordStart;
        }
        // create a line element
        line = ce('span');
        // add text to the line element
        line.appendChild(ctn(lineText));
        // add the line element to the container
        el.appendChild(line);
        // yes, we created a new line
        wasNewLine = true;
        lineCount++;
      } else {
        // did not create a new line
        wasNewLine = false;
      }
      // remember last word start position
      wordStart = pos + 1;
      // clear measurement element
      measure.removeChild(measure.firstChild);
    });
    // remove the measurement element from the container
    el.removeChild(measure);
    // create the last line element
    line = ce('span');
    // see if we need to add styles
    if(lineCount === lineClamp) {
      // give styles required for text-overflow to kick in
      (function(s) {
        s.display = 'block';
        s.overflow = 'hidden';
        s.textIndent = 0;
        s.textOverflow = 'ellipsis';
        s.whiteSpace = 'nowrap';
      })(line.style);
    }
    // add all remaining text to the line element
    line.appendChild(ctn(text.substr(lineStart)));
    // add the line element to the container
    el.appendChild(line);
  }
  w.clamp = clamp;
})(window, window.document);