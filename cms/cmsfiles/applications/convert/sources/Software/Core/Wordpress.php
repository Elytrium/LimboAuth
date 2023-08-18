<?php

/**
 * @brief		Converter Wordpress Core Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		12 December 2015
 */

namespace IPS\convert\Software\Core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Wordpress Core Converter
 */
class _Wordpress extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "WordPress (5.x)";
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "wordpress";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertMembers'				=> array(
				'table'		=> 'users',
				'where'		=> NULL
			)
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
	 * Returns a block of text, or a language string, that explains what the admin must do to start this conversion
	 *
	 * @return	string
	 */
	public static function getPreConversionInformation()
	{
		return 'convert_wordpress_preconvert';
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertMembers',
		);
	}

	/**
	 * Fix post data
	 *
	 * @param 	string		$post	Raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		return nl2br( $post );
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
			case 'convertMembers':
				$return['convertMembers']['username'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
					'field_default'			=> 'display_name',
					'field_required'		=> TRUE,
					'field_extra'			=> array( 'options' => array( 'username' => \IPS\Member::loggedIn()->language()->addToStack( 'user_name' ), 'display_name' => \IPS\Member::loggedIn()->language()->addToStack( 'display_name' ) ) ),
					'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack( 'username_hint' ),
				);
				
				/* Pseudo Fieds */
				foreach( array( 'first_name', 'last_name', 'user_url' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["field_{$field}"]		= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field', FALSE, array( 'sprintf' => ucwords( str_replace( '_', ' ', $field ) ) ) );
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
	 * Convert members
	 *
	 * @return	void
	 */
	public function convertMembers()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'users.ID' );

		foreach( $this->fetch( 'users', 'users.ID', NULL, 'users.*, usermeta.meta_value as user_level' )->join( 'usermeta', "users.ID=usermeta.user_id AND usermeta.meta_key='wp_user_level'" ) AS $user )
		{
			/* Main Members Table */
			$info = array(
				'member_id'					=> $user['ID'],
				'ips_group_id'				=> $user['user_level'] > 9 ? \IPS\Settings::i()->admin_group : \IPS\Settings::i()->member_group,
				'name'						=> $this->app->_session['more_info']['convertMembers']['username'] == 'username' ? $user['user_login'] : $user['display_name'],
				'email'						=> $user['user_email'],
				'joined'					=> new \IPS\DateTime( $user['user_registered'] ),
				'conv_password'				=> $user['user_pass']
			);
			
			$meta = array();
			foreach( $this->db->select( array( 'meta_key', 'meta_value' ), 'usermeta', array( "user_id=?", $user['ID'] ) )->setKeyField( 'meta_key' )->setValueField( 'meta_value' ) AS $key => $value )
			{
				$meta[$key] = $value;
			}
			
			$fields = array();
			foreach( array( 'first_name', 'last_name', 'user_url' ) AS $field )
			{
				if ( $this->app->_session['more_info']['convertMembers']["field_{$field}"] != 'no_convert' )
				{
					try
					{
						$fieldId = $this->app->getLink( $field, 'core_pfields_data' );
					}
					catch( \OutOfRangeException $e )
					{
						$libraryClass->convertProfileField( array(
							'pf_id'				=> $field,
							'pf_name'			=> $this->app->_session['more_info']['convertMembers']["field_{$field}"],
							'pf_desc'			=> '',
							'pf_type'			=> 'Text',
							'pf_content'		=> '[]',
							'pf_member_hide'	=> 'all',
							'pf_max_input'		=> 255,
							'pf_member_edit'	=> 1,
							'pf_show_on_reg'	=> 0,
						) );
					}
					
					$toCheck = $meta;
					if ( $field == 'user_url' )
					{
						$toCheck = $user;
					}
					if ( isset( $toCheck[$field] ) )
					{
						$fields[$field] = $toCheck[$field];
					}
					else
					{
						$fields[$field] = '';
					}
				}
			}
			
			$libraryClass->convertMember( $info, $fields );
			$libraryClass->setLastKeyValue( $user['ID'] );
		}
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
	 * Process a login
	 *
	 * @param	\IPS\Member		$member			The member
	 * @param	string			$password		Password from form
	 * @return	bool
	 */
	public static function login( $member, $password )
	{
		$success = FALSE;

		// If the hash is still md5...
		if ( \strlen( $member->conv_password ) <= 32 )
		{
			$success = ( \IPS\Login::compareHashes( $member->conv_password, md5( $password ) ) ) ? TRUE : FALSE;
		}
		// New pass hash check
		else
		{
			// Init the pass class
			require_once \IPS\ROOT_PATH . "/applications/convert/sources/Login/PasswordHash.php";
			$ph = new \PasswordHash( 8, TRUE );

			// Check it
			$success = $ph->CheckPassword( $password, $member->conv_password ) ? TRUE : FALSE;
		}

		return $success;
	}
}