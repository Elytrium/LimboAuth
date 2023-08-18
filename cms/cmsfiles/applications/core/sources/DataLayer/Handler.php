<?php
/**
 * @brief		DataLayer Handler class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		Feb 2022
 */

namespace IPS\core\DataLayer;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Data Layer Handler class
 */
class _Handler
{
	public static $cicHandlerClass = '\IPS\cloud\DataLayer\Handler';

	public static $handlerCacheKey = 'dataLayerHandlers';

	public $id = 'gtm';

	/**
	 * Returns the enabled handlers to be used in the global template as an array of head and body HTML snippets.
	 *
	 * @return array
	 */
	public static function loadForTemplates()
	{
		$return = array();

		/* try to get from cache */
		$key = static::$handlerCacheKey;
		try
		{
			$cached = json_decode( \IPS\Data\Store::i()->$key, true );
			if ( \is_array( $cached ) )
			{
				return $cached;
			}
		}
		catch ( \OutOfRangeException $e ) {}

		$handlers = static::loadEnabled();
		if ( \IPS\Settings::i()->core_datalayer_use_gtm )
		{
			$handlers[] = static::gtm();
		}

		$headInserts    = "<!-- Handlers -->\n\n<!-- Initializers -->\n";
		$bodyInserts    = "<!-- Handlers -->\n";
		$headCodes      = "";
		$eventCallbacks = array();
		$propertiesCallbacks = array();

		foreach ( $handlers as $handler )
		{

			if ( $handler->head_code )
			{
				$headCodes .= "\n{$handler->head_code}\n";
			}

			if ( $handler->body_code )
			{
				$bodyInserts .= "\n{$handler->body_code}\n";
			}

			if ( $handler->initializer_code AND $handler->use_js )
			{
				$formatted  = str_replace( '{dataLayer}', $handler->datalayer_key, $handler->initializer_code );
				$code       = <<<HTML
<script>
	let initcode{$handler->id} = context => {
        if ( !(context instanceof Object) ) {
            return;
        }
        
        /* Set the key and time */
        let ips_time = IpsDataLayerConfig._properties.ips_time.enabled ? IpsDataLayerConfig._properties.ips_time.formatted_name : false;
        let ips_key  = IpsDataLayerConfig._properties.ips_key.enabled ? IpsDataLayerConfig._properties.ips_key.formatted_name : false;
        
        if ( ips_time ) {
            context[ips_time] = Math.floor( Date.now() / 1000 );
        }
        
        if ( ips_key ) {
            let s = i => {
                return Math.floor((1 + Math.random()) * Math.pow(16, i))
                    .toString(16)
                    .substring(1);
            };

            let mt = Date.now();
            let sec = Math.floor(mt / 1000);
            let secString = sec.toString(16);
            secString = secString.substring( secString.length - 8 );
            let ms  = ( mt - ( sec * 1000 ) ) * 1000; // milliseconds
            let msString = (ms + 0x100000).toString(16).substring(1);
            let randomId = secString + msString + s(1) + '.' + s(4) + s(4);
            context[ips_key] = randomId;
        }
        
        for ( let i in context ) {
            if ( context[i] === null ) {
                context[i] = undefined;
            }
        }
        
        try {
			$formatted
        } catch (e) {
            Debug.error('Bad Data Layer Initializer: Event initializer failed!');
        }
    };
    initcode{$handler->id}(IpsDataLayerContext || {});
</script>
HTML;
				$code           = \preg_replace( '/\/\/(.*)[\n|\r]/', '/*$1*/', $code );
				$code           = \preg_replace( '/\s+/', ' ', $code);
				$headInserts    .= \str_replace("\n", ' ', $code ) . "\n";
			}

			if ( $handler->event_handler AND $handler->use_js )
			{
				$formatted  = str_replace( '{dataLayer}', $handler->datalayer_key, $handler->event_handler );
				$code       = <<<JS
( () => _event => {
    try {
		$formatted
    } catch (e) {
        Debug.error( e );
    }
} )
JS;
				$code           = \preg_replace( '/\/\/(.*)[\n|\r]?/', '/*$1*/', $code );
				$code               = \preg_replace( '/\s+/', ' ', $code);
				$eventCallbacks[]   = \str_replace( "\n", ' ', $code );
			}

			if ( $handler->properties_handler AND $handler->use_js )
			{
				$formatted  = str_replace( '{dataLayer}', $handler->datalayer_key, $handler->properties_handler );
				$code       = <<<JS
( () => _properties => {
    try {
		$formatted
    } catch (e) {
        Debug.error( e );
    }
} )
JS;
				$code           = \preg_replace( '/\/\/(.*)[\n|\r]?/', '/*$1*/', $code );
				$code                   = \preg_replace( '/\s+/', ' ', $code);
				$propertiesCallbacks[]  = \str_replace( "\n", ' ', $code );
			}
		}

		$headInserts    .= "<!-- END Initializers -->\n";
		$headInserts    .= "\n<!-- Head Snippets -->\n$headCodes\n<!-- END Head Snippets -->\n";
		$eventsJoined   = implode( ",\n\t", $eventCallbacks );
		$headInserts    .= <<<HTML

<!-- Event Callbacks -->
<script>
const IpsDataLayerEventHandlers = [
    $eventsJoined
];
</script>
<!-- END Event Callbacks -->

HTML;

		$propertiesJoined   = implode( ",\n\t", $propertiesCallbacks );
		$headInserts        .= <<<HTML

<!-- Properties Callbacks -->
<script>
const IpsDataLayerPropertiesHandlers = [
    $propertiesJoined
];
</script>
<!-- END Properties Callbacks -->

HTML;

		$headInserts .= "\n<!-- END Handlers -->";
		$bodyInserts .= "\n<!-- END Handlers -->";

		$return = array(
			'headInserts' => $headInserts,
			'bodyInserts' => $bodyInserts,
		);

		/* add to cache before returning */
		\IPS\Data\Store::i()->$key = json_encode( $return );

		return $return;
	}

