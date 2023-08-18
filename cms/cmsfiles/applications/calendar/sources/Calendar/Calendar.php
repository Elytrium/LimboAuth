<?php
/**
 * @brief		Calendar Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		18 Dec 2013
 */

namespace IPS\calendar;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Calendar Node
 */
class _Calendar extends \IPS\Node\Model implements \IPS\Node\Permissions
{
	use \IPS\Content\ClubContainer, \IPS\Content\ViewUpdates;
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'calendar_calendars';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'cal_';
		
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'calendars';
			
	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 	array(
	 		'app'		=> 'core',				// The application key which holds the restrictrions
	 		'module'	=> 'foo',				// The module key which holds the restrictions
	 		'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 			'add'			=> 'foo_add',
	 			'edit'			=> 'foo_edit',
	 			'permissions'	=> 'foo_perms',
	 			'delete'		=> 'foo_delete'
	 		),
	 		'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 		'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
		'app'		=> 'calendar',
		'module'	=> 'calendars',
		'prefix' => 'calendars_'
	);
	
	/** 
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'calendar';
	
	/** 
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'calendar';
	
	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array(
		'view' 				=> 'view',
		'read'				=> 2,
		'add'				=> 3,
		'reply'				=> 4,
		'review'			=> 7,
		'askrsvp'			=> 5,
		'rsvp'				=> 6,
	);

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'calendar_calendar_';
	
	/** 
	 * @brief	[Node] Moderator Permission
	 */
	public static $modPerm = 'calendar_calendars';
	
	/**
	 * @brief	Content Item Class
	 */
	public static $contentItemClass = 'IPS\calendar\Event';

	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = 'calperm_';

	/**
	 * @brief	Bitwise values for cal_bitoptions field
	 */
	public static $bitOptions = array(
		'calendar_bitoptions' => array(
			'calendar_bitoptions' => array(
				'bw_disable_tagging'		=> 1,
				'bw_disable_prefixes'		=> 2
			)
		)
	);

	/**
	 * @brief   The class of the ACP \IPS\Node\Controller that manages this node type
	 */
	protected static $acpController = "IPS\\calendar\\modules\\admin\\calendars\\calendars";
		
	/**
	 * [Node] Set whether or not this node is enabled
	 *
	 * @param	bool|int	$enabled	Whether to set it enabled or disabled
	 * @return	void
	 */
	protected function set__enabled( $enabled )
	{
	}

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_title_seo()
	{
		if( !$this->_data['title_seo'] )
		{
			$this->title_seo	= \IPS\Http\Url\Friendly::seoTitle( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'calendar_calendar_' . $this->id ) );
			$this->save();
		}

		return $this->_data['title_seo'] ?: \IPS\Http\Url\Friendly::seoTitle( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'calendar_calendar_' . $this->id ) );
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Translatable( 'cal_title', NULL, TRUE, array( 'app' => 'calendar', 'key' => ( $this->id ? "calendar_calendar_{$this->id}" : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Color( 'cal_color', $this->id ? $this->color : $this->_generateColor(), TRUE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'cal_moderate', $this->id ? $this->moderate : FALSE, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'cal_allow_comments', $this->id ? $this->allow_comments : TRUE, FALSE, array( 'togglesOn' => array( 'cal_comment_moderate' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'cal_comment_moderate', $this->id ? $this->comment_moderate : FALSE, FALSE, array(), NULL, NULL, NULL, 'cal_comment_moderate' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'cal_allow_reviews', $this->id ? $this->allow_reviews : FALSE, FALSE, array( 'togglesOn' => array( 'cal_review_moderate' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'cal_review_moderate', $this->id ? $this->review_moderate : FALSE, FALSE, array(), NULL, NULL, NULL, 'cal_review_moderate' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'cal_allow_anonymous', $this->id ? $this->allow_anonymous : FALSE, FALSE, array(), NULL, NULL, NULL, 'cal_allow_anonymous' ) );

		if ( \IPS\Settings::i()->tags_enabled )
		{
			$form->addHeader( 'tags' );
			$form->add( new \IPS\Helpers\Form\YesNo( 'bw_disable_tagging', !$this->calendar_bitoptions['bw_disable_tagging'], FALSE, array( 'togglesOn' => array( 'bw_disable_prefixes' ) ), NULL, NULL, NULL, 'bw_disable_tagging' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'bw_disable_prefixes', !$this->calendar_bitoptions['bw_disable_prefixes'], FALSE, array(), NULL, NULL, NULL, 'bw_disable_prefixes' ) );
		}
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( !$this->id )
		{
			$this->save();
		}

		if( isset( $values['cal_title'] ) )
		{
			\IPS\Lang::saveCustom( 'calendar', 'calendar_calendar_' . $this->id, $values['cal_title'] );
			$values['title_seo']	= \IPS\Http\Url\Friendly::seoTitle( $values['cal_title'][ \IPS\Lang::defaultLanguage() ] );

			unset( $values['cal_title'] );
		}

		/* Bitwise */
		foreach ( array( 'bw_disable_tagging', 'bw_disable_prefixes' ) as $k )
		{
			if( isset( $values[ $k ] ) )
			{
				$values['calendar_bitoptions'][ $k ] = !$values[ $k ];
				unset( $values[ $k ] );
			}
		}

		return $values;
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;
	
	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=calendar&module=calendar&controller=view&id=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'calendar_calendar';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'title_seo';

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Db::i()->delete( 'calendar_import_feeds', array( 'feed_calendar_id=?', $this->id ) );

		return parent::delete();
	}

	/**
	 * @brief Default colors
	 */
	protected static $colors	= array(
		'#6E4F99',		// Purple
		'#4F994F',		// Green
		'#4F7C99',		// Blue
		'#F3F781',		// Yellow
		'#DF013A',		// Red
		'#FFBF00',		// Orange
	);

	/**
	 * Grab the next available color
	 *
	 * @return	string
	 */
	public function _generateColor()
	{
		foreach( static::$colors as $color )
		{
			foreach( static::roots( NULL ) as $calendar )
			{
				if( mb_strtolower( $color ) == mb_strtolower( $calendar->color ) )
				{
					continue 2;
				}
			}

			return $color;
		}

		/* If we're still here, all of our pre-defined codes are used...generate something random */
		return '#' . str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT ) . 
			str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT ) . 
			str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT );
	}

	/**
	 * Add the appropriate CSS to the page output
	 *
	 * @return void
	 */
	public static function addCss()
	{
		$output	= '';

		foreach( static::roots() as $calendar )
		{
			$output	.= "a.cEvents_style{$calendar->id}, .cEvents_style{$calendar->id} a, .cCalendarIcon.cEvents_style{$calendar->id} {
	background-color: {$calendar->color};
}\n";
		}

		\IPS\Output::i()->headCss	= \IPS\Output::i()->headCss . $output;
	}
	
	/* !Clubs */
	
	/**
	 * Get front-end language string
	 *
	 * @return	string
	 */
	public static function clubFrontTitle()
	{
		return 'calendars_sg';
	}
	
	/**
	 * Set form for creating a node of this type in a club
	 *
	 * @param	\IPS\Helpers\Form	$form	Form object
	 * @return	void
	 */
	public function clubForm( \IPS\Helpers\Form $form, \IPS\Member\Club $club )
	{
		$itemClass = static::$contentItemClass;
		$form->add( new \IPS\Helpers\Form\Text( 'club_node_name', $this->_id ? $this->_title : \IPS\Member::loggedIn()->language()->addToStack( $itemClass::$title . '_pl' ), TRUE, array( 'maxLength' => 255 ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'cal_allow_comments', $this->id ? $this->allow_comments : TRUE, FALSE, array( 'togglesOn' => array( 'cal_comment_moderate' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'cal_allow_reviews', $this->id ? $this->allow_reviews : FALSE, FALSE, array( 'togglesOn' => array( 'cal_review_moderate' ) ) ) );
		if( $club->type == 'closed' )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_node_public', $this->id ? $this->isPublic() : 0, TRUE, array( 'options' => array( '0' => 'club_node_public_no', '1' => 'club_node_public_view', '2' => 'club_node_public_participate' ) ) ) );
		}
	}
	
	/**
	 * Class-specific routine when saving club form
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @param	array				$values	Values
	 * @return	void
	 */
	public function _saveClubForm( \IPS\Member\Club $club, $values )
	{
		if ( $values['club_node_name'] )
		{
			$this->title_seo	= \IPS\Http\Url\Friendly::seoTitle( $values['club_node_name'] );
		}

		$this->allow_comments = $values['cal_allow_comments'];
		$this->allow_reviews = $values['cal_allow_reviews'];
	}
	
	/**
	 * Content was held for approval by container
	 * Allow node classes that can determine if content should be held for approval in individual nodes
	 *
	 * @param	string				$content	The type of content we are checking (item, comment, review).
	 * @param	\IPS\Member|NULL	$member		Member to check or NULL for currently logged in member.
	 * @return	bool
	 */
	public function contentHeldForApprovalByNode( string $content, ?\IPS\Member $member = NULL ): bool
	{
		/* If members group bypasses, then no. */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $member->group['g_avoid_q'] )
		{
			return FALSE;
		}
		
		switch( $content )
		{
			case 'item':
				return (bool) $this->moderate;
				break;
			
			case 'comment':
				return (bool) $this->comment_moderate;
				break;
			
			case 'review':
				return (bool) $this->comment_review;
				break;
		}
	}

	/**
	 * Get URL
	 *
	 * @return	\IPS\Http\Url
	 * @throws	\BadMethodCallException
	 */
	public function url()
	{
		$url = parent::url();

		if( \IPS\Settings::i()->calendar_default_view == 'overview' )
		{
			$url = $url->setQueryString( array( 'view' => 'month') );
		}

		return $url;
	}
}