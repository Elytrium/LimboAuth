<?php
/**
 * @brief		Member visit statistics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Mar 2017
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member visit statistics
 */
class _membervisits extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'membervisits_manage' );
		parent::execute();
	}

	/**
	 * Member visit statistics
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$count		= NULL;
		$table		= NULL;
		$start		= NULL;
		$end		= NULL;

		$defaults = array( 'start' => \IPS\DateTime::create()->setDate( date('Y'), date('m'), 1 ), 'end' => new \IPS\DateTime );

		if( isset( \IPS\Request::i()->visitDateStart ) AND isset( \IPS\Request::i()->visitDateEnd ) )
		{
			$defaults = array( 'start' => \IPS\DateTime::ts( \IPS\Request::i()->visitDateStart ), 'end' => \IPS\DateTime::ts( \IPS\Request::i()->visitDateEnd ) );
		}

		$groupOptions = array_combine( array_keys( \IPS\Member\Group::groups( TRUE, FALSE ) ), array_map( function( $_group ) { return (string) $_group; }, \IPS\Member\Group::groups( TRUE, FALSE ) ) );

		if( isset( \IPS\Request::i()->visitGroups ) )
		{
			$defaultGroups = explode( ',', \IPS\Request::i()->visitGroups );
		}
		else
		{
			$defaultGroups = array_keys( $groupOptions );
		}

		$form = new \IPS\Helpers\Form( 'visits', 'continue' );
		$form->add( new \IPS\Helpers\Form\DateRange( 'date', $defaults, TRUE ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'groups', $defaultGroups, FALSE, array( 'options' => $groupOptions ), NULL, NULL, NULL, 'group_filters' ) );

		if( $values = $form->values() )
		{
			/* Determine start and end time */
			$startTime	= $values['date']['start']->getTimestamp();
			$endTime	= $values['date']['end']->getTimestamp();

			$start		= $values['date']['start']->html();
			$end		= $values['date']['end']->html();

			$groups		= ( \count( array_diff( array_keys( $groupOptions ), $values['groups'] ) ) ) ? $values['groups'] : NULL;
		}
		else
		{
			/* Determine start and end time */
			$startTime	= $defaults['start']->getTimestamp();
			$endTime	= $defaults['end']->getTimestamp();

			$start		= $defaults['start']->html();
			$end		= $defaults['end']->html();

			$groups		= ( \count( array_diff( array_keys( $groupOptions ), $defaultGroups ) ) ) ? $defaultGroups : NULL;
		}

		/* Do we have our date ranges? */
		if( $start AND $end )
		{
			/* Build our where clause */
			$where = array( array( 'last_visit BETWEEN ? AND ?', $startTime, $endTime ) );

			if( $groups !== NULL )
			{
				$where[] = array( '(' . \IPS\Db::i()->in( 'member_group_id', $groups ) . ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $groups ) . ')' );
			}

			/* Get the count */
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where )->first();
			
			/* And now build the table */
			$table = new \IPS\Helpers\Table\Db( 'core_members', \IPS\Request::i()->url()->setQueryString( array( 'visitDateStart' => $startTime, 'visitDateEnd' => $endTime, 'visitGroups' => \is_array( $groups ) ? implode( ',', $groups ) : NULL ) ), $where );

			$table->include = array( 'name', 'email', 'last_visit', 'group_name', 'ip_address' );
			$table->mainColumn = 'name';
			$table->langPrefix = 'visits_';
			$table->rowClasses = array( 'email' => array( 'ipsTable_wrap' ), 'group_name' => array( 'ipsTable_wrap' ) );

			/* Default sort options */
			$table->sortBy = $table->sortBy ?: 'last_visit';
			$table->sortDirection = $table->sortDirection ?: 'desc';
			
			/* Custom parsers */
			$table->parsers = array(
				'name'			=> function( $val, $row )
				{
					$member = \IPS\Member::constructFromData( $row );
					return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $member, 'tiny' ) . ' ' . $member->link();
				},
				'email'				=> function( $val, $row )
				{
					return \IPS\Theme::i()->getTemplate( 'members', 'core', 'admin' )->memberEmailCell( htmlentities( $val, ENT_DISALLOWED, 'UTF-8', FALSE ) );				
				},
				'last_visit'				=> function( $val, $row )
				{
					return \IPS\DateTime::ts( $val )->html();
				},
				'group_name'	=> function( $val, $row )
				{
					$secondary = \IPS\Member::constructFromData( $row )->groups;
					
					foreach( $secondary as $k => $v )
					{
						if( $v == $row['member_group_id'] or $v == 0 )
						{
							unset( $secondary[ $k ] );
							continue;
						}
						
						$secondary[ $k ] = \IPS\Member\Group::load( $v );
					}

					return \IPS\Theme::i()->getTemplate( 'members', 'core', 'admin' )->groupCell( \IPS\Member\Group::load( $row['member_group_id'] ), $secondary );
				},
				'ip_address'	=> function( $val, $row )
				{
					if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_ip' ) )
					{
						return "<a href='" . \IPS\Http\Url::internal( "app=core&module=members&controller=ip&ip={$val}" ) . "'>{$val}</a>";
					}
					return $val;
				},
			);

			$table->extraHtml = \IPS\Theme::i()->getTemplate( 'stats' )->tableheader( $start, $end, $count, "member_visits_results" );
		}

		$formHtml = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'stats' ), 'filtersFormTemplate' ) );

		\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_stats.js', 'core' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_membervisits');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'stats' )->membervisits( $formHtml, $count, $table );
	}
}