<?php
/**
 * @brief		Converter Application Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 * @version		
 */
 
namespace IPS\convert;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Converter Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * Can the user access this application?
	 *
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$memberOrGroup		Member/group we are checking against or NULL for currently logged on user
	 * @return	bool
	 */
	public function canAccess( $memberOrGroup=NULL )
	{
		if ( \IPS\DEMO_MODE === TRUE )
		{
			return FALSE;
		}
		
		return parent::canAccess( $memberOrGroup );
	}
	
	/**
	 * [Node] Get Node Description
	 *
	 * @return	string|null
	 */
	protected function get__description()
	{
		return NULL;
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'random';
	}

	/**
	 * [Node] Get whether or not this node is locked to current enabled/disabled status
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__locked()
	{
		/* We don't allow the application to be disabled since its hooks are required for redirects */
		return TRUE;
	}

	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @param	string	$url	Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = parent::getButtons( $url, $subnode );

		if( isset( $buttons['delete'] ) )
		{
			$buttons['delete']['title']	= 'uninstall';
			$buttons['delete']['data']['delete-message'] = \IPS\Member::loggedIn()->language()->addToStack('converter_uninstall');
			$buttons['delete']['data']['delete-warning'] = \IPS\Member::loggedIn()->language()->addToStack('converter_uninstall_warning');
		}

		return $buttons;
	}

	/**
	 * Install Other
	 *
	 * @return	void
	 */
	public function installOther()
	{
		static::checkConvParent();
		
		try
		{
			\IPS\Db::i()->select( '*', 'core_login_methods', array( "login_classname=?", 'IPS\\convert\\Login' ) )->first();
		}
		catch( \UnderflowException $e )
		{
			$position = \IPS\Db::i()->select( 'MAX(login_order)', 'core_login_methods' )->first();

			$handler = new \IPS\convert\Login;
			$handler->classname = 'IPS\\convert\\Login';
			$handler->order = $position + 1;
			$handler->acp = TRUE;
			$handler->settings = array( 'auth_types' => \IPS\Login::AUTH_TYPE_EMAIL );
			$handler->enabled = TRUE;
			$handler->register = FALSE;
			$handler->save();

			\IPS\Lang::saveCustom( 'core', "login_method_{$handler->id}", 'Converter' );
		}
	}
	
	/**
	 * Ensure the appropriate tables have a conv_parent column for internal references
	 *
	 * @param	string|NULL		$application	The application to check, or NULL to check all.
	 * @return	void
	 */
	public static function checkConvParent( $application=NULL )
	{
		$parents = array(
			'blog'	=> array(
				'tables'	=> array(
					'blog_categories'	=> array(
						'prefix'	=> 'category_',
						'column'	=> 'conv_parent'
					)
				)
			),
			'downloads'	=> array(
				'tables'	=> array(
					'downloads_categories'	=> array(
						'prefix'	=> 'c',
						'column'	=> 'conv_parent'
					)
				)
			),
			'forums'	=> array(
				'tables'	=> array(
					'forums_forums'			=> array(
						'prefix'	=> '',
						'column'	=> 'conv_parent'
					)
				)
			),
			'gallery'	=> array(
				'tables'	=> array(
					'gallery_categories'	=> array(
						'prefix'	=> 'category_',
						'column'	=> 'conv_parent'
					)
				)
			),
			'cms'		=> array(
				'tables'	=> array(
					'cms_containers'			=> array(
						'prefix'	=> 'container_',
						'column'	=> 'conv_parent'
					),
					'cms_database_categories'	=> array(
						'prefix'	=> 'category_',
						'column'	=> 'conv_parent'
					),
					'cms_folders'				=> array(
						'prefix'	=> 'folder_',
						'column'	=> 'conv_parent'
					)
				)
			),
			'nexus'		=> array(
				'tables'	=> array(
					'nexus_alternate_contacts'	=> array(
						'prefix'	=> '',
						'column'	=> 'conv_alt_id',
					),
					'nexus_package_groups'		=> array(
						'prefix'	=> 'pg_',
						'column'	=> 'conv_parent'
					),
					'nexus_packages'			=> array(
						'prefix'	=> 'p_',
						'column'	=> 'conv_associable'
					),
					'nexus_purchases'			=> array(
						'prefix'	=> 'ps_',
						'column'	=> 'conv_parent'
					)
				)
			)
		);
		
		foreach( $parents AS $app => $tables )
		{
			if ( !\is_null( $application ) AND $application != $app )
			{
				continue;
			}
			
			if ( static::appisEnabled( $app ) OR $application === NULL )
			{
				foreach( $tables['tables'] AS $table => $data )
				{
					if ( \IPS\Db::i()->checkForTable( $table ) )
					{
						$column = $data['prefix'] . $data['column'];
						if ( \IPS\Db::i()->checkForColumn( $table, $column ) === FALSE )
						{
							\IPS\Db::i()->addColumn( $table, array(
								'name'		=> $column,
								'type'		=> 'VARCHAR',
								'length'	=> 255,
								'default'	=> '0',
							) );
						}
						else
						{
							$localDefinition = \IPS\Db::i()->getTableDefinition( $table, TRUE );
							if( $localDefinition['columns'][ $column ]['type'] !== 'VARCHAR' )
							{
								\IPS\Db::i()->changeColumn( $table, $column, array(
									'name'		=> $column,
									'type'		=> 'VARCHAR',
									'length'	=> 255,
									'default'	=> '0',
								) );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Check if we need to redirect requests coming in to index.php
	 *
	 * @note	In an ideal world, we'd check the individual converter libraries, however that requires looping over lots of files or
	 *	querying the database on every single page load, so instead we will sniff and see if we think anything needs to be done based
	 *	on hardcoded potential patterns.
	 * @return	void
	 */
	public function convertLegacyParameters()
	{
		$_qs = '';
		if ( isset( $_SERVER['QUERY_STRING'] ) )
		{
			$_qs = $_SERVER['QUERY_STRING'];
		}
		elseif ( isset( $_SERVER['PATH_INFO'] ) )
		{
			$_qs = $_SERVER['PATH_INFO'];
		}
		elseif ( isset( $_SERVER['REQUEST_URI'] ) )
		{
			$_qs = $_SERVER['REQUEST_URI'];
		}

		/* Expression Engine */
		preg_match ( '#(viewforum|viewthread|viewreply|member)\/([0-9]+)#i', $_qs, $matches );
		if( isset( $matches[1] ) AND $matches[1] )
		{
			static::checkRedirects();
		}

		/* Vanilla */
		preg_match ( '#(discussion|profile)\/([0-9]+)\/#i', $_qs, $matches );

		if( isset( $matches[1] ) AND $matches[1] )
		{
			static::checkRedirects();
		}

		/* Xenforo */
		preg_match ( '#(forums|threads|members)\/(.*)\.([0-9]+)#i', $_qs, $matches );

		if( isset( $matches[1] ) AND $matches[1] )
		{
			static::checkRedirects();
		}

		/* SMF */
		if( \IPS\Request::i()->board OR \IPS\Request::i()->topic OR \IPS\Request::i()->action )
		{
			static::checkRedirects();
		}
	}

	/**
	 * Check if we need to redirect
	 *
	 * @return void
	 */
	public static function checkRedirects()
	{
		/* Try each of our converted applications. We will assume the most important conversions were done first */
		foreach( \IPS\convert\App::apps() as $app )
		{
			try
			{
				$redirect	= $app->getSource( TRUE, FALSE )->checkRedirects();
			}
			catch( \InvalidArgumentException $e )
			{
				/* This converter app doesn't exist on disk, this is expected for sites upgraded from 3.x where there isn't a 4.x version of the converter app */
				continue;
			}

			/* Pass the request off to the application to see if it can redirect */
			if( $redirect !== NULL )
			{
				/* We got a valid redirect, so send the user there */
				\IPS\Output::i()->redirect( $redirect, NULL, 301 );
			}
		}
	}
}