<?php
/**
 * @brief		Venue Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		2017
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
class _Venue extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'calendar_venues';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'venue_';

	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';

	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'venues';

	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'calendar_venue';

	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=calendar&module=calendar&controller=venue&id=';

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
		'module'	=> 'venues',
		'prefix' => 'venues_'
	);

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'calendar_venue_';

	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';

	/**
	 * @brief	[Node] Moderator Permission
	 */
	public static $modPerm = 'calendar_venues';

	/**
	 * @brief	[Node] Maximum results to display at a time in any node helper form elements. Useful for user-submitted node types when there may be a lot. NULL for no limit.
	 */
	public static $maxFormHelperResults = 2000;

	/**
	 * @brief   The class of the ACP \IPS\Node\Controller that manages this node type
	 */
	protected static $acpController = "IPS\\calendar\\modules\\admin\\calendars\\venues";

	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		return (bool) $this->enabled;
	}

	/**
	 * [Node] Set whether or not this node is enabled
	 *
	 * @param	bool|int	$enabled	Whether to set it enabled or disabled
	 * @return	void
	 */
	protected function set__enabled( $enabled )
	{
		$this->enabled	= $enabled;
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_title_seo()
	{
		if( !$this->_data['title_seo'] )
		{
			$this->title_seo	= \IPS\Http\Url\Friendly::seoTitle( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'calendar_venue_' . $this->id ) );
			$this->save();
		}

		return $this->_data['title_seo'] ?: \IPS\Http\Url\Friendly::seoTitle( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'calendar_venue_' . $this->id ) );
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Translatable( 'venue_title', NULL, TRUE, array( 'app' => 'calendar', 'key' => ( $this->id ? "calendar_venue_{$this->id}" : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'venue_description', NULL, FALSE, array( 'app' => 'calendar', 'key' => ( $this->id ? "calendar_venue_{$this->id}_desc" : NULL ), 'editor' => array( 'app' => 'calendar', 'key' => 'Venue', 'autoSaveKey' => ( $this->id ? "calendar-venue-{$this->id}" : "calendar-new-venue" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Address( 'venue_address', $this->id ? \IPS\GeoLocation::buildFromJson( $this->address ) : NULL, TRUE, array( 'minimize' => ( $this->id and $this->address ) ? FALSE : TRUE, 'requireFullAddress' => FALSE, 'preselectCountry' => FALSE ), NULL, NULL, NULL, 'venue_address' ) );
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
			\IPS\File::claimAttachments( 'calendar-new-venue', $this->id, NULL, 'description', TRUE );
		}
		else
		{
			foreach ( \IPS\Lang::languages() as $lang )
			{
				\IPS\Request::i()->setClearAutosaveCookie( "calendar-venue-{$this->id}{$lang->id}" );
			}
		}

		if( isset( $values['venue_title'] ) )
		{
			\IPS\Lang::saveCustom( 'calendar', 'calendar_venue_' . $this->id, $values['venue_title'] );
			$values['title_seo']	= \IPS\Http\Url\Friendly::seoTitle( $values['venue_title'][ \IPS\Lang::defaultLanguage() ] );

			unset( $values['venue_title'] );
		}

		if( isset( $values['venue_description'] ) )
		{
			\IPS\Lang::saveCustom( 'calendar', 'calendar_venue_' . $this->id .'_desc', $values['venue_description'] );
			unset( $values['venue_description'] );
		}

		$values['venue_address'] = ( $values['venue_address'] !== NULL ) ? json_encode( $values['venue_address'] ) : NULL;

		return $values;
	}

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
		\IPS\Lang::deleteCustom( 'calendar', 'calendar_venue_' . $this->id );
		\IPS\Lang::deleteCustom( 'calendar', 'calendar_venue_' . $this->id . '_desc' );

		return parent::delete();
	}

	/**
	 * Return the map for the venue
	 *
	 * @param	int		$width	Width
	 * @param	int		$height	Height
	 * @return	string
	 * @note	\BadMethodCallException can be thrown if the google maps integration is shut off - don't show any error if that happens.
	 */
	public function map( $width, $height )
	{
		if( $this->address )
		{
			try
			{
				return \IPS\GeoLocation::buildFromJson( $this->address )->map()->render( $width, $height );
			}
			catch( \BadMethodCallException $e ){}
		}

		return '';
	}

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int						id				ID number
	 * @apiresponse	string					title			Title
	 * @apiresponse	string					description		Description
	 * @apiresponse	\IPS\GeoLocation		address		The address
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'			=> $this->id,
			'title'			=> $this->_title,
			'description'	=> \IPS\Member::loggedIn()->language()->addToStack('calendar_venue_' . $this->id . '_desc', NULL, array( 'removeLazyLoad' => true ) ),
			'address'		=> \IPS\GeoLocation::buildFromJson( $this->address )->apiOutput( $authorizedMember )
		);
	}
}