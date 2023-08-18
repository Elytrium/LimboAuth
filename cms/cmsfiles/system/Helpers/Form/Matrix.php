<?php
/**
 * @brief		Matrix Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Feb 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Matrix Builder
 * @code
$matrix = new \IPS\Helpers\Form\Matrix;

$matrix->columns = array(
	'foo'	=> function( $key, $value, $data )
	{
		return new \IPS\Helpers\Form\Text( $key, $value );
	},
	//...
);

$matrix->rows = array(
	0	=> array(
		'foo'	=> TRUE,
		// ...
	),
	// ...
)

if ( $values = $matrix->values() )
{

}

\IPS\Output::i()->output = $matrix;
 * @endcode
 */
class _Matrix extends \IPS\Helpers\Form
{
	/**
	 * @brief	Input Elements array
	 */
	public $elements = NULL;
	
	/**
	 * @brief	Columns array
	 */
	public $columns = array();
	
	/**
	 * @brief	Widths
	 */
	public $widths = array();
	
	/**
	 * @brief	Columns to have "check all" checkboxes
	 */
	public $checkAlls = array();
	
	/**
	 * @brief	Should rows have all/none toggles?
	 */
	public $checkAllRows = FALSE;
	
	/**
	 * @brief	Rows array
	 */
	public $rows = array();
	
	/**
	 * @brief	Manageable? (Rows can be added and deleted)
	 */
	public $manageable = TRUE;
	
	/**
	 * @brief	Sortable?
	 */
	public $sortable = FALSE;

	/**
	 * @brief	Squash fields? (values in the matrix are json_encode'd as a single value to get around max_post_vars limits)
	 */
	public $squashFields = TRUE;
	
	/**
	 * @brief	Added rows
	 * @see		\IPS\Helpers\Form\Matrix::values
	 */
	public $addedRows = array();
	
	/**
	 * @brief	Changed rows
	 * @see		\IPS\Helpers\Form\Matrix::values
	 */
	public $changedRows = array();
	
	/**
	 * @brief	Removed rows
	 * @see		\IPS\Helpers\Form\Matrix::elements
	 */
	public $removedRows = array();
	
	/**
	 * @brief	Prefix to add to the language keys used for column headers
	 */
	public $langPrefix = '';

	/**
	 * @brief	Classnames to add to the table within the matrix
	 */
	public $classes = array();

	/**
	 * @brief	Show tooltips in each cell?
	 */
	public $showTooltips = FALSE;

	/**
	 * @brief	Form Id, Set it Matrix is part of a form
	 */
	public $formId = NULL;

