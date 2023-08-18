<?php
/**
 * @brief		Clubs View
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Feb 2017
 */

namespace IPS\core\modules\front\clubs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clubs View
 */
class _view extends \IPS\Helpers\CoverPhoto\Controller
{
	/**
	 * @brief	The club being viewed
	 */
	protected $club;
	
	/**
	 * @brief	The logged in user's status
	 */
	protected $memberStatus;

	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 */
	public static $dataLayerContext = array(
		'community_area' =>  [ 'value' => 'clubs', 'odkUpdate' => 'true']
	);
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( \IPS\Request::i()->do != 'embed' )
		{
			/* Permission check */
			if ( !\IPS\Settings::i()->clubs )
			{
				\IPS\Output::i()->error( 'no_module_permission', '2C350/P', 403, '' );
			}
			
			/* Load the club */
			try
			{
				$this->club = \IPS\Member\Club::load( \IPS\Request::i()->id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C350/1', 404, '' );
			}
			$this->memberStatus = $this->club->memberStatus( \IPS\Member::loggedIn() );
			
			/* If we can't even know it exists, show an error */
			if ( !$this->club->canView() )
			{
				\IPS\Output::i()->error( \IPS\Member::loggedIn()->member_id ? 'no_module_permission' : 'no_module_permission_guest', '2C350/2', 403, '' );
			}
							
			/* Sort out the breadcrumb */
			\IPS\Output::i()->breadcrumb = array(
				array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') )
			);
			
			/* Add a "Search in this club" contextual search option and set to default*/
			\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item_club' ) ] = array( 'type' => '', 'club' => "c{$this->club->id}" );
			\IPS\Output::i()->defaultSearchOption = array( '', 'search_contextual_item_club' );

			/* CSS */
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs.css', 'core', 'front' ) );
			if ( \IPS\Theme::i()->settings['responsive'] )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs_responsive.css', 'core', 'front' ) );
			}
	
			/* JS */
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_clubs.js', 'core', 'front' ) );
			
			/* Location for online list */
			if ( $this->club->type !== \IPS\Member\Club::TYPE_PRIVATE )
			{
				\IPS\Session::i()->setLocation( $this->club->url(), array(), 'loc_clubs_club', array( $this->club->name => FALSE ) );
			}
			else
			{
				\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), array(), 'loc_clubs_directory' );
			}

			\IPS\Output::i()->sidebar['contextual'] = '';

			/* Club info in sidebar */
			if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
			{
				\IPS\Output::i()->sidebar['contextual'] .= \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $this->club, NULL, 'sidebar' );
			}
			$club = $this->club;

			if( ( \IPS\GeoLocation::enabled() and \IPS\Settings::i()->clubs_locations AND $location = $this->club->location() ) )
			{
				\IPS\Output::i()->sidebar['contextual'] .= \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->clubLocationBox( $this->club, $location );
			}
			if( $this->club->type != $club::TYPE_PUBLIC AND $this->club->canViewMembers() )
			{
				\IPS\Output::i()->sidebar['contextual'] .= \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->clubMemberBox( $this->club );
			}
		}
		
		if ( !\in_array( \IPS\Request::i()->do, array( 'rules', 'leave', 'embed' ) ) AND $this->club->memberStatus( \IPS\Member::loggedIn() ) !== NULL AND !$this->club->rulesAcknowledged()  )
		{
			\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'rules' )->addRef( \IPS\Request::i()->url() ) );
		}
		
		/* Pass upwards */
		parent::execute();
	}
	
	/**
	 * View
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$firstTab = $this->club->firstTab();

		$key = key($firstTab);

		if ( !\in_array( $key, array( 'club_home', 'club_members') ) )
		{
			\IPS\Output::i()->redirect( (string) $firstTab[ $key ]['href'] );
		}
			if( $key == 'club_members' )
			{
				$this->members();
			}
			else
			{
				$this->overview();
			}
			return;
	}

	/**
	 * Overview page
	 *
	 * @return void
	 */
	protected function overview()
	{
		/* Get the activity stream */
		$activity = \IPS\Content\Search\Query::init()->filterByClub( $this->club )->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED )->search();

		/* Get who joined the club in between those results */
		if ( $this->club->type != \IPS\Member\Club::TYPE_PUBLIC )
		{
			$lastTime = NULL;
			foreach ( $activity as $key => $result )
			{
				if ( $result !== NULL )
				{
					$lastTime = $result->createdDate->getTimestamp();
				}
				else
				{
					unset( $activity[ $key ] );
				}
			}
			$joins = array();

			if( $this->club->canViewMembers() )
			{
				$joinWhere = array( array( 'club_id=?', $this->club->id ), array( \IPS\Db::i()->in( 'status', array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER ) ) ) );
				if ( $lastTime )
				{
					$joinWhere[] = array( 'core_clubs_memberships.joined>?', $lastTime );
				}
				$select = 'core_clubs_memberships.joined' . ',' . implode( ',', array_map( function( $column ) {
						return 'core_members.' . $column;
					}, \IPS\Member::columnsForPhoto() ) );
				foreach ( \IPS\Db::i()->select( $select, 'core_clubs_memberships', $joinWhere, 'joined DESC', array( 0, 50 ), NULL, NULL, \IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS )->join( 'core_members', 'core_members.member_id=core_clubs_memberships.member_id' ) as $join )
				{
					$joins[] = new \IPS\Content\Search\Result\Custom(
						\IPS\DateTime::ts( $join['core_clubs_memberships']['joined'] ),
						\IPS\Member::loggedIn()->language()->addToStack( 'clubs_activity_joined', FALSE, array( 'htmlsprintf' => \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLinkFromData( $join['core_members']['member_id'], $join['core_members']['name'], $join['core_members']['members_seo_name'] ) ) ),
						\IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userPhotoFromData( $join['core_members']['member_id'], $join['core_members']['name'], $join['core_members']['members_seo_name'], \IPS\Member::photoUrl( $join['core_members'] ), 'tiny' )
					);
				}
			}


			/* Merge them in */
			if ( !empty( $joins ) )
			{
				$activity = array_filter( array_merge( iterator_to_array( $activity ), $joins ) );
				uasort( $activity, function( $a, $b )
				{
					if ( $a->createdDate->getTimestamp() == $b->createdDate->getTimestamp() )
					{
						return 0;
					}
					elseif( $a->createdDate->getTimestamp() < $b->createdDate->getTimestamp() )
					{
						return 1;
					}
					else
					{
						return -1;
					}
				} );
			}
		}

		/* Display */
		\IPS\Output::i()->linkTags['canonical'] = (string) $this->club->url();
		\IPS\Output::i()->title = $this->club->name;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clubs')->view( $this->club, $activity, $this->club->fieldValues() );

		if( $firstTab = $this->club->firstTab() AND !isset( $firstTab['club_home'] ) )
		{
			\IPS\Output::i()->breadcrumb[] = array( $this->club->url(), $this->club->name );
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'club_home' ) );
		}
		else
		{
			\IPS\Output::i()->breadcrumb[] = array( NULL, $this->club->name );
		}

		/* Set some meta tags for the club */
		$this->_setDefaultMetaTags();
	}
	
	/**
	 * Map Callback
	 *
	 * @return	void
	 */
	protected function mapPopup()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clubs')->mapPopup( $this->club );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	protected function edit()
	{
		if ( !( $this->club->owner and $this->club->owner->member_id == \IPS\Member::loggedIn()->member_id ) and !\IPS\Member::loggedIn()->modPermission('can_access_all_clubs') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/A', 403, '' );
		}
		
		$form = $this->club->form();

		if( $values = $form->values() )
		{
			$this->club->skipCloneDuplication = TRUE;
			$old = clone $this->club;

			$this->club->processForm( $values, FALSE, FALSE, NULL );

			$changes = $this->club::renewalChanges( $old, $this->club );

			if ( !empty( $changes ) )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->decision( 'product_change_blurb', array(
					'product_change_blurb_existing'	=> \IPS\Http\Url::internal( "app=core&module=clubs&controller=view&do=updateExisting&id={$this->club->id}" )->setQueryString( 'changes', json_encode( $changes ) )->csrf(),
					'product_change_blurb_new'		=> $this->club->url(),
				) );

				return;
			}
			else
			{
				\IPS\Output::i()->redirect( $this->club->url() );
			}
		}

		\IPS\Output::i()->title = $this->club->name;
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		else
		{
			\IPS\Output::i()->output = $form;
		}

	}
	
	/**
	 * Edit Photo
	 *
	 * @return	void
	 */
	protected function editPhoto()
	{
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/R', 403, '' );
		}
		\IPS\Output::i()->title = $this->club->name;
		
		$form = new \IPS\Helpers\Form( 'club_profile_photo', 'continue' );
		$form->ajaxOutput = TRUE;
		$form->add( new \IPS\Helpers\Form\Upload( 'club_profile_photo', $this->club->profile_photo_uncropped ? \IPS\File::get( 'core_Clubs', $this->club->profile_photo_uncropped ) : NULL, FALSE, array( 'storageExtension' => 'core_Clubs', 'allowStockPhotos' => TRUE, 'image' => array( 'maxWidth' => 200, 'maxHeight' => 200 ) ) ) );
		if ( $values = $form->values() )
		{
			if ( !$values['club_profile_photo'] or $this->club->profile_photo_uncropped != (string) $values['club_profile_photo'] )
			{
				foreach ( array( 'profile_photo', 'profile_photo_uncropped' ) as $k )
				{
					try
					{
						\IPS\File::get( 'core_Clubs', $this->club->$k )->delete();
					}
					catch ( \Exception $e ) { }
					$this->club->$k = NULL;
				}
			}
			
			if ( $values['club_profile_photo'] )
			{
				$this->club->profile_photo_uncropped = (string) $values['club_profile_photo'];
				$this->club->save();
				
				if ( \IPS\Request::i()->isAjax() )
				{					
					$this->cropPhoto();
					return;
				}
				else
				{
					\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'cropPhoto' ) );
				}
			}
			else
			{
				$this->club->save();
				\IPS\Output::i()->redirect( $this->club->url() );
			}
		}
		
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Crop Photo
	 *
	 * @return	void
	 */
	protected function cropPhoto()
	{
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/V', 403, '' );
		}
		\IPS\Output::i()->title = $this->club->name;
		
		/* Get the photo */
		if ( !$this->club->profile_photo_uncropped )
		{
			\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'editPhoto' ) );
		}
		$original = \IPS\File::get( 'core_Clubs', $this->club->profile_photo_uncropped );
		$image = \IPS\Image::create( $original->contents() );
		
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
		$form = new \IPS\Helpers\Form( 'photo_crop', 'save', $this->club->url()->setQueryString( 'do', 'cropPhoto' ) );
		$form->class = 'ipsForm_noLabels';
		$form->add( new \IPS\Helpers\Form\Custom('photo_crop', array( 0, 0, $suggestedWidth, $suggestedHeight ), FALSE, array(
			'getHtml'	=> function( $field ) use ( $original )
			{
				return \IPS\Theme::i()->getTemplate('members', 'core', 'global')->photoCrop( $field->name, $field->value, $this->club->url()->setQueryString( 'do', 'cropPhotoGetPhoto' )->csrf() );
			}
		) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Crop it */
			$image->cropToPoints( $values['photo_crop'][0], $values['photo_crop'][1], $values['photo_crop'][2], $values['photo_crop'][3] );
			
			/* Delete the existing */
			if ( $this->club->profile_photo )
			{
				try
				{
					\IPS\File::get( 'core_Clubs', $this->club->profile_photo )->delete();
				}
				catch ( \Exception $e ) { }
			}
						
			/* Save it */
			$croppedFilename = mb_substr( $original->originalFilename, 0, mb_strrpos( $original->originalFilename, '.' ) ) . '.cropped' . mb_substr( $original->originalFilename, mb_strrpos( $original->originalFilename, '.' ) );
			$cropped = \IPS\File::create( 'core_Clubs', $croppedFilename, (string) $image );
			$this->club->profile_photo = (string) $cropped;
			$this->club->save();

			/* Edited member, so clear widget caches (stats, widgets that contain photos, names and so on) */
			\IPS\Widget::deleteCaches();
							
			/* Redirect */
			\IPS\Output::i()->redirect( $this->club->url() );
		}
		
		/* Display */
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
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
		$original = \IPS\File::get( 'core_Clubs', $this->club->profile_photo_uncropped );
		$headers = array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'inline', $original->filename ) );
		\IPS\Output::i()->sendOutput( $original->contents(), 200, \IPS\File::getMimeType( $original->filename ), $headers );
	}
	
	/**
	 * See Members
	 *
	 * @return	void
	 */
	protected function members()
	{
		/* Public groups have no member list */
		if ( !$this->club->canViewMembers() )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/H', 404, '' );
		}
		
		/* What members are we getting? */
		$filter = NULL;
		$statuses = array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER );
		$baseUrl = $this->club->url()->setQueryString( 'do', 'members' );
		if ( isset( \IPS\Request::i()->filter ) )
		{
			switch ( \IPS\Request::i()->filter )
			{
				case \IPS\Member\Club::STATUS_LEADER:
					$filter = \IPS\Member\Club::STATUS_LEADER;
					$statuses = array( \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER );
					$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_LEADER );
					break;
				
				case \IPS\Member\Club::STATUS_REQUESTED:
					if ( $this->club->isLeader() )
					{
						$filter = \IPS\Member\Club::STATUS_REQUESTED;
						$statuses = array( \IPS\Member\Club::STATUS_REQUESTED );
						$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_REQUESTED );
					}
					break;
					
				case \IPS\Member\Club::STATUS_WAITING_PAYMENT:
					if ( $this->club->isLeader() )
					{
						$filter = \IPS\Member\Club::STATUS_WAITING_PAYMENT;
						$statuses = array( \IPS\Member\Club::STATUS_WAITING_PAYMENT );
						$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_WAITING_PAYMENT );
					}
					break;
					
				case \IPS\Member\Club::STATUS_BANNED:
					if ( $this->club->isLeader() )
					{
						$filter = \IPS\Member\Club::STATUS_BANNED;
						$statuses = array( \IPS\Member\Club::STATUS_DECLINED, \IPS\Member\Club::STATUS_BANNED );
						$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_BANNED );
					}
					break;
					
				case \IPS\Member\Club::STATUS_INVITED:
					if ( $this->club->isLeader() )
					{
						$filter = \IPS\Member\Club::STATUS_INVITED;
						$statuses = array( \IPS\Member\Club::STATUS_INVITED, \IPS\Member\Club::STATUS_INVITED_BYPASSING_PAYMENT );
						$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_INVITED );
					}
					break;
					
				case \IPS\Member\Club::STATUS_EXPIRED:
					if ( $this->club->isLeader() )
					{
						$filter = \IPS\Member\Club::STATUS_EXPIRED;
						$statuses = array( \IPS\Member\Club::STATUS_EXPIRED, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR );
						$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_EXPIRED );
					}
					break;
			}
		}
		
		/* What are we sorting by? */
		$orderByClause = 'core_clubs_memberships.joined DESC';
		$sortBy = 'joined';
		if ( isset( \IPS\Request::i()->sortby ) and \IPS\Request::i()->sortby === 'name' )
		{
			$orderByClause = 'core_members.name ASC';
			$sortBy = 'name';
		}
		
		/* Sort out the offset */
		$perPage = $this->club->membersPerPage();

		$activePage = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;

		if( $activePage < 1 )
		{
			$activePage = 1;
		}

		$offset = ( $activePage - 1 ) * $perPage;

		/* Fetch them */
		$members = $this->club->members( $statuses, array( $offset, $perPage ), $orderByClause, $this->club->isLeader() ? 5 : 1 );

		$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl, ceil( $this->club->members( $statuses, NULL, $orderByClause, 4 ) / $perPage ), $activePage, $perPage );


		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'rows' => \IPS\Theme::i()->getTemplate( 'clubs' )->membersRows( $this->club, $members ), 'pagination' => $pagination, 'extraHtml' => '' ) );
		}
		else
		{
			$staffStatuses = array( \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_MODERATOR );
			if ( $this->club->isLeader( \IPS\Member::loggedIn() ) )
			{
				$staffStatuses[] = \IPS\Member\Club::STATUS_EXPIRED_MODERATOR;
			}
			$clubStaff = $this->club->members( $staffStatuses, NULL, "IF(core_clubs_memberships.status='leader',0,1), core_clubs_memberships.joined ASC", $this->club->isLeader( \IPS\Member::loggedIn() ) ? 5 : 3 );
			
			\IPS\Output::i()->title = $this->club->name;

			if( $firstTab = $this->club->firstTab() AND !isset( $firstTab['club_members'] ) )
			{
				\IPS\Output::i()->breadcrumb[] = array( $this->club->url(), $this->club->name );
				\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'club_members' ) );
			}
			else
			{
				\IPS\Output::i()->breadcrumb[] = array( NULL, $this->club->name );
			}

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'clubs' )->members( $this->club, $members, $pagination, $sortBy, $filter, $clubStaff );
		}

		/* Set some meta tags for the club */
		$this->_setDefaultMetaTags();
	}
	
	/**
	 * Accept a join request
	 *
	 * @return	void
	 */
	protected function acceptRequest()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/3', 403, '' );
		}
		
		/* Check the member's request is pending */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( $this->club->memberStatus( $member ) != \IPS\Member\Club::STATUS_REQUESTED )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/4', 403, '' );
		}
		
		/* Add them */
		$status = \IPS\Member\Club::STATUS_MEMBER;
		if ( $this->club->isPaid() and !isset( \IPS\Request::i()->waiveFee ) )
		{
			$status = \IPS\Member\Club::STATUS_WAITING_PAYMENT;
		}
		$this->club->addMember( $member, $status, TRUE, \IPS\Member::loggedIn(), NULL, TRUE );
		$this->club->recountMembers();
		
		/* Notify the member */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_response', $this->club, array( $this->club, TRUE ) );
		$notification->recipients->attach( $member );
		$notification->send();
		
		/* Send a notification to any leaders besides ourselves */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_join', $this->club, array( $this->club, $member ), array( 'response' => TRUE ) );
		foreach ( $this->club->members( array( \IPS\Member\Club::STATUS_LEADER ), NULL, NULL, 2 ) as $leader )
		{
			$leader = \IPS\Member::constructFromData( $leader );
			if ( $leader->member_id != \IPS\Member::loggedIn()->member_id )
			{
				$notification->recipients->attach( $leader );
			}
		}
		if ( \count( $notification->recipients ) )
		{
			$notification->send();
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'status' => 'approved' ) );
		}
		else
		{
			/* If other requests are pending, send us back, otherwise take us to the main member list */
			$url = $this->club->url()->setQueryString( 'do', 'members' );
			if ( \count( $this->club->members( array( \IPS\Member\Club::STATUS_REQUESTED ) ) ) )
			{
				\IPS\Output::i()->redirect( $url->setQueryString( 'filter', \IPS\Member\Club::STATUS_REQUESTED ) );
			}
			else
			{
				\IPS\Output::i()->redirect( $url );
			}
		}
	}
	
	/**
	 * Decline a join request
	 *
	 * @return	void
	 */
	protected function declineRequest()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/F', 403, '' );
		}
		
		/* Check the member's request is pending */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( $this->club->memberStatus( $member ) != \IPS\Member\Club::STATUS_REQUESTED )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/G', 403, '' );
		}
		
		/* Decline them */
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_DECLINED, TRUE, \IPS\Member::loggedIn() );
		
		/* Notify the member */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_response', $this->club, array( $this->club, FALSE ) );
		$notification->recipients->attach( $member );
		$notification->send();
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'status' => 'declined' ) );
		}
		else
		{
			/* If other requests are pending, send us back, otherwise take us to the main member list */
			$url = $this->club->url()->setQueryString( 'do', 'members' );
			if ( \count( $this->club->members( array( \IPS\Member\Club::STATUS_REQUESTED ) ) ) )
			{
				\IPS\Output::i()->redirect( $url->SetQueryString( 'filter', \IPS\Member\Club::STATUS_REQUESTED ) );
			}
			else
			{
				\IPS\Output::i()->redirect( $url );
			}
		}
	}
	
	/**
	 * Make a member a leader
	 *
	 * @return	void
	 */
	protected function makeLeader()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/6', 403, '' );
		}
		
		/* Get member */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( !\in_array( $this->club->memberStatus( $member ), array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_EXPIRED, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/7', 403, '' );
		}
		
		/* Promote */
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_LEADER, TRUE );
		$this->club->recountMembers();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Demote a member from being a leader
	 *
	 * @return	void
	 */
	protected function demoteLeader()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/8', 403, '' );
		}
		
		/* Get member */
		$member = ( \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on ) ? \IPS\nexus\Customer::load( \IPS\Request::i()->member ) : \IPS\Member::load( \IPS\Request::i()->member );
		if ( $this->club->memberStatus( $member ) != \IPS\Member\Club::STATUS_LEADER )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/9', 403, '' );
		}
		
		/* Are they expired? */
		$status = \IPS\Member\Club::STATUS_MEMBER;
		if ( \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on and $this->club->renewal_price )
		{
			foreach ( \IPS\core\extensions\nexus\Item\ClubMembership::getPurchases( $member, $this->club->id ) as $purchase )
			{
				if ( $purchase->expire and $purchase->expire->getTimestamp() < time() )
				{
					$status = \IPS\Member\Club::STATUS_EXPIRED;
				}
			}
		}
		
		/* Promote */
		$this->club->addMember( $member, $status, TRUE );
		$this->club->recountMembers();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Make a member a moderator
	 *
	 * @return	void
	 */
	protected function makeModerator()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/K', 403, '' );
		}
		
		/* Get member */
		$member = ( \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on ) ? \IPS\nexus\Customer::load( \IPS\Request::i()->member ) : \IPS\Member::load( \IPS\Request::i()->member );
		if ( !\in_array( $this->club->memberStatus( $member ), array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_EXPIRED, \IPS\Member\Club::STATUS_LEADER ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/L', 403, '' );
		}
		
		/* Are they expired? */
		$status = \IPS\Member\Club::STATUS_MODERATOR;
		if ( \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on and $this->club->renewal_price )
		{
			foreach ( \IPS\core\extensions\nexus\Item\ClubMembership::getPurchases( $member, $this->club->id ) as $purchase )
			{
				if ( $purchase->expire and $purchase->expire->getTimestamp() < time() )
				{
					$status = \IPS\Member\Club::STATUS_EXPIRED_MODERATOR;
				}
			}
		}
		
		/* Promote */
		$this->club->addMember( $member, $status, TRUE );
		$this->club->recountMembers();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Demote a member from being a moderator
	 *
	 * @return	void
	 */
	protected function demoteModerator()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/M', 403, '' );
		}
		
		/* Get member */
		$member = ( \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on ) ? \IPS\nexus\Customer::load( \IPS\Request::i()->member ) : \IPS\Member::load( \IPS\Request::i()->member );
		if ( !\in_array( $this->club->memberStatus( $member ), array( \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/N', 403, '' );
		}
		
		/* Are they expired? */
		$status = \IPS\Member\Club::STATUS_MEMBER;
		if ( \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on and $this->club->renewal_price )
		{
			foreach ( \IPS\core\extensions\nexus\Item\ClubMembership::getPurchases( $member, $this->club->id ) as $purchase )
			{
				if ( $purchase->expire and $purchase->expire->getTimestamp() < time() )
				{
					$status = \IPS\Member\Club::STATUS_EXPIRED;
				}
			}
		}
		
		/* Promote */
		$this->club->addMember( $member, $status, TRUE );
		$this->club->recountMembers();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Remove a member
	 *
	 * @return	void
	 */
	protected function removeMember()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/C', 403, '' );
		}
		
		/* Get member */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		$status = $this->club->memberStatus( $member );
		
		/* If this was just an invite, then just remove that */
		if ( \in_array( $status, array( \IPS\Member\Club::STATUS_INVITED, \IPS\Member\Club::STATUS_INVITED_BYPASSING_PAYMENT ) ) )
		{
			$this->club->removeMember( $member );
			\IPS\Db::i()->delete( 'core_notifications', array( '`member`=? AND notification_app=? AND notification_key=? and item_id=?', $member->member_id, 'core', 'club_invitation', $this->club->id ) );
			$member->recountNotifications();
		}
		
		/* If they were previously accepted and waiting for payment, treat it as a decline */
		elseif ( \in_array( $status, array( \IPS\Member\Club::STATUS_WAITING_PAYMENT ) ) )
		{
			/* Decline them */
			$this->club->addMember( $member, \IPS\Member\Club::STATUS_DECLINED, TRUE, \IPS\Member::loggedIn() );
			
			/* Notify the member */
			$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_response', $this->club, array( $this->club, FALSE ) );
			$notification->recipients->attach( $member );
			$notification->send();
		}
		
		/* Otherwise check they are actually a member that can be removed */
		else
		{
			if ( !\in_array( $status, array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_EXPIRED, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR ) ) ) 
			{
				\IPS\Output::i()->error( 'node_error', '2C350/9', 403, '' );
			}
			if ( $this->club->owner and $this->club->owner->member_id === $member->member_id )
			{
				\IPS\Output::i()->error( 'club_cannot_remove_owner', '2C350/E', 403, '' );
			}
			
			/* Cancel purchase */
			if ( \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on )
			{
				foreach ( \IPS\core\extensions\nexus\Item\ClubMembership::getPurchases( \IPS\nexus\Customer::load( \IPS\Request::i()->member ), $this->club->id ) as $purchase )
				{
					$purchase->cancelled = TRUE;
					$purchase->member->log( 'purchase', array( 'type' => 'cancel', 'id' => $purchase->id, 'name' => $purchase->name ) );
					$purchase->can_reactivate = FALSE;
					$purchase->save();
				}
			}
			
			/* Remove */
			$this->club->addMember( $member, \IPS\Member\Club::STATUS_BANNED, TRUE, \IPS\Member::loggedIn() );
		}
		
		/* Recount */
		$this->club->recountMembers();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Waive membership fee
	 *
	 * @return	void
	 */
	protected function bypassPayment()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() or !\IPS\Application::appIsEnabled('nexus') or !\IPS\Settings::i()->clubs_paid_on )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/W', 403, '' );
		}
		
		/* Get member */
		$member = \IPS\nexus\Customer::load( \IPS\Request::i()->member );
		
		/* Do it */
		switch ( $this->club->memberStatus( $member ) )
		{
			/* If they were waiting for payment, just go ahead and promote them to a member */
			case \IPS\Member\Club::STATUS_WAITING_PAYMENT:
				$this->club->addMember( $member, \IPS\Member\Club::STATUS_MEMBER, TRUE );
				break;
				
			/* If they were already in the club, find their purchase and remove the expiry date */
			case \IPS\Member\Club::STATUS_MEMBER:
			case \IPS\Member\Club::STATUS_EXPIRED:
			case \IPS\Member\Club::STATUS_MODERATOR:
			case \IPS\Member\Club::STATUS_EXPIRED_MODERATOR:
			case \IPS\Member\Club::STATUS_LEADER:
				foreach ( \IPS\core\extensions\nexus\Item\ClubMembership::getPurchases( $member, $this->club->id ) as $purchase )
				{
					$extra = $purchase->extra;
					$extra['originalExpire'] = $purchase->expire ? $purchase->expire->getTimestamp() : NULL;
					$purchase->extra = $extra;
					$purchase->expire = NULL;
					$purchase->save();
					
					$member->log( 'purchase', array( 'type' => 'info', 'info' => 'never_expire', 'id' => $purchase->id, 'name' => $purchase->name ) );
				}
				break;
				
			/* If they have been invited, just change their status to invited bypassing payment */
			case \IPS\Member\Club::STATUS_INVITED:
				$this->club->addMember( $member, \IPS\Member\Club::STATUS_INVITED_BYPASSING_PAYMENT, TRUE );
				break;			
				
			/* For anything else, we can't do this... */
			default:
				\IPS\Output::i()->error( 'node_error', '2C350/X', 403, '' );
		}
		$this->club->recountMembers();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Restore Payment
	 *
	 * @return	void
	 */
	protected function restorePayment()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() or !\IPS\Application::appIsEnabled('nexus') or !\IPS\Settings::i()->clubs_paid_on )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/W', 403, '' );
		}
		
		/* Get member */
		$member = \IPS\nexus\Customer::load( \IPS\Request::i()->member );
		
		/* Do it */
		$status = $this->club->memberStatus( $member );
		switch ( $status )
		{
			/* If they were already in the club, find their purchase and restore the expiry date */
			case \IPS\Member\Club::STATUS_MEMBER:
			case \IPS\Member\Club::STATUS_EXPIRED:
			case \IPS\Member\Club::STATUS_MODERATOR:
			case \IPS\Member\Club::STATUS_EXPIRED_MODERATOR:
			case \IPS\Member\Club::STATUS_LEADER:
				
				$foundPurchase = FALSE;
				foreach ( \IPS\core\extensions\nexus\Item\ClubMembership::getPurchases( $member, $this->club->id ) as $purchase )
				{
					$foundPurchase = TRUE;
					
					if ( !$purchase->renewals or !$purchase->renewals->cost->amount->isZero() )
					{
						$purchase->renewals = $this->club->renewalTerm( $purchase->renewal_currency ?: $member->defaultCurrency() );
					}
					if ( isset( $purchase->extra['originalExpire'] ) )
					{
						$newExpiry = \IPS\DateTime::ts( $purchase->extra['originalExpire'] );
					}
					else
					{
						$newExpiry = \IPS\DateTime::create()->add( $purchase->renewals->interval );					
					}
					$purchase->expire = $newExpiry;
					$purchase->save();
					$member->log( 'purchase', array( 'type' => 'info', 'info' => 'restored_expire', 'id' => $purchase->id, 'name' => $purchase->name ) );
					
					if ( $newExpiry->getTimestamp() < time() )
					{
						if ( $status === \IPS\Member\Club::STATUS_MEMBER )
						{
							$this->club->addMember( $member, \IPS\Member\Club::STATUS_EXPIRED, TRUE );
						}
						elseif ( $status === \IPS\Member\Club::STATUS_MODERATOR )
						{
							$this->club->addMember( $member, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR, TRUE );
						}
					}
					else
					{
						if ( $status === \IPS\Member\Club::STATUS_EXPIRED )
						{
							$this->club->addMember( $member, \IPS\Member\Club::STATUS_MEMBER, TRUE );
						}
						elseif ( $status === \IPS\Member\Club::STATUS_EXPIRED_MODERATOR )
						{
							$this->club->addMember( $member, \IPS\Member\Club::STATUS_MODERATOR, TRUE );
						}
					}
				}
				
				/* If we couldn't find a purchase, we'll have to remove them and re-invite them */
				if ( !$foundPurchase )
				{
					$this->club->addMember( $member, \IPS\Member\Club::STATUS_INVITED, TRUE, NULL, \IPS\Member::loggedIn(), TRUE );
					
					$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_invitation', $this->club, array( $this->club, \IPS\Member::loggedIn() ), array( 'invitedBy' => \IPS\Member::loggedIn()->member_id ) );
					$notification->recipients->attach( $member );
					$notification->send();
				}
				
				break;
				
			/* If they have been invited, just change their status to invited NOT bypassing payment */
			case \IPS\Member\Club::STATUS_INVITED_BYPASSING_PAYMENT:
				$this->club->addMember( $member, \IPS\Member\Club::STATUS_INVITED, TRUE );
				break;
				
			/* For anything else, we can't do this... */
			default:
				\IPS\Output::i()->error( 'node_error', '2C350/X', 403, '' );
		}
		$this->club->recountMembers();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Rules
	 *
	 * @return void
	 */
	protected function rules()
	{
		if ( $this->club->rules_required AND !$this->club->rulesAcknowledged() )
		{
			$form = new \IPS\Helpers\Form( 'form', 'accept' );
			$form->hiddenValues['accepted'] = 1;
			if ( $referrer = \IPS\Request::i()->referrer() )
			{
				$form->hiddenValues['ref'] = base64_encode( (string) $referrer );
			}
			$form->class = 'ipsForm_vertical';

			if ( $this->club->memberStatus( \IPS\Member::loggedIn() ) === NULL )
			{
				$form->addButton( 'cancel', 'link', $this->club->url() );
			}
			else
			{
				$form->addButton( 'club_leave', 'link', $this->club->url()->setQueryString( 'do', 'leave' )->csrf() );
			}
			
			if ( $values = $form->values() )
			{
				/* If we're not a member of this club yet, send us back to the join form */
				if( $this->club->memberStatus( \IPS\Member::loggedIn() ) === NULL  )
				{
					if( $referrer = \IPS\Request::i()->referrer() )
					{
						\IPS\Output::i()->redirect( $referrer->setQueryString( 'rulesAcknowledged', 1 ), 'accepted' );
					}
					else
					{
						\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'rulesAcknowledged', 1 ), 'accepted' );
					}
				}

				$this->club->acknowledgeRules( \IPS\Member::loggedIn() );
				if ( $referrer = \IPS\Request::i()->referrer() )
				{
					\IPS\Output::i()->redirect( $referrer, 'accepted' );
				}
				
				\IPS\Output::i()->redirect( $this->club->url(), 'accepted' );
			}
			
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'club_rules' );
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'clubs', 'core' ), 'rulesForm' ), $this->club );
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'club_rules' );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->clubRules( $this->club );
		}
	}
	
	/**
	 * Join
	 *
	 * @return	void
	 */
	protected function join()
	{
		/* Can we join? */
		if ( !$this->club->canJoin() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/I', 403, '' );
		}

		/* Are there rules that need to be acknowledged which we haven't acknowledged yet? */
		if( $this->club->rules_required AND !$this->club->rulesAcknowledged() AND !\IPS\Request::i()->rulesAcknowledged )
		{
			$rulesUrl = $this->club->url()->setQueryString( 'do', 'rules' );

			if( $referrer = \IPS\Request::i()->referrer() )
			{
				$rulesUrl	= $rulesUrl->addRef( \IPS\Request::i()->url()->addRef( $referrer ) );
			}

			\IPS\Output::i()->redirect( $rulesUrl );
		}
		
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* If this is an open club, or the member was invited, or they have mod access anyway go ahead and add them */
		if ( \in_array( $this->memberStatus, array( \IPS\Member\Club::STATUS_INVITED, \IPS\Member\Club::STATUS_INVITED_BYPASSING_PAYMENT, \IPS\Member\Club::STATUS_WAITING_PAYMENT ) ) or $this->club->type === \IPS\Member\Club::TYPE_OPEN or \IPS\Member::loggedIn()->modPermission('can_access_all_clubs') )
		{
			/* Unless they have to pay */
			if ( $this->club->isPaid() and $this->memberStatus !== \IPS\Member\Club::STATUS_INVITED_BYPASSING_PAYMENT )
			{
				if ( $this->club->joiningFee() )
				{
					$invoiceUrl = $this->club->generateInvoice();
					
					/* Take them to it */
					\IPS\Output::i()->redirect( $invoiceUrl );
				}
				else
				{
					\IPS\Output::i()->error( 'club_paid_unavailable', '1C350/N', 403, '' );
				}
			}
			else
			{
				$this->club->addMember( \IPS\Member::loggedIn(), \IPS\Member\Club::STATUS_MEMBER, TRUE, NULL, NULL, TRUE );
				$this->club->recountMembers();
				$notificationKey = 'club_join';
			}
		}
		/* Otherwise, add the request */
		else
		{
			$this->club->addMember( \IPS\Member::loggedIn(), \IPS\Member\Club::STATUS_REQUESTED );
			$notificationKey = 'club_request';
		}
		
		/* Send a notification to any leaders */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), $notificationKey, $this->club, array( $this->club, \IPS\Member::loggedIn() ), array( \IPS\Member::loggedIn()->member_id ) );
		foreach ( $this->club->members( array( \IPS\Member\Club::STATUS_LEADER ), NULL, NULL, 2 ) as $member )
		{
			$notification->recipients->attach( \IPS\Member::constructFromData( $member ) );
		}
		$notification->send();

		/* If we just accepted the rules, set the flag */
		if( \IPS\Request::i()->rulesAcknowledged )
		{
			$this->club->acknowledgeRules( \IPS\Member::loggedIn() );
		}
		
		/* Redirect */
		if ( ! $this->club->rulesAcknowledged() )
		{
			\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'rules' ) );
		}
		else
		{
			if ( $url = \IPS\Request::i()->referrer() )
			{
				\IPS\Output::i()->redirect( $url );
			}
			else
			{
				\IPS\Output::i()->redirect( $this->club->url() );
			}
		}
	}
	
	/**
	 * Leave
	 *
	 * @return	void
	 */
	protected function leave()
	{
		/* Can we leave? */
		if ( !\in_array( $this->club->memberStatus( \IPS\Member::loggedIn() ), array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_EXPIRED, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR, \IPS\Member\Club::STATUS_WAITING_PAYMENT, \IPS\Member\Club::STATUS_INVITED, \IPS\Member\Club::STATUS_REQUESTED ) ) or ( $this->club->owner and $this->club->owner->member_id == \IPS\Member::loggedIn()->member_id ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/S', 403, '' );
		}
		
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Cancel purchase */
		if ( \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on )
		{
			foreach ( \IPS\core\extensions\nexus\Item\ClubMembership::getPurchases( \IPS\nexus\Customer::loggedIn(), $this->club->id ) as $purchase )
			{
				$purchase->cancelled = TRUE;
				$purchase->member->log( 'purchase', array( 'type' => 'cancel', 'id' => $purchase->id, 'name' => $purchase->name ) );
				$purchase->can_reactivate = FALSE;
				$purchase->save();
			}
		}
		
		/* Leave */
		$this->club->removeMember( \IPS\Member::loggedIn() );
		$this->club->recountMembers();
		$this->club->save();
		
		/* Delete the invitation */
		\IPS\Db::i()->delete( 'core_notifications', array( '`member`=? AND notification_app=? AND notification_key=? and item_id=?', \IPS\Member::loggedIn()->member_id, 'core', 'club_invitation', $this->club->id ) );
		\IPS\Member::loggedIn()->recountNotifications();

		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ) );
	}

	/**
	 * Cancel Join Request
	 *
	 * @return	void
	 */
	protected function cancelJoin()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();

		/* Leave */
		$this->club->removeMember( \IPS\Member::loggedIn() );
		$this->club->recountMembers();
		$this->club->save();

		/* Update notification counts */
		\IPS\Db::i()->delete( 'core_notifications', array( 'notification_key=? and item_id=? and extra=?', 'club_request', $this->club->id, json_encode( array( \IPS\Member::loggedIn()->member_id ) ) ) );
		foreach( $this->club->members( array( 'moderator', 'leader' ), 250, NULL, 2 ) as $member )
		{
			$member = \IPS\Member::constructFromData( $member );
			$member->recountNotifications();
		}

		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url(), 'clubs_request_cancelled' );
	}

	/**
	 * Renew
	 *
	 * @return	void
	 */
	protected function renew()
	{
		/* Can we renew? */
		if ( !\in_array( $this->club->memberStatus( \IPS\Member::loggedIn() ), array( \IPS\Member\Club::STATUS_EXPIRED, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR ) ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/Y', 403, '' );
		}
		
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Find the purchase */
		foreach ( \IPS\core\extensions\nexus\Item\ClubMembership::getPurchases( \IPS\nexus\Customer::loggedIn(), $this->club->id ) as $purchase )
		{
			\IPS\Output::i()->redirect( $purchase->url()->setQueryString( array( 'do' => 'renew', 'cycles' => 1 ) )->csrf() );
		}
		
		/* Couldn't find it? */
		\IPS\Output::i()->error( 'no_module_permission', '2C350/Z', 500, '' );
	}
	
	/**
	 * Invite Members
	 *
	 * @return	void
	 */
	protected function invite()
	{
		if ( !$this->club->canInvite() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/5', 403, '' );
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'club_send_invitations' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Member( 'members', NULL, TRUE, array( 'multiple' => NULL ) ) );
		if ( $this->club->isPaid() and $this->club->isLeader() )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'club_invite_waive_fee', FALSE ) );
			if ( $this->club->renewal_price )
			{
				\IPS\Member::loggedIn()->language()->words['club_invite_waive_fee_desc'] = \IPS\Member::loggedIn()->language()->addToStack('club_invite_waive_fee_renewal');
			}
		}
		
		if ( $values = $form->values() )
		{
			$status = \IPS\Member\Club::STATUS_INVITED;
			if ( $this->club->isPaid() and $this->club->isLeader() and $values['club_invite_waive_fee'] )
			{
				$status = \IPS\Member\Club::STATUS_INVITED_BYPASSING_PAYMENT;
			}

			foreach ( $values['members'] as $member )
			{
				if ( $member instanceof \IPS\Member )
				{
					$memberStatus = $this->club->memberStatus( $member );
					if ( !$memberStatus or \in_array( $memberStatus, array( \IPS\Member\Club::STATUS_INVITED, \IPS\Member\Club::STATUS_REQUESTED, \IPS\Member\Club::STATUS_DECLINED, \IPS\Member\Club::STATUS_BANNED ) ) )
					{
						$this->club->addMember( $member, $status, TRUE, NULL, \IPS\Member::loggedIn(), TRUE );
					}
				}
			}
			$this->club->sendInvitation( \IPS\Member::loggedIn(), $values['members'] );
			
			\IPS\Output::i()->redirect( $this->club->url(), 'club_notifications_sent' );
		}
		
		\IPS\Output::i()->title = $this->club->name;
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Re-invite a banned memer
	 *
	 * @return	void
	 */
	protected function reInvite()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/J', 403, '' );
		}
		
		/* Check the member needs to be reinvited */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( !\in_array( $this->club->memberStatus( $member ), array( \IPS\Member\Club::STATUS_DECLINED, \IPS\Member\Club::STATUS_BANNED ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/K', 403, '' );
		}
		
		/* Add them */
		$this->club->removeMember( $member );
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_INVITED, FALSE, NULL, \IPS\Member::loggedIn() );
		$this->club->recountMembers();
		
		/* Notify the member */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_invitation', $this->club, array( $this->club, \IPS\Member::loggedIn() ), array( 'invitedBy' => \IPS\Member::loggedIn()->member_id ) );
		$notification->recipients->attach( $member );
		$notification->send();
				
		/* If other requests are banned, send us back, otherwise take us to the main member list */
		$url = $this->club->url()->setQueryString( 'do', 'members' );
		if ( \count( $this->club->members( array( \IPS\Member\Club::STATUS_DECLINED, \IPS\Member\Club::STATUS_BANNED ) ) ) )
		{
			\IPS\Output::i()->redirect( $url->SetQueryString( 'filter', \IPS\Member\Club::STATUS_BANNED ) );
		}
		else
		{
			\IPS\Output::i()->redirect( $url );
		}
	}
	
	/**
	 * Feature
	 *
	 * @return	void
	 */
	protected function feature()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !\IPS\Member::loggedIn()->modPermission('can_manage_featured_clubs') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/Q', 403, '' );
		}
		
		/* Feature */
		$this->club->featured = TRUE;
		$this->club->save();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url() );
	}
	
	/**
	 * Unfeature
	 *
	 * @return	void
	 */
	protected function unfeature()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !\IPS\Member::loggedIn()->modPermission('can_manage_featured_clubs') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/Q', 403, '' );
		}
		
		/* Unfeature */
		$this->club->featured = FALSE;
		$this->club->save();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url() );
	}
	
	/**
	 * Approve/Deny
	 *
	 * @return	void
	 */
	protected function approve()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !\IPS\Member::loggedIn()->modPermission('can_manage_featured_clubs') or $this->club->approved or !\IPS\Settings::i()->clubs_require_approval )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/U', 403, '' );
		}
		
		/* Approve... */
		if ( \IPS\Request::i()->approved )
		{
			$this->club->approved = TRUE;
			$this->club->save();
			$this->club->onApprove();
			
			\IPS\Output::i()->redirect( $this->club->url() );
		}
		
		/* ... or delete */
		else
		{
			$this->club->delete();
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ) );
		}
	}
	
	/**
	 * Create a node
	 *
	 * @return	void
	 */
	protected function nodeForm()
	{
		/* Permission check */
		$class = \IPS\Request::i()->type;
		if ( !$this->club->isLeader() or !\in_array( $class, \IPS\Member\Club::availableNodeTypes( \IPS\Member::loggedIn() ) ) or ( \IPS\Settings::i()->clubs_require_approval and !$this->club->approved ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/T', 403, '' );
		}
		
		/* Load if editing */
		if ( isset( \IPS\Request::i()->node ) )
		{
			try
			{
				$node = $class::load( (int) \IPS\Request::i()->node );
				$club = $node->club();
				if ( !$club or $club->id !== $this->club->id )
				{
					throw new \Exception;
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C350/O', 404, '' );
			}
		}
		else
		{
			$node = new $class;
		}
		
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		$node->clubForm( $form, $this->club );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$node->saveClubForm( $this->club, $values );
			\IPS\Output::i()->redirect( $node->url() );
		}
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('club_create_node');
		\IPS\Output::i()->output = \IPS\Request::i()->isAjax() ? $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) ) : $form;
	}
	
	/**
	 * Delete a node
	 *
	 * @return	void
	 */
	protected function nodeDelete()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/AA', 403, '' );
		}
		
		/* Load */
		$class = \IPS\Request::i()->type;

		try
		{
			if ( !\in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				throw new \Exception;
			}

			$node = $class::load( (int) \IPS\Request::i()->node );
			$club = $node->club();
			if ( !$club or $club->id !== $this->club->id )
			{
				throw new \Exception;
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/AB', 404, '' );
		}
		
		/* Permission check */
		$itemClass = $node::$contentItemClass;
		if ( !$node->modPermission( 'delete', \IPS\Member::loggedIn(), $itemClass ) and $itemClass::contentCount( $node, TRUE, TRUE, TRUE, 1 ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/AC', 403, '' );
		}
		
		/* Delete */
		\IPS\Db::i()->delete( 'core_clubs_node_map', array( 'club_id=? AND node_class=? AND node_id=?', $club->id, $class, $node->_id ) );
		$node->deleteOrMoveFormSubmit( array() );
		
		/* Redirect */
		\IPS\Output::i()->redirect( $club->url() );
	}
	
	/**
	 * Add a page
	 *
	 * @return	void
	 */
	public function addPage()
	{
		/* Init form */
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		\IPS\Member\Club\Page::form( $form, $this->club );
		
		/* Form Submission */
		if ( $values = $form->values() )
		{
			$page = new \IPS\Member\Club\Page;
			$page->formatFormValues( $values );
			$page->save();
			
			\IPS\File::claimAttachments( 'club-page-new', $page->id );
			
			\IPS\Output::i()->redirect( $page->url() );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( "add_page_to_club", NULL, array( "sprintf" => array( $this->club->name ) ) );
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/* !Cover Photo */
	
	/**
	 * Get Cover Photo Storage Extension
	 *
	 * @return	string
	 */
	protected function _coverPhotoStorageExtension()
	{
		return 'core_Clubs';
	}
	
	/**
	 * Set Cover Photo
	 *
	 * @param	\IPS\Helpers\CoverPhoto	$photo	New Photo
	 * @return	void
	 */
	protected function _coverPhotoSet( \IPS\Helpers\CoverPhoto $photo )
	{
		$this->club->cover_photo = (string) $photo->file;
		$this->club->cover_offset = (int) $photo->offset;
		$this->club->save();
	}
	
	/**
	 * Get Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	protected function _coverPhotoGet()
	{
		return $this->club->coverPhoto();
	}
	
	/**
	 * Embed
	 *
	 * @return	void
	 */
	protected function embed()
	{
		$title = \IPS\Member::loggedIn()->language()->addToStack( 'error_title' );
		
		try
		{
			$club = \IPS\Member\Club::load( \IPS\Request::i()->id );
			if ( !$club->canView() )
			{
				$output = \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedNoPermission();
			}
			else
			{
				$output = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->embedClub( $club );
			}
		}
		catch( \Exception $e )
		{
			$output = \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedUnavailable();
		}
		
		/* Make sure our iframe contents get the necessary elements and JS */
		$js = array(
			\IPS\Output::i()->js( 'js/commonEmbedHandler.js', 'core', 'interface' ),
			\IPS\Output::i()->js( 'js/internalEmbedHandler.js', 'core', 'interface' )
		);
		\IPS\Output::i()->base = '_parent';

		/* We need to keep any embed.css files that have been specified so that we can re-add them after we re-fetch the css framework */
		$embedCss = array();
		foreach( \IPS\Output::i()->cssFiles as $cssFile )
		{
			if( \mb_stristr( $cssFile, 'embed.css' ) )
			{
				$embedCss[] = $cssFile;
			}
		}

		/* We need to reset the included CSS files because by this point the responsive files are already in the output CSS array */
		\IPS\Output::i()->cssFiles = array();
		\IPS\Output::i()->responsive = FALSE;
		\IPS\Dispatcher\Front::baseCss();
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, $embedCss );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/embeds.css', 'core', 'front' ) );

		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->embedInternal( $output, $js ), 200, 'text/html' );
	}

	/**
	 * Save the club menu order
	 *
	 * @return	void
	 */
	protected function saveMenu()
	{
		\IPS\Session::i()->csrfCheck();

		/* Permission check */
		if ( !$this->club->canManageNavigation() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '1C350/O', 403, '' );
		}

		if ( \is_array( \IPS\Request::i()->tabOrder ) )
		{
			$tabs =  \IPS\Request::i()->tabOrder;
			$this->club->menu_tabs = json_encode( $tabs );
			$this->club->save();
		}

		\IPS\Output::i()->json( 'ok' );
	}

	/**
	 * Set some default meta tags for the club
	 *
	 * @return void
	 */
	protected function _setDefaultMetaTags()
	{
		if( $this->club->cover_photo )
		{
			\IPS\Output::i()->metaTags['og:image'] = \IPS\File::get( 'core_Clubs', $this->club->cover_photo )->url;
		}
		
		\IPS\Output::i()->metaTags['og:title'] = $this->club->name;

		if( $this->club->about )
		{
			\IPS\Output::i()->metaTags['description'] = $this->club->about;
			\IPS\Output::i()->metaTags['og:description'] = $this->club->about;
		}
	}

	/**
	 * Update Existing Purchases
	 *
	 * @return	void
	 */
	public function updateExisting()
	{
		\IPS\Session::i()->csrfCheck();

		/* Make sure logged-in user has permission */
		if ( !( $this->club->owner and $this->club->owner->member_id == \IPS\Member::loggedIn()->member_id ) and !\IPS\Member::loggedIn()->modPermission('can_access_all_clubs') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '3C350/Q', 403, '' );
		}

		$changes = json_decode( \IPS\Request::i()->changes, TRUE );

		\IPS\Task::queue( 'core', 'UpdateClubRenewals', array( 'changes' => $changes, 'club' => $this->club->id ), 5 );

		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url(), 'saved' );
	}
}
