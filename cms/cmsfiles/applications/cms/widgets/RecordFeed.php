<?php
/**
 * @brief		RecordFeed Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		24 Nov 2014
 */

namespace IPS\cms\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * RecordFeed Widget
 */
class _RecordFeed extends \IPS\Content\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'RecordFeed';
	
	/**
	 * @brief	App
	 */
	public $app = 'cms';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Class
	 */
	protected static $class = 'IPS\cms\Records';

	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
		$databases = array();
		$database  = NULL;
		foreach( \IPS\cms\Databases::databases() as $obj )
		{
			if ( $obj->page_id )
			{
				$databases[ $obj->_id ] = $obj->_title;
			}
			
			if ( ( isset( \IPS\Request::i()->cms_rf_database ) AND $obj->id == \IPS\Request::i()->cms_rf_database ) OR ( ( isset( $this->configuration['cms_rf_database'] ) AND $obj->id == $this->configuration['cms_rf_database'] ) ) )
			{
				$database = $obj;
				static::$class = '\IPS\cms\Records' . $database->id;
			}
		}
		
		$form = parent::configuration( $form );

		if ( isset( $this->configuration['cms_rf_database'] ) )
		{
			$form->addDummy( 'cms_rf_database', $database->_title, \IPS\Member::loggedIn()->language()->addToStack( 'cms_rf_database_description' ) );
			$form->hiddenValues['cms_rf_database'] = $database->_id;
		}
		else
		{
			$form->add( new \IPS\Helpers\Form\Select( 'cms_rf_database', 0, FALSE, array(
				'disabled' => false,
				'options'  => $databases
			) ) );
		}

		$form = parent::configuration( $form );

		/* Tags */
		if ( $database )
		{
			/* Sort */
	 		$sortOptions = array(
				'primary_id_field'    => 'database_field__id',
				'member_id'		      => 'database_field__member',
				'record_publish_date' => 'database_field__saved',
				'record_updated' => ( $database and $database->_comment_bump === \IPS\cms\Databases::BUMP_ON_EDIT ) ? 'database_field__edited' : 'database_field__updated',
				'record_last_comment' => 'sort_record_last_comment',
				'record_rating' 	  => 'database_field__rating'
			);
			
	 		$class = static::$class;
	 		foreach ( array( 'num_comments', 'views', 'rating_average' ) as $k )
	 		{
		 		if ( isset( $class::$databaseColumnMap[ $k ] ) )
		 		{
			 		$sortOptions[ $class::$databaseColumnMap[ $k ] ] = 'sort_' . $k;
		 		}
	 		}

			if ( $database )
			{
				$FieldsClass = '\IPS\cms\Fields' . $database->id;
	
				foreach( $FieldsClass::data() as $id => $field )
				{
					if ( \in_array( $field->type, array( 'checkbox', 'multiselect', 'attachments' ) ) )
					{
						continue;
					}
	
					$sortOptions[ 'field_' . $field->id ] = $field->_title;
				}
			}

			$form->add( new \IPS\Helpers\Form\Select( 'widget_feed_sort_on', isset( $this->configuration['widget_feed_sort_on'] ) ? $this->configuration['widget_feed_sort_on'] : $class::$databaseColumnMap['updated'], FALSE, array( 'options' => $sortOptions ) ), NULL, NULL, NULL, 'widget_feed_sort_on' );
			
			if ( $database->tags_enabled )
			{
				$options = array( 'autocomplete' => array( 'unique' => TRUE, 'source' => NULL, 'freeChoice' => TRUE ) );
	
				if ( \IPS\Settings::i()->tags_force_lower )
				{
					$options['autocomplete']['forceLower'] = TRUE;
				}
	
				if ( \IPS\Settings::i()->tags_clean )
				{
					$options['autocomplete']['filterProfanity'] = TRUE;
				}
	
				$options['autocomplete']['prefix'] = FALSE;
	
				$form->add( new \IPS\Helpers\Form\Text( 'widget_feed_tags', ( isset( $this->configuration['widget_feed_tags'] ) ? $this->configuration['widget_feed_tags'] : array( 'tags' => NULL ) ), FALSE, $options ) );
			}
		}
		
		/* Any filterable fields */
		if ( $database )
		{
			$fieldClass   = 'IPS\cms\Fields' .  $database->id;
			foreach( $fieldClass::fields( $this->_getCustomValuesFromConfiguration(), 'view', NULL, $fieldClass::FIELD_SKIP_TITLE_CONTENT | $fieldClass::FIELD_DISPLAY_FILTERS ) as $id => $field )
			{
				$form->add( $field );
			}
		}
		
		if ( $database )
		{
			\IPS\Member::loggedIn()->language()->words['widget_feed_container_content_db_lang_su_' . $database->id ] = \IPS\Member::loggedIn()->language()->addToStack('widget_feed_container_cms');
			
			if ( $database->_comment_bump === \IPS\cms\Databases::BUMP_ON_EDIT )
			{
				\IPS\Member::loggedIn()->language()->words['sort_updated'] = \IPS\Member::loggedIn()->language()->addToStack('database_field__edited');
			}
		}
		
		return $form;
 	} 
 	
 	/**
	 * Fetch custom field values from the saved configuration
	 *
	 * @param	boolean	$keyAsInt	Returns an array with just the field ID, as opposed to 'field_x'
	 * @return array
	 */
 	protected function _getCustomValuesFromConfiguration( $keyAsInt=false )
 	{
	 	$customValues = array();
		foreach( $this->configuration as $k => $v )
		{
			if ( mb_substr( $k, 0, 8 ) === 'content_' )
			{
				$customValues[ $keyAsInt ? str_replace( 'content_field_', '', $k ) : mb_substr( $k, 8 ) ] = $v;
			}
		}
		
		return $customValues;
 	}
 	
 	/**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
	 	static::$class = '\IPS\cms\Records' . $values['cms_rf_database'];
	 	
	 	foreach( $values as $k => $v )
	 	{
		 	/* We need to reformat this a little */
		 	if ( \is_array( $v ) and isset( $v['start'] ) and isset( $v['end'] ) )
		 	{
				$start = ( $v['start'] instanceof \IPS\DateTime ) ? $v['start']->getTimestamp() : \intval( $v['start'] );
				$end   = ( $v['end'] instanceof \IPS\DateTime )   ? $v['end']->getTimestamp()   : \intval( $v['end'] );
				
				$values[ $k ] = array( 'start' => $start, 'end' => $end );
			}
	 	}
	 	
	 	return parent::preConfig( $values );
 	}
 	
 	/**
	 * Get where clause
	 *
	 * @return	array
	 */
	protected function buildWhere()
	{
		static::$class = '\IPS\cms\Records' . $this->configuration['cms_rf_database'];
		
		$where = parent::buildWhere();

		$fieldClass   = 'IPS\cms\Fields' .  $this->configuration['cms_rf_database'];
		$customFields = $fieldClass::data( 'view', NULL, $fieldClass::FIELD_SKIP_TITLE_CONTENT | $fieldClass::FIELD_DISPLAY_FILTERS );

		foreach( $this->_getCustomValuesFromConfiguration( TRUE ) as $f => $v )
		{
			$k = 'field_' . $f;
			if ( isset( $customFields[ $f ] ) and $v !== '___any___' AND $v !== NULL )
			{
				if ( \is_array( $v ) )
				{
					if ( array_key_exists( 'start', $v ) or array_key_exists( 'end', $v ) )
					{
						$start = ( $v['start'] instanceof \IPS\DateTime ) ? $v['start']->getTimestamp() : \intval( $v['start'] );
						$end   = ( $v['end'] instanceof \IPS\DateTime )   ? $v['end']->getTimestamp()   : \intval( $v['end'] );
						
						if ( $start or $end )
						{
							$where[] = array( '( ' . $k . ' BETWEEN ' . $start . ' AND ' . $end . ' )' );
						}
					}
					else
					{
						$like = array();
						if ( \count( $v ) )
						{
							foreach( $v as $val )
							{
								if ( $val === 0 or ! empty( $val ) )
								{
									$like[]  = "CONCAT( ',', " .  $k . ", ',') LIKE '%," . \IPS\Db::i()->real_escape_string( $val ) . ",%'";
								}
							}
							
							$where[] = array( '( ' . \IPS\Db::i()->in( $k, $v ) .  ( \count( $like ) ? " OR (" . implode( ' OR ', $like ) . ') )' : ')' ) );
						}
					}
				}
				else
				{
					if ( $v === false )
					{
						continue;
					}
					if ( $v !== 0 and ! $v )
					{
						$where[] = array( $k . " IS NULL" );
					}
					else
					{
						$where[] = array( $k . "=?", $v );
					}
				}
			}
		}
		
		return $where;
	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		if( isset( $this->configuration['cms_rf_database'] ) )
		{
			try
			{
				$database = \IPS\cms\Databases::load($this->configuration['cms_rf_database']);
				static::$class = '\IPS\cms\Records' . $database->id;
				
				if ( ! $database->page_id )
				{
					throw new \OutOfRangeException;
				}
			}
			catch ( \OutOfRangeException $e )
			{
				return '';
			}
		}
		else
		{
			return '';
		}

		return parent::render();
	}
}