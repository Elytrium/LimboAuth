//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_Url extends _HOOK_CLASS_
{	
	/**
	 * Create from string
	 *
	 * This is overridden so that when we are examing a raw URL the "Gateway File" feature
	 * can appropriately claim the URL as belonging to it
	 *
	 * @param	string	$url	A valid URL as per our definition (see phpDoc on class)
	 * @param	bool	$couldBeFriendly	If the URL is known to not be friendly, FALSE can be passed here to save the performance implication of checking the URL
	 * @param	bool	$autoEncode			If true, any invalid components will be automatically encoded rather than an exception thrown - useful if the entire link is user-provided
	 * @return	\IPS\Http\Url
	 */
	public static function createFromString( $url, $couldBeFriendly=TRUE, $autoEncode=FALSE )
	{
		/* See what normal handling makes of it... */
		$return = parent::createFromString( $url, $couldBeFriendly, $autoEncode );
		
		/* If the normal handling doesn't recognise it as an internal URL and we
			have a gateway file, check that */
		if ( !( $return instanceof \IPS\Http\Url\Internal ) and \IPS\Settings::i()->cms_root_page_url )
		{
			/* Decode it */
			$components = static::componentsFromUrlString( $url, $autoEncode );
						
			/* Is it underneath the gateway? */
			$gatewayUrlComponents = static::componentsFromUrlString( \IPS\Settings::i()->cms_root_page_url  );
			if ( $components[ static::COMPONENT_HOST ] === $gatewayUrlComponents[ static::COMPONENT_HOST ] and
				$components[ static::COMPONENT_USERNAME ] === $gatewayUrlComponents[ static::COMPONENT_USERNAME ] and
				$components[ static::COMPONENT_PASSWORD ] === $gatewayUrlComponents[ static::COMPONENT_PASSWORD ] and
				$components[ static::COMPONENT_PORT ] === $gatewayUrlComponents[ static::COMPONENT_PORT ] and
				mb_substr( $components[ static::COMPONENT_PATH ], 0, mb_strlen( $gatewayUrlComponents[ static::COMPONENT_PATH ] ) ) === $gatewayUrlComponents[ static::COMPONENT_PATH ]
			)
			{
				$pathFromGatewayUrl = mb_substr( $components[ static::COMPONENT_PATH ], mb_strlen( $gatewayUrlComponents[ static::COMPONENT_PATH ] ) );
				$fallback = FALSE;
				if ( !$pathFromGatewayUrl or $pathFromGatewayUrl === 'index.php' )
				{
					if ( !$pathFromGatewayUrl )
					{
						$fallback = TRUE;
					}
					$queryString = \IPS\Http\Url::convertQueryAsArrayToString( $components[ static::COMPONENT_QUERY ] );
					$pathFromGatewayUrl = trim( mb_substr( $queryString, 0, mb_strpos( $queryString, '&' ) ?: NULL ), '/' );
				}

				/* Try to find a page */
				$page = NULL;
				try
				{
					$page = \IPS\cms\Pages\Page::loadFromPath( $pathFromGatewayUrl );
					return \IPS\Http\Url\Friendly::createFromComponents( $components[ static::COMPONENT_HOST ], $components[ static::COMPONENT_SCHEME ], $components[ static::COMPONENT_PATH ], $components[ static::COMPONENT_QUERY ], $components[ static::COMPONENT_PORT ], $components[ static::COMPONENT_USERNAME ], $components[ static::COMPONENT_PASSWORD ], $components[ static::COMPONENT_FRAGMENT ] )
					->setFriendlyUrlData( 'content_page_path', array( $pathFromGatewayUrl ), array( 'path' => $pathFromGatewayUrl ), $pathFromGatewayUrl );
				}
				/* Couldn't find one? Don't accept responsibility, unless there was no $pathFromGatewayUrl and this is the gateway URL */
				catch ( \OutOfRangeException $e )
				{
					if ( $fallback and (string) $return->stripQueryString() === \IPS\Settings::i()->cms_root_page_url )
					{
						try
						{
							$page = \IPS\cms\Pages\Page::loadFromPath( '' );
							return \IPS\Http\Url\Friendly::createFromComponents( $components[ static::COMPONENT_HOST ], $components[ static::COMPONENT_SCHEME ], $components[ static::COMPONENT_PATH ], $components[ static::COMPONENT_QUERY ], $components[ static::COMPONENT_PORT ], $components[ static::COMPONENT_USERNAME ], $components[ static::COMPONENT_PASSWORD ], $components[ static::COMPONENT_FRAGMENT ] )
							->setFriendlyUrlData( 'content_page_path', array( '' ), array( 'path' => '' ), '' );
						}
						catch ( \OutOfRangeException $e ) { }
					}
				}
			}
		}
		
		/* Return */
		return $return;
	}
	
}