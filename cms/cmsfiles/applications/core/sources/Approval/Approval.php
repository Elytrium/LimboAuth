<?php
/**
 * @brief		Deletion Log Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Nov 2016
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Deletion Log Model
 */
class _Approval extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_approval_queue';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'approval_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * Set Held Data
	 *
	 * @param	NULL|array	$data	The data indicating why the content was held for approval
	 * @return	void
	 */
	public function set_held_data( ?array $data )
	{
		if ( \is_array( $data ) )
		{
			$this->_data['held_data'] = json_encode( $data );
			return;
		}
		
		$this->_data['held_data'] = NULL;
	}
	
	/**
	 * Get Held Data
	 *
	 * @return	array
	 */
	public function get_held_data(): ?array
	{
		if ( $this->_data['held_data'] )
		{
			return json_decode( $this->_data['held_data'], true );
		}
		
		return NULL;
	}
	
	/**
	 * Set Held Reason
	 *
	 * @param	NULL|string		$reason		The reason for requiring approval.
	 * @return	void
	 */
	public function set_held_reason( ?string $reason )
	{
		if ( $reason AND \in_array( $reason, static::availableReasons() ) )
		{
			$this->_data['held_reason'] = $reason;
			return;
		}
		
		$this->_data['held_reason'] = NULL;
	}
	
	/**
	 * Get Held Reason
	 *
	 * @param	\IPS\Member|NULL	$member		The member, or NULL for currently logged in.
	 * #return	NULL|string
	 */
	public function reason( ?\IPS\Member $member = NULL ): string
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $this->held_reason )
		{
			if ( $extra = $this->parseReason() )
			{
				return $member->language()->addToStack( $extra['lang'], TRUE, ( isset( $extra['sprintf'] ) ) ? array( 'sprintf' => $extra['sprintf'] ) : array() );
			}
			else
			{
				return $member->language()->addToStack( "approval_reason_{$this->held_reason}" );
			}
		}
		else
		{
			return $member->language()->addToStack( "approval_reason_unknown" );
		}
	}
	
	/**
	 * Parse Reason
	 *
	 * @param	\IPS\Member|NULL	$member		The member, or NULL for currently logged in.
	 * @return	NULL|array
	 */
	public function parseReason( ?\IPS\Member $member = NULL ): ?array
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $this->held_reason )
		{
			switch( $this->held_reason )
			{
				case 'profanity':
					return array(
						'lang'		=> 'approval_reason_profanity',
						'sprintf'	=> array( $this->held_data['word'] )
					);
					break;
				
				case 'url':
					return array(
						'lang'		=> 'approval_reason_url',
						'sprintf'	=> array( $this->held_data['url'] )
					);
					break;
				
				case 'email':
					return array(
						'lang'		=> 'approval_reason_email',
						'sprintf'	=> array( $this->held_data['email'] )
					);
					break;
				
				case 'node':
					$contentClass = $this->content_class;
					$content = $contentClass::load( $this->content_id );
					return array(
						'lang'		=> 'approval_reason_node',
						'sprintf'	=> array( $content->indefiniteArticle(), $content->container()->url(), $content->container()->getTitleForLanguage( $member->language() ), $content->definiteArticle( $member->language(), 2 ) )
					);
					break;
				
				case 'item':
					$contentClass = $this->content_class;
					$content = $contentClass::load( $this->content_id );
					$title = ( $content instanceof \IPS\Content\Comment ) ? $content->item()->mapped('title') : $content->mapped('title');
					return array(
						'lang'		=> 'approval_reason_item',
						'sprintf'	=> array( $content->url(), $title )
					);
					break;
				
				default:
					return NULL;
					break;
			}
		}
		
		return NULL;
	}
	
	/**
	 * Available Reasons
	 *
	 * @return	array
	 */
	public static function availableReasons(): array
	{
		return array(
			'profanity',
			'url',
			'email',
			'user',
			'group',
			'node',
			'item'
		);
	}
	
	/**
	 * Load from Content
	 *
	 * @param	string	$class	Content class
	 * @param	int		$id		Content ID
	 * @return	static
	 */
	public static function loadFromContent( string $class, int $id ): \IPS\core\Approval
	{
		try
		{
			return static::constructFromData( \IPS\Db::i()->select( '*', 'core_approval_queue', array( "approval_content_class=? AND approval_content_id=?", $class, $id ) )->first() );
		}
		catch( \UnderflowException $e )
		{
			throw new \OutOfRangeException;
		}
	}
	 
}