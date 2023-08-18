<?php
/**
 * @brief		Promote Items
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 Feb 2017
 */

namespace IPS\core\modules\front\promote;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Curated "Our Picks" listing. (Very technical description there)
 */
class _ourpicks extends \IPS\Dispatcher\Controller
{

	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 */
	public static $dataLayerContext = array(
		'community_area' =>  [ 'value' => 'our_picks', 'odkUpdate' => 'true']
	);

	/**
	 * List promoted internally promoted items
	 *
	 * @return void
	 */
	protected function manage()
	{
		if ( ! \IPS\Settings::i()->promote_community_enabled )
		{ 
			\IPS\Output::i()->error( 'promote_no_permission', '2C356/9', 403, '' );
		}
		
		/* Create the table */
		$table = new \IPS\core\Promote\PublicTable( \IPS\Http\Url::internal( 'app=core&module=promote&controller=ourpicks', 'front', 'promote_show' ) );
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/promote.css' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
  		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/promote_responsive.css' ) );
		}

		\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( "app=core&module=promote&controller=ourpicks", 'front', 'promote_show' ), \IPS\Member::loggedIn()->language()->addToStack('promoted_items_title') );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('promote_table_header');
		\IPS\Output::i()->output = $table;
	}
}