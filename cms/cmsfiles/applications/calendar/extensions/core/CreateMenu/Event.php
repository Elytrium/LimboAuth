<?php
/**
 * @brief		Create Menu Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		23 Dec 2013
 */

namespace IPS\calendar\extensions\core\CreateMenu;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Create Menu Extension
 */
class _Event
{
	/**
	 * Get Items
	 *
	 * @return	array
	 */
	public function getItems()
	{
		if ( \IPS\calendar\Calendar::canOnAny( 'add', NULL, \IPS\Settings::i()->club_nodes_in_apps ? array() : array( array( 'cal_club_id IS NULL' ) ) ) )
		{
			if ( !\IPS\Settings::i()->club_nodes_in_apps and $theOnlyNode = \IPS\calendar\Calendar::theOnlyNode() )
			{
				return array(
					'event' => array(
						'link' 	=> \IPS\Http\Url::internal( "app=calendar&module=calendar&controller=submit&do=submit&id=" . $theOnlyNode->_id, 'front', 'calendar_submit' ),
					)
				);
			}
			else
			{
				return array(
					'event' => array(
						'link' => \IPS\Http\Url::internal( "app=calendar&module=calendar&controller=submit&_new=1", 'front', 'calendar_submit' ),
						'extraData'	=> array( 'data-ipsDialog' => true, 'data-ipsDialog-size' => "narrow", ),
						'title' 	=> 'select_calendar'
					)
				);
			}
		}

		return array();
	}
}