<?php
/**
 * @brief		Form builder helper to allow user-created "groups" of other users
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Mar 2014
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Form builder helper to allow user-created "groups" of other users
 */
class _SocialGroup extends Member
{
	/**
	 * @brief	Default Options
	 * @code
	 	$childDefaultOptions = array(
	 		'owner'			=> 1,		// \IPS\Member object or member ID who "owns" this group
	 		'multiple'	=> 1,	// Maximum number of members. NULL for any. Default is NULL.
	 	);
	 * @endcode
	 */
	public $childDefaultOptions = array(
		'multiple'	=> NULL,
		'owner'		=> NULL,
	);

	/**
	 * @brief	Group ID, if already set
	 */
	public $groupId	= NULL;

	/**
	 * Constructor
	 *
	 * @param	string			$name					Name
	 * @param	mixed			$defaultValue			Default value
	 * @param	bool|NULL		$required				Required? (NULL for not required, but appears to be so)
	 * @param	array			$options				Type-specific options
	 * @param	callback		$customValidationCode	Custom validation code
	 * @param	string			$prefix					HTML to show before input field
	 * @param	string			$suffix					HTML to show after input field
	 * @param	string			$id						The ID to add to the row
	 * @return	void
	 */
	public function __construct( $name, $defaultValue=NULL, $required=FALSE, $options=array(), $customValidationCode=NULL, $prefix=NULL, $suffix=NULL, $id=NULL )
	{
		/* Call parent constructor */
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );

		/* If we are editing, the value will be a group ID we need to load */
		if( \is_int( $this->value ) )
		{
			$this->groupId	= $this->value;

			$values = array();

			foreach( \IPS\Db::i()->select( '*', 'core_sys_social_group_members', array( 'group_id=?', $this->value ) )->setKeyField('member_id')->setValueField('member_id') as $k => $v )
			{
				$values[ $k ] = \IPS\Member::load( $v );
			}
			
			$this->value = $values;
		}

		/* Make sure we have an owner...fall back to logged in member */
		if( !$this->options['owner'] )
		{
			$this->options['owner']	= \IPS\Member::loggedIn();
		}
		else if( !$this->options['owner'] instanceof \IPS\Member AND \is_int( $this->options['owner'] ) )
		{
			$this->options['owner']	= \IPS\Member::load( $this->options['owner'] );
		}
	}

	/**
	 * Save the social group
	 *
	 * @return	void
	 */
	public function saveValue()
	{
		/* Delete any existing entries */
		if( $this->groupId )
		{
			\IPS\Db::i()->delete( 'core_sys_social_group_members', array( 'group_id=?', $this->groupId ) );
		}
		else if( $this->value )
		{
			$this->groupId	= \IPS\Db::i()->insert( 'core_sys_social_groups', array( 'owner_id' => $this->options['owner']->member_id ) );
		}

		if( $this->value )
		{
			$inserts = array();
			foreach( $this->value as $member )
			{
				$inserts[] = array( 'group_id' => $this->groupId, 'member_id' => $member->member_id );
			}
			
			if( \count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'core_sys_social_group_members', $inserts );
			}
		}

		$this->value	= $this->groupId;
		
		\IPS\Db::i()->update( 'core_members', array( 'permission_array' => NULL ) );
	}
}