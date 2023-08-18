<?php
/**
 * @brief		Badge Model (as in, a representation of a badge a member *can* earn, not a badge a particular member *has* earned)
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		22 Feb 2021
 */

namespace IPS\core\Achievements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Badge Model (as in, a representation of a badge a member *can* earn, not a badge a particular member *has* earned)
 */
class _Badge extends \IPS\Node\Model
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_badges';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'menu__core_achievements_badges';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'core_badges_';

	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 * @note	If using this, declare a static $multitonMap = array(); in the child class to prevent duplicate loading queries
	 */
	protected static $databaseIdFields = array('recognize');

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
		'app'		=> 'core',
		'module'	=> 'achievements',
		'prefix'	=> 'badges_',
		'all'		=> 'badges_manage',
	);

	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$obj = parent::constructFromData( $data, $updateMultitonStoreIfExists );

		if ( isset( $obj->recognize ) and ! empty( $obj->recognize ) )
		{
			try
			{
				$obj->recognize = \IPS\core\Achievements\Recognize::load( $obj->recognize );
			}
			catch( \Exception $e )
			{
				/* Problem loading, so reset to 0 */
				$obj->recognize = 0;
			}
		}

		if ( isset( $obj->rule ) and ! empty( $obj->rule ) )
		{
			$obj->awardDescription = $obj->awardDescription( $obj->rule, $obj->actor );
		}

		return $obj;
	}

	/**
	 * @var null|array
	 */
	static $badgeAwardLangBits = NULL;

	/**
	 * Show the public reason for this badge award
	 *
	 * @param int $ruleId
	 * @param $actor
	 * @return string|null
	 */
	public function awardDescription( int $ruleId, $actor ):? string
	{
		if ( static::$badgeAwardLangBits === NULL )
		{
			foreach( \IPS\Db::i()->select( 'word_key, word_default, word_custom', 'core_sys_lang_words', [ 'lang_id=? AND (word_key LIKE \'core_award_subject_badge_%\' OR word_key LIKE \'core_award_other_badge_%\')', \IPS\Member::loggedIn()->language()->id ] ) as $row )
			{
				static::$badgeAwardLangBits[ $row['word_key'] ] = $row['word_custom'] ?: $row['word_default'];
			}
		}

		return static::$badgeAwardLangBits['core_award_' . $actor . '_badge_' . $ruleId] ?? NULL;
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		/* Allow SVGs without the obscure hash removing the file extension */
		\IPS\File::$safeFileExtensions[] = 'svg';

		$form->add( new \IPS\Helpers\Form\Translatable( 'badge_name', NULL, TRUE, array( 'app' => 'core', 'key' => ( $this->id ? "core_badges_{$this->id}" : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'badge_image', $this->image ? \IPS\File::get( 'core_Badges', $this->image ) : NULL, TRUE, array( 'obscure' => TRUE, 'checkImage' => TRUE, 'allowedFileTypes' => array_merge( \IPS\Image::supportedExtensions(), ['svg'] ), 'storageExtension' => 'core_Badges', 'allowStockPhotos' => FALSE ), function( $val ) {
			if( !$val )
			{
				throw new \DomainException('achievements_bad_image');
			}

			/* Good luck with your fancy SVG */
			$ext = mb_substr( $val->originalFilename, ( mb_strrpos( $val->originalFilename, '.' ) + 1 ) );
			if( $ext !== 'svg' )
			{
				try
				{
					$image = \IPS\Image::create( $val->contents() );
				}
				catch ( \Exception $e )
				{
					throw new \DomainException( 'achievements_bad_image' );
				}
			}
		} ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'badge_manually_awarded', $this->manually_awarded ) );
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
		
		\IPS\Lang::saveCustom( 'core', "core_badges_{$this->id}", $values['badge_name'] );
		unset( $values['badge_name'] );
		
		$_values = $values;
		$values = array();
		foreach ( $_values as $k => $v )
		{
			if( mb_substr( $k, 0, 6 ) === 'badge_' )
			{
				$values[ mb_substr( $k, 6 ) ] = $v;
			}
			else
			{
				$values[ $k ]	= $v;
			}
		}
		
		return $values;
	}
	
	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe', the 'fa fa-' is added automatically so you do not need this here)
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return \IPS\File::get( 'core_Badges', $this->image );
	}
	
	/**
	 * Get badge HTML
	 *
	 * @param	string|null		$cssClass	Additional CSS class to apply
	 * @param	bool|null		$tooltip	Whether or not to apply a tooltip to the badge
	 * @return	string
	 */
	public function html( ?string $cssClass = NULL, ?bool $tooltip = TRUE, ?bool $showRare = FALSE ): string
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->badge( $this, $cssClass, $tooltip, $showRare );
	}
	
	/**
	 * Is this badge rare?
	 *
	 * @return bool
	 */
	public function isRare(): bool
	{
		if ( !\IPS\Settings::i()->rare_badge_percent )
		{
			return FALSE;
		}

		$stats = $this->getBadgeStats();

		return ( 100 / $stats['memberCount'] * $stats['badgeCount'][ $this->id ] ) < \IPS\Settings::i()->rare_badge_percent;
	}

	/**
	 * Return number of people who have this badge
	 *
	 * @param bool|null $roundUp Round number to nearest 100 to be less specific
	 * @return int
	 */
	public function ownedByCount( bool $roundUp=FALSE )
	{
		if ( $stats = $this->getBadgeStats() and isset( $stats['badgeCount'][ $this->id ] ) )
		{
			if ( $roundUp )
			{
				return ceil( $stats['badgeCount'][$this->id] / 100 ) * 100;
			}
			else
			{
				return $stats['badgeCount'][ $this->id ];
			}
		}

		return NULL;
	}

	/**
	 * Get statistics for this badge
	 *
	 * @return array|null
	 */
	public function getBadgeStats(): ?array
	{
		/* Fetch the counts we need from the datastore. If they are old or non-existent, refresh and store in datastore for next time. */
		$stats	= NULL;

		if( isset( \IPS\Data\Store::i()->badgeStats ) )
		{
			$stats	= json_decode( \IPS\Data\Store::i()->badgeStats, true );

			if( !isset( $stats['expiration'] ) OR time() > $stats['expiration'] OR !isset( $stats['badgeCount'][ $this->id ] ) )
			{
				$stats	= NULL;
			}
		}

		if( $stats === NULL )
		{
			$exclude = json_decode( \IPS\Settings::i()->rules_exclude_groups, TRUE );
			$where   = [ [ 'completed=?', true ] ];
			$subQuery = NULL;

			if ( \is_array( $exclude ) and \count( $exclude ) )
			{
				$subQuery = \IPS\Db::i()->select( 'member_id', 'core_members', [ \IPS\Db::i()->in( 'member_group_id', $exclude ) ] );
				$where[]  = [ \IPS\Db::i()->in( 'member_group_id', $exclude, TRUE ) ];
			}

			$stats	= array(
				'expiration'	=> time() + ( 3600 * 6 ),		// Cache for 6 hours
				'memberCount'	=> \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where )->first(),
				'badgeCount'	=> array(),
			);

			foreach( \IPS\Db::i()->select( 'id', 'core_badges' ) as $badgeId )
			{
				$where = [ [ 'badge=?', $badgeId ] ];

				if ( \is_array( $exclude ) and \count( $exclude ) )
				{
					$where[]  = [ \IPS\Db::i()->in( 'core_member_badges.member', $subQuery, TRUE ) ];
				}

				$stats['badgeCount'][ $badgeId ] = \IPS\Db::i()->select( 'COUNT(*)', 'core_member_badges', $where )->first();
			}

			\IPS\Data\Store::i()->badgeStats = json_encode( $stats );
		}

		return $stats;
	}

	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		if ( $this->skipCloneDuplication === TRUE )
		{
			return;
		}
		
		$oldId = $this->id;
		$oldImage = $this->image;

		parent::__clone();

		\IPS\Lang::saveCustom( 'core', "core_badges_{$this->id}", iterator_to_array( \IPS\Db::i()->select( 'word_custom, lang_id', 'core_sys_lang_words', array( 'word_key=?', "core_badges_{$oldId}" ) )->setKeyField( 'lang_id' )->setValueField('word_custom') ) );
		
		if ( $oldImage )
		{
			try
			{
				$image = \IPS\File::get( 'core_Badges', $oldImage );
				$newImage = \IPS\File::create( 'core_Badges', $image->originalFilename, $image->contents() );
				$this->image = (string) $newImage;
			}
			catch ( \Exception $e )
			{
				$this->image = NULL;
			}
			
			$this->save();
		}
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Remove from recognize where just the badge is used, and no points */
		\IPS\Db::i()->delete( 'core_member_recognize', [ 'r_points=0 and r_badge=?', $this->id ] );

		/* Remove the badge from recognize, but leave points */
		\IPS\Db::i()->update( 'core_member_recognize', [ 'r_badge' => 0 ], [ 'r_badge=?', $this->id ] );

		if ( $this->image )
		{
			try
			{
				\IPS\File::get( 'core_Badges', $this->image )->delete();
			}
			catch( \Exception $ex ) { }
		}
		
		parent::delete();

		\IPS\Db::i()->delete( 'core_member_badges', [ 'badge=?', $this->id ] );
		\IPS\Lang::deleteCustom( 'core', "core_badges_{$this->id}" );
	}

	/**
	 * Show badges on the front end?
	 *
	 * @return bool
	 */
	public static function show(): bool
	{
		return !( ( \IPS\Settings::i()->achievements_rebuilding or !\IPS\Settings::i()->achievements_enabled ) );
	}

	/**
	 * Fetch assigned (to rules) badge IDs
	 *
	 * @return array
	 */
	public static function getAssignedBadgeIds(): array
	{
		$assignedBadges = [];
		foreach( \IPS\Db::i()->select( 'badge_subject, badge_other', 'core_achievements_rules', [ 'badge_subject > 0 or badge_other > 0' ] ) as $row )
		{
			if ( $row['badge_subject'] )
			{
				$assignedBadges[] = $row['badge_subject'];
			}

			if ( $row['badge_other'] )
			{
				$assignedBadges[] = $row['badge_other'];
			}
		}

		return $assignedBadges;
	}

	/**
	 * Import from Xml
	 *
	 * @param	string	$file			The file to import data from
	 * @param	boolean	$deleteExisting Remove existing rules first?
	 *
	 * @return	void
	 */
	public static function importXml( $file, $deleteExisting=FALSE )
	{
		/* Open XML file */
		$xml = \IPS\Xml\XMLReader::safeOpen( $file );

		if ( ! @$xml->read() )
		{
			throw new \DomainException( 'xml_upload_invalid' );
		}

		/* Did we want to wipe first? */
		if ( $deleteExisting )
		{
			$assignedBadges = static::getAssignedBadgeIds();

			$where = NULL;
			if ( \count( $assignedBadges ) )
			{
				$where = [ \IPS\Db::i()->in( '`id`', $assignedBadges, TRUE ) ];
			}

			foreach( \IPS\Db::i()->select('*', 'core_badges', $where ) as $row )
			{
				static::constructFromData( $row )->delete();
			}
		}

		/* Start looping through each row */
		while ( $xml->read() and $xml->name == 'badge' )
		{
			if( $xml->nodeType != \XMLReader::ELEMENT )
			{
				continue;
			}

			$insert	= [];
			$title = NULL;

			while ( $xml->read() and $xml->name != 'badge' )
			{
				if( $xml->nodeType != \XMLReader::ELEMENT )
				{
					continue;
				}

				switch( $xml->name )
				{
					case 'manually_awarded':
						$insert[ $xml->name ] = (int) $xml->readString();
						break;
					case 'title':
						$title = $xml->readString();
						break;
					case 'icon_name':
						$insert['icon_name'] = $xml->readString();
						break;
					case 'icon_data':
						$insert['icon_data'] = base64_decode( $xml->readString() );
						break;
				}
			}

			if ( ! empty( $insert['icon_name'] ) and ! empty( $insert['icon_data'] ) )
			{
				$insert['image'] = (string) \IPS\File::create( 'core_Badges', $insert['icon_name'], $insert['icon_data'], NULL, TRUE, NULL, FALSE );

				unset( $insert['icon_name'] );
				unset( $insert['icon_data'] );
			}

			$insertId = \IPS\Db::i()->insert( 'core_badges', $insert );

			if ( ! empty( $title ) )
			{
				\IPS\Lang::saveCustom( 'core', "core_badges_{$insertId}", $title );
			}
		}
	}

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int			id				ID number
	 * @apiresponse	string		name			Name
	 * @apiresponse	array		statistcs				Badge Statistics
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$return = array(
			'id'			=> $this->id,
			'name'			=> $this->_title,
			'statistics'	=> $this->getBadgeStats(),
		);

		return $return;
	}
}