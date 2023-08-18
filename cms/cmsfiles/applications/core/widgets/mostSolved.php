<?php
/**
 * @brief		mostSolved Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		09 Mar 2020
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * mostSolved Widget
 */
class _mostSolved extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'mostSolved';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Initialise this widget
	 *
	 * @return void
	 */ 
	public function init()
	{
		parent::init();
	}
	
	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
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
		$topSolvedThisWeek = array();
		foreach ( \IPS\Db::i()->select( 'member_id', 'core_solved_index', array( 'member_id > 0 AND solved_date>?', \IPS\DateTime::create()->sub( new \DateInterval( 'P1W' ) )->getTimestamp() ) ) as $memberId )
		{
			if ( !isset( $topSolvedThisWeek[ $memberId ] ) )
			{
				$topSolvedThisWeek[ $memberId ] = 1;
			}
			else
			{
				$topSolvedThisWeek[ $memberId ]++;
			}
		}
		arsort( $topSolvedThisWeek );
		$topSolvedThisWeek = \array_slice( $topSolvedThisWeek, 0, $limit, TRUE );
		
		/* Load their data */	
		if( \count( $topSolvedThisWeek ) )
		{
			foreach ( \IPS\Db::i()->select( '*', 'core_members', \IPS\Db::i()->in( 'member_id', array_keys( $topSolvedThisWeek ) ) ) as $member )
			{
				\IPS\Member::constructFromData( $member );
			}
		}
		
		/* Display */
		return $this->output( $topSolvedThisWeek, $limit );
	}
}