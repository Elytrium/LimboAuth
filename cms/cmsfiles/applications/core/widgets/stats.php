<?php
/**
 * @brief		Stats Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Nov 2013
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Stats Widget
 */
class _stats extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'stats';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
	
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Specify widget configuration
	 *
	 * @param	\IPS\Helpers\Form|NULL	$form	Form helper
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
		$form = parent::configuration( $form );

		$mostOnline = json_decode( \IPS\Settings::i()->most_online, TRUE );
		$form->add( new \IPS\Helpers\Form\Number( 'stats_most_online', $mostOnline['count'], TRUE ) );
		
		return $form;
 	}
 	
 	/**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
 		if ( \IPS\Member::loggedIn()->isAdmin() and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_recount_content' ) )
 		{
 			$mostOnline = array( 'count' => $values['stats_most_online'], 'time' => time() );
			\IPS\Settings::i()->changeValues( array( 'most_online' => json_encode( $mostOnline ) ) );

			unset( $values['stats_most_online'] );

 			\IPS\Widget::deleteCaches( 'stats', 'core' );
 		}

 		return $values;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$stats = array();
		$mostOnline = json_decode( \IPS\Settings::i()->most_online, TRUE );

		/* fetch only successful registered members ; if this needs to be changed, please review the other areas where we have the name<>? AND email<>? condition */
		$where = array( 'completed=?', true );

		/* Member count */
		$stats['member_count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where )->first();
		
		/* Most online */
		$count = \IPS\Session\Store::i()->getOnlineUsers( \IPS\Session\Store::ONLINE_GUESTS | \IPS\Session\Store::ONLINE_MEMBERS | \IPS\Session\Store::ONLINE_COUNT_ONLY );
		if( $count > $mostOnline['count'] )
		{
			$mostOnline = array( 'count' => $count, 'time' => time() );
			\IPS\Settings::i()->changeValues( array( 'most_online' => json_encode( $mostOnline ) ) );
		}
		$stats['most_online'] = $mostOnline;
				
		/* Last Registered Member */
		$where   = array( array( "completed=1 AND temp_ban != -1" ) );
		$where[] = array( '( ! ' . \IPS\Db::i()->bitwiseWhere( \IPS\Member::$bitOptions['members_bitoptions'], 'bw_is_spammer' ) . ' )' );
		$where[] = array( 'member_id NOT IN(?)', \IPS\Db::i()->select( 'member_id', 'core_validating', array( 'new_reg=1' ) ) );
		$where[] = array( 'NOT(members_bitoptions2 & ?)', \IPS\Member::$bitOptions['members_bitoptions']['members_bitoptions2']['is_support_account'] );

		try
		{
			$stats['last_registered'] = \IPS\Member::constructFromData( \IPS\Db::i()->select( 'core_members.*', 'core_members', $where, 'core_members.member_id DESC', array( 0, 1 ) )->first() );
		}
		catch( \UnderflowException $ex )
		{
			$stats['last_registered'] = NULL;
		}
		
		/* Display */		
		return $this->output( $stats );
	}
}