<?php
/**
 * @brief		Rank Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Mar 2021
 */

namespace IPS\core\Achievements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Rank Model
 */
class _Rank extends \IPS\Node\Model
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_member_ranks';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'points';
	
	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = FALSE;
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'menu__core_achievements_ranks';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'core_member_rank_';

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
		'prefix'	=> 'ranks_',
	);

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = ['achievementRanks'];
	
	/**
	 * [ActiveRecord] Get cached rules
	 *
	 * @return	array
	 */
	public static function getStore(): array
	{
		if ( !isset( \IPS\Data\Store::i()->achievementRanks ) )
		{
			\IPS\Data\Store::i()->achievementRanks = iterator_to_array( \IPS\Db::i()->select( '*', static::$databaseTable, NULL, 'points' )->setKeyField( static::$databasePrefix . static::$databaseColumnId ) );
		}

		return iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( new \ArrayIterator( \IPS\Data\Store::i()->achievementRanks ), 'IPS\core\Achievements\Rank' ) );
	}
	
	/**
	 * Work out the rank for a given number of points
	 *
	 * @param	int		$points		Number of points
	 * @return	\IPS\core\Achievements\Rank|NULL
	 */
	public static function fromPoints( $points ): ?\IPS\core\Achievements\Rank
	{
		$return = NULL;
		foreach ( static::getStore() as $rank )
		{
			if ( $points >= $rank->points )
			{
				$return = $rank;
			}
			else
			{
				break;
			}
		}
		return $return;
	}
	
	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe', the 'fa fa-' is added automatically so you do not need this here)
	 * @return	mixed
	 */
	protected function get__icon()
	{
		if ( $this->icon )
		{
			return \IPS\File::get( 'core_Ranks', $this->icon );
		}
		else
		{
			return \IPS\Theme::i()->resource( 'default_rank.png', 'core', 'global' );
		}
	}

	protected static $rankPositions = [];
	/**
	 * Fetch the rank position from all ranks
	 *
	 * @return array
	 */
	public function rankPosition(): array
	{
		if ( ! isset( static::$rankPositions[ $this->id ] ) )
		{
			$pos = 1;

			foreach( static::getStore() as $rank )
			{
				if ( $rank->id == $this->id )
				{
					break;
				}

				$pos++;
			}

			static::$rankPositions[ $this->id ] = [
				'pos' => $pos,
				'max' => \count( static::getStore() )
			];
		}

		return static::$rankPositions[ $this->id ];

	}
	
	/**
	 * Get rank image HTML
	 *
	 * @param	string|NULL	$cssClass	Optional CSS class to apply
	 * @return 	string
	 */
	public function html( ?string $cssClass = NULL ): string
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->rank( $this, $cssClass );
	}
	
	/**
	 * [Node] Get Node Description
	 *
	 * @return	string
	 */
	protected function get__description()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'achievements_awards_points', FALSE, [ 'pluralize' => [ $this->points ] ] );
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

		$form->add( new \IPS\Helpers\Form\Translatable( 'member_ranks_word_custom', NULL, TRUE, array( 'app' => 'core', 'key' => ( $this->id ? "core_member_rank_{$this->id}" : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'member_ranks_points', $this->points ?: 0, TRUE, array( 'min' => 0 ) ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'member_ranks_icon', $this->icon ? \IPS\File::get( 'core_Ranks', $this->icon ) : NULL, TRUE, array( 'obscure' => TRUE, 'allowedFileTypes' => array_merge( \IPS\Image::supportedExtensions(), ['svg'] ), 'checkImage' => TRUE, 'storageExtension' => 'core_Ranks' ), function( $val ) {
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
		}, NULL, NULL, 'member_ranks_icon' ) );
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
		
		\IPS\Lang::saveCustom( 'core', "core_member_rank_{$this->id}", $values['member_ranks_word_custom'] );
		unset( $values['member_ranks_word_custom'] );
		
		$_values = $values;
		$values = array();
		foreach ( $_values as $k => $v )
		{
			if( mb_substr( $k, 0, 13 ) === 'member_ranks_' )
			{
				$values[ mb_substr( $k, 13 ) ] = $v;
			}
			else
			{
				$values[ $k ]	= $v;
			}
		}
		
		return $values;
	}

	/**
	 * Show ranks on the community?
	 *
	 * @return bool
	 */
	public static function show(): bool
	{
		return !( ( \IPS\Settings::i()->achievements_rebuilding or !\IPS\Settings::i()->achievements_enabled ) );
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

		$oldImage = $this->icon;

		parent::__clone();


		if ( $oldImage )
		{
			try
			{
				$image = \IPS\File::get( 'core_Ranks', $oldImage );
				$newImage = \IPS\File::create( 'core_Ranks', $image->originalFilename, $image->contents() );
				$this->icon = (string) $newImage;
			}
			catch ( \Exception $e )
			{
				$this->icon = NULL;
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
		if ( $this->icon )
		{
			try
			{
				\IPS\File::get( 'core_Ranks', $this->icon )->delete();
			}
			catch( \Exception $ex ) { }
		}

		parent::delete();
	}

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int			id				ID number
	 * @apiresponse	string		name			Name
	 * @apiresponse	string		url				Path to the rank icon
	 * @apiresponse	int			points			Points
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'			=> $this->id,
			'name'			=> $this->_title,
			'icon'			=> $this->icon ? (string) $this->_icon->url : $this->_icon,
			'points'		=> $this->points
		);
	}

	/**
	 * Import from Xml
	 *
	 * @param	string	$file			The file to import data from
	 * @param	boolean	$option			How to handle existing ranks
	 *
	 * @return	void
	 */
	public static function importXml( $file, $option=NULL )
	{
		/* Open XML file */
		$xml = \IPS\Xml\XMLReader::safeOpen( $file );

		if ( ! @$xml->read() )
		{
			throw new \DomainException( 'xml_upload_invalid' );
		}

		/* Did we want to wipe first? */
		if ( $option == 'wipe' )
		{
			foreach( \IPS\core\Achievements\Rank::getStore() as $rank )
			{
				$rank->delete();
			}
		}

		/* Start looping through each row */
		while ( $xml->read() and $xml->name == 'rank' )
		{
			if( $xml->nodeType != \XMLReader::ELEMENT )
			{
				continue;
			}

			$insert	= array(
				'points' => 0,
				'title'	 => NULL,
				'icon'	 => NULL
			);

			while ( $xml->read() and $xml->name != 'rank' )
			{
				if( $xml->nodeType != \XMLReader::ELEMENT )
				{
					continue;
				}

				switch( $xml->name )
				{
					case 'title':
						$insert['title'] = $xml->readString();
						break;

					case 'points':
						$insert['points'] = $xml->readString();
						break;

					case 'icon_name':
						$insert['icon_name'] = $xml->readString();
						break;

					case 'icon_data':
						$insert['icon_data'] = base64_decode( $xml->readString() );
						break;
				}
			}

			/* Did we want to wipe existing ranks with the same points? */
			if ( $option == 'replace' )
			{
				foreach( \IPS\core\Achievements\Rank::getStore() as $rank )
				{
					if ( $rank->points == $insert['points'] )
					{
						$rank->delete();
					}
				}
			}

			if ( ! empty( $insert['icon_name'] ) and ! empty( $insert['icon_data'] ) )
			{
				$insert['icon'] = (string) \IPS\File::create( 'core_Ranks', $insert['icon_name'], $insert['icon_data'], NULL, TRUE, NULL, FALSE );

				unset( $insert['icon_name'] );
				unset( $insert['icon_data'] );
			}

			$insertId = \IPS\Db::i()->insert( 'core_member_ranks', $insert );

			if ( ! empty( $insert['title'] ) )
			{
				\IPS\Lang::saveCustom( 'core', "core_member_rank_{$insertId}", $insert['title'] );
			}
		}

		unset( \IPS\Data\Store::i()->achievementRanks );
	}
}