<?php
/**
 * @brief		Visual Theme Editor
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		7 Nov 2013
 */
 
namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Visual Language Editor
 */
class _vse extends \IPS\Dispatcher\Controller
{
	/**
	 * Shows the VSE
	 *
	 * @return void
	 */
	public function show()
	{
		if ( ! \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_easy_editor' ) OR ! \IPS\Member::loggedIn()->isAdmin() OR ! \IPS\Member::loggedIn()->members_bitoptions['bw_using_skin_gen'] )
		{
			\IPS\Output::i()->error( 'core_theme_cant_easy_mode', '2S164/1', 403, '' );
		}
		
		$theme = null;
		
		if ( isset( \IPS\Request::i()->cookie['vseThemeId'] ) and \IPS\Request::i()->cookie['vseThemeId'] )
		{
			try
			{
				$theme = \IPS\Theme::load( \intval( \IPS\Request::i()->cookie['vseThemeId'] ) );
				
				if ( ! $theme->by_skin_gen )
				{
					$theme = null;
				}
			}
			catch( \OutOfRangeException $ex )
			{
				$theme = null;
			}
		}
		
		if ( $theme === null )
		{
			\IPS\Output::i()->error( 'core_theme_cant_easy_mode', '2S164/2', 403, '' );
		}
		
		/* CSS */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'framework.css', 'core', 'front' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/vse.css', 'core', 'front' ) );
		
		/* JS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'library.js' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'js/jslang.php?langId=' . \IPS\Member::loggedIn()->language()->id, 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'framework.js' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'app.js' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'vse/vsedata.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_vse.js', 'core', 'front' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/diff_match_patch.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/codemirror.js', 'core', 'interface' ) );
  		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'codemirror/codemirror.css', 'core', 'interface' ) );
		
		$css       = $theme->getRawCss( 'core', 'front', 'custom', \IPS\Theme::RETURN_ALL, true );
		$customCss = null;

		if ( isset( $css['core']['front']['custom']['custom.css'] ) )
		{
			/* Custom CSS */
			$functionName = "css_" . mt_rand();
			\IPS\Theme::makeProcessFunction( \IPS\Theme::fixResourceTags( $css['core']['front']['custom']['custom.css']['css_content'], 'front' ), $functionName, '', FALSE, TRUE );
			$themeFunction = 'IPS\\Theme\\'. $functionName;
			$customCss = $themeFunction();
		}
		
		$form			  = new \IPS\Helpers\Form( 'form', NULL );
		$settings 		  = $theme->getThemeSettings();
		$settingKeyValues = array();
		$colorValues 	  = array();

		foreach( $settings as $key => $data )
		{
			$settingKeyValues[ $key ] = $data['_value'];

			if( $data['sc_type'] === 'Color' )
			{
				if( preg_match( '/^#?[0-9a-fA-F]{6}$/', $data['_value'] ) )
				{
					$colorValue = str_replace('#', '', $data['_value']);
					$rgb = array();

					if( \strlen( $colorValue ) === 3 )
					{
						$rgb[] = hexdec( \substr( $colorValue, 0, 1 ) . \substr( $colorValue, 0, 1 ) );
						$rgb[] = hexdec( \substr( $colorValue, 1, 1 ) . \substr( $colorValue, 1, 1 ) );
						$rgb[] = hexdec( \substr( $colorValue, 2, 1 ) . \substr( $colorValue, 2, 1 ) );
					}
					else
					{
						$rgb[] = hexdec( \substr( $colorValue, 0, 2 ) );
						$rgb[] = hexdec( \substr( $colorValue, 2, 2 ) );
						$rgb[] = hexdec( \substr( $colorValue, 4, 2 ) );
					}
					
					$colorValues[ $key ] = implode( ', ', $rgb );
				}
			}
			
			if ( $data['sc_show_in_vse'] )
			{
				$value           = ( isset( $data['sv_value'] ) ) ? $data['sv_value'] : $data['sc_default'];
				$data['sc_type'] = ( empty( $data['sc_type'] ) )  ? 'Text'            : $data['sc_type'];
				
				$class = '\IPS\Helpers\Form\\' . $data['sc_type'];
				
				$options = array();
				switch ( $data['sc_type'] )
				{
					case 'Text':
					case 'TextArea':
					
					case 'Select':
						$options['sc_multiple'] = $data['sc_multiple'];
						// No break
					case 'Radio':
						$content = json_decode( $data['sc_content'], TRUE );
						
						$options = array();
						if( \is_array( $content ) )
						{
							foreach( $content as $_content )
							{
								$options[ $_content['key'] ] = $_content['value'];
							}
						}

						$options['options'] = $options;
						break;
				}
				if ( class_exists( $class ) )
				{
					$field = new $class( "core_theme_setting_title_{$data['sc_id']}", $value, false, $options );
					$field->label = \IPS\Member::loggedIn()->language()->addToStack( $data['sc_title'], FALSE, array( 'escape' => true ) );
					$form->add( $field );
				}
				else
				{
					\IPS\Log::log( 'VSE tried to load ' . $class . ' for theme setting ' . $key, 'vse_error' );
				}
			}
		}

		/* Template */
		/* @note The first param used to be the VSE CSS, however as of 4.5 we handle the VSE differently and it is no longer used */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'vse', 'core', 'front' )->globalTemplate( NULL, $customCss, $settingKeyValues, (string) $form, $theme->skin_gen_data, $colorValues );
		
