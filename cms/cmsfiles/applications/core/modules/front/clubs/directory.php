<?php
/**
 * @brief		Clubs List
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Feb 2017
 */

namespace IPS\core\modules\front\clubs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clubs List
 */
class _directory extends \IPS\Dispatcher\Controller
{

	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 * @example array(
	'community_area' => array( 'value' => 'search', 'odkUpdate' => 'true' )
	 * )
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
		/* Permission check */
		if ( !\IPS\Settings::i()->clubs )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C349/2', 403, '' );
		}
		
		/* CSS */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs.css', 'core', 'front' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs_responsive.css', 'core', 'front' ) );
		}

		/* JS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_clubs.js', 'core', 'front' ) );
		
		/* Location for online list */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), array(), 'loc_clubs_directory' );

		/* Pass up */
		parent::execute();
	}
	
	/**
	 * List
	 *
	 * @return	void
	 */
	protected function manage()
	{	
		$baseUrl = \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' );

		/* Display */
		$view = \IPS\Settings::i()->clubs_default_view;
		if ( \IPS\Settings::i()->clubs_allow_view_change )
		{
			if ( isset( \IPS\Request::i()->view ) and \in_array( \IPS\Request::i()->view, array( 'grid', 'list' ) ) )
			{
				\IPS\Session::i()->csrfCheck();
				\IPS\Request::i()->setCookie( 'clubs_view', \IPS\Request::i()->view, \IPS\DateTime::create()->add( new \DateInterval('P1Y' ) ) );
				$view = \IPS\Request::i()->view;

				\IPS\Output::i()->redirect( $baseUrl );
			}
			elseif ( isset( \IPS\Request::i()->cookie['clubs_view'] ) and \in_array( \IPS\Request::i()->cookie['clubs_view'], array( 'grid', 'list' ) ) )
			{
				$view = \IPS\Request::i()->cookie['clubs_view'];
			}
		}
		
		/* All Clubs: Sort */
		$sortOption = \IPS\Settings::i()->clubs_default_sort;
		if ( isset( \IPS\Request::i()->sort ) and \in_array( \IPS\Request::i()->sort, array( 'last_activity', 'members', 'content', 'created', 'name' ) ) )
		{
			$sortOption = \IPS\Request::i()->sort;

			$baseUrl = $baseUrl->setQueryString( 'sort', $sortOption );
		}
		
		/* All Clubs: Filters */
		$filters = array();
		$mineOnly = FALSE;
		$extraWhere = NULL;
		if ( isset( \IPS\Request::i()->filter ) and \IPS\Request::i()->filter === 'mine' AND \IPS\Member::loggedIn()->member_id )
		{
			$mineOnly = TRUE;
			$baseUrl = $baseUrl->setQueryString( 'filter', 'mine' );
		}
		if ( isset( \IPS\Request::i()->type ) and \in_array( \IPS\Request::i()->type, array( 'free', 'paid' ) ) )
		{
			$baseUrl = $baseUrl->setQueryString( 'type', \IPS\Request::i()->type );
			
			if ( \IPS\Request::i()->type === 'free' )
			{
				$extraWhere[] = array( 'fee IS NULL' );
			}
			else
			{
				$extraWhere[] = array( 'fee IS NOT NULL' );
			}
		}
		foreach ( \IPS\Member\Club\CustomField::fields() as $field )
		{
			$k = "f{$field->id}";
			if ( $field->filterable and isset( \IPS\Request::i()->$k ) )
			{
				switch ( $field->type )
				{
					case 'Checkbox':
					case 'YesNo':
						if ( \in_array( \IPS\Request::i()->$k, array( 0, 1 ) ) )
						{
							$filters[ $field->id ] = \IPS\Request::i()->$k;
							$baseUrl = $baseUrl->setQueryString( 'f' . $field->id, \IPS\Request::i()->$k );
						}
						break;
						
					case 'CheckboxSet':
					case 'Radio':
					case 'Select':
						$options = json_decode( $field->extra, TRUE );
						foreach ( \IPS\Request::i()->$k as $id )
						{
							if ( isset( $options[ $id ] ) )
							{
								if( $field->type == 'CheckboxSet' )
								{
									$filters[ $field->id ][ $id ] = $id;
								}
								else
								{
									$filters[ $field->id ][ $id ] = $options[ $id ];
								}
							}
						}
						$baseUrl = $baseUrl->setQueryString( 'f' . $field->id, array_keys( $filters[ $field->id ] ) );
						
						break;
				}
			}
		}
		
		/* Get Featured Clubs */
		$featuredClubs = \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), NULL, 'RAND()', FALSE, array(), 'featured=1' );
		
		/* Get All Clubs */
		$perPage = 24;
		$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;
		if( $page < 1 )
		{
			$page = 1;
		}

		$clubsCount	= \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), array( ( $page - 1 ) * $perPage, $perPage ), $sortOption, $mineOnly, $filters, $extraWhere, TRUE );
		$allClubs	= \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), array( ( $page - 1 ) * $perPage, $perPage ), $sortOption, $mineOnly, $filters, $extraWhere );
		$pagination	= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl, ceil( $clubsCount / $perPage ), $page, $perPage );
		
		/* Get my clubs and invites */
		$myClubsActivity = NULL;
		$myClubsInvites = NULL;
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$myClubsActivity = new \IPS\core\Stream;
			$myClubsActivity = $myClubsActivity->query()->filterByClub( \IPS\Member::loggedIn()->clubs() )->setLimit(6)->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_UPDATED )->search();
			$myClubsInvites = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_clubs_memberships', array( 'member_id=? and ( status=? or status=? )', \IPS\Member::loggedIn()->member_id, \IPS\Member\Club::STATUS_REQUESTED, \IPS\Member\Club::STATUS_INVITED )
								)->join(
										'core_clubs',
										"core_clubs_memberships.club_id=core_clubs.id"
			), '\IPS\Member\Club' );
		}
		
		/* Build Map */
		$mapMarkers = NULL;
		if ( \IPS\GeoLocation::enabled() )
		{
			$mapMarkers = array();
			
			$where = array( array( 'location_lat IS NOT NULL' ) );
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				$where[] = array( "type<>?", \IPS\Member\Club::TYPE_PRIVATE );
			}
			elseif ( !\IPS\Member::loggedIn()->modPermission('can_access_all_clubs') )
			{
				$where[] = array( "( type<>? OR status IN('" . \IPS\Member\Club::STATUS_MEMBER .  "','" . \IPS\Member\Club::STATUS_MODERATOR . "','" . \IPS\Member\Club::STATUS_LEADER . "','" . \IPS\Member\Club::STATUS_EXPIRED . "','" . \IPS\Member\Club::STATUS_EXPIRED_MODERATOR . "') )", \IPS\Member\Club::TYPE_PRIVATE );
			}
			
			$select = \IPS\Db::i()->select( array( 'id', 'name', 'location_lat', 'location_long' ), 'core_clubs', $where );
			if ( \IPS\Member::loggedIn()->member_id )
			{
				$select->join( 'core_clubs_memberships', array( 'club_id=id AND member_id=?', \IPS\Member::loggedIn()->member_id ) );
			}
			
			foreach ( $select as $club )
			{
				$mapMarkers[ $club['id'] ] = array(
					'lat'	=> \floatval( $club['location_lat'] ),
					'long'	=> \floatval( $club['location_long'] ),
					'title'	=> $club['name']
				);
			}
		}

		if ( \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate('clubs')->myClubsSidebar( \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), 10, 'last_activity', TRUE ), $myClubsActivity, $myClubsInvites );

			/* Prime the cache */
			$allClubs = iterator_to_array( $allClubs );
			
			if( \count( $allClubs ) )
			{
				foreach( $allClubs as $clubId => $clubData )
				{
					if( !isset( $allClubs[ $clubId ]->memberStatuses[ \IPS\Member::loggedIn()->member_id ] ) )
					{
						$allClubs[ $clubId ]->memberStatuses[ \IPS\Member::loggedIn()->member_id ] = NULL;
					}
				}

				foreach( \IPS\Db::i()->select( '*', 'core_clubs_memberships', array( 'member_id=? AND ' . \IPS\Db::i()->in( 'club_id', array_keys( $allClubs ) ), \IPS\Member::loggedIn()->member_id ) ) as $clubMembership )
				{
					$allClubs[ $clubMembership['club_id'] ]->memberStatuses[ \IPS\Member::loggedIn()->member_id ] = $clubMembership['status'];
				}
			}
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clubs')->directory( $featuredClubs, $allClubs, $pagination, $baseUrl, $sortOption, $myClubsActivity, $mapMarkers, $view );
	}
	
	/**
	 * Filter Form
	 *
	 * @return	void
	 */
	protected function filters()
	{		
		$fields = \IPS\Member\Club\CustomField::roots();
		
		$form = new \IPS\Helpers\Form('filter', 'filter');
		
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_filter_type', isset( \IPS\Request::i()->filter ) ? \IPS\Request::i()->filter : 'all', TRUE, array( 'options' => array(
				'all'	=> 'all_clubs',
				'mine'	=> 'my_clubs'
			) ) ) );
		}
		
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->clubs_paid_on )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_membership_fee', isset( \IPS\Request::i()->type ) ? \IPS\Request::i()->type : 'all', TRUE, array( 'options' => array(
				'all'	=> 'all_clubs',
				'free'	=> 'club_membership_free',
				'paid'	=> 'club_membership_paid'
			) ) ) );
		}
		
		foreach ( $fields as $field )
		{
			if ( $field->filterable )
			{
				$k = "f{$field->id}";
				switch ( $field->type )
				{
					case 'Checkbox':
					case 'YesNo':
						$input = new \IPS\Helpers\Form\CheckboxSet( 'field_' . $field->id, isset( \IPS\Request::i()->$k ) ? \IPS\Request::i()->$k : array( 1, 0 ), FALSE, array( 'options' => array(
							1			=> 'yes',
							0			=> 'no',
						) ) );
						$input->label = $field->_title;
						$form->add( $input );
						break;
						
					case 'CheckboxSet':
					case 'Radio':
					case 'Select':
						$options = json_decode( $field->extra, TRUE );
						$input = new \IPS\Helpers\Form\CheckboxSet( 'field_' . $field->id, isset( \IPS\Request::i()->$k ) ? \IPS\Request::i()->$k : array_keys( $options ), FALSE, array( 'options' => $options ) );
						$input->label = $field->_title;
						$form->add( $input );
						break;
				}
			}
		}
		
		if ( $values = $form->values() )
		{
			$url = \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' );
			
			if ( \IPS\Member::loggedIn()->member_id and $values['club_filter_type'] === 'mine' )
			{
				$url = $url->setQueryString( 'filter', 'mine' );
			}
			
			if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->clubs_paid_on and $values['club_membership_fee'] !== 'all' )
			{
				$url = $url->setQueryString( 'type', $values['club_membership_fee'] );
			}
			
			foreach ( $fields as $field )
			{
				if ( $field->filterable )
				{					
					switch ( $field->type )
					{
						case 'Checkbox':
						case 'YesNo':
							if ( \count( $values[ 'field_' . $field->id ] ) === 1 )
							{
								$url = $url->setQueryString( 'f' . $field->id, array_pop( $values[ 'field_' . $field->id ] ) );
							}
							break;
							
						case 'CheckboxSet':
						case 'Radio':
						case 'Select':
							$options = json_decode( $field->extra, TRUE );
							if ( \count( $values[ 'field_' . $field->id ] ) > 0 and \count( $values[ 'field_' . $field->id ] ) < \count( $options ) )
							{
								$url = $url->setQueryString( 'f' . $field->id, $values[ 'field_' . $field->id ] );
							}
							break;
					}
				}
			}
			
			\IPS\Output::i()->redirect( $url );
		}
		
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
	 * Create
	 *
	 * @return	void
	 */
	protected function create()
	{
		$availableTypes = array();
		if ( \IPS\Member::loggedIn()->member_id ) // Guests can't create any type of clubs
		{
			foreach ( explode( ',', \IPS\Member::loggedIn()->group['g_create_clubs'] ) as $type )
			{
				if ( $type !== '' )
				{
					$availableTypes[ $type ] = 'club_type_' . $type;
				}
			}
		}
		if ( !$availableTypes )
		{
			\IPS\Output::i()->error( \IPS\Member::loggedIn()->member_id ? 'no_module_permission' : 'no_module_permission_guest', '2C349/1', 403, '' );
		}
		
		if ( \IPS\Member::loggedIn()->group['g_club_limit'] )
		{
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs', array( 'owner=?', \IPS\Member::loggedIn()->member_id ) )->first() >= \IPS\Member::loggedIn()->group['g_club_limit'] )
			{
				\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'too_many_clubs', FALSE, array( 'pluralize' => array( \IPS\Member::loggedIn()->group['g_club_limit'] ) ) ), '2C349/3', 403, '' );
			}
		}
		
		$club = new \IPS\Member\Club;
		if ( $form = $club->form( FALSE, TRUE, $availableTypes ) )
		{
			if( $values = $form->values() )
			{
				$club->processForm( $values, FALSE, TRUE, $availableTypes );

				\IPS\Output::i()->redirect( $club->url() );
			}

			$form->class = 'ipsForm_vertical';
		}
		else
		{
			if ( !$club->approved and \IPS\Member::loggedIn()->modPermission('can_access_all_clubs') )
			{
				$club->approved = TRUE;
				$club->save();
			}
			
			if( $club->approved )
			{
				\IPS\Member::loggedIn()->achievementAction( 'core', 'NewClub', $club );
			}
			
			\IPS\Output::i()->redirect( $club->url() );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('create_club');
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'clubs' )->create( $form );
		}
	}
}