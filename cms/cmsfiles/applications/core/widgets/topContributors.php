<?php
/**
 * @brief		topContributors Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		02 Jul 2014
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * topContributors Widget
 */
class _topContributors extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'topContributors';
	
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
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
		$form = parent::configuration( $form );
 		
		$form->add( new \IPS\Helpers\Form\Number( 'number_to_show', isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5, TRUE, array( 'max' => 25 ) ) );
		return $form;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		/* How many? */
		$limit = isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5;
		
		/* Work out who has got the most reputation this week... */
		$topContributorsThisWeek = array();
		foreach ( \IPS\Db::i()->select( array( 'member_received', 'rep_rating' ), 'core_reputation_index', array( 'member_received>0 AND rep_date>?', \IPS\DateTime::create()->sub( new \DateInterval( 'P1W' ) )->getTimestamp() ) ) as $rep )
		{
			if ( !isset( $topContributorsThisWeek[ $rep['member_received'] ] ) )
			{
				$topContributorsThisWeek[ $rep['member_received'] ] = $rep['rep_rating'];
			}
			else
			{
				$topContributorsThisWeek[ $rep['member_received'] ] += $rep['rep_rating'];
			}
		}
		arsort( $topContributorsThisWeek );
		$topContributorsThisWeek = \array_slice( $topContributorsThisWeek, 0, $limit, TRUE );
		
		/* Load their data */	
		if( \count( $topContributorsThisWeek ) )
		{
			foreach ( \IPS\Db::i()->select( '*', 'core_members', \IPS\Db::i()->in( 'member_id', array_keys( $topContributorsThisWeek ) ) ) as $member )
			{
				\IPS\Member::constructFromData( $member );
			}
		}
		
		/* Display */
		return $this->output( $topContributorsThisWeek, $limit );
	}
}