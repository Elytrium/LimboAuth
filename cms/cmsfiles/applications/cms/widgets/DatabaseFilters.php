<?php
/**
 * @brief		DatabaseFilters Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		02 Sept 2014
 */

namespace IPS\cms\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * LatestArticles Widget
 */
class _DatabaseFilters extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'DatabaseFilters';
	
	/**
	 * @brief	App
	 */
	public $app = 'cms';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		/* Viewing or adding/editing a record */
		if ( \IPS\cms\Databases\Dispatcher::i()->recordId or \IPS\Request::i()->do == 'form' )
		{
			return '';
		}

		if ( ! \IPS\cms\Databases\Dispatcher::i()->databaseId AND ! \IPS\cms\Databases\Dispatcher::i()->categoryId )
		{
			return '';
		}
		
		try
		{
			$database = \IPS\cms\Databases::load( \IPS\cms\Databases\Dispatcher::i()->databaseId );
			$database->preLoadWords();
		}
		catch ( \OutOfRangeException $e )
		{
			return '';
		}
		
		try
		{
			$category = \IPS\cms\Categories::load( \IPS\cms\Databases\Dispatcher::i()->categoryId );
		}
		catch ( \OutOfRangeException $e )
		{
			return '';
		}
		
		if ( ! $database->use_categories AND $database->cat_index_type !== 0 )
		{
			return '';
		}
		
		$fieldClass = 'IPS\cms\Fields' . $database->id;
		
		$fields = array();
		$cookie = $category->getFilterCookie();
		$cookieValues = ( $cookie !== NULL ) ? array_combine( array_map( function( $k ) { return "field_" . $k; }, array_keys( $cookie ) ), $cookie ) : array();
		$databaseFields = $fieldClass::roots();
		
		$urlValues = array();
		
		foreach( \IPS\Request::i() as $k => $v )
		{
			if( mb_strpos( $k, 'content_field_' ) !== FALSE )
			{
				/* YesNo fields come in as _checkbox */
				if ( mb_substr( $k, -9 ) === '_checkbox' )
				{
					$k = mb_substr( $k, 0, -9 );
				}
				
				$fieldId = str_replace( 'content_field_', '', $k );
				
				if ( isset( $databaseFields[ $fieldId ] ) and $databaseFields[ $fieldId ]->type == 'Member' )
				{
					$urlValues[ str_replace( 'content_', '', $k ) ] = \is_array( $v ) ? implode( '\n', $v ) : $v;
				}
				else
				{
					$urlValues[ str_replace( 'content_', '', $k ) ] = \is_array( $v ) ? implode( ',', $v ) : $v;
				}
			}
		}

		$cookieValues = array_merge( $urlValues, $cookieValues );

		foreach( $databaseFields as $field )
		{
			/* If we pass in what is stored in the database, eg: 1\n20, \IPS\Helpers\Form\Member() actually tries to load via name, not ID */
			if ( $field->type === 'Member' and isset( $cookieValues[ 'field_' . $field->id ] ) )
			{
				$members = array();
				$memberArray = \is_array( $cookieValues[ 'field_' . $field->id ] ) ? $cookieValues[ 'field_' . $field->id ] : explode( '\n', $cookieValues[ 'field_' . $field->id ] );
				foreach( $memberArray as $id )
				{
					try
					{
						$members[] = \IPS\Member::load( $id );
					}
					catch( \Exception $e ) { }
				}
				
				$cookieValues[ 'field_' . $field->id ] = $members;
			}
		}

		foreach( $fieldClass::fields( $cookieValues, 'view', $category, $fieldClass::FIELD_SKIP_TITLE_CONTENT | $fieldClass::FIELD_DISPLAY_FILTERS ) as $id => $field )
		{
			/* Force a unique ID to prevent other areas using this same field htmlID */
			$field->htmlId = $field->name .'_' . md5( uniqid() );
			$fields[ $id ] = $field;
		}
		
		if ( \count( $fields ) )
		{
			$form = new \IPS\Helpers\Form( 'category_filters', 'update', $category->url() );
			$form->class = 'ipsForm_vertical'; 
			if ( \IPS\Request::i()->sortby )
			{
				$form->hiddenValues['sortby']		 = \IPS\Request::i()->sortby;
				$form->hiddenValues['sortdirection'] = isset( \IPS\Request::i()->sortdirection ) ? \IPS\Request::i()->sortdirection : 'desc';
			}
			else
			{
				$form->hiddenValues['sortby']		 = $database->field_sort;
				$form->hiddenValues['sortdirection'] = $database->field_direction;
			}
			
			$form->hiddenValues['record_type'] = 'all';
			$form->hiddenValues['time_frame'] = 'show_all';
			
			foreach( $fields as $id => $field )
			{
				$form->add( $field );
			}
			
			if ( \IPS\Member::loggedIn()->member_id )
			{
				$iStarted = FALSE;
				if ( isset( $cookie['cms_record_i_started'] ) and $cookie['cms_record_i_started'] )
				{
					$iStarted = TRUE;
				}
				
				/* Form submission takes preference over any previously stored cookie values */
				if ( ( isset( \IPS\Request::i()->cms_record_i_started ) and \IPS\Request::i()->cms_record_i_started ) )
				{
					$iStarted = TRUE;
				}

				$form->add( new \IPS\Helpers\Form\Checkbox( 'cms_record_i_started', $iStarted, FALSE ) );
			}
			
			$form->add( new \IPS\Helpers\Form\Checkbox( 'cms_widget_filters_remember', ( $cookie !== NULL ) ? TRUE : FALSE, FALSE, array( 'label' => 'cms_widget_filters_remember_text') ) );

			if ( $values = $form->values() )
			{
				$url    = $category->url()->setQueryString( array( 'advanced_search_submitted' => 1 ) );
				$cookie = array();
				$params = array();
				foreach( $values as $k => $v )
				{
					if ( mb_substr( $k, 0, 14 ) === 'content_field_' )
					{
						$id = mb_substr( $k, 14 );
						
						if ( isset( $fields[ $id ] ) and $fields[ $id ] instanceof \IPS\Helpers\Form\CheckboxSet )
						{
							/* We need to reformat this a little */
							$v = array_combine( $v, $v );
						}
						else if ( isset( $fields[ $id ] ) and $fields[ $id ] instanceof \IPS\Helpers\Form\YesNo )
						{
							/* The form class looks for {$name}_checkbox to determine the value */
							$k = $k . '_checkbox';
						}
						else if ( isset( $fields[ $id ] ) and $fields[ $id ] instanceof \IPS\Helpers\Form\DateRange )
						{
							/* We need to reformat this a little */
							$start = ( $v['start'] instanceof \IPS\DateTime ) ? $v['start']->getTimestamp() : \intval( $v['start'] );
							$end   = ( $v['end'] instanceof \IPS\DateTime )   ? $v['end']->getTimestamp()   : \intval( $v['end'] );
							$v = array( 'start' => $start, 'end' => $end );
						}
						else if ( isset( $fields[ $id ] ) and $fields[ $id ] instanceof \IPS\Helpers\Form\Member )
						{
							$ids = array();
							if ( \is_array( $v ) )
							{
								foreach( $v as $member )
								{
									if ( $member instanceof \IPS\Member )
									{
										$ids[] = $member->member_id;
									}
								}
								
								$v = $ids;
							}
							else if ( $v instanceof \IPS\Member )
							{
								$v = $v->member_id;
							}
						}
						
						$cookie[ $id ] = $v;
						$params[ $k ] = $v;
					}
					
					if ( ! empty( $values['cms_record_i_started_checkbox'] ) or ! empty( $values['cms_record_i_started'] ) )
					{
						$cookie['cms_record_i_started'] = 1;
						$params['cms_record_i_started'] = 1;
					}
				}
				
				if ( \count( $form->hiddenValues ) )
				{
					foreach( $form->hiddenValues as $k => $v )
					{
						if ( $k !== 'csrfKey' )
						{
							if ( !\in_array( $k, array( 'sortby', 'sortdirection' ) ) )
							{
								$cookie[ $k ] = $v;
							}
							$params[ $k ] = $v;
						}
					}
				}
				
				if ( $values['cms_widget_filters_remember'] )
				{
					$category->saveFilterCookie( $cookie );
					\IPS\Output::i()->redirect( $category->url() );
				}
				else
				{
					\IPS\Output::i()->redirect( $url->setQueryString( $params ) );
				}
			}

			return $this->output( $database, $category, $form );
		}
		else
		{
			return '';
		}
	}
}