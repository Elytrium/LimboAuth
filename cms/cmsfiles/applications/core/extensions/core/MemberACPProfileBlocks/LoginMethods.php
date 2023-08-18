<?php
/**
 * @brief		ACP Member Profile: Login Methods Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Login Methods Block
 */
class _LoginMethods extends \IPS\core\MemberACPProfile\LazyLoadingBlock
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function lazyOutput()
	{
		$loginMethods = array();
		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method->canProcess( $this->member ) and !( $method instanceof \IPS\Login\Handler\Standard ) )
			{
				$link = NULL;
				try
				{					
					$link = $method->userLink( $method->userId( $this->member ), $method->userProfileName( $this->member ) );
					
					$forceSyncErrors = array();
					foreach ( $method->forceSync() as $type )
					{
						if ( isset( $this->member->profilesync[ $type ]['error'] ) )
						{
							$forceSyncErrors[ $type ] = \IPS\Member::loggedIn()->language()->addToStack( $this->member->profilesync[ $type ]['error'] );
						}
					}
					
					$syncOptions = FALSE;
					foreach ( $method->syncOptions( $this->member ) as $option )
					{
						if ( $option == 'photo' and !$this->member->group['g_edit_profile'] )
						{
							continue;
						}
						if ( $option == 'cover' and ( !$this->member->group['g_edit_profile'] or !$this->member->group['gbw_allow_upload_bgimage'] ) )
						{
							continue;
						}
						if ( $option == 'status' and ( !$this->member->canAccessModule( \IPS\Application\Module::get( 'core', 'status', 'front' ) ) or !\IPS\core\Statuses\Status::canCreateFromCreateMenu( $this->member ) or !\IPS\Settings::i()->profile_comments or $this->member->group['gbw_no_status_update'] ) )
						{
							continue;
						}
						
						$syncOptions = TRUE;
						break;
					}
					
					$canDisassociate = FALSE;
					foreach ( \IPS\Login::methods() as $_method )
					{
						if ( $_method->id != $method->id and $_method->canProcess( $this->member ) )
						{
							$canDisassociate = TRUE;
							break;
						}
					}

					/* Login handlers may return NULL if they do not support display name syncing */
					$memberName = $method->userProfileName( $this->member ) ?? \IPS\Member::loggedIn()->language()->get( 'profilesync_unknown_name' );
					
					$loginMethods[ $method->id ] = array(
						'title'				=> $method->_title,
						'blurb'				=> \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_headline', FALSE, array( 'sprintf' => array( $memberName ) ) ),
						'forceSyncErrors'	=> $forceSyncErrors,
						'icon'				=> $method->userProfilePhoto( $this->member ),
						'logo'				=> $method->logoForUcp(),
						'link'				=> $link,
						'edit'				=> $syncOptions,
						'delete'			=> $canDisassociate
					);
				}
				catch ( \IPS\Login\Exception $e )
				{
					$loginMethods[ $method->id ] = array( 'title' => $method->_title, 'blurb' => \IPS\Member::loggedIn()->language()->addToStack('profilesync_reauth_needed'), 'logo' => $method->logoForUcp(), 'link' => $link );
				}
				catch( \IPS\Http\Request\Exception $e )
				{
					\IPS\Log::log( $e, 'login_method_connect' );
				}
			}
		}
		
		return \IPS\Theme::i()->getTemplate('memberprofile')->loginMethods( $this->member, $loginMethods );
	}
}