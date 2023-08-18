<?php

/**
 * @brief		Converter Photopost Core Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		6 December 2016
 */

namespace IPS\convert\Software\Core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Photopost Core Converter
 */
class _Photopost extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "Photopost (8.x)";
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "photopost";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertGroups'				=> array(
				'table'						=> 'usergroups',
				'where'						=> NULL,
			),
			'convertMembers'			=> array(
				'table'						=> 'users',
				'where'						=> NULL,
			)
		);
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertGroups',
			'convertMembers'
		);
	}

	/**
	 * Get More Information
	 *
	 * @param	string	$method	Conversion method
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();

		switch( $method )
		{
			case 'convertGroups':
				$return['convertGroups'] = array();

				$options = array();
				$options['none'] = 'None';
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_groups' ), 'IPS\Member\Group' ) AS $group )
				{
					$options[$group->g_id] = $group->name;
				}

				foreach( $this->db->select( '*', 'usergroups' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['groupid']}"]			= $group['groupname'];
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['groupid']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );

					$return['convertGroups']["map_group_{$group['groupid']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
				break;

			case 'convertMembers':
				$return['convertMembers'] = array();

				\IPS\Member::loggedIn()->language()->words['photo_location_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'photo_location_nodb_desc' );
				$return['convertMembers']['photo_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> "The path to the folder where avatars are saved (no trailing slash - usually /path_to_photopost/data/avatars):",
					'field_validation'		=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);

				/* And decide what to do about these... */
				foreach( array( 'homepage', 'icq', 'aim', 'yahoo', 'location', 'interests', 'occupation', 'bio' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["field_{$field}"]		= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field', FALSE, array( 'sprintf' => ucwords( $field ) ) );
					\IPS\Member::loggedIn()->language()->words["field_{$field}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field_desc' );
					$return['convertMembers']["field_{$field}"] = array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'			=> 'no_convert',
						'field_required'		=> TRUE,
						'field_extra'			=> array(
							'options'				=> array(
								'no_convert'			=> \IPS\Member::loggedIn()->language()->addToStack( 'no_convert' ),
								'create_field'			=> \IPS\Member::loggedIn()->language()->addToStack( 'create_field' ),
							),
							'userSuppliedInput'		=> 'create_field'
						),
						'field_hint'			=> NULL
					);
				}
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}

	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		/* Search Index Rebuild */
		\IPS\Content\Search\Index::i()->rebuild();

		/* Clear Cache and Store */
		\IPS\Data\Store::i()->clearAll();
		\IPS\Data\Cache::i()->clearAll();

		/* Non-Content Rebuilds */
		\IPS\Task::queue( 'convert', 'RebuildProfilePhotos', array( 'app' => $this->app->app_id ), 5, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ), 2, array( 'app', 'link', 'extension' ) );

		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );

		return array( "f_search_index_rebuild", "f_clear_caches", "f_signatures_rebuild" );
	}

	/**
	 * Convert groups
	 *
	 * @return 	void
	 */
	public function convertGroups()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'groupid' );

		foreach( $this->fetch( 'usergroups', 'groupid' ) AS $row )
		{
			$info = array(
				'g_id'				=> $row['groupid'],
				'g_name'			=> $row['groupname']
			);

			$merge = $this->app->_session['more_info']['convertGroups']["map_group_{$row['groupid']}"] != 'none' ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['groupid']}"] : NULL;

			$libraryClass->convertGroup( $info, $merge );
			$libraryClass->setLastKeyValue( $row['groupid'] );
		}

		/* Now check for group promotions */
		if( \count( $libraryClass->groupPromotions ) )
		{
			foreach( $libraryClass->groupPromotions as $groupPromotion )
			{
				$libraryClass->convertGroupPromotion( $groupPromotion );
			}
		}
	}

	/**
	 * Convert members
	 *
	 * @return 	void
	 */
	public function convertMembers()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'userid' );
		
		foreach( $this->fetch( 'users', 'userid' ) AS $row )
		{
			$birthdayYear = $birthdayMonth = $birthdayDay = NULL;

			/* Is there a birthdate? */
			if( $row['birthday'] != '0000-00-00' )
			{
				list( $birthdayYear, $birthdayMonth, $birthdayDay ) = explode( '-', $row['birthday'] );
			}

			$info = array(
				'member_id'				=> $row['userid'],
				'email'					=> $row['email'],
				'name'					=> $row['username'],
				'md5_password'			=> $row['password'],
				'member_group_id'		=> $row['usergroupid'],
				'joined'				=> $row['joindate'] ?: NULL,
				'ip_address'			=> $row['ipaddress'],
				'last_visit'			=> $row['laston'],
				'last_activity'			=> $row['laston'],
				'bday_day'				=> $birthdayDay,
				'bday_month'			=> $birthdayMonth,
				'bday_year'				=> $birthdayYear,
				'signature'				=> static::fixPostData( $row['signature'] )
			);

			/* Pseudo Fields */
			$profileFields = array();
			foreach( array( 'homepage', 'icq', 'aim', 'yahoo', 'location', 'interests', 'occupation', 'bio' ) AS $pseudo )
			{
				/* Are we retaining? */
				if ( $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"] == 'no_convert' )
				{
					/* No, skip */
					continue;
				}

				try
				{
					/* We don't actually need this, but we need to make sure the field was created */
					$this->app->getLink( $pseudo, 'core_pfields_data' );
				}
				catch( \OutOfRangeException $e )
				{
					$libraryClass->convertProfileField( array(
						'pf_id'				=> $pseudo,
						'pf_name'			=> $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"],
						'pf_desc'			=> '',
						'pf_type'			=> 'Text',
						'pf_content'		=> '[]',
						'pf_member_hide'	=> 'all',
						'pf_max_input'		=> 255,
						'pf_member_edit'	=> 1,
						'pf_show_on_reg'	=> 0,
					) );
				}

				$profileFields[ $pseudo ] = $row[ $pseudo ];
			}

			$libraryClass->convertMember( $info, $profileFields, $row['avatar'], rtrim( $this->app->_session['more_info']['convertMembers']['photo_location'], '/' ) );
			$libraryClass->setLastKeyValue( $row['userid'] );
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		/* If we can't access profiles, don't bother trying to redirect */
		if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
		{
			return NULL;
		}

		$url = \IPS\Request::i()->url();

		if( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'member.php' ) !== FALSE )
		{
			try
			{
				$data = (string) $this->app->getLink( \IPS\Request::i()->cat, array( 'members', 'core_members' ) );
				return \IPS\Member::load( $data )->url();
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}

		return NULL;
	}
}