<?php
/**
 * @brief		Moderator Control Panel Extension: IP Tools
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		02 Oct 2014
 */

namespace IPS\core\extensions\core\ModCp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	IP Tools
 */
class _IPTools extends \IPS\core\modules\admin\members\ip
{
	/**
	 * Returns the primary tab key for the navigation bar
	 *
	 * @return	string
	 */
	public function getTab()
	{
		if ( ! \IPS\Member::loggedIn()->modPermission('can_use_ip_tools') )
		{
			return null;
		}
		
		return 'ip_tools';
	}
	
	/**
	 * Get content to display
	 *
	 * @return	string
	 */
	public function manage()
	{
		if ( ! \IPS\Member::loggedIn()->modPermission('can_use_ip_tools') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C250/1', 403, '' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_ip_tools' );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=ip_tools", 'front', 'modcp_ip_tools' ), \IPS\Member::loggedIn()->language()->addToStack( 'modcp_ip_tools' ) );
		
		if ( isset( \IPS\Request::i()->ip ) )
		{
			$ip = \IPS\Request::i()->ip;
			\IPS\Output::i()->title = $ip;
			
			$url =  \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=ip_tools", 'front', 'modcp_ip_tools' )->setQueryString( 'ip', $ip );
			\IPS\Output::i()->breadcrumb[] = array( $url, $ip );

			if ( isset( \IPS\Request::i()->area ) )
			{
				$exploded = explode( '_', \IPS\Request::i()->area );
				$extensions = \IPS\Application::appIsEnabled( $exploded[0] ) ? \IPS\Application::load( $exploded[0] )->extensions( 'core', 'IpAddresses' ) : array();

				/* If the extension no longer exists (application uninstalled) then fall back */
				if( isset( $extensions[ mb_substr( \IPS\Request::i()->area, mb_strlen( $exploded[0] ) + 1 ) ] ) and ( !method_exists( $extensions[ mb_substr( \IPS\Request::i()->area, mb_strlen( $exploded[0] ) + 1 ) ], 'supportedInModCp' ) or $extensions[ mb_substr( \IPS\Request::i()->area, mb_strlen( $exploded[0] ) + 1 ) ]->supportedInModCp() ) )
				{
					$class = $extensions[ mb_substr( \IPS\Request::i()->area, mb_strlen( $exploded[0] ) + 1 ) ];
					
					\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'ipAddresses__' .  \IPS\Request::i()->area ) );
					\IPS\Output::i()->output = $class->findByIp( str_replace( '*', '%', $ip ), $url->setQueryString( 'area', \IPS\Request::i()->area ) );
				}
				else
				{
					\IPS\Output::i()->error( 'node_error', '2C250/2', 404, '' );
				}
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
					if( method_exists( $ext, 'supportedInModCp' ) AND !$ext->supportedInModCp() )
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
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->ipLookup( $url, $geolocation, $map, $hostName, array_merge( $otherCounts, $contentCounts ) );
			}
		}
		elseif ( isset( \IPS\Request::i()->id ) )
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );

			\IPS\Output::i()->title = $member->name;
			
			$url =  \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=ip_tools", 'front', 'modcp_ip_tools' )->setQueryString( 'id', $member->member_id );
			\IPS\Output::i()->breadcrumb[] = array( $url, $member->name );

			/* Init Table */
			$ips = $member->ipAddresses();
			
			$table = new \IPS\Helpers\Table\Custom( $ips, $url );
			$table->langPrefix		= 'members_iptable_';
			$table->mainColumn		= 'ip';
			$table->sortBy			= $table->sortBy ?: 'last';
			$table->quickSearch		= 'ip';
			$table->rowsTemplate	= array( \IPS\Theme::i()->getTemplate( 'modcp', 'core' ), 'ipMemberRows' );
			$table->tableTemplate	= array( \IPS\Theme::i()->getTemplate( 'modcp', 'core' ), 'ipMemberTable' );
			$table->extra			= $member;
			
			/* Parsers */
			$table->parsers = array(
				'first'			=> function( $val )
				{
					return \IPS\DateTime::ts( $val )->localeDate();
				},
				'last'			=> function( $val )
				{
					return \IPS\DateTime::ts( $val )->localeDate();
				},
			);
			
			/* Buttons */
			$table->rowButtons = function( $row )
			{
				return array(
					'view'	=> array(
						'icon'		=> 'search',
						'title'		=> 'see_uses',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=ip_tools&ip=' ) . $row['ip'],
					),
				);
			};

			\IPS\Output::i()->output		= $table;
		}
		else
		{
			$form = new \IPS\Helpers\Form( 'form', 'continue' );
			$form->class = 'ipsForm_vertical';
			
			$form->add( new \IPS\Helpers\Form\Text( 'ip_address', NULL, TRUE, array(), function( $val )
			{
				if( trim( $val, '*' ) == '' )
				{
					throw new \DomainException('not_just_asterisk');
				}
			} ) );
			
			if ( $values = $form->values() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=ip_tools", 'front', 'modcp_ip_tools' )->setQueryString( 'ip', $values['ip_address'] ) );
			}

			$members = new \IPS\Helpers\Form( 'members', 'continue' );
			$members->class = 'ipsForm_vertical';
			$members->add( new \IPS\Helpers\Form\Member( 'ip_username', NULL, TRUE ) );
			
			if ( $values = $members->values() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=ip_tools' )->setQueryString( 'id', $values['ip_username']->member_id ) );
			}
		
			return \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' )->iptools( $form, $members );
		}
	}
}