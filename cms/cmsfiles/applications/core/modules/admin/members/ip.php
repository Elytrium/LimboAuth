<?php
/**
 * @brief		IP Address Tools
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Apr 2013
 */

namespace IPS\core\modules\admin\members;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * IP Address Tools
 */
class _ip extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * @brief	GeoLocation
	 */
	protected $geoLocation = NULL;
	
	/**
	 * @brief	GeoLocation Exception
	 */
	protected $getLocationException = NULL;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'membertools_ip' );
		return parent::execute();
	}

	/**
	 * IP Address Lookup
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( isset( \IPS\Request::i()->ip ) )
		{
			$ip = \IPS\Request::i()->ip;
			\IPS\Output::i()->title = $ip;
			
			$url =  \IPS\Http\Url::internal( 'app=core&module=members&controller=ip' )->setQueryString( 'ip', $ip );
			
			if ( isset( \IPS\Request::i()->area ) )
			{
				\IPS\Output::i()->breadcrumb[] = array( $url, $ip );
				\IPS\Output::i()->breadcrumb[] = array( NULL, 'ipAddresses__' .  \IPS\Request::i()->area );

				$exploded = explode( '_', \IPS\Request::i()->area );
				$extensions = \IPS\Application::load( $exploded[0] )->extensions( 'core', 'IpAddresses' );
				$class = $extensions[ mb_substr( \IPS\Request::i()->area, mb_strlen( $exploded[0] ) + 1 ) ];
				\IPS\Output::i()->output = $class->findByIp( str_replace( '*', '%', $ip ), $url->setQueryString( 'area', \IPS\Request::i()->area ) );
			}
			else
			{
				$geolocation	= NULL;
				$map			= NULL;
				$hostName		= $ip;

				if( filter_var( $ip, FILTER_VALIDATE_IP ) !== false )
				{
					try
					{
						$geolocation = \IPS\GeoLocation::getByIp( $ip );
						$map = $geolocation->map()->render( 400, 350, 0.6 );
					}
					catch ( \Exception $e ) {}

					$hostName	= @gethostbyaddr( $ip );
				}
				
				$contentCounts = array();
				$otherCounts = array();
				foreach ( \IPS\Application::allExtensions( 'core', 'IpAddresses' ) as $k => $ext )
				{
					/* If the method does not exist, we presume it is supported - this is for legacy purposes as the method is new so
						third parties won't have it present */
					if( method_exists( $ext, 'supportedInAcp' ) AND !$ext->supportedInAcp() )
					{
						continue;
					}

					$count = $ext->findByIp( str_replace( '*', '%', $ip ) );
					if ( $count !== NULL )
					{			
						if ( isset( $ext->class ) )
						{
							$class = $ext->class;
							if ( isset( $class::$databaseColumnMap['ip_address'] ) )
							{
								$contentCounts[ $k ] = $count;
							}
						}
						else
						{
							$otherCounts[ $k ] = $count;
						}
					}
				}
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'admin' )->block( '', \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->ipLookup( $url, $geolocation, $map, $hostName, array_merge( $otherCounts, $contentCounts ) ) );
			}
		}
		else
		{
			$form = new \IPS\Helpers\Form( 'form', 'continue' );
			$form->add( new \IPS\Helpers\Form\Text( 'ip_address', NULL, TRUE, array(), function( $val )
			{
				if( trim( $val, '*' ) == '' )
				{
					throw new \DomainException('not_just_asterisk');
				}
			} ) );
			
			if ( $values = $form->values() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=ip' )->setQueryString( 'ip', $values['ip_address'] ) );
			}

			$members = new \IPS\Helpers\Form( 'members', 'continue' );
			$members->add( new \IPS\Helpers\Form\Member( 'ip_username', NULL, TRUE ) );
			
			if ( $values = $members->values() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=ip' )->setQueryString( 'id', $values['ip_username']->member_id ) );
			}
			
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_members_ip');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'members' )->ipform( $form, $members );
		}
	}
}