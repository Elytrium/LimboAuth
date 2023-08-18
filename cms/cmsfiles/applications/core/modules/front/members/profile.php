<?php
/**
 * @brief		Profile
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Jul 2013
 */

namespace IPS\core\modules\front\members;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Profile
 */
class _profile extends \IPS\Helpers\CoverPhoto\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\core\Statuses\Status';

	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 */
	public static $dataLayerContext = array(
		'community_area' =>  [ 'value' => 'profile', 'odkUpdate' => 'true']
	);
	
	/**
	 * Main execute entry point - used to override breadcrumb
	 *
	 * @return void
	 */
	public function execute()
	{
		/* Load Member */
		$this->member = \IPS\Member::load( \IPS\Request::i()->id );
		if ( !$this->member->member_id )
		{
			\IPS\Output::i()->error( 'node_error', '2C138/1', 404, '' );
		}
		
		/* Set breadcrumb */
		unset( \IPS\Output::i()->breadcrumb['module'] );
		\IPS\Output::i()->breadcrumb[] = array( $this->member->url(), $this->member->name );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;

		if( !\IPS\Request::i()->isAjax() )
		{
			/* Don't index new empty profiles */
	        if( ! $this->member->member_posts )
	        {
	            \IPS\Output::i()->metaTags['robots'] = 'noindex, follow';
	        }
	
	        \IPS\Output::i()->linkTags['canonical'] = (string) $this->member->url();

			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_statuses.js', 'core' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_core.js', 'core', 'global' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/profiles.css' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/streams.css' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/leaderboard.css' ) );

			if ( \IPS\Theme::i()->settings['responsive'] )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/profiles_responsive.css' ) );
			}			
		}
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_profile.js', 'core' ) );
		
		/* Go */
		parent::execute();
	}
	
	/**
	 * Change the users follow preference
	 *
	 * @return void
	 */
	protected function changeFollow()
	{
		if ( !\IPS\Member::loggedIn()->modPermission('can_modify_profiles') AND ( \IPS\Member::loggedIn()->member_id !== $this->member->member_id OR !$this->member->group['g_edit_profile'] ) )
		{
			\IPS\Output::i()->error( 'no_permission_edit_profile', '2C138/3', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();

		\IPS\Member::loggedIn()->members_bitoptions['pp_setting_moderate_followers'] = ( \IPS\Request::i()->enabled == 1 ? FALSE : TRUE );
		\IPS\Member::loggedIn()->save();

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->member->url(), 'follow_saved' );
		}
	}

	/**
	 * Show Profile
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->linkTags['canonical'] = (string) $this->member->url();

		/* Can we access this member's statuses */
		$canAccessSingleStatuses = ( \IPS\Settings::i()->profile_comments and \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) );
		$canAccessStatuses = $canAccessSingleStatuses and ( $this->member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $this->member->member_id );
		
		/* Are we loading a different page of comments? */
		if ( \IPS\Request::i()->status && \IPS\Request::i()->isAjax() && \IPS\Request::i()->commentPage && $canAccessStatuses && !isset( \IPS\Request::i()->getUploader ) )
		{
			try
			{
				$status = \IPS\core\Statuses\Status::loadAndCheckPerms( \IPS\Request::i()->status );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C138/M', 404, '' );
			}

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'statuses' )->statusReplies( $status );
			return;
		}
		
		/* Get profile field values */
		try
		{
			$profileFieldValues	= \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id=?', $this->member->member_id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			$profileFieldValues = array();
		}
		
		/* Split the fields into sidebar and main fields */
		$mainFields = array();
		$sidebarFields = array();
		if( !empty( $profileFieldValues ) )
		{
			if( \IPS\Member::loggedIn()->isAdmin() OR \IPS\Member::loggedIn()->modPermissions() )
			{
				$where = array( "pfd.pf_member_hide='owner' OR pfd.pf_member_hide='staff' OR pfd.pf_member_hide='all'" );
			}
			elseif( \IPS\Member::loggedIn()->member_id == $this->member->member_id )
			{
				$where = array( "pfd.pf_member_hide='owner' OR pfd.pf_member_hide='all'" );
			}
			else
			{
				$where = array( "pfd.pf_member_hide='all'" );
			}

			foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( 'pfd.*', array('core_pfields_data', 'pfd'), $where, 'pfg.pf_group_order, pfd.pf_position' )->join(
				array('core_pfields_groups', 'pfg'),
				"pfd.pf_group_id=pfg.pf_group_id"
			), 'IPS\core\ProfileFields\Field' ) as $field )
			{
				if( $profileFieldValues[ 'field_' . $field->id ] !== '' AND $profileFieldValues[ 'field_' . $field->id ] !== NULL )
				{
					if( $field->type == 'Editor' )
					{
						$mainFields['core_pfieldgroups_' . $field->group_id]['core_pfield_' . $field->id] = $field->displayValue( $profileFieldValues['field_' . $field->id], FALSE, \IPS\core\ProfileFields\Field::PROFILE, $this->member );
					}
					else
					{
						$sidebarFields['core_pfieldgroups_' . $field->group_id]['core_pfield_' . $field->id] = array( 'value' => $field->displayValue( $profileFieldValues['field_' . $field->id], FALSE, \IPS\core\ProfileFields\Field::PROFILE, $this->member ), 'custom' => $field->profile_format ? TRUE : FALSE );
					}
				}
			}
		}
		
		/* Work out the main content to display */
		if ( $canAccessSingleStatuses and \IPS\Request::i()->status and \IPS\Request::i()->type == 'status' )
		{
			try
			{
				$status = \IPS\core\Statuses\Status::loadAndCheckPerms( \IPS\Request::i()->status );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C138/E', 404, '' );
			}

			$mainContent = \IPS\Theme::i()->getTemplate( 'profile' )->singleStatus( $this->member, $status );

			\IPS\Output::i()->linkTags['canonical'] = (string) $status->url();
			\IPS\Output::i()->title			= \IPS\Member::loggedIn()->language()->addToStack( 'viewing_single_status_of', FALSE, array('sprintf' => array( \IPS\DateTime::ts( $status->mapped('date') )->localeDate() , $status->author()->name ) ) );
			\IPS\Output::i()->breadcrumb[]	= array( NULL, \IPS\Member::loggedIn()->language()->get( 'viewing_single_status' ) );
			/* Remove the robots tag we set earlier, the page can be indexed if a status exists, but the member doesn't have any posts */
			unset( \IPS\Output::i()->metaTags['robots'] );
		}
		else
		{
			/* What tabs are available? */
			$tabs = array( 'activity' => 'users_activity_feed' );

			if ( \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) and \IPS\Settings::i()->clubs and \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), NULL, 'created', $this->member, array(), NULL, TRUE ) )
			{
				$tabs['clubs'] = 'users_clubs';
			}

			foreach( $mainFields as $group => $fields )
			{
				foreach( $fields as $field => $value )
				{
					if ( $value )
					{
						$tabs["field_{$field}"] = $field;
					}
				}
			}
			$nodes = array();
			foreach ( \IPS\Application::allExtensions( 'core', 'Profile', TRUE, NULL, NULL, FALSE ) as $extension )
			{
				$profileExtension = new $extension( $this->member );
				if ( $profileExtension->showTab() )
				{
					preg_match( '/^IPS\\\(.+?)\\\extensions\\\core\\\Profile\\\(.+?)$/', $extension, $matches );
					$nodes[ "{$matches[1]}_{$matches[2]}" ] = $profileExtension;
					$tabs[ "node_{$matches[1]}_{$matches[2]}" ] = "profile_{$matches[1]}_{$matches[2]}";
				}
			}
	
			/* What tab are we on? */
			if ( !isset( \IPS\Request::i()->tab ) or !array_key_exists( \IPS\Request::i()->tab, $tabs ) )
			{
				$tab = 'activity';
			}
			else
			{
				$tab = \IPS\Request::i()->tab;
			}
	
			/* Work out the content */
			$tabContents = '';
			if ( $tab == 'activity' )
			{
				$latestActivity = \IPS\Content\Search\Query::init()->filterForProfile( $this->member )->setLimit( 15 )->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED )->search();
				$latestActivity->init( TRUE );
	
				$extra = array();
				foreach ( array( 'register', 'follow_member', 'follow_content', 'photo', 'votes', 'like', 'rep_neg' ) as $k )
				{
					$key = "all_activity_{$k}";
					if ( \IPS\Settings::i()->$key )
					{
						$extra[] = $k;
					}
				}
				if ( !empty( $extra ) )
				{
					$latestActivity = $latestActivity->addExtraItems( $extra, $this->member );
				}
	
				$statusForm = NULL;
				if ( \IPS\core\Statuses\Status::canCreate( \IPS\Member::loggedIn() ) )
				{
					if ( isset( \IPS\Request::i()->status_content_ajax ) )
					{
						\IPS\Request::i()->status_content = \IPS\Request::i()->status_content_ajax;
					}
	
					$form = new \IPS\Helpers\Form( 'new_status', 'status_new' );
					foreach( \IPS\core\Statuses\Status::formElements() AS $k => $element )
					{
						$form->add( $element );
					}
	
					if ( $values = $form->values() )
					{
						$status = \IPS\core\Statuses\Status::createFromForm( $values );
	
						if ( \IPS\Request::i()->isAjax() )
						{
							\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'statuses', 'core', 'front' )->statusContainer( $status ) );
						}
						else
						{
							\IPS\Output::i()->redirect( $status->url() );
						}
					}
	
					$statusForm = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'statusTemplate' ) );
	
					if ( \IPS\core\Statuses\Status::moderateNewItems( \IPS\Member::loggedIn() ) )
					{
						$statusForm = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->postingInformation( NULL, TRUE, FALSE ) . $statusForm;
					}

					if( isset( \IPS\Output::i()->httpHeaders['X-IPS-FormError'] ) AND \IPS\Output::i()->httpHeaders['X-IPS-FormError'] AND \IPS\Request::i()->isAjax() )
					{
						\IPS\Output::i()->sendOutput( $statusForm, 500 );
						return;
					}
				}
	
				$tabContents = \IPS\Theme::i()->getTemplate( 'profile' )->profileActivity( $this->member, $latestActivity, $statusForm );
			}
			elseif ( $tab == 'clubs' )
			{
				/* Get All User Clubs */
				$baseUrl = \IPS\Request::i()->url()->setQueryString('tab', 'clubs');
				$perPage = 24;
				$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;
				if( $page < 1 )
				{
					$page = 1;
				}

				$clubsCount	= \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), array( ( $page - 1 ) * $perPage, $perPage ), 'last_activity', $this->member, array(), \IPS\Member::loggedIn()->modPermission( 'can_access_all_clubs' ) ? NULL : "( show_membertab = 'nonmember' OR show_membertab IS NULL )", TRUE );
				$allClubs	= \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), array( ( $page - 1 ) * $perPage, $perPage ), 'last_activity', $this->member, array(), \IPS\Member::loggedIn()->modPermission( 'can_access_all_clubs' ) ? NULL : "( show_membertab = 'nonmember' OR show_membertab IS NULL )" );
				$pagination	= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl, ( ceil( $clubsCount / $perPage ) ), $page, $perPage );

				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs.css', 'core', 'front' ) );
				if ( \IPS\Theme::i()->settings['responsive'] )
				{
					\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs_responsive.css', 'core', 'front' ) );
				}
				$tabContents = \IPS\Theme::i()->getTemplate( 'profile' )->profileClubs( $this->member, $allClubs, $pagination );
			}
			elseif ( mb_substr( $tab, 0, 6 ) == 'field_' )
			{
				$fieldId = mb_substr( $tab, 6 );
				foreach( $mainFields as $group => $fields )
				{
					foreach( $fields as $field => $value )
					{
						if ( $field == $fieldId )
						{
							$tabContents = \IPS\Theme::i()->getTemplate( 'profile' )->fieldTab( $fieldId, $value );
						}
					}
				}
			}
			elseif ( mb_substr( $tab, 0, 5 ) == 'node_' )
			{
				$type = mb_substr( $tab, 5 );
				$tabContents = (string) $nodes[ $type ]->render();
			}

			/* If this is AJAX request to change the tab, just display that */
			if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->tab ) )
			{
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( $tabContents ) );
			}
	
			/* Otherwise wrap it in the tabs */
			$mainContent = \IPS\Theme::i()->getTemplate( 'profile' )->profileTabs( $this->member, $tabs, $tab, $tabContents );

			\IPS\Output::i()->title = $this->member->name;
		}
		
		/* Log a visit */
		if( \IPS\Member::loggedIn()->member_id and $this->member->member_id != \IPS\Member::loggedIn()->member_id and !\IPS\Session::i()->getAnon() )
		{
			$this->member->addVisitor( \IPS\Member::loggedIn() );
		}
		
		/* Update views */
		\IPS\Db::i()->update(
				'core_members',
				"`members_profile_views`=`members_profile_views`+1",
				array( "member_id=?", $this->member->member_id ),
				array(),
				NULL,
				\IPS\Db::LOW_PRIORITY
		);

		/* Data Layer Stuff */
		if ( \IPS\Settings::i()->core_datalayer_enabled )
		{
			try
			{
				$groupName = \IPS\Member\Group::load( $this->member->member_group_id )->formattedName;
			}
			catch ( \UnderflowException $e )
			{
				$groupName = null;
			}
			$properties = array(
				'profile_group' => $groupName,
				'profile_group_id'  => $this->member->member_group_id ?: null,
				'profile_id'    => \intval( $this->member->member_id ) ?: null,
				'profile_name'  => $this->member->name ?: null,
				'view_location' => 'page',
			);
			\IPS\core\DataLayer::i()->addEvent( 'social_view', $properties );
		}
		
		/* Get visitor data */
		$visitors = $this->member->profileVisitors;
		
		/* Get followers */
		$followers = $this->member->followers( ( \IPS\Member::loggedIn()->isAdmin() OR \IPS\Member::loggedIn()->member_id === $this->member->member_id ) ? \IPS\Member::FOLLOW_PUBLIC + \IPS\Member::FOLLOW_ANONYMOUS : \IPS\Member::FOLLOW_PUBLIC, array( 'immediate', 'daily', 'weekly', 'none' ), NULL, array( 0, 12 ) );

		/* Get solutions */
		$solutions = \IPS\Db::i()->select( 'COUNT(*)', 'core_solved_index', array( 'member_id=?', $this->member->member_id ) )->first();
		
		/* Update online location */		
		$module = \IPS\Application\Module::get( 'core', 'members', 'front' )->permissions();
		\IPS\Session::i()->setLocation( $this->member->url(), explode( ",", $module['perm_view'] ), 'loc_viewing_profile', array( $this->member->name => FALSE ) );
		
		/* Work out add warning URL */
		$addWarningUrl = \IPS\Http\Url::internal( "app=core&module=system&controller=warnings&do=warn&id={$this->member->member_id}", 'front', 'warn_add', array( $this->member->members_seo_name ) );
		if ( isset( \IPS\Request::i()->wr ) )
		{
			$addWarningUrl = $addWarningUrl->setQueryString( 'ref', \IPS\Request::i()->wr );
		}

		/* Set JSON-LD output */
		\IPS\Output::i()->jsonLd['profile']	= array(
			'@context'		=> "http://schema.org",
			'@type'			=> "ProfilePage",
			'url'			=> (string) $this->member->url(),
			'name'			=> $this->member->name,
			'primaryImageOfPage'	=> array(
				'@type'					=> "ImageObject",
				'contentUrl'			=> (string) $this->member->get_photo( TRUE, TRUE ),
				'representativeOfPage'	=> true,
				'thumbnail'	=> array(
					'@type'				=> "ImageObject",
					'contentUrl'		=> (string) $this->member->get_photo( TRUE, TRUE ),
				)
			),
			'thumbnailUrl'	=> (string) $this->member->get_photo( TRUE, TRUE ),
			'image'			=> (string) $this->member->get_photo( FALSE, TRUE ),
			'relatedLink'	=> (string) \IPS\Http\Url::internal( "app=core&module=members&controller=profile&do=content&id={$this->member->member_id}", "front", "profile_content", array( $this->member->members_seo_name ) ),
			'dateCreated'	=> $this->member->joined->format( \IPS\DateTime::ISO8601 ),
			'interactionStatistic'	=> array(
				array(
					"@type"					=> "InteractionCounter",
					"interactionType"		=> "http://schema.org/CommentAction",
					'userInteractionCount'	=> $this->member->member_posts
				),
				array(
					"@type"					=> "InteractionCounter",
					"interactionType"		=> "http://schema.org/ViewAction",
					'userInteractionCount'	=> $this->member->members_profile_views
				),
			),
		);

		/* Output */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_profile.js', 'core' ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile' )->profile( $this->member, $mainContent, $visitors, $sidebarFields, $followers, $addWarningUrl, $solutions );
	}

	/**
	 * Hovercard
	 *
	 * @return	void
	 */
	public function hovercard()
	{
		$addWarningUrl = \IPS\Http\Url::internal( "app=core&module=system&controller=warnings&do=warn&id={$this->member->member_id}", 'front', 'warn_add', array( $this->member->members_seo_name ) );
		if ( isset( \IPS\Request::i()->wr ) )
		{
			$addWarningUrl = $addWarningUrl->setQueryString( 'ref', \IPS\Request::i()->wr );
		}
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'profile' )->hovercard( $this->member, $addWarningUrl ) );
	}
	
	/**
	 * Show Content
	 *
	 * @return	void
	 */
	public function content()
	{
		/* Get the different types */
		$types			= array();
		$hasCallback	= array();

		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', TRUE, NULL, NULL, TRUE ) as $router )
		{
			foreach( $router->classes as $class )
			{
				/* Add CSS for this app */
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'profile.css', $class::$application ) );
				
				if ( isset( $class::$databaseColumnMap['author'] ) )
				{
					$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ] = $class;
				}
								
				$supportsComments = ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) and $class::supportsComments( \IPS\Member::loggedIn() ) );
				if ( $supportsComments )
				{
					$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$commentClass, 4 ) ) ) ] = $class::$commentClass;
				}
				
				$supportsReviews = ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) and $class::supportsReviews( \IPS\Member::loggedIn() ) );
				if ( $supportsReviews )
				{
					$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$reviewClass, 4 ) ) ) ] = $class::$reviewClass;
				}

				if( method_exists( $router, 'customTableHelper' ) )
				{
					$hasCallback[ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ]					= $router;

					if ( $supportsComments )
					{
						$hasCallback[ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$commentClass, 4 ) ) ) ]	= $router;
					}

					if ( $supportsReviews )
					{
						$hasCallback[ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$reviewClass, 4 ) ) ) ]		= $router;
					}
				}
			}
		}

		/* What type are we looking at? */
		$currentAppModule = NULL;
		$currentType = NULL;
		if ( isset( \IPS\Request::i()->type ) )
		{
			foreach ( $types as $appModule => $_types )
			{
				if ( array_key_exists( \IPS\Request::i()->type, $_types ) )
				{
					$currentAppModule = $appModule;
					$currentType = \IPS\Request::i()->type;
					break;
				}
			}
		}

		$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;
		$baseUrl = NULL;
		
		if( $page < 1 )
		{
			$page = 1;
		}
		
		/* Build Output */
		if ( !$currentType )
		{
			$query = \IPS\Content\Search\Query::init()->filterByAuthor( $this->member )->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED )->setPage( $page );
			$results = $query->search();

			/* If we requested a higher page than is allowed, redirect back to last page */
			$totalResults = $results->count( TRUE );

			if( ceil( $totalResults / $query->resultsToGet ) < $page )
			{
				$highestPage = floor( $totalResults / $query->resultsToGet );

				if ( $highestPage > 0 OR ( $highestPage == 0 AND $page > 1 ) )
				{
					\IPS\Output::i()->redirect( \IPS\Request::i()->url()->setPage( 'page', $highestPage ?: 1 ), NULL, 303 );
				}
			}
			
			$baseUrl = \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$this->member->member_id}&do=content", 'front', 'profile_content', $this->member->members_seo_name );
			$pagination = trim( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl->setQueryString( array( 'all_activity' => 1 ) ), ceil( $results->count( TRUE ) / $query->resultsToGet ), $page, $query->resultsToGet ) );
			if ( \IPS\Request::i()->isAjax() AND \IPS\Request::i()->all_activity )
			{
				\IPS\Output::i()->json( array( 'rows' => \IPS\Theme::i()->getTemplate('profile')->userContentStream( $this->member, $results, $pagination ) ) );
				return;
			}
			else
			{
				$output = \IPS\Theme::i()->getTemplate('profile')->userContentStream( $this->member, $results, $pagination );
			}
		}
		else
		{
			$currentClass = $types[ $currentAppModule ][ $currentType ];
			$currentAppArray = explode( '_', $currentAppModule );
			$currentApp = $currentAppArray[0];
			if( isset( $hasCallback[ $currentType ] ) )
			{
				$output	= $hasCallback[ $currentType ]->customTableHelper( $currentClass, \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$this->member->member_id}&do=content", 'front', 'profile_content', $this->member->members_seo_name )->setQueryString( array( 'type' => $currentType ) ), array( array( $currentClass::$databaseTable . '.' . $currentClass::$databasePrefix . $currentClass::$databaseColumnMap['author'] . '=?', $this->member->member_id ) ) );
			}
			else
			{
				$where = array();
				$where[] = array( $currentClass::$databaseTable . '.' . $currentClass::$databasePrefix . $currentClass::$databaseColumnMap['author'] . '=?', $this->member->member_id );
				if ( isset( $currentClass::$databaseColumnMap['state'] ) )
				{
					$where[] = array( $currentClass::$databaseTable . '.' . $currentClass::$databasePrefix . $currentClass::$databaseColumnMap['state'] . ' != ?', 'link' );
				}

				if ( isset( $currentClass::$databaseColumnMap['status'] ) )
				{
					$where[] = array( $currentClass::$databaseTable . '.' . $currentClass::$databasePrefix . $currentClass::$databaseColumnMap['status'] . ' != ?', 'draft' );
				}

				if( method_exists( $currentClass, 'commentWhere' ) AND $currentClass::commentWhere() !== NULL )
				{
					$where[] = $currentClass::commentWhere();
				}

				$baseUrl = \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$this->member->member_id}&do=content", 'front', 'profile_content', $this->member->members_seo_name );
				$output = new \IPS\Helpers\Table\Content( $currentClass, $baseUrl->setQueryString( array( 'type' => $currentType ) ), $where, NULL, \IPS\Content\Hideable::FILTER_AUTOMATIC, 'read', FALSE );
			}
			
			if ( $currentType == 'core_statuses_status' )
			{
				$output->noModerate = TRUE;
			}

			$output->classes[] = 'cProfileContent';
		}
		
		/* If we've clicked from the tab section */
		if ( \IPS\Request::i()->isAjax() && \IPS\Request::i()->change_section )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile' )->userContentSection( $this->member, $types, $currentAppModule, $currentType, (string) $output );
		}
		else
		{
			/* Display */
			$profileTitle	= \IPS\Member::loggedIn()->language()->addToStack( 'members_content', FALSE, array( 'sprintf' => array( $this->member->name ) ) );
			$title			= ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( $profileTitle, \IPS\Request::i()->page ) ) ) : $profileTitle;
			
			if ( $baseUrl )
			{
				if ( isset( \IPS\Request::i()->type ) )
				{
					$baseUrl = $baseUrl->setQueryString( 'type', \IPS\Request::i()->type );
				}
				
				if ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page > 1 )
				{
					$baseUrl = $baseUrl->setPage( 'page', \IPS\Request::i()->page );
				}
				
				\IPS\Output::i()->linkTags['canonical'] = (string) $baseUrl;
			}
			\IPS\Output::i()->title = $title;
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/statuses.css' ) );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile' )->userContent( $this->member, $types, $currentAppModule, $currentType, (string) $output );
		}
	}

	/**
	 * Show badges
	 *
	 * @return	void
	 */
	public function badges()
	{
		if( !$this->member->canHaveAchievements() || !\IPS\core\Achievements\Badge::show() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C138/S', 403, '' );
		}

		$percentage = NULL;
		if ( $this->member->achievements_points )
		{
			$percentage = \IPS\Db::i()->select( "CEIL( 100 * COUNT( IF( achievements_points > " . ( $this->member->achievements_points - 1 ) . ", 1, NULL ) ) / COUNT(*) ) as percentage", 'core_members', ['achievements_points > 0'] )->first();

			if ( $percentage > 51 )
			{
				$percentage = NULL;
			}
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'members_badges', FALSE, array( 'sprintf' => array( $this->member->name ) ) );;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile' )->userBadgeOverview( $this->member, $percentage );
	}
	
	/**
	 * Show Reputation
	 *
	 * @return	void
	 */
	public function reputation()
	{
		if ( !\IPS\Member::loggedIn()->group['gbw_view_reps'] )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C138/B', 403, '' );
		}
		
		/* Get the different types */
		$types = array();
		$hasCallback = array();
		foreach ( \IPS\Content::routedClasses( TRUE, FALSE, TRUE ) as $class )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'profile.css', $class::$application ) );
			
			if ( \IPS\IPS::classUsesTrait( $class, 'IPS\Content\Reactable' ) and !$class::$firstCommentRequired )
			{
				$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ] = $class;
			}
			
			if ( isset( $class::$commentClass ) and \IPS\IPS::classUsesTrait( $class::$commentClass, 'IPS\Content\Reactable' ) )
			{
				$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$commentClass, 4 ) ) ) ] = $class::$commentClass;
			}
			
			if ( isset( $class::$reviewClass ) and \IPS\IPS::classUsesTrait( $class::$reviewClass, 'IPS\Content\Reactable' ) )
			{
				$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$reviewClass, 4 ) ) ) ] = $class::$reviewClass;
			}
		}

		/* What type are we looking at? */
		$currentAppModule = NULL;
		$currentType = NULL;
		if ( isset( \IPS\Request::i()->type ) )
		{
			foreach ( $types as $appModule => $_types )
			{
				if ( array_key_exists( \IPS\Request::i()->type, $_types ) )
				{
					$currentAppModule = $appModule;
					$currentType = \IPS\Request::i()->type;
					break;
				}
			}
		}		
		if ( $currentType === NULL )
		{
			foreach ( $types as $appModule => $_types )
			{
				foreach ( $_types as $key => $class )
				{
					$currentAppModule = $appModule;
					$currentType = $key;
					break 2;
				}
			}
		}
		$currentClass = $types[ $currentAppModule ][ $currentType ];
		$currentAppArray = explode( '_', $currentAppModule );
		$currentApp = $currentAppArray[0];
		$member = $this->member;

		/* Build a callback to merge in reputation data */
		$callback = function( $rows ) use ( $member, $currentClass )
		{
			$ids = array();
			$idColumn = $currentClass::$databaseColumnId;
			
			foreach( $rows as $id => $row )
			{
				$ids[ $id ] = $row->$idColumn;
			}

			if ( \count( $ids ) )
			{
				$rep = iterator_to_array(
					\IPS\Db::i()->select( 'core_reputation_index.type_id, core_reputation_index.id AS rep_id, core_reputation_index.rep_date, core_reputation_index.rep_rating, core_reputation_index.member_received as rep_member_received, core_reputation_index.member_id as rep_member, core_reputation_index.reaction as rep_reaction',
					'core_reputation_index',
					array( \IPS\Db::i()->in( 'core_reputation_index.type_id', array_values( $ids ) ) . " AND ( core_reputation_index.member_id=? OR core_reputation_index.member_received=? ) AND core_reputation_index.app=? AND core_reputation_index.type=?", $member->member_id, $member->member_id, $currentClass::$application, $currentClass::reactionType() ),
					'rep_date desc'
					)->setKeyField('rep_id')
				);
				
				$mapped = array();
				foreach( $rep as $repId => $data )
				{
					$mapped[ $data['type_id' ] ][] = $data;
				}
				
				/* Now overwrite the data */
				foreach( $rows as $id => $row )
				{
					if ( isset( $mapped[ $row->$idColumn ] ) )
					{
						/* Shift it to remove it from the stack as we always sort by desc */
						$useThisRep = \array_shift( $mapped[ $row->$idColumn ] );
						
						/* We now want to clone the object so we can make a separate copy, thus not updating the one object used multiple times */
						$row->skipCloneDuplication = TRUE;
						$rows[ $id ] = clone $row;
						
						/* Now overwrite the data in $row */
						foreach( array( 'rep_id', 'rep_date', 'rep_rating', 'rep_member_received', 'rep_member', 'rep_reaction' ) as $field )
						{
							$rows[ $id ]->$field = $useThisRep[ $field ];
						}
					}
				}
			}

			return $rows;
		};
		
		
		/* Build Output */
		$url = \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$this->member->member_id}&do=reputation&type={$currentType}", 'front', 'profile_reputation', array( $this->member->members_seo_name ) );

		$table = new \IPS\Helpers\Table\Content( $currentClass, $url, NULL, NULL, \IPS\Content\Hideable::FILTER_AUTOMATIC, 'read', FALSE, FALSE, $callback );
		$table->joinContainer = TRUE;
		$table->sortOptions = array( 'rep_date' );
		$table->sortBy = 'rep_date';
		$table->joins = array(
			array(
				'select' => "core_reputation_index.id AS rep_id, core_reputation_index.rep_date, core_reputation_index.rep_rating, core_reputation_index.member_received as rep_member_received, core_reputation_index.member_id as rep_member, core_reputation_index.reaction as rep_reaction",
				'from'   => 'core_reputation_index',
				'where'  => array( "core_reputation_index.type_id=" . $currentClass::$databaseTable . "." . $currentClass::$databasePrefix . $currentClass::$databaseColumnId  . " AND ( core_reputation_index.member_id=? OR core_reputation_index.member_received=? ) AND core_reputation_index.app=? AND core_reputation_index.type=?", $this->member->member_id, $this->member->member_id, $currentClass::$application, $currentClass::reactionType() ),
				'type'   => 'INNER'
			)
		);
		
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'profile', 'core' ), 'userReputationTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'profile', 'core' ), 'userReputationRows' );
		$table->showAdvancedSearch = FALSE;

		/* Display */
		if ( \IPS\Request::i()->isAjax() && \IPS\Request::i()->change_section )
		{
			\IPS\Output::i()->sendOutput( (string) $table );
		}
		else
		{
			/* Get the reputation summary */
			$reactions = array( 'given' => array(), 'received' => array() );

			foreach( \IPS\Db::i()->select( 'reaction, count(*) as count', 'core_reputation_index', array( 'member_id=?', $this->member->member_id ), NULL, NULL, 'reaction' ) as $reaction )
			{
				try
				{
					$object = \IPS\Content\Reaction::load( $reaction['reaction'] );

					/* Don't show disabled reactions */
					if( !$object->enabled )
					{
						throw new \UnderflowException;
					}

					$reactions['given'][] = array( 'count' => $reaction['count'], 'reaction' => $object );
				}
				catch( \UnderflowException $e ){}
			}

			foreach( \IPS\Db::i()->select( 'reaction, count(*) as count', 'core_reputation_index', array( 'member_received=?', $this->member->member_id ), NULL, NULL, 'reaction' ) as $reaction )
			{
				try
				{
					$object = \IPS\Content\Reaction::load( $reaction['reaction'] );

					/* Don't show disabled reactions */
					if( !$object->enabled )
					{
						throw new \UnderflowException;
					}

					$reactions['received'][] = array( 'count' => $reaction['count'], 'reaction' => $object );
				}
				catch( \UnderflowException $e ){}
			}


			\IPS\Output::i()->title = ( $table->page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'member_reputation_from', FALSE, array('sprintf' => $this->member->name ) ), $table->page ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'member_reputation_from', FALSE, array('sprintf' => $this->member->name ) );

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile' )->userReputation( $this->member, $types, $currentAppModule, $currentType, (string) $table, $reactions );
		}
	}
	
	/**
	 * Toggle Visitors
	 *
	 * @return	void
	 */
	protected function visitors()
	{
		if ( !\IPS\Member::loggedIn()->modPermission('can_modify_profiles') AND ( \IPS\Member::loggedIn()->member_id !== $this->member->member_id OR !$this->member->group['g_edit_profile'] ) )
		{
			\IPS\Output::i()->error( 'no_permission_edit_profile', '2C138/3', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();
		
		if ( \IPS\Request::i()->state == 0 )
		{
			$this->member->members_bitoptions['pp_setting_count_visitors']	= FALSE;
			$visitors = array();
		}
		else
		{
			$this->member->members_bitoptions['pp_setting_count_visitors']	= TRUE;

			$visitors = $this->member->profileVisitors;
		}

		$this->member->save();

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile', 'core' )->recentVisitorsBlock( $this->member, $visitors );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->member->url(), 'saved' );
		}
	}
	
	/**
	 * Edit Status
	 *
	 * @return	void
	 */
	protected function editStatus()
	{
		try
		{
			$status = \IPS\core\Statuses\Status::loadAndCheckPerms( \IPS\Request::i()->status );
			if ( !$status->canEdit() )
			{
				throw new \DomainException;
			}
						
			$form = new \IPS\Helpers\Form( 'form', 'status_save' );
			$form->class = 'ipsForm_vertical ipsForm_noLabels';
			
			$formElements = \IPS\core\Statuses\Status::formElements( $status );
			$form->add( $formElements['status_content'] );

			if ( $values = $form->values() )
			{
				$status->processForm( $values );
				$status->save();
				$status->processAfterEdit( $values );

				\IPS\Output::i()->redirect( $status->url() );
			}
			
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C138/A', 404, '' );
		}
	}
	
	/**
	 * Edit Status Reply
	 *
	 * @return	void
	 */
	protected function editStatusReply()
	{
		try
		{
			$reply = \IPS\core\Statuses\Reply::loadAndCheckPerms( \IPS\Request::i()->reply );
			if ( !$reply->canEdit() )
			{
				throw new \DomainException;
			}
			
			$form = new \IPS\Helpers\Form;
			$form->class = 'ipsForm_vertical';
			
			$form->add( new \IPS\Helpers\Form\Editor( 'comment_value', $reply->content, TRUE, array(
				'app'			=> 'core',
				'key'			=> 'Members',
				'maxLength'		=> 65535,
				'autoSaveKey' 	=> 'editComment-core/members-' . $reply->id,
				'attachIds'		=> $reply->attachmentIds()
			) ) );
			
			if ( $values = $form->values() )
			{
				$reply->editContents( $values['comment_value'] );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->output = $reply->html();
					return;
				}
				else
				{
					\IPS\Output::i()->redirect( $reply->url() );
				}
			}
			
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C138/K', 404, '' );
		}
	}
	
	/**
	 * Edit Profile
	 *
	 * @return	void
	 */
	protected function edit()
	{
		/* Do we have permission? */
		if ( !\IPS\Member::loggedIn()->modPermission('can_modify_profiles') and ( \IPS\Member::loggedIn()->member_id !== $this->member->member_id or !$this->member->group['g_edit_profile'] ) )
		{
			\IPS\Output::i()->error( 'no_permission_edit_profile', '2S147/1', 403, '' );
		}
		
		$form = $this->buildEditForm();
		
		/* Handle the submission */
		if ( $values = $form->values() )
		{
			$this->_saveMember( $form, $values );

			/* Data Layer Stuff */
			if ( \IPS\Settings::i()->core_datalayer_enabled )
			{
				try
				{
					$groupName = \IPS\Member\Group::load( $this->member->member_group_id )->formattedName;
				}
				catch ( \UnderflowException $e )
				{
					$groupName = null;
				}
				$properties = array(
					'profile_group'    => $groupName,
					'profile_group_id' => $this->member->member_group_id ?: null,
					'profile_id'       => \intval( $this->member->member_id ) ?: null,
					'profile_name'     => $this->member->name ?: null,
				);
				\IPS\core\DataLayer::i()->addEvent( 'social_update', $properties );
			}

			\IPS\Output::i()->redirect( $this->member->url() );
		}
		
		/* Set Session Location */
		\IPS\Session::i()->setLocation( $this->member->url(), array(), 'loc_editing_profile', array( $this->member->name => FALSE ) );

		if( !\count( $form->elements ) )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->genericBlock( \IPS\Member::loggedIn()->language()->addToStack( 'profile_nothing_to_edit' ), NULL, 'ipsPad' );
		}
		else if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->editContentForm( \IPS\Member::loggedIn()->language()->addToStack( 'profile_edit' ), $form );
		}
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'editing_profile', FALSE, array( 'sprintf' => array( $this->member->name ) ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'editing_profile', FALSE, array( 'sprintf' => array( $this->member->name ) ) ) );
	}


	/**
	 * Build Edit Form
	 *
	 * @return \IPS\Helpers\Form
	 */
	protected function buildEditForm()
	{
		/* Build the form */
		$form = new \IPS\Helpers\Form;

		/* The basics */
		$form->addTab( 'profile_edit_basic_tab', 'user');

		$canChangeBirthday = ( \IPS\Settings::i()->profile_birthday_type !== 'none' );
		$canEnableDisableStatuses = ( \IPS\Settings::i()->profile_comments and $this->member->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) AND !$this->member->members_bitoptions['bw_no_status_update'] );

		if( $canChangeBirthday or $canEnableDisableStatuses )
		{
			$form->addHeader( 'profile_edit_basic_header' );
		}

		if ( \IPS\Settings::i()->profile_birthday_type !== 'none' )
		{
			$form->add( new \IPS\Helpers\Form\Custom( 'bday', array( 'year' => $this->member->bday_year, 'month' => $this->member->bday_month, 'day' => $this->member->bday_day ), FALSE, array( 'getHtml' => function( $element )
			{
				return strtr( \IPS\Member::loggedIn()->language()->preferredDateFormat(), array(
					'DD'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_day( $element->name, $element->value, $element->error ),
					'MM'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_month( $element->name, $element->value, $element->error ),
					'YY'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_year( $element->name, $element->value, $element->error ),
					'YYYY'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_year( $element->name, $element->value, $element->error ),
				) );
			} ), function( $val )
			{
				$date = $val['day'];
				$month = $val['month'];
				$year = $val['year'];

				try
				{
					if( ( $month AND !$date ) OR ( !$month AND $date ) )
					{
						throw new \UnexpectedValueException;
					}

					new \IPS\DateTime( $year . "-" . $month . "-" . $date );
				}
				catch ( \Exception $e )
				{
					throw new \InvalidArgumentException( 'invalid_bdate') ;
				}
			}
			) );
			if ( \IPS\Settings::i()->profile_birthday_type == 'private' )
			{
				$form->addMessage( 'profile_birthday_display_private', 'ipsMessage ipsMessage_info' );
			}
		}

		if ( \IPS\Settings::i()->profile_comments and $this->member->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) AND !$this->member->members_bitoptions['bw_no_status_update'] )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'enable_status_updates', $this->member->pp_setting_count_comments ) );
		}

		/* Profile fields */
		try
		{
			$values = \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id=?', $this->member->member_id ) )->first();
		}
		catch( \UnderflowException $e )
		{
			$values	= array();
		}

		foreach ( \IPS\core\ProfileFields\Field::fields( $values, \IPS\core\ProfileFields\Field::EDIT, $this->member ) as $group => $fields )
		{
			$form->addHeader( "core_pfieldgroups_{$group}" );
			foreach ( $fields as $field )
			{
				$form->add( $field );
			}
		}

		/* Moderator stuff */
		if ( ( \IPS\Member::loggedIn()->modPermission('can_modify_profiles') OR \IPS\Member::loggedIn()->modPermission('can_unban') ) AND \IPS\Member::loggedIn()->member_id != $this->member->member_id )
		{
			$sigLimits = explode( ":", $this->member->group['g_signature_limits'] );
			if( \IPS\Settings::i()->signatures_enabled AND !$sigLimits[0] AND $sigLimits[5] != 0 )
			{
				$form->add( new \IPS\Helpers\Form\Editor( 'signature',  $this->member->signature, FALSE, array( 'app' => 'core', 'key' => 'Signatures', 'autoSaveKey' => "frontsig-" . $this->member->member_id, 'attachIds' => array(  $this->member->member_id ) ) ) );
			}

			if ( \IPS\Member::loggedIn()->modPermission('can_unban') )
			{
				$form->addTab( 'profile_edit_moderation', 'times' );

				if ( $this->member->mod_posts !== 0 )
				{
					$form->add( new \IPS\Helpers\Form\YesNo( 'remove_mod_posts', NULL, FALSE ) );
				}

				if ( $this->member->restrict_post !== 0 )
				{
					$form->add( new \IPS\Helpers\Form\YesNo( 'remove_restrict_post', NULL, FALSE ) );
				}

				if ( $this->member->temp_ban !== 0 )
				{
					$form->add( new \IPS\Helpers\Form\YesNo( 'remove_ban', NULL, FALSE ) );
				}
			}
		}

		return $form;
	}

	/**
	 * Save Member
	 *
	 * @param $form
	 * @param array $values
	 */
	protected function _saveMember( $form, array $values )
	{
		if( isset( $values['bday'] ) )
		{
			if( $values['bday']  and ( ( $values['bday']['day'] and !$values['bday']['month'] ) or ( $values['bday']['month'] and !$values['bday']['day'] ) ) )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'bday_month_and_day_required' );
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->editContentForm( \IPS\Member::loggedIn()->language()->addToStack( 'profile_edit' ), $form );
				return;
			}

			if ( $values['bday'] and $values['bday']['day'] and $values['bday']['month'] )
			{
				$this->member->bday_day		= $values['bday']['day'];
				$this->member->bday_month	= $values['bday']['month'];
				$this->member->bday_year	= $values['bday']['year'];
			}
			else
			{
				$this->member->bday_day = NULL;
				$this->member->bday_month = NULL;
				$this->member->bday_year = NULL;
			}
		}

		if ( isset( $values['enable_status_updates'] ) )
		{
			$this->member->pp_setting_count_comments = $values['enable_status_updates'];

			if ( $values['enable_status_updates'] )
			{
				\IPS\Content\Search\Index::i()->massUpdate( 'IPS\core\Statuses\Status', $this->member->member_id, NULL, '*', NULL, NULL, $this->member->member_id );
			}
			else
			{
				\IPS\Content\Search\Index::i()->massUpdate( 'IPS\core\Statuses\Status', $this->member->member_id, NULL, '', NULL, NULL, $this->member->member_id );
			}
		}

		/* Profile Fields */
		try
		{
			$profileFields = \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id=?', $this->member->member_id ) )->first();
		}
		catch( \UnderflowException $e )
		{
			$profileFields = array();
		}

		/* If the row only contains one column (eg. member_id) then the result of the query is a string, we do not want this */
		if ( !\is_array( $profileFields ) )
		{
			$profileFields = array();
		}
		
		$profileFields['member_id'] = $this->member->member_id;

		foreach ( \IPS\core\ProfileFields\Field::fields( $profileFields, \IPS\core\ProfileFields\Field::EDIT, $this->member ) as $group => $fields )
		{
			foreach ( $fields as $id => $field )
			{
				if ( $field instanceof \IPS\Helpers\Form\Upload )
				{
					$profileFields[ "field_{$id}" ] = (string) $values[ $field->name ];
				}
				else
				{
					$profileFields[ "field_{$id}" ] = $field::stringValue( $values[ $field->name ] );
				}

				if ( $field instanceof \IPS\Helpers\Form\Editor )
				{
					\IPS\core\ProfileFields\Field::load( $id )->claimAttachments( $this->member->member_id );
				}
			}

			$this->member->changedCustomFields = $profileFields;
		}

		/* Moderator stuff */
		if ( \IPS\Member::loggedIn()->modPermission('can_modify_profiles') AND \IPS\Member::loggedIn()->member_id != $this->member->member_id)
		{
			if ( isset( $values['remove_mod_posts'] ) AND $values['remove_mod_posts'] )
			{
				$this->member->mod_posts = 0;
			}

			if ( isset( $values['remove_restrict_post'] ) AND $values['remove_restrict_post'] )
			{
				$this->member->restrict_post = 0;
			}

			if ( isset( $values['remove_ban'] ) AND $values['remove_ban'] )
			{
				$this->member->temp_ban = 0;
			}

			if ( isset( $values['signature'] ) )
			{
				$this->member->signature = $values['signature'];
			}
		}
		
		/* Reset Profile Complete flag in case this was an optional step */
		$this->member->members_bitoptions['profile_completed'] = FALSE;

		/* Save */
		\IPS\Db::i()->replace( 'core_pfields_content', $profileFields );
		$this->member->save();
	}

	/**
	 * Edit Photo
	 *
	 * @return	void
	 */
	protected function editPhoto()
	{
		if ( !\IPS\Member::loggedIn()->modPermission('can_modify_profiles') and ( \IPS\Member::loggedIn()->member_id !== $this->member->member_id or !$this->member->group['g_edit_profile'] ) )
		{
			\IPS\Output::i()->error( 'no_permission_edit_profile', '2C138/9', 403, '' );
		}

		$photoVars = explode( ':', $this->member->group['g_photo_max_vars'] );

		/* Init */
		$form = new \IPS\Helpers\Form( 'profile_photo', 'continue' );
		$form->ajaxOutput = TRUE;
		$toggles = array( 'custom' => array( 'member_photo_upload' ) );

		$options = array();
		$defaultType =  ( $this->member->pp_photo_type == 'letter' ) ? 'none' : $this->member->pp_photo_type;
		
		/* Can we upload? */
		if ( $photoVars[0] )
		{
			$options['custom'] = 'member_photo_upload';
		}
		
		/* Can we use gallery images? */
		if ( \IPS\Application::appIsEnabled('gallery') AND $this->member->pp_photo_type == 'gallery_Images' )
		{
			$options['gallery_Images'] = 'member_gallery_image';
		}
		
		/* And of course we can always not have a photo... except when we can't */
		$photoRequired = FALSE;
		foreach( \IPS\Member\ProfileStep::loadAll() AS $step )
		{
			if ( $step->completion_act === 'photo' AND $step->required )
			{
				$photoRequired = TRUE;
				break;
			}
		}
		if ( $photoRequired === FALSE )
		{
			if ( $this->member->pp_photo_type != 'none' )
			{
				$options['none'] = 'member_photo_remove';
			}
			else
			{
				$options['none'] = 'member_photo_none';
			}
		}
		
		/* iOS doesn't like upload forms being hidden by a toggle; and it makes sense if we do not have a profile photo to show the upload as the selected option */
		if ( $defaultType == 'none' and $photoVars[0] )
		{
			$defaultType = 'custom';
		}
		
		/* Create that selection */
		if( \count( $options ) > 1 )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'pp_photo_type', $defaultType, TRUE, array( 'options' => $options, 'toggles' => $toggles ) ) );
		}
		else
		{
			$form->hiddenValues['pp_photo_type']  = $defaultType;
		}
		
		/* Create the upload field */		
		if ( $photoVars[0] )
		{
			$form->add( new \IPS\Helpers\Form\Upload( 'member_photo_upload', ( $this->member->pp_main_photo and $this->member->pp_photo_type == 'custom') ? \IPS\File::get( 'core_Profile', $this->member->pp_main_photo ) : NULL, FALSE, array( 'supportsDelete' => FALSE, 'image' => array( 'maxWidth' => $photoVars[1], 'maxHeight' => $photoVars[2] ), 'allowStockPhotos' => TRUE, 'storageExtension' => 'core_Profile', 'maxFileSize' => $photoVars[0] ? $photoVars[0] / 1024 : NULL ), function( $val ) {
				if( \IPS\Request::i()->pp_photo_type == 'custom' AND !$val )
				{
					throw new \DomainException('form_required');
				}

				if ( $val instanceof \IPS\File )
				{
					try
					{
						$image = \IPS\Image::create( $val->contents() );
						if( $image->isAnimatedGif and !$this->member->group['g_upload_animated_photos'] )
						{
							throw new \DomainException( 'member_photo_upload_no_animated' );
						}
					} catch ( \IPS\File\Exception $e ){}

				}
			}, NULL, NULL, 'member_photo_upload' ) );
		}

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Disable syncing */
			$profileSync = $this->member->profilesync;
			if ( isset( $profileSync['photo'] ) )
			{
				unset( $profileSync['photo'] );
				$this->member->profilesync = $profileSync;
			}

			switch ( $values['pp_photo_type'] )
			{
				case 'custom':
					if ( $photoVars[0] and $values['member_photo_upload'] )
					{

						if ( (string) $values['member_photo_upload'] !== '' and $this->member->pp_main_photo !== (string) $values['member_photo_upload'] )
						{
							$this->member->pp_photo_type  = 'custom';
							$this->member->pp_main_photo  = (string) $values['member_photo_upload'];
							
							$thumbnail = $values['member_photo_upload']->thumbnail( 'core_Profile', \IPS\PHOTO_THUMBNAIL_SIZE, \IPS\PHOTO_THUMBNAIL_SIZE, TRUE );
							$this->member->pp_thumb_photo = (string) $thumbnail;
							
							$this->member->photo_last_update = time();
						}
					}
					break;

				case 'none':
					$this->member->pp_photo_type								= NULL;
					$this->member->pp_main_photo								= NULL;
					$this->member->photo_last_update = NULL;
					break;
			}
			
			/* Reset Profile Complete flag in case this was an optional step */
			$this->member->members_bitoptions['profile_completed'] = FALSE;
							
			$this->member->save();
			
			if ( $this->member->pp_photo_type )
			{
				$this->member->logHistory( 'core', 'photo', array( 'action' => 'new', 'type' => $this->member->pp_photo_type ) );
			}
			else
			{
				$this->member->logHistory( 'core', 'photo', array( 'action' => 'remove' ) );
			}
			
			if ( $this->member->pp_photo_type == 'custom' )
			{
				if ( \IPS\Request::i()->isAjax() )
				{					
					$this->cropPhoto();
					return;
				}
				else
				{
					\IPS\Output::i()->redirect( $this->member->url()->setQueryString( 'do', 'cropPhoto' ) );
				}
			}
			else
			{
				\IPS\Output::i()->redirect( $this->member->url() );
			}
		}
		
		/* Display */
		\IPS\Session::i()->setLocation( $this->member->url(), array(), 'loc_editing_profile', array( $this->member->name => FALSE ) );
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'editing_profile', FALSE, array( 'sprintf' => array( $this->member->name ) ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'editing_profile', FALSE, array( 'sprintf' => array( $this->member->name ) ) ) );
	}
	
	/**
	 * Get photo for cropping
	 * If the photo is on a different domain to the JS that handles cropping,
	 * it will be blocked because of CORS. See notes in Cropper documentation.
	 *
	 * @return	void
	 */
	protected function cropPhotoGetPhoto()
	{
		\IPS\Session::i()->csrfCheck();
		$original = \IPS\File::get( 'core_Profile', $this->member->pp_main_photo );
		$headers = array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'inline', $original->filename ) );
		\IPS\Output::i()->sendOutput( $original->contents(), 200, \IPS\File::getMimeType( $original->filename ), $headers );
	}
	
	/**
	 * Crop Photo
	 *
	 * @return	void
	 */
	protected function cropPhoto()
	{
		if ( !\IPS\Member::loggedIn()->modPermission('can_modify_profiles') and ( \IPS\Member::loggedIn()->member_id !== $this->member->member_id or !$this->member->group['g_edit_profile'] ) )
		{
			\IPS\Output::i()->error( 'no_permission_edit_profile', '2C138/F', 403, '' );
		}
		
		if( !$this->member->pp_main_photo )
		{
			\IPS\Output::i()->error( 'no_photo_to_crop', '2C138/C', 404, '' );
		}

		/* Get the photo */
		try
		{
			$original = \IPS\File::get( 'core_Profile', $this->member->pp_main_photo );
			$image = \IPS\Image::create( $original->contents() );
		}
		catch( \Exception $e )
		{
			\IPS\Log::log( $e, 'crop_error' );

			\IPS\Output::i()->error( 'no_photo_to_crop', '3C138/N', 404, '' );
		}
		
		/* Work out which dimensions to suggest */
		if ( $image->width < $image->height )
		{
			$suggestedWidth = $suggestedHeight = $image->width;
		}
		else
		{
			$suggestedWidth = $suggestedHeight = $image->height;
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form( 'photo_crop', 'save', $this->member->url()->setQueryString( 'do', 'cropPhoto' ) );
		$form->class = 'ipsForm_noLabels';
		$member = $this->member;
		$form->add( new \IPS\Helpers\Form\Custom('photo_crop', array( 0, 0, $suggestedWidth, $suggestedHeight ), FALSE, array(
			'getHtml'	=> function( $field ) use ( $original, $member )
			{
				return \IPS\Theme::i()->getTemplate('members', 'core', 'global')->photoCrop( $field->name, $field->value, $member->url()->setQueryString( 'do', 'cropPhotoGetPhoto' )->csrf() );
			}
		) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			try
			{
				/* Create new file */
				$image->cropToPoints( $values['photo_crop'][0], $values['photo_crop'][1], $values['photo_crop'][2], $values['photo_crop'][3] );
				
				/* Delete the current thumbnail */					
				if ( $this->member->pp_thumb_photo )
				{
					try
					{
						\IPS\File::get( 'core_Profile', $this->member->pp_thumb_photo )->delete();
					}
					catch ( \Exception $e ) { }
				}
								
				/* Save the new */
				$cropped = \IPS\File::create( 'core_Profile', $original->originalFilename, (string) $image );
				$this->member->pp_thumb_photo = (string) $cropped->thumbnail( 'core_Profile', \IPS\PHOTO_THUMBNAIL_SIZE, \IPS\PHOTO_THUMBNAIL_SIZE );
				$this->member->save();

				/* Delete the temporary full size cropped image */
				$cropped->delete();

				/* Edited member, so clear widget caches (stats, widgets that contain photos, names and so on) */
				\IPS\Widget::deleteCaches();
								
				/* Redirect */
				\IPS\Output::i()->redirect( $this->member->url() );
			}
			catch ( \Exception $e )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack('photo_crop_bad');
			}
		}
		
		/* Display */
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Moderate
	 *
	 * @return	void
	 */
	protected function moderate()
	{
		\IPS\Session::i()->csrfCheck();

		try
		{		
			$item = ( \IPS\Request::i()->type == 'status' ) ? \IPS\core\Statuses\Status::load( \IPS\Request::i()->status ) : \IPS\core\Statuses\Reply::load( \IPS\Request::i()->reply );

			$item->modAction( \IPS\Request::i()->action, \IPS\Member::loggedIn() );
				
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}
			else
			{
				if( \IPS\Request::i()->action == 'delete' )
				{
					\IPS\Output::i()->redirect( ( $item instanceof \IPS\core\Statuses\Status ) ? \IPS\Member::load( $item->member_id )->url()->setQueryString( array( 'do' => 'content', 'type' => 'core_statuses_status' ) ) : \IPS\core\Statuses\Status::load( $item->status_id )->url());
				}
				else
				{
					if ( isset( \IPS\Request::i()->_fromFeed ) )
					{
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=status&controller=feed' )->setFragment( 'status-' . $item->id ), 'mod_confirm_' . \IPS\Request::i()->action );
					}
					else
					{
						\IPS\Output::i()->redirect( $item->url()->setQueryString( 'tab', 'statuses' )->setFragment( 'status-' . $item->id ), 'mod_confirm_' . \IPS\Request::i()->action );
					}
				}
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C138/5', 404, '' );
		}
	}
	
	/**
	 * React to a status / comment
	 *
	 * @return	void
	 */
	protected function react()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$item = ( \IPS\Request::i()->type == 'status' ) ? \IPS\core\Statuses\Status::load( \IPS\Request::i()->status ) : \IPS\core\Statuses\Reply::load( \IPS\Request::i()->reply );
			
			if ( !isset( \IPS\Request::i()->reaction ) )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->reputationMini( $item );
			}
			else
			{
				$item->react( \IPS\Content\Reaction::load( \IPS\Request::i()->reaction ) );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( array(
						'status' => 'ok',
						'count' => \count( $item->reactions() ),
						'score' => $item->reactionCount(),
						'blurb' => ( \IPS\Settings::i()->reaction_count_display == 'count' ) ? '' : \IPS\Theme::i()->getTemplate( 'global', 'core' )->reactionBlurb( $item )
					));
				}
				else
				{
					\IPS\Output::i()->redirect( $item->url() );
				}
			}
		}
		catch( \DomainException $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ) ), 403 );
			}
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '1C138/H', 403, '' );
			}
		}
	}
	
	/**
	 * Unreact to a status / comment
	 *
	 * @return	void
	 */
	protected function unreact()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$item = ( \IPS\Request::i()->type == 'status' ) ? \IPS\core\Statuses\Status::load( \IPS\Request::i()->status ) : \IPS\core\Statuses\Reply::load( \IPS\Request::i()->reply );
			$member = ( isset( \IPS\Request::i()->member ) and \IPS\Member::loggedIn()->modPermission('can_remove_reactions') ) ? \IPS\Member::load( \IPS\Request::i()->member ) : \IPS\Member::loggedIn();

			$item->removeReaction( $member );
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'status' => 'ok',
					'count' => \count( $item->reactions() ),
					'score' => $item->reactionCount(),
					'blurb' => ( \IPS\Settings::i()->reaction_count_display == 'count' ) ? '' : \IPS\Theme::i()->getTemplate( 'global', 'core' )->reactionBlurb( $item )
				));
			}
			else
			{
				\IPS\Output::i()->redirect( $item->url() );
			}
		}
		catch( \DomainException $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ) ), 403 );
			}
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '1C138/I', 403, '' );
			}
		}
	}
	
	/**
	 * Show Reactions
	 *
	 * @return	void
	 */
	protected function showReactions()
	{
		try
		{
			$item = ( \IPS\Request::i()->type == 'status' ) ? \IPS\core\Statuses\Status::load( \IPS\Request::i()->status ) : \IPS\core\Statuses\Reply::load( \IPS\Request::i()->reply );

			if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->tooltip ) and isset( \IPS\Request::i()->reaction ) )
			{
				$reaction = \IPS\Content\Reaction::load( \IPS\Request::i()->reaction );
				
				$numberToShowInPopup = 10;
				$where = $item->getReactionWhereClause( $reaction );
				$total = \IPS\Db::i()->select( 'COUNT(*)', 'core_reputation_index', $where )->join( 'core_reactions', 'reaction=reaction_id' )->first();
				$names = \IPS\Db::i()->select( 'name', 'core_reputation_index', $where, 'rep_date DESC', $numberToShowInPopup )->join( 'core_reactions', 'reaction=reaction_id' )->join( 'core_members', 'core_reputation_index.member_id=core_members.member_id' );
				
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reactionTooltip( $reaction, $total ? $names : [], ( $total > $numberToShowInPopup ) ? ( $total - $numberToShowInPopup ) : 0 ) );
			}
			else
			{			
				$tabs = array();
				$tabs['all'] = \IPS\Member::loggedIn()->language()->addToStack('all');
				foreach( \IPS\Content\Reaction::roots() AS $reaction )
				{
					if ($reaction->_enabled !== FALSE)
					{
						$tabs[$reaction->id] = $reaction->_title;
					}
				}
				
				$activeTab = 'all';
				if ( isset( \IPS\Request::i()->reaction ) )
				{
					$activeTab = \IPS\Request::i()->reaction;
				}
				
				$url = $item->url('showReactions');
				$url = $url->setQueryString( 'changed', 1 );
				
				if ( $activeTab !== 'all' )
				{
					$url = $url->setQueryString( 'reaction', $activeTab );
				}

				\IPS\Output::i()->metaTags['robots'] = 'noindex';
				
				if ( \IPS\Content\Reaction::isLikeMode() or ( \IPS\Request::i()->isAjax() AND isset( \IPS\Request::i()->changed ) ) )
				{
					\IPS\Output::i()->output = $item->reactionTable( $activeTab !== 'all' ? $activeTab : NULL, $url, 'reaction', FALSE );
				}
				else
				{
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, $item->reactionTable( $activeTab !== 'all' ? $activeTab : NULL ), $url, 'reaction', FALSE );
				}
			}
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/18', 404, '' );
		}
		catch( \DomainException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/19', 403, '' );
		}
	}
	
	/**
	 * Report Status
	 *
	 * @return	void
	 */
	protected function report()
	{
		try
		{
			$itemClass		= '\IPS\core\Statuses\Status';
			$commentClass	= '\IPS\core\Statuses\Reply';
			$item			= ( \IPS\Request::i()->type == 'status' ) ? $itemClass::load( \IPS\Request::i()->status ) : $commentClass::load( \IPS\Request::i()->reply );
			$canReport		= $item->canReport();

			if ( $canReport !== TRUE AND !( $canReport == 'report_err_already_reported' AND \IPS\Settings::i()->automoderation_enabled ) )
			{
				\IPS\Output::i()->error( $canReport, '1C138/6', 403, '' );
			}
			
			$form			= new \IPS\Helpers\Form( NULL, 'report_submit' );
			$form->class	= 'ipsForm_vertical';
			$idColumn		= ( \IPS\Request::i()->type == 'status' ) ? $itemClass::$databaseColumnId : $commentClass::$databaseColumnId;
			$autoSaveKey	= ( \IPS\Request::i()->type == 'status' ) ? "report-{$itemClass::$application}-{$itemClass::$module}-{$item->$idColumn}" : "report-{$itemClass::$application}-{$itemClass::$module}-{$item->status_id}-{$item->$idColumn}";

			/* As we group by user id to determine if max points have been reached, guests cannot contribute to counts */
			if ( \IPS\Member::loggedIn()->member_id and \IPS\Settings::i()->automoderation_enabled )
			{
				/* Has this member already reported this in the past 24 hours */
				try
				{
					$index = \IPS\core\Reports\Report::loadByClassAndId( \get_class( $item ), $item->$idColumn );
					$report = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'rid=? and report_by=? and date_reported > ?', $index->id, \IPS\Member::loggedIn()->member_id, time() - ( \IPS\Settings::i()->automoderation_report_again_mins * 60 ) ) )->first();

					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system', 'core' )->reportedAlready( $index, $report, $item );
					return;
				}
				catch( \Exception $e ) { }

				$options = array( \IPS\core\Reports\Report::TYPE_MESSAGE => \IPS\Member::loggedIn()->language()->addToStack('report_message_item') );
				foreach( \IPS\core\Reports\Types::roots() as $type )
				{
					$options[ $type->id ] = $type->_title;
				}

				$form->add( new \IPS\Helpers\Form\Radio( 'report_type', NULL, FALSE, array( 'options' => $options ) ) );
			}

			$form->add( new \IPS\Helpers\Form\Editor( 'report_message', NULL, FALSE, array( 'app' => 'core', 'key' => 'Reports', 'autoSaveKey' => $autoSaveKey, 'minimize' => 'report_message_placeholder' ) ) );
			if( !\IPS\Member::loggedIn()->member_id )
			{
				$form->add( new \IPS\Helpers\Form\Captcha );
			}
			if ( $values = $form->values() )
			{
				$report = $item->report( $values['report_message'], ( isset( $values['report_type'] ) ) ? $values['report_type'] : 0 );
				\IPS\File::claimAttachments( $autoSaveKey, $report->id );
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->sendOutput( \IPS\Member::loggedIn()->language()->addToStack('report_submit_success') );
				}
				else
				{
					\IPS\Output::i()->redirect( $item->url(), 'report_submit_success' );
				}
			}
			
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'report_content' );
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		catch( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C138/7', 404, '' );
		}
	}
	
	/**
	 * Followers
	 *
	 * @return	void
	 */
	protected function followers()
	{
		$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;

		if( $page < 1 )
		{
			$page = 1;
		}

		$limit		= array( ( $page - 1 ) * 50, 50 );
		$followers	= $this->member->followers( ( \IPS\Member::loggedIn()->isAdmin() OR \IPS\Member::loggedIn()->member_id === $this->member->member_id ) ? \IPS\Member::FOLLOW_PUBLIC + \IPS\Member::FOLLOW_ANONYMOUS : \IPS\Member::FOLLOW_PUBLIC, array( 'immediate', 'daily', 'weekly', 'none' ), NULL, $limit, 'name' );
		$followersCount = $this->member->followersCount( ( \IPS\Member::loggedIn()->isAdmin() OR \IPS\Member::loggedIn()->member_id === $this->member->member_id ) ? \IPS\Member::FOLLOW_PUBLIC + \IPS\Member::FOLLOW_ANONYMOUS : \IPS\Member::FOLLOW_PUBLIC, array( 'immediate', 'daily', 'weekly', 'none' ) );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'profile' )->followers( $this->member, $followers ) );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('members_followers', FALSE, array( 'sprintf' => array( $this->member->name ) ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('members_followers', FALSE, array( 'sprintf' => array( $this->member->name ) ) ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile' )->allFollowers( $this->member, $followers, $followersCount );
	}
	
	/**
	 * Get Cover Photo Storage Extension
	 *
	 * @return	string
	 */
	protected function _coverPhotoStorageExtension()
	{
		return 'core_Profile';
	}
	
	/**
	 * Set Cover Photo
	 *
	 * @param	\IPS\Helpers\CoverPhoto	$photo	New Photo
	 * @param	string					$type	'new', 'remove', 'reposition'
	 * @return	void
	 */
	protected function _coverPhotoSet( \IPS\Helpers\CoverPhoto $photo, $type=NULL )
	{
		/* Disable syncing */
		$profileSync = $this->member->profilesync;
		if ( isset( $profileSync['cover'] ) )
		{
			unset( $profileSync['cover'] );
			$this->member->profilesync = $profileSync;
		}
		
		$this->member->pp_cover_photo = (string) $photo->file;
		$this->member->pp_cover_offset = (int) $photo->offset;
		
		/* Reset Profile Complete flag in case this was an optional step */
		$this->member->members_bitoptions['profile_completed'] = FALSE;
	
		$this->member->save();
		if ( $type != 'reposition' )
		{
			$this->member->logHistory( 'core', 'coverphoto', array( 'action' => $type ) );
		}
	}

	/**
	 * Get Name History
	 */
	protected function namehistory()
	{
		if ( !\IPS\Member::loggedIn()->group['g_view_displaynamehistory'] )
		{
			\IPS\Output::i()->error( 'no_module_permission', '1C138/G', 403, '' );
		}

		$table = new \IPS\Helpers\Table\Db( 'core_member_history', $this->member->url()->setQueryString( 'do', 'namehistory' ), array( 'log_member=? AND log_app=? AND log_type=?', $this->member->member_id, 'core', 'display_name' ) );
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' ), 'nameHistoryTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' ), 'nameHistoryRows' );
		$table->sortBy = 'log_date';
		$table->sortDirection = 'desc';

		$table->parsers = array(
			'log_data'	=> function( $val )
			{
				return json_decode( $val, TRUE );
			}
		);

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addtoStack( 'members_dname_history', FALSE, array( 'sprintf' => ( $this->member->name ) ) );
		\IPS\Output::i()->output = $table;

	}
	
	/**
	 * Get Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	protected function _coverPhotoGet()
	{
		return $this->member->coverPhoto();
	}

	/**
	 * Hide a status reply or status update
	 *
	 * @return	void
	 */
	protected function hide()
	{
		\IPS\Request::i()->action = 'hide';
		return $this->moderate();
	}

	/**
	 * Delete a status reply or status update
	 *
	 * @return	void
	 */
	protected function delete()
	{
		\IPS\Request::i()->action = 'delete';
		return $this->moderate();
	}

	/**
	 * Show Solutions
	 *
	 * @return	void
	 */
	public function solutions()
	{
		/* Get the different types */
		$types = array();
		$hasCallback = array();
		foreach ( \IPS\Content::routedClasses( TRUE, FALSE, TRUE ) as $class )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'profile.css', $class::$application ) );
			
			if ( \IPS\IPS::classUsesTrait( $class, 'IPS\Content\Solvable' ) )
			{
				$types[ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ] = $class::$commentClass;
			}
		}

		/* What type are we looking at? */
		$currentType = NULL;
		if ( isset( \IPS\Request::i()->type ) AND array_key_exists( \IPS\Request::i()->type, $types ) )
		{
			$currentType = \IPS\Request::i()->type;
		}

		if ( $currentType === NULL )
		{
			foreach ( $types as $key => $type )
			{
				$currentType = $key;
				break;
			}
		}

		$currentClass = $types[ $currentType ];

		/* Build Output */
		$url = \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$this->member->member_id}&do=solutions&type={$currentType}", 'front', 'profile_solutions', array( $this->member->members_seo_name ) );

		$table = new \IPS\Helpers\Table\Content( $currentClass, $url, NULL, NULL, \IPS\Content\Hideable::FILTER_AUTOMATIC, 'read', FALSE );
		$table->joinContainer = TRUE;
		$table->sortOptions = array( 'solved_date' );
		$table->sortBy = 'solved_date';
		$table->joins = array(
			array(
				'select' => "core_solved_index.id AS solved_id, core_solved_index.solved_date",
				'from'   => 'core_solved_index',
				'where'  => array( "core_solved_index.comment_id=" . $currentClass::$databaseTable . "." . $currentClass::$databasePrefix . $currentClass::$databaseColumnId  . " AND core_solved_index.member_id=? AND core_solved_index.comment_class=?", $this->member->member_id, $currentClass ),
				'type'   => 'INNER'
			)
		);
		
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'profile', 'core' ), 'userSolutionsTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'profile', 'core' ), 'userSolutionsRows' );
		$table->showAdvancedSearch = FALSE;

		/* Display */
		if ( \IPS\Request::i()->isAjax() && \IPS\Request::i()->change_section )
		{
			\IPS\Output::i()->sendOutput( (string) $table );
		}
		else
		{
			\IPS\Output::i()->title = ( $table->page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'member_solutions', FALSE, array('sprintf' => $this->member->name ) ), $table->page ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'member_solutions', FALSE, array('sprintf' => $this->member->name ) );

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile' )->userSolutions( $this->member, $types, $currentType, (string) $table, $this->member->solutionCount() );
		}
	}

	/**
	 * Recognize
	 *
	 * @return void
	 */
	protected function recognize()
	{		
		$class = \IPS\Request::i()->content_class;
		$id = \IPS\Request::i()->content_id;

		/* Does this content exist? */
		try
		{
			$content = $class::loadAndCheckPerms( $id );

			/* Can we view this item? */
			if ( ! $content->canView() )
			{
				\IPS\Output::i()->error( 'node_error', '2C138/O', 403, '' );
			}

			/* Make sure there's no shenanigans */
			if ( $this->member->member_id != $content->author()->member_id )
			{
				\IPS\Output::i()->error( 'node_error', '2C138/P', 403, '' );
			}

			/* Can we recognize this ? */
			if ( ! $content->canRecognize() )
			{
				\IPS\Output::i()->error( 'node_error', '2C138/Q', 403, '' );
			}

			/* Now build the form */
			$form = new \IPS\Helpers\Form( 'badge_form', 'save' );
			$form->class = 'ipsForm_vertical ipsForm_fullWidth';

			/* What has been awarded so far today? */
			$message = NULL;
			if ( $this->member->todaysRecognizePoints() and $this->member->todaysRecognizeBadges() )
			{
				$message = \IPS\Member::loggedIn()->language()->addToStack( 'recognize_points_so_far_both', FALSE, [
					'sprintf' => [
						$this->member->name,
						\IPS\Member::loggedIn()->language()->addToStack( 'recognize_points_pluralize', FALSE, [ 'sprintf' => [ $this->member->todaysRecognizePoints() ], 'pluralize' => [ $this->member->todaysRecognizePoints() ] ]),
						\IPS\Member::loggedIn()->language()->addToStack( 'recognize_badges_pluralize', FALSE, [ 'sprintf' => [ $this->member->todaysRecognizeBadges() ], 'pluralize' => [ $this->member->todaysRecognizeBadges() ] ]),
					] ] );
			}
			else if ( $this->member->todaysRecognizePoints() )
			{
				$message = \IPS\Member::loggedIn()->language()->addToStack( 'recognize_points_so_far_single', FALSE, [
					'sprintf' => [
						$this->member->name,
						\IPS\Member::loggedIn()->language()->addToStack( 'recognize_points_pluralize', FALSE, [ 'sprintf' => [ $this->member->todaysRecognizePoints() ], 'pluralize' => [ $this->member->todaysRecognizePoints() ] ])
					] ] );
			}
			else if ( $this->member->todaysRecognizeBadges() )
			{
				$message = \IPS\Member::loggedIn()->language()->addToStack( 'recognize_points_so_far_single', FALSE, [
					'sprintf' => [
						$this->member->name,
						\IPS\Member::loggedIn()->language()->addToStack( 'recognize_points_pluralize', FALSE, [ 'sprintf' => [ $this->member->todaysRecognizeBadges() ], 'pluralize' => [ $this->member->todaysRecognizeBadges() ] ])
					] ] );
			}

			$maxPoints = NULL;
			$disabled = FALSE;
			if ( \IPS\Settings::i()->achievements_recognize_max_per_user_day != -1 )
			{
				$maxPoints = \IPS\Settings::i()->achievements_recognize_max_per_user_day - $this->member->todaysRecognizePoints();
				if ( $maxPoints > 0 )
				{
					$message .= \IPS\Member::loggedIn()->language()->addToStack( 'recognize_points_so_far_limit', FALSE, ['sprintf' => [$this->member->name, $maxPoints]] );
				}
				else
				{
					$message .= \IPS\Member::loggedIn()->language()->addToStack( 'recognize_points_so_far_limit_none', FALSE, ['sprintf' => [$this->member->name]] );
					$disabled = TRUE;
				}

				if ( \IPS\Member::loggedIn()->modPermission('can_recognize_content_no_point_limit') )
				{
					$message .= \IPS\Member::loggedIn()->language()->addToStack('recognize_points_so_far_limit_but_none');
					$maxPoints = NULL;
					$disabled = FALSE;
				}
			}

			if ( $message )
			{
				$form->addMessage( $message, 'ipsMessage ipsMessage_info' );
			}

			if ( \IPS\Member::loggedIn()->modPermission('can_recognize_content') == '*' OR \in_array( 'badges', \IPS\Member::loggedIn()->modPermission('can_recognize_content_options') ) )
			{
				$form->add( new \IPS\Helpers\Form\Node( 'recognize_badge', NULL, FALSE, [
					'class' => '\IPS\core\Achievements\Badge',
					'permissionCheck' => function ( $node ) {
						return $node->manually_awarded;
					},
					'url' => $content->author()->url()->setQueryString( array('do' => 'recognize', 'content_class' => $class, 'content_id' => $id) )
				] ) );
			}

			if ( \IPS\Member::loggedIn()->modPermission('can_recognize_content') == '*' OR \in_array( 'points', \IPS\Member::loggedIn()->modPermission('can_recognize_content_options') ) )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'recognize_points', 0, FALSE, [ 'disabled' => $disabled, 'min' => 0, 'max' => $maxPoints ] ) );
			}

			$form->add( new \IPS\Helpers\Form\Text( 'recognize_message', NULL, FALSE ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'recognize_public', TRUE, FALSE ) );

			\IPS\Member::loggedIn()->language()->words['recognize_message_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'recognize_message__desc', FALSE, [ 'sprintf' => [ $content->author()->name ] ] );
			if ( $values = $form->values() )
			{
				if ( ! $values['recognize_badge'] and ! $values['recognize_points'] )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('recognize_form_empty');
				}
				else
				{
					\IPS\core\Achievements\Recognize::add( $content, $this->member, $values['recognize_points'], $values['recognize_badge'], $values['recognize_message'], \IPS\Member::loggedIn(), $values['recognize_public'] );

					if ( \IPS\Request::i()->isAjax() )
					{
						\IPS\Output::i()->sendOutput( \IPS\Member::loggedIn()->language()->addToStack( 'recognize_submit_success' ) );
					}
					else
					{
						\IPS\Output::i()->redirect( $content->url(), 'recognize_submit_success' );
					}
				}
			}

			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'recognize_author', FALSE, [ 'sprintf' => [ $content->author()->name ] ] );
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C138/R', 403, '' );
		}
	}

	/**
	 * Recognize
	 *
	 * @return void
	 */
	protected function unrecognize()
	{
		$class = \IPS\Request::i()->content_class;
		$id = \IPS\Request::i()->content_id;

		/* Does this content exist? */
		try
		{
			$content = $class::loadAndCheckPerms( $id );

			/* Can we view this item? */
			if ( ! $content->canView() )
			{
				\IPS\Output::i()->error( 'node_error', '2C138/S', 403, '' );
			}

			/* Can we recognize this ? */
			if ( ! $content->canRemoveRecognize() )
			{
				\IPS\Output::i()->error( 'node_error', '2C138/T', 403, '' );
			}

			$content->removeRecognize();
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C138/Ug', 403, '' );
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Member::loggedIn()->language()->addToStack( 'recognize_removed_success' ) );
		}
		else
		{
			\IPS\Output::i()->redirect( $content->url(), 'recognize_removed_success' );
		}
	}
}