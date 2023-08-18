<?php
/**
 * @brief		Ignore Record
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Aug 2013
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Ignore Record
 */
class _Ignore extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_ignored_users';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'ignore_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'ignore_ignore_id' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * Get types
	 *
	 * @return	array
	 */
	public static function types()
	{
		$types = array( 'topics', 'messages', 'mentions' );
		
		if( \IPS\Settings::i()->signatures_enabled )
		{
			$types[] = 'signatures';
		}
		
		return $types;
	}
	
	/**
	 * Display Form
	 *
	 * @return	\IPS\Helpers\Form
	 */
	public static function form()
	{
		$ignore = NULL;
		try
		{
			$ignore = static::load( \IPS\Request::i()->id, 'ignore_ignore_id', array( 'ignore_owner_id=?', \IPS\Member::loggedIn()->member_id ) );
		}
		catch( \OutOfRangeException $e )
		{
			if ( \IPS\Request::i()->id )
			{
				$ignore = new static;
				$ignore->ignore_id = \IPS\Request::i()->id;
			}
		}
		
		$form = new \IPS\Helpers\Form( 'ignore_form', $ignore ? 'ignore_edit' : 'ignore_submit' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Member( 'member', $ignore ? \IPS\Member::load( $ignore->ignore_id ) : NULL, TRUE, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('ignore_placeholder') ) ) );
		
		foreach ( static::types() as $type )
		{
			$form->add( new \IPS\Helpers\Form\Checkbox( "ignore_{$type}", $ignore ? $ignore->$type : NULL ) );
		}
				
		return $form;
	}
	
	/**
	 * Create from form
	 *
	 * @param	array	$values	Values from form
	 * @return	\IPS\core\Ignore
	 */
	public static function createFromForm( $values )
	{
		try
		{
			$obj = static::load( $values['member']->member_id, 'ignore_ignore_id', array( 'ignore_owner_id=?', \IPS\Member::loggedIn()->member_id ) );
		}
		catch ( \OutOfRangeException $e )
		{
			$obj = new static;
		}
		
		if ( $values['member']->member_id == \IPS\Member::loggedIn()->member_id )
		{
			throw new \InvalidArgumentException( 'cannot_ignore_self' );
		}
		
		if ( !$values['member']->canBeIgnored() )
		{
			throw new \InvalidArgumentException( 'cannot_ignore_that_member' );
		}
		
		$obj->owner_id	= \IPS\Member::loggedIn()->member_id;
		$obj->ignore_id	= $values['member']->member_id;
		
		foreach ( static::types() as $type )
		{
			$obj->$type = $values["ignore_{$type}"];
		}

		$obj->save();
		
		\IPS\Member::loggedIn()->members_bitoptions['has_no_ignored_users'] = FALSE;
		\IPS\Member::loggedIn()->save();
		
		return $obj;
	}
}