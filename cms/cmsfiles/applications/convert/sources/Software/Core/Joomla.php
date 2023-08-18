<?php

/**
 * @brief		Converter Joomla Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Joomla Core Converter
 */
class _Joomla extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "Joomla";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "joomla";
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
				'table'		=> 'usergroups',
				'where'		=> NULL
			),
			'convertMembers'			=> array(
				'table'		=> 'users',
				'where'		=> NULL
			),
		);
	}
	
	/**
	 * Can we convert passwords from this software.
	 *
	 * @return 	boolean
	 */
	public static function loginEnabled()
	{
		return TRUE;
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertGroups'
		);
	}
	
	/**
	 * Get More Information
	 *
	 * @param	string	$method	Method name
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
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['id']}"]		= $group['title'];
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['id']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );

					$return['convertGroups']["map_group_{$group['id']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
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

		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );

		return array( "f_search_index_rebuild", "f_clear_caches" );
	}
	
	/**
	 * Convert groups
	 *
	 * @return 	void
	 */
	public function convertGroups()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id' );
		
		foreach( $this->fetch( 'usergroups', 'id' ) as $row )
		{
			/* Basic info */
			$info = array(
				'g_id'		=> $row['id'],
				'g_name'	=> $row['title'],
			);

			$merge = ( $this->app->_session['more_info']['convertGroups']["map_group_{$row['id']}"] != 'none' ) ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['id']}"] : NULL;
			
			$libraryClass->convertGroup( $info, $merge );
			
			$libraryClass->setLastKeyValue( $row['id'] );
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
		
		$libraryClass::setKey( 'id' );
		
		foreach( $this->fetch( 'users', 'id' ) as $row )
		{
			/* Basic info */
			$info = array(
				'member_id'		=> $row['id'],
				'name'			=> $row['username'],
				'email'			=> $row['email'],
				'joined'		=> strtotime( $row['registerDate'] ),
				'mgroup_others'	=> array(),
				'temp_ban'		=> $row['block'] ? -1 : 0
			);

			/* Figure out password */
			$pass	= explode ( ':', $row['password'] );
			$info['conv_password']			= $pass[0];
			$info['conv_password_extra']	= $pass[1];

			/* Figure out groups */
			$libraryClass::$usingKeys = FALSE;
			foreach( $this->fetch( 'user_usergroup_map', 'group_id', array( 'user_id=?', $row['id'] ), 'group_id' ) as $groupId )
			{
				/* First one we find we'll set as primary - Joomla does not make a distinction */
				if( !isset( $info['member_group_id'] ) )
				{
					$info['member_group_id'] = $groupId;
				}
				else
				{
					$info['mgroup_others'][] = $groupId;
				}
			}
			$libraryClass::$usingKeys = TRUE;

			$libraryClass->convertMember( $info );
			
			$libraryClass->setLastKeyValue( $row['id'] );
		}
	}

	/**
	 * Process a login
	 *
	 * @param	\IPS\Member		$member			The member
	 * @param	string			$password		Password from form
	 * @return	bool
	 */
	public static function login( $member, $password )
	{
		/* Joomla 3 */
		if( preg_match( '/^\$2[ay]\$(0[4-9]|[1-2][0-9]|3[0-1])\$[a-zA-Z0-9.\/]{53}/', $member->conv_password ) )
		{
			require_once \IPS\ROOT_PATH . "/applications/convert/sources/Login/PasswordHash.php";
			$ph = new \PasswordHash( 8, TRUE );
			return $ph->CheckPassword( $password, $member->conv_password ) ? TRUE : FALSE;
		}

		/* Joomla 2 */
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( $password . $member->misc ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
}