<?php
/**
 * @brief		Members Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 July 2015
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Members Widget
 */
class _members extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'members';
	
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

	    /* Block title */
	    $form->add( new \IPS\Helpers\Form\Text( 'widget_member_title', isset( $this->configuration['widget_member_title'] ) ? $this->configuration['widget_member_title'] : \IPS\Member::loggedIn()->language()->addToStack( 'widget_member_title_default' ) ) );

	    $form->add( new \IPS\Helpers\Form\Select(
            'widget_member_groups',
            ( isset( $this->configuration['widget_member_groups'] ) ) ? ( $this->configuration['widget_member_groups'] === '*' ? '*' : $this->configuration['widget_member_groups'] ) : '*',
            FALSE,
            array( 'options' => \IPS\Member\Group::groups(), 'multiple' => TRUE, 'parse' => 'normal', 'unlimited' => '*', 'unlimitedLang' => 'all' ),
            NULL,
            NULL,
            NULL,
            'widget_member_groups'
        ) );
		
		$form->add( new \IPS\Helpers\Form\Checkbox( 'widget_member_secondary_groups', isset( $this->configuration['widget_member_secondary_groups'] ) ? $this->configuration['widget_member_secondary_groups'] : FALSE, FALSE ) );

	    $form->add( new \IPS\Helpers\Form\Number( 'widget_member_posts', isset( $this->configuration['widget_member_posts'] ) ? $this->configuration['widget_member_posts'] : 0, FALSE ) );
	    $form->add( new \IPS\Helpers\Form\Number( 'widget_member_rep'  , isset( $this->configuration['widget_member_rep'] ) ? $this->configuration['widget_member_rep'] : 0, FALSE ) );

	    $form->add( new \IPS\Helpers\Form\YesNo( 'widget_member_online', isset( $this->configuration['widget_member_online'] ) ? $this->configuration['widget_member_online'] : TRUE, FALSE ) );
	    $form->add( new \IPS\Helpers\Form\YesNo( 'widget_member_born_today', isset( $this->configuration['widget_member_born_today'] ) ? $this->configuration['widget_member_born_today'] : FALSE, FALSE ) );
	    $form->add( new \IPS\Helpers\Form\YesNo( 'widget_member_born_month', isset( $this->configuration['widget_member_born_month'] ) ? $this->configuration['widget_member_born_month'] : FALSE, FALSE ) );

	    if ( \IPS\Application::appIsEnabled('blog') )
	    {
		    $form->add( new \IPS\Helpers\Form\YesNo( 'widget_member_has_blog', isset( $this->configuration['widget_member_has_blog'] ) ? $this->configuration['widget_member_has_blog'] : FALSE, FALSE ) );
	    }

	    if ( \IPS\Application::appIsEnabled('gallery') )
	    {
		    $form->add( new \IPS\Helpers\Form\YesNo( 'widget_member_has_album', isset( $this->configuration['widget_member_has_album'] ) ? $this->configuration['widget_member_has_album'] : FALSE, FALSE ) );
	    }

	    $form->add( new \IPS\Helpers\Form\Number( 'widget_member_show', ( isset( $this->configuration['widget_member_show'] ) and $this->configuration['widget_member_show'] != -1 ) ? $this->configuration['widget_member_show'] : 100, FALSE ) );

	    $form->add( new \IPS\Helpers\Form\Select( 'widget_feed_sort_on', isset( $this->configuration['widget_feed_sort_on'] ) ? $this->configuration['widget_feed_sort_on'] : 'last_activity', FALSE, array( 'options' => array(
		    'name'                 => 'widget__sort_name',
		    'member_posts'         => 'widget__sort_posts',
		    'joined'               => 'widget__sort_joined',
		    'last_activity'        => 'widget__sort_activity',
		    'last_visit'           => 'widget__sort_visit',
		    'member_last_post'     => 'widget__sort_last_post',
			'_age'                 => 'widget__sort_age',
			'pp_reputation_points' => 'widget__sort_points',
		    '_random'              => 'widget__sort_random'
	    ) ) ), NULL, NULL, NULL, 'widget_feed_sort_on' );

	    $form->add( new \IPS\Helpers\Form\Select( 'widget_feed_sort_dir', isset( $this->configuration['widget_feed_sort_dir'] ) ? $this->configuration['widget_feed_sort_dir'] : 'desc', FALSE, array(
		    'options' => array(
			    'desc'   => 'descending',
			    'asc'    => 'ascending'
		    )
	    ) ) );

	    $form->add( new \IPS\Helpers\Form\Radio( 'widget_member_display', isset( $this->configuration['widget_member_display'] ) ? $this->configuration['widget_member_display'] : 'csv', FALSE, array(
		    'options' => array(
			    'csv'    => 'widget__display_csv',
			    'list'   => 'widget__display_list'
		    )
	    ) ) );

		return $form;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$where = array( array( 'completed=?', true ) );
		$joins = array();

		if ( ! empty( $this->configuration['widget_member_groups'] ) AND $this->configuration['widget_member_groups'] !== '*' )
		{
			$groupWhere = \IPS\Db::i()->in( 'member_group_id', $this->configuration['widget_member_groups'] );
			
			if ( ! empty( $this->configuration['widget_member_secondary_groups'] ) )
			{
				$groupWhere .= ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $this->configuration['widget_member_groups'] );
			}
			
			$where[] = array( '(' . $groupWhere . ')' );
		}

		if ( ! empty( $this->configuration['widget_member_posts'] ) )
		{
			$where[] = array( 'member_posts >= ?', $this->configuration['widget_member_posts'] );
		}

		if ( ! empty( $this->configuration['widget_member_rep'] ) )
		{
			$where[] = array( 'pp_reputation_points >= ?', $this->configuration['widget_member_rep'] );
		}

		if ( ! empty( $this->configuration['widget_member_online'] ) OR !isset( $this->configuration['widget_member_online'] ) )
		{
			$where[] = array( 'last_activity > ?', \IPS\DateTime::create()->sub( new \DateInterval( 'PT30M' ) )->getTimeStamp() );
		}

		if ( ! empty( $this->configuration['widget_member_born_today'] ) )
		{
			$time = \IPS\DateTime::ts( time(), TRUE );
			$mon  = $time->format('n');
			$mday = $time->format('j');
			$year = $time->format('Y');
			$where[] = array( 'bday_month=?', $mon );

			if ( $mon == 2 AND $mday == 28 AND ! ( gmdate( 'L', $year ) ) )
			{
				$where[] = array( 'bday_day IN(28,29)' );
			}
			else
			{
				$where[] = array( 'bday_day=?',$mday );
			}
		}

		if ( ! empty( $this->configuration['widget_member_born_month'] ) )
		{
			$time = \IPS\DateTime::ts( time(), TRUE );
			$mon  = $time->format('n');

			$where[] = array( 'bday_month=?', $mon );
		}

		if ( \IPS\Application::appIsEnabled('blog') and ! empty( $this->configuration['widget_member_has_blog'] ) )
		{
			$joins['blog'] = array(
				'select' => 'blog.blog_id',
				'from'   => array( 'blog_blogs', 'blog' ),
				'where'  => array( 'core_members.member_id=blog.blog_member_id' )
			);
		}

		if ( \IPS\Application::appIsEnabled('gallery') and ! empty( $this->configuration['widget_member_has_album'] ) )
		{
			$joins['gallery'] = array(
				'select' => 'gallery.album_id',
				'from'   => array( 'gallery_albums', 'gallery' ),
				'where'  => array( 'core_members.member_id=gallery.album_owner_id' )
			);
		}

		$sort = 'core_members.last_activity desc';

		if ( ! empty( $this->configuration['widget_feed_sort_on' ] ) )
		{
			if ( $this->configuration['widget_feed_sort_on' ] === '_age' )
			{
				$where[] = array( 'bday_year IS NOT NULL' );
				$sort    = "core_members.bday_year " . $this->configuration['widget_feed_sort_dir'] . ",core_members.bday_month " . $this->configuration['widget_feed_sort_dir'] . ",core_members.bday_day ";
			}
			else if ( $this->configuration['widget_feed_sort_on' ] === '_random' )
			{
				$sort = 'RAND()';
			}
			else
			{
				$sort = $this->configuration['widget_feed_sort_on' ] . ' ' . $this->configuration['widget_feed_sort_dir'];
			}
		}

		/* Exclude banned, validating and spammers */
		$where[] = array( "core_members.temp_ban=0" );
		$where[] = array( "core_members.email!=''" );
		$where[] = array( '( ! ' . \IPS\Db::i()->bitwiseWhere( \IPS\Member::$bitOptions['members_bitoptions'], 'bw_is_spammer' ) . ' )' );
		$where[] = array( "( core_validating.vid IS NULL ) " );

		$select = \IPS\Db::i()->select(
			'core_members.*',
			'core_members',
			$where,
			$sort,
			( isset( $this->configuration['widget_member_show'] ) and $this->configuration['widget_member_show'] !== -1 ) ? array( 0, $this->configuration['widget_member_show']  ) : 100
		);

		$select->join( 'core_validating', 'core_validating.member_id=core_members.member_id AND core_validating.new_reg = 1' );

		if ( \count( $joins ) )
		{
			foreach( $joins as $join )
			{
				$select->join( $join['from'], ( isset( $join['where'] ) ? $join['where'] : null ), 'STRAIGHT_JOIN' );
			}
		}

		$members = new \IPS\Patterns\ActiveRecordIterator(
			$select,
			'\IPS\Member'
		);
		
		/* Display */		
		return $this->output( $members, isset( $this->configuration['widget_member_title'] ) ? $this->configuration['widget_member_title'] : \IPS\Member::loggedIn()->language()->addToStack( 'widget_member_title_default' ), isset( $this->configuration['widget_member_display'] ) ? $this->configuration['widget_member_display'] : 'csv' );
	}
}