<?php
/**
 * @brief		blogs Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		13 Jul 2015
 */

namespace IPS\blog\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * blogs Widget
 */
class _blogs extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'blogs';
	
	/**
	 * @brief	App
	 */
	public $app = 'blog';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Initialize this widget
	 *
	 * @return	void
	 */
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'blog.css', 'blog' ) );
		
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

		/* Block title */
		$form->add( new \IPS\Helpers\Form\Text( 'widget_feed_title', isset( $this->configuration['widget_feed_title'] ) ? $this->configuration['widget_feed_title'] : \IPS\Member::loggedIn()->language()->addToStack( 'blogs' ) ) );

		/* Author */
		$author = NULL;
		try
		{
			if ( isset( $this->configuration['widget_feed_author'] ) and \is_array( $this->configuration['widget_feed_author'] ) )
			{
				foreach( $this->configuration['widget_feed_author']  as $id )
				{
					$author[ $id ] = \IPS\Member::load( $id );
				}
			}
		}
		catch( \OutOfRangeException $ex ) { }
		$form->add( new \IPS\Helpers\Form\Member( 'widget_feed_author', $author, FALSE, array( 'multiple' => null ) ) );



		$form->add( new \IPS\Helpers\Form\Select( 'widget_blog_last_date', isset( $this->configuration['widget_blog_last_date'] ) ? $this->configuration['widget_blog_last_date'] : '0', FALSE, array(
			'options' => array(
				0	=> 'show_all',
				1	=> 'today',
				5	=> 'last_5_days',
				7	=> 'last_7_days',
				10	=> 'last_10_days',
				15	=> 'last_15_days',
				20	=> 'last_20_days',
				25	=> 'last_25_days',
				30	=> 'last_30_days',
				60	=> 'last_60_days',
				90	=> 'last_90_days',
			)
		) ) );


		/* Number to show */
		$form->add( new \IPS\Helpers\Form\Number( 'widget_feed_show', isset( $this->configuration['widget_feed_show'] ) ? $this->configuration['widget_feed_show'] : 5, TRUE ) );

		$form->add( new \IPS\Helpers\Form\Select( 'widget_feed_sort_on', isset( $this->configuration['widget_feed_sort_on'] ) ? $this->configuration['widget_feed_sort_on'] : 'blog_last_edate', FALSE, array( 'options' => array(
			'blog_last_edate'		=> 'widget__blog_edate',
			'blog_count_entries'  	=> 'widget__blog_count_entries',
			'blog_count_comments' 	=> 'widget__blog_count_comments'
		) ), NULL, NULL, NULL, 'widget_feed_sort_on' ) );

		$form->add( new \IPS\Helpers\Form\Select( 'widget_feed_sort_dir', isset( $this->configuration['widget_feed_sort_dir'] ) ? $this->configuration['widget_feed_sort_dir'] : 'desc', FALSE, array(
			'options' => array(
				'desc'   => 'descending',
				'asc'    => 'ascending'
			)
		) ) );
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
		if ( \is_array( $values['widget_feed_author'] ) )
		{
			$members = array();
			foreach( $values['widget_feed_author'] as $member )
			{
				$members[] = $member->member_id;
			}

			$values['widget_feed_author'] = $members;
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
		$where  = array( array( 'blog_disabled=0 AND blog_social_group IS NULL AND blog_club_id IS NULL' ) );
		$sortBy = ( isset( $this->configuration['widget_feed_sort_on'] ) and isset( $this->configuration['widget_feed_sort_dir'] ) ) ? ( $this->configuration['widget_feed_sort_on'] . ' ' . $this->configuration['widget_feed_sort_dir'] ) : NULL;
		$limit  = isset( $this->configuration['widget_feed_show'] ) ? $this->configuration['widget_feed_show'] : 5;

		if ( isset( $this->configuration['widget_feed_author'] ) and \is_array( $this->configuration['widget_feed_author'] ) and \count( $this->configuration['widget_feed_author'] ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'blog_member_id', $this->configuration['widget_feed_author'] ) );
		}

		/* Get results */
		$blogs = array();

		foreach( \IPS\Db::i()->select( '*', 'blog_blogs', $where, $sortBy, array( 0, $limit ) ) as $row )
		{
			$blog = \IPS\blog\Blog::constructFromData( $row );
			$blog->coverPhoto()->editable = false;
			$blogs[] = $blog;
		}

		if ( \count( $blogs ) )
		{
			return $this->output( $blogs, isset( $this->configuration['widget_feed_title'] ) ? $this->configuration['widget_feed_title'] : \IPS\Member::loggedIn()->language()->addToStack( 'blogs' ) );
		}
		else
		{
			return '';
		}

	}
}