<?php
/**
 * @brief		Emoticons
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		02 May 2013
 */

namespace IPS\core\modules\admin\editor;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Emoticons
 */
class _emoticons extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_manage' );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customization/emoticons.css', 'core', 'admin' ) );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tabs = array(
			'standard'	=> 'standard_emoji',
			'custom'	=> 'custom_emoji',
		);
		$activeTab = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'standard';
		
		if ( $activeTab == 'standard' )
		{
			if ( \IPS\Settings::i()->getFromConfGlobal('sql_utf8mb4') === TRUE )
			{
				$form = new \IPS\Helpers\Form;
				$form->add( new \IPS\Helpers\Form\Radio( 'emoji_style', \IPS\Settings::i()->emoji_style, FALSE, array(
					'options' => array(
						'native'	=> 'emoji_style_native',
						'twemoji'	=> 'emoji_style_twemoji',
						'disabled'	=> 'emoji_style_disabled',
					),
				) ) );
				\IPS\Member::loggedIn()->language()->get( 'emoji_style_native' ); // We need to preload the word before we can add more text to it.
				\IPS\Member::loggedIn()->language()->words['emoji_style_native'] .= "<br><div class='ipsType_large ipsSpacer_top ipsSpacer_half'><span class='ipsEmoji'>😀</span><span class='ipsEmoji'>😉</span><span class='ipsEmoji'>😂</span><span class='ipsEmoji'>😍</span><span class='ipsEmoji'>🤘</span><span class='ipsEmoji'>🤦‍♀️</span><span class='ipsEmoji'>🤷‍♂️</span><span class='ipsEmoji'>🍿</span><span class='ipsEmoji'>🚀</span><span class='ipsEmoji'>🎉</span><span class='ipsEmoji'>🏳️‍🌈</span></div>"; // Have to do this here because if we try to put it in the actual language string, that will cause an error if not utf8mb4
				$form->add( new \IPS\Helpers\Form\YesNo( 'emoji_shortcodes', \IPS\Settings::i()->emoji_shortcodes, FALSE, array(), NULL, NULL, NULL, 'emoji_shortcodes' ) );
				$form->add( new \IPS\Helpers\Form\YesNo( 'emoji_ascii', \IPS\Settings::i()->emoji_ascii, FALSE, array(), NULL, NULL, NULL, 'emoji_ascii' ) );
				if ( $values = $form->values() )
				{
					$values['emoji_cache'] = time();
					$form->saveAsSettings( $values );
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons&tab=standard' ), 'saved' );
				}
				
				$activeTabContents = $form;
			}
			else
			{
				$activeTabContents = \IPS\Theme::i()->getTemplate( 'global' )->message( \IPS\CIC ? 'emoji_utf8mb4_required_cic' : 'emoji_utf8mb4_required', 'error', NULL, TRUE, TRUE );
			}
		}
		else
		{
			$activeTabContents = \IPS\Theme::i()->getTemplate( 'customization' )->emoticons( $this->_getEmoticons() );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_editor_emoticons');
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('admin_customization.js', 'core', 'admin') );
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=editor&controller=emoticons" ) );
		}
	}
	
	/**
	 * Add
	 *
	 * @return	void
	 */
	protected function add()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_add' );
	
		$groups = iterator_to_array( \IPS\Db::i()->select( "emo_set, CONCAT( 'core_emoticon_group_', emo_set ) as emo_set_name", 'core_emoticons', null, null, null, 'emo_set' )->setKeyField('emo_set')->setValueField('emo_set_name') );

		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Upload( 'emoticons_upload', NULL, TRUE, array( 'multiple' => TRUE, 'image' => TRUE, 'storageExtension' => 'core_Emoticons', 'storageContainer' => 'emoticons', 'obscure' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'emoticons_add_group', 'create', TRUE, array(
			'options'	=> array( 'create' => 'emoticons_add_create', 'existing' => 'emoticons_add_existing' ),
			'toggles'	=> array( 'create' => array( 'emoticons_add_newgroup' ), 'existing' => array( 'emoticons_add_choosegroup' ) ),
			'disabled'	=> empty($groups)
		) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'emoticons_add_newgroup', NULL, FALSE, array(), function( $value )
		{
			if ( \IPS\Request::i()->emoticons_add_group === 'create' )
			{
				foreach ( \IPS\Lang::languages() as $lang )
				{
					if ( $lang->default )
					{
						if( ! $value[ $lang->id ] )
						{		
							throw new \InvalidArgumentException('form_required');
						}
					}
				}
			}
		}, NULL, NULL, 'emoticons_add_newgroup' ) );
		
		if ( !empty( $groups ) )
		{
			$form->add( new \IPS\Helpers\Form\Select( 'emoticons_add_choosegroup', NULL, FALSE, array( 'options' => $groups ), NULL, NULL, NULL, 'emoticons_add_choosegroup' ) );
		}
		
		if ( $values = $form->values() )
		{
			if ( $values['emoticons_add_group'] === 'create' )
			{
				$position = 0;
				$setId = mt_rand();
				\IPS\Lang::saveCustom( 'core', "core_emoticon_group_{$setId}", $values['emoticons_add_newgroup'] );
                \IPS\Session::i()->log( 'acplog__emoticon_group_created', array( "core_emoticon_group_{$setId}" => TRUE ) );
			}
			else
			{
				$setId = $values['emoticons_add_choosegroup'];
				$position = \IPS\Db::i()->select( 'MAX(emo_position)', 'core_emoticons', array( 'emo_set=?', $setId ) )->first( );
			}
					
			if ( !\is_array( $values['emoticons_upload'] ) )
			{
				$values['emoticons_upload'] = array( $values['emoticons_upload'] );
			}
			
			$inserts = array();
			$images2x = array();
			foreach ( $values['emoticons_upload'] as $file )
			{
				/* Is it "retina" */
				if( \mb_stristr( $file->filename, '@2x' ) )
				{
					$filename_2x = preg_replace( "/^(.+?)\.[0-9a-f]{32}(?:\..+?)$/i", "$1", str_replace( '@2x', '', $file->filename ) );

					$images2x[ $this->_getRawFilename( $filename_2x ) ] = (string) $file;
					continue;
				}

				$filename	= preg_replace( "/^(.+?)\.[0-9a-f]{32}(?:\..+?)$/i", "$1", $file->filename );

				$inserts[] = array(
					'typed'			=> ':' . preg_replace( "#\s#", "", $this->_getRawFilename( $filename ) ) . ':',
					'image'			=> (string) $file,
					'clickable'		=> TRUE,
					'emo_set'		=> $setId,
					'emo_position'	=> ++$position,
				);
			}

			if( \count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'core_emoticons', $inserts );
			}

			/* Add 2x */
			if( \count( $images2x ) )
			{
				foreach( \IPS\Db::i()->select( '*', 'core_emoticons', array( 'emo_set=?', $setId ) ) as $emo )
				{
					$file = \IPS\File::get( 'core_Emoticons', $emo['image'] );
					$filename = $this->_getRawFilename( $file->filename );

					/* There isn't an original for the 2x emo */
					if( !isset( $images2x[ $filename ] ) )
					{
						continue;
					}

					/* Get the dimensions of the smaller emoticon */
					$imageDimensions = $file->getImageDimensions();

					\IPS\Db::i()->update( 'core_emoticons', array(
						'image_2x' => $images2x[ $filename ],
						'width' => $imageDimensions[0],
						'height' => $imageDimensions[1]
					), 'id=' . $emo['id'] );

					unset( $images2x[ $filename ] );
				}

				/* Delete any unused 2x files */
				foreach( $images2x as $img )
				{
					\IPS\File::get( 'core_Emoticons', $img )->delete();
				}
			}

			\IPS\Settings::i()->changeValues( array( 'emoji_cache' => time() ) );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

            \IPS\Session::i()->log( 'acplog__emoticons_added', array( "core_emoticon_group_{$setId}" => TRUE ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons&tab=custom' ), 'saved' );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'emoticons_add', $form, FALSE );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	protected function edit()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_edit' );
		\IPS\Session::i()->csrfCheck();

		$position = 0;
		$set = NULL;
		
		if ( \IPS\Request::i()->isAjax() )
		{
			$i = 1;
			if ( isset( \IPS\Request::i()->setOrder ) )
			{
				foreach ( \IPS\Request::i()->setOrder as $set )
				{
					$set = preg_replace( '/^core_emoticon_group_/', '', $set );
					
					\IPS\Db::i()->update( 'core_emoticons', array( 'emo_set_position' => $i ), array( 'emo_set=?', $set ) );
					$i++;
				}
			}
			else
			{			
				$emoticons	= $this->_getEmoticons( TRUE );
				$setPos		= 1;
				
				foreach ( $emoticons as $group => $emos )
				{
					if( isset( \IPS\Request::i()->$group ) AND \is_array( \IPS\Request::i()->$group ) )
					{
						foreach( \IPS\Request::i()->$group as $id )
						{
							\IPS\Db::i()->update( 'core_emoticons', array( 'emo_position' => $i, 'emo_set_position' => $setPos, 'emo_set' => str_replace( 'core_emoticon_group_', '', $group ) ), array( 'id=?', $id ) );
							$i++;
						}
					}

					$setPos++;
				}
			}
			
			\IPS\Settings::i()->changeValues( array( 'emoji_cache' => time() ) );
			
			\IPS\Session::i()->log( 'acplog__emoticons_edited' );
			
			\IPS\Output::i()->json( 'OK' );
			return;
		}

		// Do we need to unsquash any values?
		// Squashed values are json_encoded by javascript to prevent us exceeding max_post_vars		
		// If 'squashedField' isn't in the request it might indicate the user didn't have JS enabled
		if ( isset( \IPS\Request::i()->emoticons_squashed ) )
		{
			if ( isset( \IPS\Request::i()->emoticons_squashed ) )
			{
				$unsquashed = json_decode( \IPS\Request::i()->emoticons_squashed, TRUE );
				
				foreach( $unsquashed as $key => $value )
				{
					\IPS\Request::i()->$key = $value;
				}

				unset( \IPS\Request::i()->emoticons_squashed );
			}
		}

		$emoticons = $this->_getEmoticons( FALSE );

		foreach ( \IPS\Request::i()->emo as $id => $data )
		{
			if ( isset( $emoticons[ $id ] ) )
			{
				if ( !$data['name'] )
				{
					continue;
				}

				if ( $emoticons[ $id ]['typed'] !== $data['name'] )
				{
					$save = array( 'typed' => preg_replace( "#\s#", "", $data['name'] ) );

					if ( $set !== NULL )
					{
						$save['emo_set'] = str_replace( 'core_emoticon_group_', '', $data['set'] );
					}

					\IPS\Db::i()->update( 'core_emoticons', $save, array( 'id=?', $id ) );
				}
			}
		}

		\IPS\Settings::i()->changeValues( array( 'emoji_cache' => time() ) );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

        \IPS\Session::i()->log( 'acplog__emoticons_edited' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons&tab=custom' ), 'saved' );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$emoticon = \IPS\Db::i()->select( '*', 'core_emoticons', array( 'id=?', \IPS\Request::i()->id ) )->first();
			if ( $emoticon['id'] )
			{
				\IPS\File::get( 'core_Emoticons', $emoticon['image'] )->delete();
				\IPS\File::get( 'core_Emoticons', $emoticon['image_2x'] )->delete();
			}

			\IPS\Db::i()->delete( 'core_emoticons', array( 'id=?', (int) \IPS\Request::i()->id ) );

			/* delete the group name, if there are no other emoticons in this group */
			$emoticons = \IPS\Db::i()->select( 'COUNT(*) as count', 'core_emoticons', array( 'emo_set =?', $emoticon['emo_set'] ) )->first();

			if ( $emoticons == 0 )
			{
				\IPS\Lang::deleteCustom( 'core', 'core_emoticon_group_'. $emoticon['emo_set'] );
			}

	        \IPS\Session::i()->log( 'acplog__emoticon_deleted' );

			\IPS\Settings::i()->changeValues( array( 'emoji_cache' => time() ) );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
		}
		catch ( \UnderflowException $e ) { }

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons&tab=custom' ), 'saved' );
	}
	
	/**
	 * Delete set
	 *
	 * @return	void
	 */
	public function deleteSet()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		$set = preg_replace( '/^core_emoticon_group_/', '', \IPS\Request::i()->key );
		
		foreach ( \IPS\Db::i()->select( '*', 'core_emoticons', array( 'emo_set=?', $set ) ) as $emoticon )
		{
			try
			{
				\IPS\File::get( 'core_Emoticons', $emoticon['image'] )->delete();
				\IPS\File::get( 'core_Emoticons', $emoticon['image_2x'] )->delete();
			}
			catch ( \UnderflowException $e ) { }
		}
		
		\IPS\Db::i()->delete( 'core_emoticons', array( 'emo_set=?', $set ) );
		\IPS\Lang::deleteCustom( 'core', 'core_emoticon_group_'. $set );

		\IPS\Settings::i()->changeValues( array( 'emoji_cache' => time() ) );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();
		
		\IPS\Session::i()->log( 'acplog__emoticon_set_deleted' );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons&tab=custom' ), 'saved' );
	}

	/**
	 * Edit group title
	 *
	 * @return	void
	 */
	protected function editTitle()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_edit' );

		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical ipsForm_fullWidth';
		$form->add( new \IPS\Helpers\Form\Translatable( 'emoticons_add_newgroup', NULL, FALSE, array( 'app' => 'core', 'key' => \IPS\Request::i()->key ), NULL, NULL, NULL, 'emoticons_add_newgroup' ) );
		
		if ( $values = $form->values() )
		{
			\IPS\Lang::saveCustom( 'core', \IPS\Request::i()->key, $values['emoticons_add_newgroup'] );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons&tab=custom' ), 'saved' );
		}
		
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $form;
			return;
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'emoticons_edit_groupname', $form, FALSE );
	}

	/**
	 * Get Emoticons
	 *
	 * @param	bool	$group	Group by their group?
	 * @return	array
	 */
	protected function _getEmoticons( $group=TRUE )
	{
		$emoticons = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_emoticons', NULL, 'emo_set_position,emo_position' ) as $row )
		{			
			if ( $group )
			{
				$emoticons[ 'core_emoticon_group_' . $row['emo_set'] ][ $row['id'] ] = $row;
			}
			else
			{
				$emoticons[ $row['id'] ] = $row;
			}
		}
		
		return $emoticons;
	}

	/**
	 * Returns the filename and extension for given emoticon path
	 *
	 * @param	string		$path		Emoticon path
	 * @return	array
	 */
	protected function _getRawFilename( $path )
	{
		$parts = explode( '/', $path );
		$filenamePart = array_pop( $parts );
		$filename = mb_substr( $filenamePart, 0, mb_strrpos( $filenamePart, '.' ) );

		return $filename;
	}
}