	/**
	 * @brief Determines whether the Row Titles are processed as raw html instead of a language string. Not needed if $langPrefix is set
	 */
	public $styledRowTitle = FALSE;
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function __toString()
	{
		try
		{			
			return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->matrix( $this->id, array_keys( $this->columns ), $this->elements(), $this->action, $this->hiddenValues, $this->actionButtons, $this->langPrefix, $this->calculateWidths(), $this->manageable, $this->checkAlls, $this->checkAllRows, $this->classes, $this->showTooltips, $this->squashFields, $this->sortable, $this->styledRowTitle );
		}
		catch ( \Exception $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
		catch ( \Throwable $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
	}
	
	/**
	 * Get Nested HTML
	 *
	 * @return	string
	 */
	public function nested()
	{		
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->matrixNested( $this->id, array_keys( $this->columns ), $this->elements(), $this->action, $this->hiddenValues, $this->actionButtons, $this->langPrefix, $this->calculateWidths(), $this->manageable, $this->checkAlls, $this->checkAllRows, $this->classes, $this->showTooltips, $this->squashFields, $this->sortable, $this->styledRowTitle );
	}
	
	/**
	 * Calculate widths
	 *
	 * @return	array
	 */
	protected function calculateWidths()
	{
		$widths = $this->widths;
		$width = ( ( 100 - array_sum( $this->widths ) ) / \count( $this->columns ) );
		foreach ( array_keys( $this->columns ) as $c )
		{
			if ( !isset( $widths[ $c ] ) )
			{
				$widths[ $c ] = number_format( $width, 2, '.', '' );
			}
		}
				
		return $widths;
	}
	
	/**
	 * Get elements
	 *
	 * @param	bool	$getValues	Get values?
	 * @return	array
	 */
	public function elements( $getValues=TRUE )
	{
		if ( $this->elements === NULL )
		{
			/* Stuff about our form */
			$name = "{$this->id}_submitted";
			$formName = $this->formId ? "{$this->formId}_submitted" : NULL;
			$matrixName = "{$this->id}_matrixRows";
			$matrixValues = \IPS\Request::i()->$matrixName;

			/* Loop our defined rows */
			$this->elements = array();
			foreach ( $this->rows as $rowId => $data )
			{
				/* Have we deleted this row? */
				$deleteKey = "{$rowId}_delete";
				if ( $this->manageable and ( isset( \IPS\Request::i()->$name ) or ( $formName and isset( \IPS\Request::i()->$formName ) ) ) and ( isset( \IPS\Request::i()->$deleteKey ) or !isset( $matrixValues[ $rowId ] ) or !$matrixValues[ $rowId ] ) )
				{
					$this->removedRows[] = $rowId;
					continue;
				}
				
				/* Build the row */
				$this->elements[ $rowId ] = $this->buildRow( $rowId, $data );
			}
						
			/* Create blank row */
			if ( $this->manageable )
			{
				$blankRow = $this->buildRow( "_new_[x]", NULL );
			}
						
			/* Look for added ones */
			if ( isset( \IPS\Request::i()->_new_ ) )
			{
				$i = 1;
				foreach ( \IPS\Request::i()->_new_ as $newId => $data )
				{
					$added = TRUE;
					if ( $newId === 'x_unlimited' )
					{
						continue;
					}
					elseif ( $newId === 'x' )
					{
						$added = FALSE;
						foreach ( $data as $k => $v )
						{
							if ( isset( $blankRow[$k] ) and $v and $v !== $blankRow[$k]->value )
							{
								$added = TRUE;
								$blankRow[$k] = $blankRow[$k]->getValue();
							}
						}
					}
					
					if ( $added )
					{
						/* Select lists can have user-supplied input, so look for that too */
						foreach( \IPS\Request::i() as $inputKey => $inputValue )
						{
							if( $pos = mb_strpos( $inputKey, '_new_' ) AND $inputKey !== '_new_' )
							{
								if( \is_array( $inputValue ) AND isset( $inputValue[ $newId ] ) )
								{
									/* @note This previously used an array_merge() however this was causing elements to be overwritten with a blank value in some cases */
									foreach( $inputValue[ $newId ] AS $key => $value )
									{
										if ( $value )
										{
											$data[ $key ] = $value;
										}
									}
								}
							}
						}

						$this->elements["_new_[{$newId}]"] = $this->buildRow( "_new_[{$newId}]", $data );
						$this->addedRows[] = "_new_[{$newId}]";
						$i++;
					}
				}
			}
									
			/* Add blank one */
			if ( $this->manageable and $getValues )
			{
				$this->elements["_new_[x]"] = $blankRow;
			}
			
			/* Do we need to order it? */
			if ( $this->sortable )
			{
				$matrixOrderName = "{$this->id}_matrixOrder";
				if  ( isset( \IPS\Request::i()->$matrixOrderName ) )
				{
					$matrixOrderValues = \IPS\Request::i()->$matrixOrderName;
					
					uksort( $this->elements, function( $a, $b ) use ( $matrixOrderValues ) {
						if ( \in_array( $a, $matrixOrderValues ) and \in_array( $b, $matrixOrderValues ) )
						{
							return array_search( $a, $matrixOrderValues ) - array_search( $b, $matrixOrderValues );
						}
						return 0;
					} );
				}
			}
		}

		return $this->elements;
	}
	
	/**
	 * Build Row
	 *
	 * @param	mixed	$rowId	Row identifier
	 * @param	array	$data	Values
	 * @return	array
	 */
	protected function buildRow( $rowId, $data )
	{
		$row = array();
		if ( \is_string( $data ) )
		{
			return $data;
		}
		foreach ( $this->columns as $columnName => $columnData )
		{
			$inputName = "{$rowId}[{$columnName}]";
				
			/* Create using array */
			if ( \is_array( $columnData ) )
			{
				$classname = '\IPS\Helpers\Form\\' . $columnData[0];
				$row[ $columnName ] = new $classname(
					$inputName,
					isset( $data[ $columnName ] ) ? $data[ $columnName ] : ( isset( $columnData[1] ) ? $columnData[1] : NULL ),
					isset( $columnData[2] ) ? $columnData[2] : FALSE,
					isset( $columnData[3] ) ? $columnData[3] : array()
					);
			}
			/* Create using callback function */
			else
			{
				$row[ $columnName ] = $columnData( $inputName, isset( $data[ $columnName ] ) ? $data[ $columnName ] : NULL, $data );
			}
		}
		if ( isset( $data['_level'] ) )
		{
			$row['_level'] = $data['_level'];
		}

		return $row;
	}
	
	/**
	 * Get submitted values
	 *
	 * @param	bool			$force	If true, wil not check if form was submitted (used for nested matrixes)
	 * @return	array|FALSE		Array of field values or FALSE if the form has not been submitted or if there were validation errors
	 */
	public function values( $force=FALSE )
	{
		$values = array();
				
		$name = "{$this->id}_submitted";		
		if( $force or ( isset( \IPS\Request::i()->$name ) and \IPS\Login::compareHashes( (string) \IPS\Session::i()->csrfKey, (string) \IPS\Request::i()->csrfKey ) ) )
		{
			// Do we need to unsquash any values?
			// Squashed values are json_encoded by javascript to prevent us exceeding max_post_vars
			$squashedField = $this->id . '_squashed';
			
			// If 'squashedField' isn't in the request it might indicate the user didn't have JS enabled
			if ( $this->squashFields && isset( \IPS\Request::i()->$squashedField ) )
			{
				if ( isset( \IPS\Request::i()->$squashedField ) )
				{
					$unsquashed = json_decode( \IPS\Request::i()->$squashedField, TRUE );
					
					foreach( $unsquashed as $key => $value )
					{
						\IPS\Request::i()->$key = $value;
					}
				}
			}

			foreach ( $this->elements( FALSE ) as $rowId => $columns )
			{
				if ( \is_array( $columns ) )
				{
					foreach ( $columns as $columnName => $element )
					{
						/* If this was a ehader or something, skip it */
						if ( !\is_object( $element ) )
						{
							continue;
						}
						
						/* Return FALSE on error */
						if( $element->error !== NULL )
						{
							return FALSE;
						}
						
						/* If the "check all" box was checked, set it to TRUE */
						if ( $element instanceof Checkbox and isset( \IPS\Request::i()->__all[ $columnName ] ) and ! $element->options['disabled'] )
						{
							$element->value = TRUE;
						}
						
						/* If our element has an unlimited option, and that's set, set that. */
						if ( isset( $element->options['unlimited'] ) )
						{
							$unlimitedKey = "{$rowId}[{$columnName}_unlimited]";
							$value = \IPS\Request::i()->valueFromArray( $unlimitedKey );
							if ( $value !== NULL )
							{
								$element->value = $value;
							}
						}

						if ( isset( $element->options['nullLang'] ) )
						{
							$nullKey = "{$rowId}[{$columnName}_null]";
							if ( $value = \IPS\Request::i()->valueFromArray( $nullKey ) )
							{
								$element->value = NULL;
							}
						}
																				
						/* Set value */
						$values[ $rowId ][ $columnName ] = $element->value;
						
						/* Not if it's changed */
						if ( $element->value !== $element->defaultValue and mb_substr( $rowId, 0, 5 ) !== '_new_' )
						{					
							$this->changedRows[ $rowId ] = $rowId;
						}
					}
				}
			}
				
			return $values;
		}
		
		return FALSE;
	}
}