		\IPS\Output::i()->sendOutput( \IPS\Output::i()->output, 200, 'text/html' );
	}
	
	/**
	 * Close the VSE
	 *
	 * @return	void
	 */
	public function close()
	{
		\IPS\Session::i()->csrfCheck();

		/* Update the current member */
		\IPS\Member::loggedIn()->members_bitoptions['bw_using_skin_gen'] = 0;
		\IPS\Member::loggedIn()->save();
			
		\IPS\Request::i()->setCookie( 'vseThemeId', 0 );
		
		\IPS\Output::i()->json( 'ok' );
	}
	
	/**
	 * Home
	 *
	 * @return	void
	 */
	protected function home()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Member::loggedIn()->skin = (int) \IPS\Request::i()->id;
		\IPS\Member::loggedIn()->save();
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ) );
	}
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function build()
	{
		if ( ! \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_easy_editor' ) OR ! \IPS\Member::loggedIn()->isAdmin() OR ! \IPS\Member::loggedIn()->members_bitoptions['bw_using_skin_gen'] )
		{
			\IPS\Output::i()->json( 'NO_PERMISSION', 403 );
		}

		\IPS\Session::i()->csrfCheck();
		
		$theme = null;
		
		if ( isset( \IPS\Request::i()->cookie['vseThemeId'] ) )
		{
			$theme = \IPS\Theme::load( \intval( \IPS\Request::i()->cookie['vseThemeId'] ) );
			
			if ( ! $theme->by_skin_gen )
			{
				$theme = null;
			}
		}
		
		if ( $theme === null )
		{
			\IPS\Output::i()->json( 'NO_PERMISSION', 403 );
		}
						
		$theme->vseSave( \IPS\Request::i()->colors, \IPS\Request::i()->customcss, \IPS\Request::i()->settings );
		
		\IPS\File::getClass('core_Theme')->deleteContainer( 'css_built_' . $theme->id );
		$theme->css_map = array();
		$theme->save();
		foreach( $theme->children() as $child )
		{
			\IPS\File::getClass('core_Theme')->deleteContainer( 'css_built_' . $child->id );
			$child->css_map = array();
			$child->save();
		}
	
		\IPS\Output::i()->json( array( 'status' => 'ok', 'theme_set_id' => $theme->id ) );
	}
}