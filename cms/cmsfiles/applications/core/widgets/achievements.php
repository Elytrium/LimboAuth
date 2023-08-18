<?php
/**
 * @brief		achievements Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		05 Mar 2021
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * achievements Widget
 */
class _achievements extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'achievements';
	
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
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
 		$form = parent::configuration( $form );

		$form->add( new \IPS\Helpers\Form\Translatable( 'widget_feed_title', isset( $this->configuration['language_key'] ) ? NULL : \IPS\Member::loggedIn()->language()->addToStack( 'achievements_widget_title' ), FALSE, array( 'app' => 'core', 'key' => ( isset( $this->configuration['language_key'] ) ? $this->configuration['language_key'] : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'achievements_to_show', isset( $this->configuration['achievements_to_show'] ) ? explode( ',', $this->configuration['achievements_to_show'] ) : [ 'badges', 'ranks' ], TRUE, [ 'options' => [
			'badges'	=> 'block_achievements_badges',
			'ranks'		=> 'block_achievements_rank',
		] ] ) );
		$form->add( new \IPS\Helpers\Form\Number( 'number_to_show', isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5, TRUE, array( 'max' => 25 ) ) );
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
		if ( !isset( $this->configuration['language_key'] ) )
		{
			$this->configuration['language_key'] = 'widget_title_' . md5( mt_rand() );
		}
		$values['language_key'] = $this->configuration['language_key'];
		\IPS\Lang::saveCustom( 'core', $this->configuration['language_key'], $values['widget_feed_title'] );
		unset( $values['widget_feed_title'] );

		$values['achievements_to_show'] = implode( ',', $values['achievements_to_show'] );
 		return $values;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$result = [];
		$toShow = explode( ',', ( $this->configuration['achievements_to_show'] ?? 'badges,ranks' ) );
		$countToShow = $this->configuration['number_to_show'] ?? 5;
		$exclude = json_decode( \IPS\Settings::i()->rules_exclude_groups, TRUE );

		foreach( \IPS\Member\Group::groups() as $group )
		{
			if( ! $group->g_view_board )
			{
				$exclude[] = $group->g_id;
			}
		}

		if ( \is_array( $exclude ) and \count( $exclude ) )
		{
			$subWhere[] = [ \IPS\Db::i()->in( 'member_group_id', $exclude ) . ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $exclude ) ];
		}

		$subWhere[] = [ 'temp_ban != 0 OR ' . \IPS\Db::i()->bitwiseWhere( \IPS\Member::$bitOptions['members_bitoptions'], 'bw_is_spammer' ) ];
		$subQuery = \IPS\Db::i()->select( 'member_id', 'core_members', $subWhere );

		if ( \IPS\core\Achievements\Badge::show() and \in_array( 'badges', $toShow ) )
		{
			foreach ( \IPS\Db::i()->select( '*', 'core_member_badges', ( $subQuery ? [ \IPS\Db::i()->in( 'core_member_badges.member', $subQuery, TRUE ) ] : NULL ), 'datetime DESC', $countToShow )->join( 'core_badges', 'core_member_badges.badge=core_badges.id' ) as $earnedBadge )
			{
				try
				{
					$member =  \IPS\Member::load( $earnedBadge['member'] );
					if ( !$member->member_id )
					{
						throw new \OutOfRangeException;
					}
					
					$result[] = [
						'type'		=> 'badge',
						'badge'		=> \IPS\core\Achievements\Badge::constructFromData( $earnedBadge ),
						'member'	=> $member,
						'date'		=> $earnedBadge['datetime']
					];
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}
		
		if ( \IPS\core\Achievements\Rank::show() and \in_array( 'ranks', $toShow ) )
		{
			$query = [ [ 'new_rank IS NOT NULL' ] ];

			if ( $subQuery )
			{
				$query[] = [ \IPS\Db::i()->in( 'core_points_log.member', $subQuery, TRUE ) ];
			}
			
			foreach ( \IPS\Db::i()->select( '*', 'core_points_log', $query, 'datetime DESC', $countToShow ) as $earnedRank )
			{
				try
				{
					$member =  \IPS\Member::load( $earnedRank['member'] );
					if ( !$member->member_id )
					{
						throw new \OutOfRangeException;
					}
					
					$result[] = [
						'type'		=> 'rank',
						'rank'		=> \IPS\core\Achievements\Rank::load( $earnedRank['new_rank'] ),
						'member'	=> $member,
						'date'		=> $earnedRank['datetime']
					];
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}
		
		if ( ( \IPS\core\Achievements\Badge::show() and \in_array( 'badges', $toShow ) ) and ( \IPS\core\Achievements\Rank::show() and \in_array( 'ranks', $toShow ) ) )
		{
			usort( $result, function( $a, $b ) { return $b['date'] <=> $a['date']; } );
			$result = array_splice( $result, 0, $countToShow );
		}
		
		return $result ? $this->output( $result, isset( $this->configuration['language_key'] ) ? \IPS\Member::loggedIn()->language()->addToStack( $this->configuration['language_key'], FALSE, array( 'escape' => TRUE ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'achievements_widget_title' ) ) : '';
	}

	/**
	 * Before the widget is removed, we can do some clean up
	 *
	 * @return void
	 */
	public function delete()
	{
		\IPS\Lang::deleteCustom( 'core', $this->configuration['language_key'] );
	}
}