<?php
/**
 * @brief		4.0.10 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		07 Jul 2015
 */

namespace IPS\blog\setup\upg_100038;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.10 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Update future entry flag
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if ( !\IPS\Db::i()->checkForColumn( 'blog_entries', 'entry_is_future_entry' ) )
		{
			\IPS\Db::i()->addColumn( 'blog_entries', array(
				'name'			=> 'entry_is_future_entry',
				'type'			=> "TINYINT",
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> "0",
				'comment'		=> "Flag to show if an entry is set to be published in the future via the task",
				'unsigned'		=> true,
			) );
			
			\IPS\Db::i()->update( 'blog_entries', array( 'entry_is_future_entry' => 1 ), array( "entry_date>? AND entry_status=?", time(), 'draft' ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Updating blog future entry flag";
	}
}