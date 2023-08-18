<?php
/**
 * @brief		ACP Member Profile: Notification Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Jun 2019
 */

namespace IPS\core\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Profile Data Block
 */
class _Notifications extends \IPS\core\MemberACPProfile\Block
{	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		parent::__construct( $member );
		
		$this->extensions = \IPS\Application::allExtensions( 'core', 'Notifications' );
	}
		
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		return \IPS\Theme::i()->getTemplate('memberprofile')->notificationTypes( $this->member, \IPS\Notification::membersOptionCategories( $this->member, $this->extensions ) );
	}
	
	/**
	 * Edit Window
	 *
	 * @return	string
	 */
	public function edit()
	{
		if ( isset( \IPS\Request::i()->type ) and array_key_exists( \IPS\Request::i()->type, $this->extensions ) )
		{
			$form = \IPS\Notification::membersTypeForm( $this->member, $this->extensions[ \IPS\Request::i()->type ] );
			if ( $form === TRUE )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=view&id={$this->member->member_id}" ), 'saved' );
			}
			
			return $form;
		}
		elseif ( isset( \IPS\Request::i()->method ) and \in_array( \IPS\Request::i()->method, array( 'inline', 'email' ) ) )
		{
			$form = \IPS\Notification::membersMethodForm( $this->member, \IPS\Request::i()->method, $this->extensions );
			if ( $form === TRUE )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=view&id={$this->member->member_id}" ), 'saved' );
			}
			
			return $form;
		}
		else
		{
			\IPS\Output::i()->error( 'node_error', '2C403/1', 404, '' );
		}
	}
}