	/**
	 * Get all enabled handlers
	 *
	 * @return static[]
	 */
	public static function loadEnabled()
	{
		if ( class_exists( static::$cicHandlerClass ) )
		{
			$class = static::$cicHandlerClass;
			return $class::loadEnabled();
		}
		return array();
	}

	/**
	 * Get a built in gtm handler
	 *
	 * @return static
	 */
	public static function gtm()
	{
		$gtm = new static();
		$gtm->initializer_code = <<<JS

if (context instanceof Object) {
	{dataLayer} = {dataLayer} || [];
	{dataLayer}.push(context);
    return;
}
Debug.log( 'Invalid Data Layer Context: The IPS GTM Data Layer Initializer failed because the context wasn\'t an Object' );

JS;
		$gtm->head_code = \IPS\Settings::i()->googletag_head_code;
		$gtm->use_js    = true;
		$gtm->body_code = \IPS\Settings::i()->googletag_noscript_code;
		$gtm->datalayer_key = \IPS\Settings::i()->core_datalayer_gtmkey ?: 'window.dataLayer';
		$gtm->event_handler = <<<JS

if ( (_event._properties instanceof Object) && (typeof _event._key === 'string')) {
	{dataLayer} = {dataLayer} || [];
    let properties = {};
    for ( let pKey in _event._properties ) {
        properties[_event._key + '.' + pKey] = _event._properties[pKey];
    }
	{dataLayer}.push( { ...properties, 'event': _event._key } );
    return;
}
Debug.log( 'Invalid Data Layer Event: An event wasn\'t processed by the IPS GTM Data Layer Handler. The event\'s _key has to be a string, and its _properties has to be an Object.' );

JS;
		$gtm->properties_handler = <<<JS

if ( _properties instanceof Object ) {
    delete _properties.event; // this cannot be set since this handler is NOT for adding GTM events
    {dataLayer} = {dataLayer} || [];
    {dataLayer}.push( _properties );
}

JS;
		return $gtm;
	}

	/**
	 * Get all handler objects according to the where clause
	 *
	 * @param   array   $where   The where clause
	 *
	 * @return  array
	 * @throws \UnexpectedValueException If $where contains invalid fields and/or invalid SQL logic
	 */
	public static function loadWhere( array $where=array() )
	{
		if ( ( $class = static::$cicHandlerClass ) AND class_exists( $class ) )
		{
			return $class::loadWhere( $where );
		}
		return array();
	}

	/**
	 * Mock instance method since our autoloader will complain if there are only static methods in a class
	 *
	 * @return bool
	 */
	public function mockFunction()
	{
		return true;
	}
}