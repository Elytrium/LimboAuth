//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_FriendlyUrl extends _HOOK_CLASS_
{
	/**
	 * Get FURL Definition
	 *
	 * @param	bool	$revert	If TRUE, ignores all customisations and reloads from json
	 * @return	array
	 */
	public static function furlDefinition( $revert=FALSE )
	{
		return array_merge( parent::furlDefinition( $revert ), array( 'content_page_path' => static::buildFurlDefinition( 'app=cms&module=pages&controller=page', 'app=cms&module=pages&controller=page', NULL, FALSE, NULL, FALSE, 'IPS\cms\Pages\Router' ) ) );
	}
	
	/**
	 * Create a friendly URL from a full URL, working out friendly URL data
	 *
	 * This is overridden so that when we are examing a raw URL (such as from \IPS\Http\Url::createFromString()), Pages
	 * can appropriately claim the URL as belonging to it
	 *
	 * @param	array		$components			An array of components as returned by componentsFromUrlString()
	 * @param	string		$potentialFurl		The potential FURL slug (e.g. "topic/1-test")
	 * @return	\IPS\Http\Url\Friendly|NULL
	 */
	public static function createFriendlyUrlFromComponents( $components, $potentialFurl )
	{		
		/* If the normal URL handling has it, or this is the root page, use normal handling, unless Pages is the default app, in which case we'll fallback to it */
		if ( $return = parent::createFriendlyUrlFromComponents( $components, $potentialFurl ) or !$potentialFurl )
		{
			if ( !\IPS\Application::load('cms')->default OR $potentialFurl )
			{
				return $return;
			}
		}
				
		/* Try to find a page */
		try
		{
			list( $pagePath, $pageNumber ) = \IPS\cms\Pages\Page::getStrippedPagePath( $potentialFurl );
			try
			{
				$page = \IPS\cms\Pages\Page::loadFromPath( $pagePath );
			}
			catch( \Exception $e )
			{
				/* Try from furl */
				try
				{
					$page = \IPS\cms\Pages\Page::load( \IPS\Db::i()->select( 'store_current_id', 'cms_url_store', array( 'store_type=? and store_path=?', 'page', $pagePath ) )->first() );
				}
				catch( \UnderflowException $e )
				{
					throw new \OutOfRangeException;
				}
			}

			return static::createFromComponents( $components[ static::COMPONENT_HOST ], $components[ static::COMPONENT_SCHEME ], $components[ static::COMPONENT_PATH ], $components[ static::COMPONENT_QUERY ], $components[ static::COMPONENT_PORT ], $components[ static::COMPONENT_USERNAME ], $components[ static::COMPONENT_PASSWORD ], $components[ static::COMPONENT_FRAGMENT ] )
			->setFriendlyUrlData( 'content_page_path', array( $potentialFurl ), array( 'path' => $potentialFurl ), $potentialFurl );
		}
		/* Couldn't find one? Don't accept responsibility */
		catch ( \OutOfRangeException $e )
		{
			return $return;
		}
		/* The table may not yet exist if we're using the parser in an upgrade */
		catch ( \Exception $e )
		{
			if( $e->getCode() == 1146 )
			{
				return $return;
			}

			throw $e;
		}
	}
	
	/**
	 * Create a friendly URL from a query string with known friendly URL data
	 *
	 * @param	string			$queryString	The query string
	 * @param	string			$seoTemplate	The key for making this a friendly URL
	 * @param	string|array	$seoTitles		The title(s) needed for the friendly URL
	 * @param	int				$protocol		Protocol (one of the PROTOCOL_* constants)
	 * @return	\IPS\Http\Url\Friendly
	 * @throws	\IPS\Http\Url\Exception
	 */
	public static function friendlyUrlFromQueryString( $queryString, $seoTemplate, $seoTitles, $protocol )
	{
		if ( $seoTemplate === 'content_page_path' )
		{
			/* Get the friendly URL component */
			$friendlyUrlComponent = static::buildFriendlyUrlComponentFromData( $queryString, $seoTemplate, $seoTitles );
			
			/* Return */
			return static::friendlyUrlFromComponent( $protocol, $friendlyUrlComponent, $queryString )->setFriendlyUrlData( $seoTemplate, $seoTitles, array( 'path' => $friendlyUrlComponent ), $friendlyUrlComponent );
		}
		
		return parent::friendlyUrlFromQueryString( $queryString, $seoTemplate, $seoTitles, $protocol );
	}
	
	/**
	 * Set friendly URL data
	 *
	 * This is overriden so when we are creating a friendly URL with known data (such as from \IPS\Http\Url::internal()),
	 * Pages can set the data for it's URLs properly
	 *
	 * @param	string			$seoTemplate			The key for making this a friendly URL
	 * @param	string|array	$seoTitles				The title(s) needed for the friendly URL
	 * @param	array			$matchedParams			The values for hidden query string properties
	 * @param	string			$friendlyUrlComponent	The friendly URL component, which may be for the path or the query string (e.g. "topic/1-test")
	 * @return	\IPS\Http\Url\Friendly
	 * @throws	\IPS\Http\Url\Exception
	 */
	protected function setFriendlyUrlData( $seoTemplate, $seoTitles, $matchedParams=array(), string $friendlyUrlComponent = '')
	{		
		if ( $seoTemplate === 'content_page_path' )
		{
			/* Set basic properties */
			$this->seoTemplate = 'content_page_path';
			$this->seoTitles = \is_string( $seoTitles ) ? array( $seoTitles ) : $seoTitles;
			$this->friendlyUrlComponent = $friendlyUrlComponent;
			$this->seoPagination = true;
			
			/* Set hidden query string */
			$this->hiddenQueryString = array( 'app' => 'cms', 'module' => 'pages', 'controller' => 'page' ) + $matchedParams;
			
			/* Return */
			return $this;
		}
		
		return parent::setFriendlyUrlData( $seoTemplate, $seoTitles, $matchedParams, $friendlyUrlComponent );
	}
	
	/**
	 * Get friendly URL data from a query string and SEO template
	 *
	 * This is overriden so when we are creating a friendly URL with known data (such as from \IPS\Http\Url::internal()),
	 * Pages can set the data for it's URLs properly
	 * 
	 * @param	string			$queryString	The query string - is passed by reference and any parts used are removed, which can be used to detect extraneous parts
	 * @param	string			$seoTemplate	The key for making this a friendly URL
	 * @param	string|array	$seoTitles		The title(s) needed for the friendly URL
	 * @return	string
	 * @throws	\IPS\Http\Url\Exception
	 */
	public static function buildFriendlyUrlComponentFromData( &$queryString, $seoTemplate, $seoTitles )
	{
		if ( $seoTemplate === 'content_page_path' )
		{
			parse_str( $queryString, $queryString );
			unset( $queryString['app'] );
			unset( $queryString['module'] );
			unset( $queryString['controller'] );
			unset( $queryString['page'] );
			
			$return = $queryString['path'];
			unset( $queryString['path'] );
			
			return $return;
		}
		
		return parent::buildFriendlyUrlComponentFromData( $queryString, $seoTemplate, $seoTitles );
	}
	
}