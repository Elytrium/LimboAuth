<?php
/**
 * @brief		Abstract class that Controllers should extend
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract class that Controllers should extend
 */
abstract class _Controller
{
	/**
	 * @brief	Base URL
	 */
	public $url;

	/**
	 * @brief	Is this for displaying "content"? Affects if advertisements may be shown
	 */
	public $isContentPage = TRUE;

	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 * @example array(
		'community_area' => array( 'value' => 'search', 'odkUpdate' => 'true' )
	 * )
	 */
	public static $dataLayerContext = array();
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url|NULL	$url		The base URL for this controller or NULL to calculate automatically
	 * @return	void
	 */
	public function __construct( $url=NULL )
	{
		if ( $url === NULL )
		{
			$class		= \get_called_class();
			$exploded	= explode( '\\', $class );
			$this->url = \IPS\Http\Url::internal( "app={$exploded[1]}&module={$exploded[4]}&controller={$exploded[5]}", \IPS\Dispatcher::i()->controllerLocation );
		}
		else
		{
			$this->url = $url;
		}
	}

	/**
	 * Force a specific method within a controller to execute.  Useful for unit testing.
	 *
	 * @param	null|string		$method		The specific method to call
	 * @return	mixed
	 */
	public function forceExecute( $method=NULL )
	{
		if( \IPS\ENFORCE_ACCESS === true and $method !== null )
		{
			if ( method_exists( $this, $method ) )
			{
				return $this->$method();
			}
			else
			{
				return $this->execute();
			}
		}

		return $this->execute();
	}

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( !empty( static::$dataLayerContext ) AND \IPS\Settings::i()->core_datalayer_enabled AND !\IPS\Request::i()->isAjax() )
		{
			foreach ( static::$dataLayerContext as $property => $data )
			{
				\IPS\core\DataLayer::i()->addContextProperty( $property, $data['value'], $data['odkUpdate'] ?? false );
			}
		}

		if( \IPS\Request::i()->do and preg_match( '/^[a-zA-Z\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', \IPS\Request::i()->do ) )
		{
			if ( method_exists( $this, \IPS\Request::i()->do ) or method_exists( $this, '__call' ) )
			{
				$method = \IPS\Request::i()->do;
				$this->$method();
			}
			else
			{
				\IPS\Output::i()->error( 'page_not_found', '2S106/1', 404, '' );
			}
		}
		else
		{
			if ( method_exists( $this, 'manage' ) or method_exists( $this, '__call' ) )
			{
				$this->manage();
			}
			else
			{
				\IPS\Output::i()->error( 'page_not_found', '2S106/2', 404, '' );
			}
		}
	}
	
	/**
	 * Embed
	 *
	 * @return	void
	 */
	protected function embed()
	{
		$title		= \IPS\Member::loggedIn()->language()->addToStack('error_title');
		$content	= null;
		
		if( !isset( static::$contentModel ) )
		{
			$output = \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedUnavailable();
		}
		else
		{			
	        try
	        {
	            $class = static::$contentModel;
	            $params = array();
	            	            
	            if( \IPS\Request::i()->embedComment )
	            {
		            $commentClass = $class::$commentClass;
		            if ( isset( $class::$archiveClass ) )
		            {
			            $item = $class::load( \IPS\Request::i()->id );
			            if ( $item->isArchived() )
			            {
				            $commentClass = $class::$archiveClass;
			            }
		            }
		            
		            $content = $commentClass::load( \IPS\Request::i()->embedComment );
		            $title = $content->item()->mapped('title');
				}
				elseif( \IPS\Request::i()->embedReview )
	            {
		            $reviewClass = $class::$reviewClass;
		            $content = $reviewClass::load( \IPS\Request::i()->embedReview );
		            $title = $content->item()->mapped('title');
				}
				else
				{
	                if ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page > 1 )
	                {
		                $params['page'] = \intval( \IPS\Request::i()->page );
	                }
	                if ( isset( \IPS\Request::i()->embedDo ) )
	                {
		                $params['do'] = \IPS\Request::i()->embedDo;
	                }

	                $content = $class::load( \IPS\Request::i()->id );
	                $title = $content instanceof \IPS\Node\Model ? $content->_title : $content->mapped( 'title' );
				}
				$output = $this->getEmbedOutput( $content, $params );
			}
			catch ( \OutOfRangeException $e )
			{
				$output = \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedUnavailable();
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'embed_error' );
				$output = \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedNoPermission();
			}
		}
		
		/* Make sure our iframe contents get the necessary elements and JS */
		$js = array(
			\IPS\Output::i()->js( 'js/commonEmbedHandler.js', 'core', 'interface' ),
			\IPS\Output::i()->js( 'js/internalEmbedHandler.js', 'core', 'interface' )
		);
		\IPS\Output::i()->base = '_parent';

		/* We need to keep any embed.css files that have been specified so that we can re-add them after we re-fetch the css framework */
		$embedCss = array();
		foreach( \IPS\Output::i()->cssFiles as $cssFile )
		{
			if( \mb_stristr( $cssFile, 'embed.css' ) )
			{
				$embedCss[] = $cssFile;
			}
		}

		/* We need to reset the included CSS files because by this point the responsive files are already in the output CSS array */
		\IPS\Output::i()->cssFiles = array();
		\IPS\Output::i()->responsive = FALSE;
		\IPS\Dispatcher\Front::baseCss();
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, $embedCss );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/embeds.css', 'core', 'front' ) );

		/* Seo Stuffs */
		\IPS\Output::i()->title	= $title;

		if( $content !== NULL )
		{
			\IPS\Output::i()->linkTags['canonical'] = (string) $content->url();
		}
		else
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}

		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->embedInternal( $output, $js ), 200, 'text/html' );
    }

	/**
	 * Returns the content item or the proper error message
	 *
	 * @param \IPS\Content $content
	 * @param array $params
	 *
	 * @return string
	 */
    protected function getEmbedOutput( $content, array $params = array() )
	{
		if ( !$content->canView() )
		{
			$output = \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedNoPermission();
		}
		else if ( !( $content instanceof \IPS\Content\Embeddable ) )
		{
			$output = \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedUnavailable();
		}
		else
		{
			$output = $content->embedContent( $params );
		}

		return $output;
	}
}
