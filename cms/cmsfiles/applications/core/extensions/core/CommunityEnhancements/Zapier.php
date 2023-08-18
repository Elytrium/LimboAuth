<?php
/**
 * @brief		Community Enhancements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		06 Feb 2020
 */

namespace IPS\core\extensions\core\CommunityEnhancements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Enhancement
 */
class _Zapier
{
	/**
	 * Get the permissions needed for the Zapier API key
	 *
	 * Since 4.7.1 we don't need to call any other methods while the upgrade, just adjust the permission here, everything else will be handled automatically.
	 *
	 * @return	array
	 */
	public static function apiKeyPermissions()
	{
		$return = array(
			'core/clubs/GETindex'				=> array( 'access' => TRUE ),
			'core/clubs/GETitem'				=> array( 'access' => TRUE ),
			'core/groups/GETindex'				=> array( 'access' => TRUE ),
			'core/hello/GETindex'				=> array( 'access' => TRUE ),
			'core/members/GETindex'				=> array( 'access' => TRUE ),
			'core/members/GETitem'				=> array( 'access' => TRUE ),
			'core/members/POSTindex'			=> array( 'access' => TRUE ),
			'core/members/POSTitem_secgroup'	=> array( 'access' => TRUE ),
			'core/members/DELETEitem_secgroup'	=> array( 'access' => TRUE ),
			'core/members/POSTitem'				=> array( 'access' => TRUE ),
			'core/webhooks/POSTindex'			=> array( 'access' => TRUE ),
			'core/webhooks/DELETEitem'			=> array( 'access' => TRUE ),
			'core/promotions/GETindex'			=> array( 'access' => TRUE ),
			'core/promotions/GETitem'			=> array( 'access' => TRUE ),
		);
		
		if ( \IPS\Application::appIsEnabled('forums') )
		{
			$return['forums/forums/GETindex'] 	= array( 'access' => TRUE );
			$return['forums/forums/GETitem'] 	= array( 'access' => TRUE );
			$return['forums/topics/GETindex'] 	= array( 'access' => TRUE );
			$return['forums/topics/GETitem'] 	= array( 'access' => TRUE );
			$return['forums/topics/POSTindex'] 	= array( 'access' => TRUE );
			$return['forums/posts/GETindex'] 	= array( 'access' => TRUE );
			$return['forums/posts/GETitem'] 	= array( 'access' => TRUE );
			$return['forums/posts/POSTindex'] 	= array( 'access' => TRUE );
		}
		
		if ( \IPS\Application::appIsEnabled('calendar') )
		{
			$return['calendar/calendars/GETindex'] 	= array( 'access' => TRUE );
			$return['calendar/calendars/GETitem'] 	= array( 'access' => TRUE );
			$return['calendar/events/GETindex'] 	= array( 'access' => TRUE );
			$return['calendar/events/GETitem'] 		= array( 'access' => TRUE );
			$return['calendar/events/POSTindex'] 	= array( 'access' => TRUE );
			$return['calendar/comments/GETindex'] 	= array( 'access' => TRUE );
			$return['calendar/comments/GETitem'] 	= array( 'access' => TRUE );
			$return['calendar/comments/POSTindex'] 	= array( 'access' => TRUE );
			$return['calendar/reviews/GETindex'] 	= array( 'access' => TRUE );
			$return['calendar/reviews/GETitem'] 	= array( 'access' => TRUE );
			$return['calendar/reviews/POSTindex'] 	= array( 'access' => TRUE );
		}
		
		if ( \IPS\Application::appIsEnabled('downloads') )
		{
			$return['downloads/categories/GETindex'] 	= array( 'access' => TRUE );
			$return['downloads/categories/GETitem'] 	= array( 'access' => TRUE );
			$return['downloads/files/GETindex'] 		= array( 'access' => TRUE );
			$return['downloads/files/GETitem'] 			= array( 'access' => TRUE );
			$return['downloads/files/POSTindex'] 		= array( 'access' => TRUE );
			$return['downloads/comments/GETindex']	 	= array( 'access' => TRUE );
			$return['downloads/comments/GETitem'] 		= array( 'access' => TRUE );
			$return['downloads/comments/POSTindex'] 	= array( 'access' => TRUE );
			$return['downloads/reviews/GETindex'] 		= array( 'access' => TRUE );
			$return['downloads/reviews/GETitem'] 		= array( 'access' => TRUE );
			$return['downloads/reviews/POSTindex'] 		= array( 'access' => TRUE );
		}

		if ( \IPS\Application::appIsEnabled('blog') )
		{
			$return['blog/categories/GETindex'] 	= array( 'access' => TRUE );
			$return['blog/categories/GETitem'] 		= array( 'access' => TRUE );
			$return['blog/blogs/GETindex'] 			= array( 'access' => TRUE );
			$return['blog/blogs/GETitem'] 			= array( 'access' => TRUE );
			$return['blog/blogs/POSTindex'] 		= array( 'access' => TRUE );
			$return['blog/entrycategories/GETindex'] 			= array( 'access' => TRUE );
			$return['blog/entrycategories/GETitem'] 			= array( 'access' => TRUE );
			$return['blog/entries/GETindex'] 		= array( 'access' => TRUE );
			$return['blog/entries/GETitem'] 		= array( 'access' => TRUE );
			$return['blog/entries/POSTindex'] 		= array( 'access' => TRUE );
			$return['blog/comments/GETindex']	 	= array( 'access' => TRUE );
			$return['blog/comments/GETitem'] 		= array( 'access' => TRUE );
			$return['blog/comments/POSTindex'] 		= array( 'access' => TRUE );
		}
		
		if ( \IPS\Application::appIsEnabled('gallery') )
		{
			$return['gallery/categories/GETindex'] 	= array( 'access' => TRUE );
			$return['gallery/categories/GETitem'] 	= array( 'access' => TRUE );
			$return['gallery/albums/GETindex'] 		= array( 'access' => TRUE );
			$return['gallery/albums/GETitem'] 		= array( 'access' => TRUE );
			$return['gallery/images/GETindex'] 		= array( 'access' => TRUE );
			$return['gallery/images/GETitem'] 		= array( 'access' => TRUE );
			$return['gallery/images/POSTindex'] 	= array( 'access' => TRUE );
			$return['gallery/comments/GETindex']	= array( 'access' => TRUE );
			$return['gallery/comments/GETitem'] 	= array( 'access' => TRUE );
			$return['gallery/comments/POSTindex'] 	= array( 'access' => TRUE );
			$return['gallery/reviews/GETindex'] 	= array( 'access' => TRUE );
			$return['gallery/reviews/GETitem'] 		= array( 'access' => TRUE );
			$return['gallery/reviews/POSTindex'] 	= array( 'access' => TRUE );
		}
		
		if ( \IPS\Application::appIsEnabled('cms') )
		{
			$return['cms/databases/GETindex'] 	= array( 'access' => TRUE );
			$return['cms/databases/GETitem'] 	= array( 'access' => TRUE );
			$return['cms/categories/GETindex'] 	= array( 'access' => TRUE );
			$return['cms/categories/GETitem'] 	= array( 'access' => TRUE );
			$return['cms/records/GETindex'] 	= array( 'access' => TRUE );
			$return['cms/records/GETitem'] 		= array( 'access' => TRUE );
			$return['cms/records/POSTindex'] 	= array( 'access' => TRUE );
			$return['cms/comments/GETindex']	= array( 'access' => TRUE );
			$return['cms/comments/GETitem'] 	= array( 'access' => TRUE );
			$return['cms/comments/POSTindex'] 	= array( 'access' => TRUE );
			$return['cms/reviews/GETindex'] 	= array( 'access' => TRUE );
			$return['cms/reviews/GETitem'] 		= array( 'access' => TRUE );
			$return['cms/reviews/POSTindex'] 	= array( 'access' => TRUE );
		}
		
		return $return;
	}

