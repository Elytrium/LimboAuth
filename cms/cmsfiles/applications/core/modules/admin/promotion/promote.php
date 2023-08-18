<?php
/**
 * @brief		promote
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Feb 2017
 */

namespace IPS\core\modules\admin\promotion;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * promote
 */
class _promote extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * @brief	Active tab
	 */
	protected $activeTab	= '';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'promote_manage' );
		
		/* Get tab content */
		$this->activeTab = \IPS\Request::i()->tab ?: 'internal';
		
		parent::execute();
	}

	/**
	 * Promote Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Work out output */
		$methodFunction = '_manage' . mb_ucfirst( $this->activeTab );
		$activeTabContents = $this->$methodFunction();
		
		/* If this is an AJAX request, just return it */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}

		/* Build tab list */
		$tabs = array();

		$tabs['internal'] = 'promote_tab_internal';
		$tabs['permissions'] = 'promote_tab_permissions';
		$tabs['twitter'] = 'promote_tab_twitter';
		$tabs['links'] = 'promote_tab_links';
		$tabs['schedule'] = 'promote_tab_schedule';
		
		/* Buttons for logs */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'promote_logs' ) )
		{
			\IPS\Output::i()->sidebar['actions']['actionLogs'] = array(
				'title'		=> 'promote_logs',
				'icon'		=> 'search',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=promotion&controller=promote&do=logs' ),
			);
		}
		
		/* Display */
		if ( $activeTabContents )
		{
			$output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->blurb( \IPS\Member::loggedIn()->language()->addToStack( 'promote_acp_blurb' ) );
			
			/* Put up a little warning about permissions */
			$enabledServices = \IPS\Db::i()->select( 'count(*)', 'core_social_promote_sharers', array( 'sharer_enabled=1' ) )->first();
			
			if ( $enabledServices and ! \IPS\core\Promote::canPromote() )
			{
				/* We do not have permission to promote, so lets show a notice about that */
				$output .= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( 'promote_no_permission_acp', 'info' );
			}

			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_promotion_promote');
			\IPS\Output::i()->output = $output . \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $this->activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=promotion&controller=promote" ) );
		}
	}
	
	/**
	 * Manage Links
	 *
	 * @return	string
	 */
	protected function _manageLinks()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\YesNo( 'bitly_enabled', \IPS\Settings::i()->bitly_enabled, FALSE, array( 'togglesOn' => array( 'bitly_token' ) ), NULL, NULL, NULL, 'bitly_enabled' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'bitly_token', \IPS\Settings::i()->bitly_token, FALSE, array(), function( $val )
		{
			try
			{
				$response = \IPS\Http\Url::external( "https://api-ssl.bitly.com/v3/shorten" )->setQueryString( array( 'access_token' => $val, 'longUrl' => 'https://www.invisioncommunity.com' ) )->request()->get()->decodeJson();
				
				if ( $response['status_code'] !== 200 )
				{
					throw new \DomainException("bitly_auth_failed");
				}
			}
			catch ( \IPS\Http\Request\Exception $e )
			{
				throw new \DomainException("bitly_auth_failed");
			}
		}, NULL, \IPS\Member::loggedIn()->language()->addToStack('bitly_generate_token'), 'bitly_token' ) );
		
		/* Are we saving? */
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			
			\IPS\Session::i()->log( 'acplogs__promote_save', array( "acplogs__promote_changed_links" => true ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=promotion&controller=promote&tab=" . \IPS\Request::i()->tab ), 'saved' );
		}
		
		return $form;
	}
	
	/**
	 * Manage Links
	 *
	 * @return	string
	 */
	protected function _manageSchedule()
	{
		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\Stack( 'promote_scheduled', explode( ',', \IPS\Settings::i()->promote_scheduled ), FALSE, array( 'stackFieldType' => 'Text', 'placeholder' => 'HH:MM' ), NULL, NULL, NULL, 'promote_scheduled' ) );
		$form->add( new \IPS\Helpers\Form\Timezone( 'promote_tz', \IPS\Settings::i()->promote_tz, FALSE, array(), NULL, NULL, NULL, 'promote_tz' ) );
		
		/* Are we saving? */
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			
			\IPS\Session::i()->log( 'acplogs__promote_save', array( "acplogs__promote_changed_schedule" => true ) );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=promotion&controller=promote&tab=" . \IPS\Request::i()->tab ), 'saved' );
		}
		
		return $form;
	}
	
	/**
	 * Manage Permissions
	 *
	 * @return	string
	 */
	protected function _managePermissions()
	{
		$form = new \IPS\Helpers\Form;
		$groups = array();
		
		foreach( \IPS\Member\Group::groups() as $group )
		{
			if ( $group->g_bitoptions['gbw_promote'] )
			{
				$groups[] = \IPS\Theme::i()->getTemplate( 'promote' )->groupLink( $group->g_id, $group->name );
			}
		}
		
		$output  = \IPS\Theme::i()->getTemplate( 'promote' )->permissionBlurb( $groups );
		$form->add( new \IPS\Helpers\Form\Member( 'promote_members', \IPS\Settings::i()->promote_members ? array_map( array( 'IPS\Member', 'load' ), explode( "\n", \IPS\Settings::i()->promote_members ) ) : NULL, FALSE, array( 'multiple' => NULL ), NULL, NULL, NULL, 'promote_twitter_members' ) );
		
		/* Are we saving? */
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			
			\IPS\Session::i()->log( 'acplogs__promote_save', array( "acplogs__promote_changed_permissions" => true ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=promotion&controller=promote&tab=" . \IPS\Request::i()->tab ), 'saved' );
		}

		return $output . $form;
	}
	
	/**
	 * Manage Internal
	 *
	 * @return	string
	 */
	protected function _manageInternal()
	{
		$account = \IPS\core\Promote::getPromoter('Internal')->setMember( \IPS\Member::loggedIn() );
		$output  = \IPS\Theme::i()->getTemplate( 'promote' )->internalBlurb();
		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'promote_internal_enabled', $account->enabled, FALSE, array(), NULL, NULL, NULL, 'promote_internal_enabled' ) );
		
		/* Are we saving? */
		if ( $values = $form->values() )
		{
			$account->enabled = $values['promote_internal_enabled'];
			$account->save();
			
			$form->saveAsSettings( array( 'promote_community_enabled' => $account->enabled ) );
			
			\IPS\Session::i()->log( 'acplogs__promote_save', array( "acplogs__promote_changed_community" => true ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=promotion&controller=promote&tab=" . \IPS\Request::i()->tab ), 'saved' );
		}
		
		return $output . $form;
	}
	
	/**
	 * Manage Twitter
	 *
	 * @return	string
	 */
	protected function _manageTwitter()
	{
		$twitter = \IPS\Login\Handler::findMethod('IPS\Login\Handler\Oauth1\Twitter');
		$account = \IPS\core\Promote::getPromoter('Twitter')->setMember( \IPS\Member::loggedIn() );

		/* Have we set up Twitter yet? */
		if ( !$twitter OR !$twitter->settings['consumer_key'] )
		{
			/* Nope, so lets do that first... */
			return \IPS\Theme::i()->getTemplate( 'promote' )->warningNoTwitterApp();
		}
		
		/* Have we linked to a Twitter account? */
		if ( ! $account->settings['token'] )
		{
			/* Nope, so lets do that now... */
			return \IPS\Theme::i()->getTemplate( 'promote' )->warningNoTwitterUser();
		}
		
		/* Do we have post to page permissions? */
		$response = $account->canPostToPage();
		if ( $response !== TRUE )
		{
			/* Token stores permissions, so wipe it */
			$account->enabled = FALSE;
			$account->saveSettings( array(
				'token' => NULL,
				'secret' => NULL,
				'owner' => NULL,
				'name' => NULL
			) );
			
			return \IPS\Theme::i()->getTemplate( 'promote' )->warningNoTwitterPostToPagePermission( $response );
		}
		
		$form = new \IPS\Helpers\Form;
		
		if ( isset( \IPS\Request::i()->clear ) and \IPS\Request::i()->clear === 'true' )
		{
			\IPS\Session::i()->csrfCheck();
			
			/* We want to clear settings */
			$account->enabled = FALSE;
			$account->saveSettings( array(
				'token' => NULL,
				'secret' => NULL,
				'owner' => NULL,
				'name' => NULL
			) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=promotion&controller=promote&tab=twitter" ) );
		}
		
		$owner = \IPS\Member::load( $account->settings['owner'] );
		$output = '';
		
		if ( $account->settings['owner'] and $account->settings['name'] )
		{
			try
			{
				$output = \IPS\Theme::i()->getTemplate( 'promote' )->twitterOwnedBy( $owner, $account->settings['name'] );
			}
			catch( \OutOfRangeException $e )
			{
				/* Member no longer exists, so clear settings */
				$account->enabled = FALSE;
				$account->saveSettings( array(
					'token' => NULL,
					'secret' => NULL,
					'owner' => NULL,
					'name' => NULL
				) );
				
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=promotion&controller=promote&tab=twitter" ) );
			}
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'promote_twitter_enabled', $account->enabled, FALSE, array( 'togglesOn' => array() ), NULL, NULL, NULL, 'promote_twitter_enabled' ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'promote_twitter_tags', $account->settings['tags'], FALSE, array( 'stackFieldType' => 'Text' ), NULL, NULL, NULL, 'promote_twitter_tags' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'promote_twitter_tags_method', $account->settings['tags_method'], FALSE, array( 'options' => array(
			'fill' => 'promote_twitter_tags_method_fill',
			'trim' => 'promote_twitter_tags_method_trim'
		) ), NULL, NULL, NULL, 'promote_twitter_tags_method' ) );

		/* Are we saving? */
		if ( $values = $form->values() )
		{
			if ( \is_array( $values['promote_twitter_tags'] ) )
			{
				array_walk( $values['promote_twitter_tags'], function( &$value )
				{
					$value = str_replace( '#', '', $value );
				} );
			}
			
			$account->enabled = $values['promote_twitter_enabled'];
			$save = array(
				'tags' => $values['promote_twitter_tags'],
				'tags_method' => $values['promote_twitter_tags_method']
			);
			
			$account->saveSettings( $save );
			
			\IPS\Session::i()->log( 'acplogs__promote_save', array( "acplogs__promote_changed_twitter" => true ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=promotion&controller=promote&tab=" . \IPS\Request::i()->tab ), 'saved' );
		}

		return $output . $form;
	}
	
	/**
	 * Response Logs
	 *
	 * @return	void
	 */
	protected function logs()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_acploginlogs' );
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_social_promote_content', \IPS\Http\Url::internal( 'app=core&module=promotion&controller=promote&do=logs' ), array( array( "response_promote_key != 'internal'" ) ) );
		$table->langPrefix = 'promote_logs_';
		$table->sortBy	= $table->sortBy ?: 'response_date';
		$table->sortDirection	= $table->sortDirection ?: 'DESC';
		$table->include = array( 'response_promote_id', 'response_promote_key', 'response_json', 'response_failed', 'response_date' );
		$table->widths['response_json'] = '40';
		
		/* Search */
		$table->quickSearch = 'response_json';
		
		/* Filters */
		$table->filters = array(
			'promote_logs_success'		=> 'response_failed = 0',
			'promote_logs_unsuccessful'	=> 'response_failed = 1',
		);
		
		/* Custom parsers */
		$table->parsers = array(
			'response_date'	=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val );
			},
			'response_failed'	=> function( $val, $row )
			{
				return ( ! $val ) ? "<i class='fa fa-check'></i>" : "<i class='fa fa-times'></i>";
			},
			'response_promote_id' => function( $val, $row )
			{
				try
				{
					$promote = \IPS\core\Promote::load( $val );
					
					return htmlspecialchars( $promote->objectTitle, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ) . ' (' . $val . ')';
					
				}
				catch ( \Exception $e ) { }
			},
			'response_promote_key'	=> function( $val, $row )
			{
				return \ucfirst( $val );
			},
			'response_json' => function( $val, $row )
			{
				$val = json_decode( $val, true );
				return '<div data-ipstruncate data-ipstruncate-type="hide" data-ipstruncate-size="3 lines" class="ipsType_break">' . var_export( $val, true ) . "</div>";
			}
		);

		/* Display */
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=promotion&controller=promote' ), \IPS\Member::loggedIn()->language()->addToStack( 'menu__core_promotion_promote' ) );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('promote_logs');
		\IPS\Output::i()->output	= (string) $table;
	}
	
	/**
	 * Reschedule things
	 *
	 * @return void
	 */
	protected function reschedule()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\core\Promote::rescheduleQueue();
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=promotion&controller=promote&tab=schedule" ), 'promote_rescheduled_done' );
	}

}