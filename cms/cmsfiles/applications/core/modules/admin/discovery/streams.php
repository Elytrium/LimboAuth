<?php
/**
 * @brief		streams
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 Jul 2015
 */

namespace IPS\core\modules\admin\discovery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * streams
 */
class _streams extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\core\Stream';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'streams_manage' );
		parent::execute();
	}
	
	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		\IPS\Output::i()->sidebar['actions'] = array(
			'rebuildIndex'	=> array(
				'title'		=> 'all_activity_stream_settings',
				'icon'		=> 'cog',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=discovery&controller=streams&do=allActivitySettings' ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('all_activity_stream_settings') )
			),
			'rebuildDefault'	=> array(
				'title'		=> 'restore_default_streams',
				'icon'		=> 'cog',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=discovery&controller=streams&do=restoreDefaultStreams' )->csrf(),
				'data'		=> array( 'confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack('restore_default_streams_confirm') )
			),
		);
		
		return parent::manage();
	}
	
	/**
	 * Restores default streams
	 *
	 * @return	void
	 */
	protected function restoreDefaultStreams()
	{
		\IPS\Session::i()->csrfCheck();
		
		$schema	= json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/core/data/schema.json" ), TRUE );
		
		/* Get the default language strings */
		if( file_exists( \IPS\ROOT_PATH . "/applications/core/data/lang.xml" ) )
		{			
			/* Open XML file */
			$dom = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
			$dom->load( \IPS\ROOT_PATH . "/applications/core/data/lang.xml" );

			$xp  = new \DomXPath( $dom );
			
			$results = $xp->query('//language/app/word[contains(@key, "stream_title_")]');
			$defaultLanguages = array();
			
			foreach( $results as $lang )
			{
				$defaultLanguages[ str_replace( 'stream_title_', '', $lang->getAttribute('key') ) ] = $lang->nodeValue;
			}
		}
		
		foreach ( $schema['core_streams']['inserts'] as $insertData )
		{
			try
			{
				$newId = \IPS\Db::i()->replace( 'core_streams', $insertData, TRUE );
				$oldId = $insertData['id'];
				
				if ( $oldId and $newId )
				{
					\IPS\Lang::saveCustom( 'core', "stream_title_{$newId}", $defaultLanguages[ $oldId ] );
				}
			}
			catch( \IPS\Db\Exception $e )
			{}
		}
		
		\IPS\Session::i()->log( 'acplog__streams_restored' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=discovery&controller=streams' ), 'restore_default_streams_restored' );
	}
	
	/**
	 * All Activity Stream Settings
	 *
	 * @return	void
	 */
	protected function allActivitySettings()
	{
		$types = array( 'register', 'follow_member', 'follow_content', 'photo' );
		if ( \IPS\Settings::i()->reputation_enabled )
		{
			$types[] = 'like';
		}
		if ( \IPS\Settings::i()->clubs )
		{
			$types[] = 'clubs';
		}

		/* Extensions */
		foreach ( \IPS\Application::allExtensions( 'core', 'StreamItems', TRUE, 'core' ) as $key => $extension )
		{
			$extensionKey = mb_strtolower( $key );
			$settingKey = "all_activity_{$extensionKey}";

			/* Only add the option if a setting for it exists - the setting must be defined by the application the extension is for. */
			if( isset( \IPS\Settings::i()->$settingKey ) )
			{
				$types[] = mb_strtolower( $key );
			}
		}
		
		$options = array();
		$currentValuesStream = array();
		foreach ( $types as $k )
		{
			$key = "all_activity_{$k}";
			if ( \IPS\Settings::i()->$key )
			{
				$currentValuesStream[] = $k;
			}
			$options[ $k ] = ( $k == 'like' and !\IPS\Content\Reaction::isLikeMode() ) ? 'all_activity_react' : $key;
		}
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'all_activity_extra_stream', $currentValuesStream, FALSE, array( 'options' => $options ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'activity_stream_rss', \IPS\Settings::i()->activity_stream_rss ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'activity_stream_subscriptions', \IPS\Settings::i()->activity_stream_subscriptions, FALSE, ['togglesOn' => ['activity_stream_subscriptions_max', 'activity_stream_subscriptions_inactive_limit' ] ] ) );
		$form->add( new \IPS\Helpers\Form\Number( 'activity_stream_subscriptions_max', \IPS\Settings::i()->activity_stream_subscriptions_max, TRUE, ['min' => 1, 'max' => \IPS\CIC ? 10:NULL ], NULL, NULL, NULL,'activity_stream_subscriptions_max' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'activity_stream_subscriptions_inactive_limit', \IPS\Settings::i()->activity_stream_subscriptions_inactive_limit, TRUE, [ ], NULL, NULL, NULL,'activity_stream_subscriptions_inactive_limit' ) );


		if ( $values = $form->values() )
		{
			$toSave = array();
			foreach ( $types as $k )
			{
				$toSave[ "all_activity_{$k}" ] = \intval( \in_array( $k, $values['all_activity_extra_stream'] ) );
			}

			$toSave[ 'activity_stream_rss' ] = $values['activity_stream_rss'];
			$toSave[ 'activity_stream_subscriptions' ] = $values['activity_stream_subscriptions'];
			$toSave[ 'activity_stream_subscriptions_max' ] = $values['activity_stream_subscriptions_max'];
			
			\IPS\Session::i()->Log( 'acplog__all_activity_settings' );

			$form->saveAsSettings( $toSave );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=discovery&controller=streams' ), 'saved' );
		}
		
		\IPS\Output::i()->output = $form;
	}
}