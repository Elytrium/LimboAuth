<?php
/**
 * @brief		Keyword Tracking
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 Mar 2017
 */

namespace IPS\core\modules\admin\activitystats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Keyword Tracking
 */
class _keywords extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'keywords_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Show button to adjust settings */
		\IPS\Output::i()->sidebar['actions']['settings'] = array(
			'icon'		=> 'cog',
			'primary'	=> TRUE,
			'title'		=> 'manage_keywords',
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=keywords&do=settings' ),
			'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('settings') )
		);
		
		$tabs = array(
			'time'	=> 'keywords_usage_over_time',
			'list'	=> 'keywords_usage_list',
		);
		\IPS\Request::i()->tab ??= 'time';
		$activeTab = ( array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'time';
		
		if ( $activeTab === 'time' )
		{
			$output = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Keywords' )->getChart( \IPS\Http\Url::internal( "app=core&module=activitystats&controller=keywords&tab=time" ) );
		}
		else
		{
			/* Create the table */
			$table = new \IPS\Helpers\Table\Db( 'core_statistics', \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=keywords&tab=list' ), array( array( 'type=?', 'keyword' ) ) );
			$table->langPrefix = 'keywordstats_';
			$table->quickSearch = 'value_4';
	
			/* Columns we need */
			$table->include = array( 'value_4', 'extra_data', 'author', 'time' );
			$table->mainColumn = 'value_4';
			$table->noSort	= array( 'extra_data' );
	
			$table->sortBy = $table->sortBy ?: 'time';
			$table->sortDirection = $table->sortDirection ?: 'desc';
	
			/* Custom parsers */
			$table->parsers = array(
				'time'			=> function( $val, $row )
				{
					return \IPS\DateTime::ts( $val )->localeDate();
				},
				'author'		=> function( $val, $row )
				{
					$data = json_decode( $row['extra_data'], TRUE );
	
					try
					{
						$class	= $data['class'];
	
						/* Check that the class exists */
						if( !class_exists( $class ) )
						{
							throw new \InvalidArgumentException;
						}
	
						$item	= $class::load( $data['id'] );
	
						return $item->author()->link();
					}
					catch( \Exception $e )
					{
						return \IPS\Member::loggedIn()->language()->addToStack( 'unknown' );
					}
				},
				'extra_data'	=> function( $val, $row )
				{
					$data = json_decode( $val, TRUE );
	
					try
					{
						$class	= $data['class'];
	
						/* Check that the class exists */
						if( !class_exists( $class ) )
						{
							throw new \InvalidArgumentException;
						}
	
						$item	= $class::load( $data['id'] );
	
						return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $item->url(), TRUE, ( $item instanceof \IPS\Content\Comment ) ? $item->item()->mapped('title') : $item->mapped('title'), TRUE );
					}
					catch( \Exception $e )
					{
						return \IPS\Member::loggedIn()->language()->addToStack( 'content_deleted' );
					}
				},
			);
	
			/* Display */
			$output	= \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( 'title', (string) $table, TRUE, 'ipsPad ipsSpacer_top' );
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $output;
		}
		else
		{
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_activitystats_keywords');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string) $output, \IPS\Http\Url::internal( "app=core&module=activitystats&controller=keywords" ), 'tab', '', 'ipsPad' );
		}
	}

	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Stack( 'stats_keywords', \IPS\Settings::i()->stats_keywords ? json_decode( \IPS\Settings::i()->stats_keywords, true ) : array(), FALSE, array( 'stackFieldType' => 'Text' ), NULL, NULL, NULL, 'stats_keywords' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'stats_keywords_prune', \IPS\Settings::i()->stats_keywords_prune, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL ) );
	
		if ( $values = $form->values() )
		{
			$values['stats_keywords'] = json_encode( array_unique( $values['stats_keywords'] ) );

			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__statskeywords_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=keywords' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('settings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'settings', $form, FALSE );
	}
	
	/**
	 * Rebuild
	 *
	 * @return	void
	 */
	protected function rebuild()
	{
		if ( !\IPS\Settings::i()->stats_keywords )
		{
			\IPS\Output::i()->error( 'no_keywords_to_rebuild', '2C433/1', 403, '' );
		}
		
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Db::i()->delete( 'core_statistics', array( 'type=?', 'keyword' ) );
		foreach( \IPS\Content::routedClasses() AS $class )
		{
			\IPS\Task::queue( 'core', 'RebuildKeywords', array( 'class' => $class ) );
		}
		
		\IPS\Session::i()->log( 'acplog__statskeywords_rebuilt' );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=keywords' ), 'saved' );
	}
}