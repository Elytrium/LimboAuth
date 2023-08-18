<?php
/**
 * @brief		Member History Table
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		7 Dec 2016
 */

namespace IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member History Model
 */
class _History extends \IPS\Helpers\Table\Db
{
	/**
	 * @brief	Parser extensions
	 */
	static $extensions = NULL;

	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url			The URL the table will be displayed on
	 * @param	mixed			$where			WHERE clause
	 * @param	bool			$showIp			If the IP address column should be included
	 * @param	bool			$showMember		If the customer column should show
	 * @param	bool			$showApp		If the app column should show
	 * @param	bool			$showType		If the type column should show
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url, $where, $showIp=TRUE, $showMember=FALSE, $showApp=TRUE, $showType=FALSE )
	{
		parent::__construct( 'core_member_history', $url, $where );

		$this->include = array();
		if( static::$extensions === NULL )
		{
			$apps = \IPS\Application::appsWithExtension( 'core', 'MemberHistory' );

			foreach( $apps as $application )
			{
				$result = $application->extensions( 'core', 'MemberHistory' );

				/* Since we're parsing, we only want to have one extension */
				static::$extensions[ $application->directory ] = array_pop( $result );
			}
		}

		if( $showType )
		{
			$this->include[] = 'log_type';
		}

		/* This is here specifically so that log_type is always shown first, if required */
		$this->include = array_merge( $this->include, array( 'log_date', 'log_data' ) );

		if ( $showIp )
		{
			$this->include[] = 'log_ip_address';
		}
		if ( $showMember )
		{
			$this->include[] = 'log_member';
		}

		$options	= array();
		$extensions	= static::$extensions;

		foreach( $extensions as $extension )
		{
			if( method_exists( $extension, 'getTypes' ) )
			{
				foreach( $extension->getTypes() as $type )
				{
					if ( $type === 'oauth' and \IPS\Db::i()->select( 'COUNT(*)', 'core_oauth_clients', array( \IPS\Db::i()->findInSet( 'oauth_grant_types', array( 'authorization_code', 'implicit', 'password' ) ) ) )->first() === 1 )
					{
						foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_oauth_clients', array( \IPS\Db::i()->findInSet( 'oauth_grant_types', array( 'authorization_code', 'implicit', 'password' ) ) ) ), 'IPS\Api\OAuthClient' ) as $client )
						{
							$options[ $type ] = $client->_title;
							continue 2;
						}
					}
					
					$options[ $type ] = 'log_type_title_' . $type;
				}
			}
		}
		
		$this->advancedSearch = array(
			'log_ip_address'		=> \IPS\Helpers\Table\SEARCH_QUERY_TEXT,
			'log_date'				=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'log_type'				=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => $options, 'multiple' => TRUE ) )
		);

		$this->sortBy = $this->sortBy ?: 'log_date';
		$this->noSort = array( 'log_type', 'log_ip_address', 'log_data' );
		$this->rowClasses = array( 'log_data' => array( 'ipsTable_wrap' ) );
		$this->parsers = array(
			'log_type'	=> function( $val, $row ) use ( $extensions )
			{
				try
				{
					if( method_exists( $extensions[ $row['log_app'] ], 'parseLogType' ) )
					{
						return $extensions[ $row['log_app'] ]->parseLogType( $val, $row );
					}
				}
				catch( \Throwable $e )
				{
					\IPS\Log::log( $e, 'member_history' );
				}

				return $val;
			},
			'log_date'	=> function( $val )
			{
				return \IPS\DateTime::ts( $val );
			},
			'log_ip_address'	=> function( $val )
			{
				return "<a href='" . \IPS\Http\Url::internal( "app=core&module=members&controller=ip&ip={$val}" ) . "'>{$val}</a>";
			},
			'log_member'=> function( $val, $row ) use ( $extensions )
			{
				try
				{
					if( method_exists( $extensions[ $row['log_app'] ], 'parseLogMember' ) )
					{
						return $extensions[ $row['log_app'] ]->parseLogMember( $val, $row );
					}
				}
				catch( \Throwable $e )
				{
					\IPS\Log::log( $e, 'member_history' );
				}

				$member = \IPS\Member::load( $val );
				return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $member, 'tiny' ) . ' ' . $member->link();
			},
			'log_data'	=> function( $val, $row ) use ( $extensions )
			{
				try
				{
					if( method_exists( $extensions[ $row['log_app'] ], 'parseLogData' ) )
					{
						return $extensions[ $row['log_app'] ]->parseLogData( $val, $row );
					}
				}
				catch( \Throwable $e )
				{
					\IPS\Log::log( $e, 'member_history' );
					return $val; # Return the value so the admin may have some clue as to what the log entry was for
				}

				return '';
			}
		);
	}
}