	/**
	 * @brief	Enhancement is enabled?
	 */
	public $enabled	= FALSE;

	/**
	 * @brief	IPS-provided enhancement?
	 */
	public $ips	= FALSE;

	/**
	 * @brief	Enhancement has configuration options?
	 */
	public $hasOptions	= TRUE;

	/**
	 * @brief	Icon data
	 */
	public $icon	= "zapier.png";
	
	/**
	 * Can we use this?
	 *
	 * @return	void
	 */
	public static function isAvailable()
	{
		return TRUE;
	}
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		if ( \IPS\Settings::i()->zapier_api_key )
		{
			try
			{
				$apiKey = \IPS\Api\Key::load( \IPS\Settings::i()->zapier_api_key );
				$this->enabled = (bool) json_decode( $apiKey->permissions, TRUE );
			}
			catch ( \OutOfRangeException $e ) {}
		}
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		$apiKey = \IPS\Api\Key::load( \IPS\Settings::i()->zapier_api_key );
		
		$correctPermissions = json_encode( static::apiKeyPermissions() );
		if ( $apiKey->permissions != $correctPermissions )
		{
			$apiKey->permissions = $correctPermissions;
			$apiKey->save();
		}
		
		try
		{
			$this->testSettings();
		}
		catch ( \DomainException $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '3C414/2' );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'api' )->zapier( $apiKey );
	}
	
	/**
	 * Enable/Disable
	 *
	 * @param	$enabled	bool	Enable/Disable
	 * @return	void
	 * @throws	\LogicException
	 */
	public function toggle( $enabled )
	{
		$isNew = FALSE;
		try
		{
			$apiKey = \IPS\Api\Key::load( \IPS\Settings::i()->zapier_api_key );
		}
		catch ( \OutOfRangeException $e )
		{
			$isNew = TRUE;
			
			$apiKey = new \IPS\Api\Key;
			$apiKey->id = \IPS\Login::generateRandomString( 32 );
		}
				
		if ( $enabled )
		{
			try
			{
				$this->testSettings();
			}
			catch ( \DomainException $e )
			{
				\IPS\Output::i()->error( $e->getMessage(), '3C414/1' );
			}
			
			$apiKey->permissions = json_encode( static::apiKeyPermissions() );
			\IPS\Db::i()->update( 'core_api_webhooks', array( 'enabled' => 1 ), array( 'api_key=?', $apiKey->id ) );
		}
		else
		{
			$apiKey->permissions = json_encode( array() );
			\IPS\Db::i()->update( 'core_api_webhooks', array( 'enabled' => 0 ), array( 'api_key=?', $apiKey->id ) );
		}
		
		$apiKey->allowed_ips = NULL;
		$apiKey->save();
		
		\IPS\Settings::i()->changeValues( array( 'zapier_api_key' => $apiKey->id ) );
		
		if ( $isNew )
		{
			\IPS\Lang::saveCustom( 'core', "core_api_name_{$apiKey->id}", "Zapier" );
			
			throw new \DomainException;
		}
	}
	
	/**
	 * Test Settings
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	protected function testSettings()
	{
		if ( !\IPS\Settings::i()->use_friendly_urls or !\IPS\Settings::i()->htaccess_mod_rewrite )
		{
			throw new \DomainException( 'zapier_error_friendly_urls' );
		}

		$url = \IPS\Http\Url::external( rtrim( \IPS\Settings::i()->base_url, '/' ) . '/api/core/hello' );
		try
		{
			if ( \IPS\Request::i()->isCgi() )
			{
				$response = $url->setQueryString( 'key', 'test' )->request()->get()->decodeJson();
			}
			else
			{
				$response = $url->request()->login( 'test', '' )->get()->decodeJson();
			}
			if ( isset( $response['errorMessage'] ) AND $response['errorMessage'] == 'IP_ADDRESS_BANNED' )
			{
				throw new \Exception;
			}
			
			if ( $response['errorMessage'] != 'INVALID_API_KEY' and $response['errorMessage'] != 'TOO_MANY_REQUESTS_WITH_BAD_KEY' )
			{
				throw new \Exception;
			}
		}
		catch ( \Exception $e )
		{
			throw new \DomainException( 'zapier_error_api' );
		}
	}

	/**
	 * Give the Zapier REST Key all required permissions.
	 *
	 * @return void
	 */
	public static function rebuildRESTApiPermissions()
	{
		/* Rebuild Zapier REST API Key Permissions */
		if( \IPS\Settings::i()->zapier_api_key )
		{
			try
			{
				$apiKey = \IPS\Api\Key::load( \IPS\Settings::i()->zapier_api_key );

				$correctPermissions = json_encode( static::apiKeyPermissions() );
				if ( $apiKey->permissions != $correctPermissions )
				{
					$apiKey->permissions = $correctPermissions;
					$apiKey->save();
				}
			}
			catch ( \OutOfRangeException $e ) {}
		}
	}
}