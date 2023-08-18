<?php
/**
 * @brief		ACP Live Search
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Sep 2013
 */

namespace IPS\core\modules\admin\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Live Search
 */
class _livesearch extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'livesearch_manage', 'core', 'livesearch' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$results = array();
		
		try
		{
			$exploded = explode( '_', \IPS\Request::i()->search_key );
			$app = \IPS\Application::load( $exploded[0] );
			foreach ( $app->extensions( 'core', 'LiveSearch' ) as $k => $extension )
			{
				if ( $k === $exploded[1] and method_exists( $extension, 'getResults' ) )
				{
					$results = $extension->getResults( urldecode( \IPS\Request::i()->search_term ) );
				}
			}
		}
		catch ( \OutOfRangeException $e ) { }
						
		\IPS\Output::i()->json( array_values( $results ) );
	}
}