<?php
/**
 * @brief		guestSignUp Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Mar 2017
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * guestSignUp Widget
 */
class _guestSignUp extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'guestSignUp';

	/**
	 * @brief Language String Key used to store the editor content
	 */
	public static $editorKey = 'block_guestsignup_message';

	/**
	 * @brief Language Key used to save the content
	 */
	public static $editorLangKey = 'widget_guestsignup_text';


	/**
	 * @brief	App
	 */
	public $app = 'core';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Constructor
	 *
	 * @param	string				$uniqueKey				Unique key for this specific instance
	 * @param	array				$configuration			Widget custom configuration
	 * @param	null|string|array	$access					Array/JSON string of executable apps (core=sidebar only, content=IP.Content only, etc)
	 * @param	null|string			$orientation			Orientation (top, bottom, right, left)
	 * @return	void
	 */
	public function __construct( $uniqueKey, array $configuration, $access=null, $orientation=null )
	{
		parent::__construct($uniqueKey, $configuration, $access, $orientation);
		$this->errorMessage = 'guest_signup_admin_message';
	}

		/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
		$form = parent::configuration( $form );

 		$form->add( new \IPS\Helpers\Form\Translatable( 'block_guestsignup_title', NULL, TRUE, array( 'app'=>'core', 'key' =>'widget_guestsignup_title' ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'block_guestsignup_message', NULL, TRUE, array(
			'app'			=> 'core',
			'key'			=> static::$editorLangKey,
			'editor'		=> array(
				'app'			=> 'core',
				'key'			=> 'Widget',
				'autoSaveKey' 	=> 'widget-' . $this->uniqueKey,
				'attachIds'	 	=> isset( $this->configuration['content'] ) ? array( 0, 0, static::$editorLangKey ) : NULL
			),
		) ) );
		return $form;
 	}

	/**
	 * Before the widget is removed, we can do some clean up
	 *
	 * @return void
	 */
	public function delete()
	{
		\IPS\Lang::deleteCustom(  'core', 'widget_guestsignup_title' );
		\IPS\Lang::deleteCustom(  'core', 'widget_guestsignup_text' );

		foreach( \IPS\Db::i()->select( '*', 'core_attachments_map', array( array( 'location_key=?', 'core_GuestSignupWidget' ) ) ) as $map )
		{
			try
			{
				$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_id=?', $map['attachment_id'] ) )->first();

				\IPS\Db::i()->delete( 'core_attachments_map', array( array( 'attachment_id=?', $attachment['attach_id'] ) ) );
				\IPS\Db::i()->delete( 'core_attachments', array( 'attach_id=?', $attachment['attach_id'] ) );


				\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();
				if ( $attachment['attach_thumb_location'] )
				{
					\IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->delete();
				}
			}
			catch ( \Exception $e ) { }
		}
	}
 	
 	 /**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
		\IPS\File::claimAttachments( 'widget-' . $this->uniqueKey, 0, 0, 'widget_guestsignup_text' );
		\IPS\Lang::saveCustom( 'core', static::$editorLangKey, $values[ 'block_guestsignup_message' ] );
		\IPS\Lang::saveCustom( 'core', 'widget_guestsignup_title', $values[ 'block_guestsignup_title' ] );
 		return $values;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		/* Show this only to guests */
		if ( \IPS\Member::loggedIn()->member_id  )
		{
			return '';
		}
		else
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( 'widget_guestsignup_title' );
			$text = \IPS\Member::loggedIn()->language()->addToStack( 'widget_guestsignup_text' );
			if ( !\IPS\Member::loggedIn()->language()->checkKeyExists( 'widget_guestsignup_title' ) )
			{
				return '';
			}
			
			$login = new \IPS\Login( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' ) );
			return $this->output( $login, $text, $title );
		}
	}
}