<?php
/**
 * @brief		dataLayer
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		08 Feb 2022
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * dataLayer
 */
class _dataLayer extends \IPS\Dispatcher\Controller
{
	/**
	 * @breif the tabs
	 */
	protected static $tabs = array(
		'main'        => 'datalayer_main',
		'pageContext' => 'datalayer_pageContext',
		'properties'  => 'datalayer_properties',
		'events'      => 'datalayer_events',
	);

	/**
	 * @breif   Makes this controller work
	 */
	public static $csrfProtected = true;

	/**
	 * @brief   used to actually handle events and properties in the browser
	 */
	public static $handlerClass = '\IPS\core\DataLayer\Handler';

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'dataLayer_manage' );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__core_settings_dataLayer' );

		/* Can we use custom handlers? Only show the tab on sites that have full analytics */
		$class = static::$handlerClass;
		if (
			class_exists( $class ) AND
			class_exists( 'IPS\cloud\Application' ) AND
			method_exists( $class, 'handlerForm' ) AND
			method_exists( $class, 'loadWhere' ) AND
			\IPS\Member::loggedIn()->hasAcpRestriction( 'dataLayer_handlers_view' ) AND
			\IPS\cloud\Application::featureIsEnabled('analytics_full')
		)
		{
			static::$tabs = array(
				'main'          => 'datalayer_main',
				'pageContext'   => 'datalayer_pageContext',
				'handlers'      => 'datalayer_handlers',
				'properties'    => 'datalayer_properties',
				'events'        => 'datalayer_events',
			);
		}

		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'settings/general.css', 'core', 'admin' ) );
		parent::execute();
	}

	/**
	 *  Call Magic Method, seamless way to integrate cloud features if there are any
	 *
	 * @return  string|void
	 */
	public function __call( $name, $arguments )
	{
		if ( method_exists( $this, $name ) )
		{
			return \call_user_func_array( $this->$name, $arguments );
		}
		elseif ( method_exists( \IPS\core\DataLayer::i(), $name ) )
		{
			$method = array( \IPS\core\DataLayer::i(), $name );
			return \call_user_func_array( $method, $arguments );
		}

		return "You should Upgrade!";
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tab    = \IPS\Request::i()->tab;
		$tab    = empty( static::$tabs[ $tab ] ) ? 'main' : $tab;
		$output = $this->$tab();
		$output = "<div id='dataLayerContent'>$output</div>" ;

		if ( isset( static::$tabs['handlers'] ) AND \IPS\Member::loggedIn()->hasAcpRestriction( 'dataLayer_handlers_edit' ) )
		{
			\IPS\Output::i()->sidebar['actions']['addHandler'] = array(
				'title'     => \IPS\Member::loggedIn()->language()->addToStack( 'datalayer_handler_form' ),
				'icon'      =>  'plus',
				'link'      => \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&do=addHandler' ),
				'data'      => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'datalayer_handler_form' ) )
			);
		}


		$addPropertyUrl = \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&do=addProperty' );
		\IPS\Output::i()->sidebar['actions']['addProperty'] = array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'datalayer_add_property' ),
			'icon'		=> 'plus',
			'link'		=> $addPropertyUrl,
			'data'      => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'datalayer_add_property' ) )
		);

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $output;
		}
		else
		{
			if ( isset( $_SESSION['deleted_datalayer_property'] ) )
			{
				\IPS\Output::i()->inlineMessage = 'Deleted Property';
				unset( $_SESSION['deleted_datalayer_property'] );
			}

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'admin' )->tabs(
				static::$tabs,
				$tab,
				$output,
				\IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer', 'admin' )
			);
		}
	}


	/**
	 * Gets the general settings form
	 *
	 * @return string
	 */
	protected function main()
	{
		$form = new \IPS\Helpers\Form( 'datalayer_general' );

		$form->addHeader('Settings');
		$form->add( new \IPS\Helpers\Form\YesNo( 'core_datalayer_enabled', \IPS\Settings::i()->core_datalayer_enabled, false ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'core_datalayer_include_pii', \IPS\Settings::i()->core_datalayer_include_pii, false, array( 'togglesOn' => [ 'datalayer_general_core_datalayer_member_pii_choice' ] ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'core_datalayer_member_pii_choice', \IPS\Settings::i()->core_datalayer_member_pii_choice, false ) );

		try
		{
			$default = \IPS\Login\Handler::load( \IPS\Settings::i()->core_datalayer_replace_with_sso );
		}
		catch ( \OutOfRangeException $e )
		{
			$default = 0;
		}

		$form->add( new \IPS\Helpers\Form\Node( 'core_datalayer_replace_with_sso', $default, false, array(
			'class'   => '\IPS\Login\Handler',
			'zeroVal'   => 'Use internal Member ID (default)',
			'where'     => array(
				['login_enabled=?', 1],
				['login_classname LIKE ?', '%Login_Handler%'],
				['login_classname NOT LIKE ?', '%Login_Handler_Standard']
			),

		) ) );

		$form->addHeader('enhancements__core_GoogleTagManager');
		$form->addMessage( 'use_gtm_for_installation', 'ipsMessage ipsMessage_info ipsMargin' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'core_datalayer_use_gtm', \IPS\Settings::i()->core_datalayer_use_gtm, false ) );
		$gtmkey = explode( '.', \IPS\Settings::i()->core_datalayer_gtmkey );
		$form->add( new \IPS\Helpers\Form\Text(
			'core_datalayer_gtmkey',
			array_pop( $gtmkey ),
			false,
			array(),
            function( $_value ) {
	            /* Check for invalid characters */
	            if ( preg_match( '/[^a-z,A-Z,0-9,\_]/', $_value ) )
	            {
		            throw new \InvalidArgumentException( 'The variable name can only contain alphanumeric characters and underscores.' );
	            }
	            elseif ( preg_match( '/[0-9]/', \mb_substr( $_value, 0, 1 ) ) )
	            {
		            throw new \InvalidArgumentException( 'The variable name cannot start with a number.' );
	            }

				/* Is a custom handler using this? Note that, in JS, window.variable and variable are nearly always the same */
	            $where = [
		            [ 'enabled=?', 1 ],
		            [ 'use_js=?', 1 ],
		            \IPS\Db::i()->in( 'datalayer_key', [ $_value, "window.$_value" ] ),
	            ];

	            if (
		            class_exists( 'IPS\cloud\DataLayer' ) AND
		            \IPS\core\DataLayer::i() instanceof \IPS\cloud\DataLayer AND
		            \count( \IPS\Db::i()->select( 'datalayer_key', \IPS\cloud\DataLayer\Handler::$databaseTable, $where, null, 1 ) )
	            )
	            {
		            throw new \InvalidArgumentException( "$_value or window.$_value is in use by a custom handler." );
	            }
            },
			'<span class="ipsType_monospace">window.</span>'
        ));

		if ( $values = $form->values() )
		{
			\IPS\Session::i()->csrfCheck();

			if ( isset( $values['core_datalayer_replace_with_sso'] ) )
			{
				$values['core_datalayer_replace_with_sso'] = $values['core_datalayer_replace_with_sso'] ? $values['core_datalayer_replace_with_sso']->_id : 0;
			}

			/* Prefix the gtmkey with window. */
			if ( isset( $values['core_datalayer_gtmkey'] ) )
			{
				$values['core_datalayer_gtmkey'] = "window.{$values['core_datalayer_gtmkey']}";
			}

			/* Since GTM is a handler, cachebust the handler for template if it was updated */
			foreach( ['core_datalayer_use_gtm', 'core_datalayer_gtmkey', 'googletag_head_code', 'googletag_noscript_code'] as $input )
			{
				if ( isset( $values[$input] ) )
				{
					$key = \IPS\core\DataLayer\Handler::$handlerCacheKey;
					unset( \IPS\Data\Store::i()->$key );
					break;
				}
			}

			/* Clear the cached configuration since we're changing it */
			\IPS\core\DataLayer::i()->clearCachedConfiguration([ '_jsConfig', '_eventProperties', '_propertyEvents' ]);

			\IPS\Settings::i()->changeValues( $values );
			
			\IPS\Session::i()->log( 'acplog__datalayer_settings_edited' );

			/* Redirect to avoid csrfKey errors */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=main' ) );
		}

		return "<br>$form";
	}

	/**
	 * Property settings and configuration
	 *
	 * @return string
	 */
	protected function pageContext()
	{
		/* Get the page level properties */
		$_properties = \IPS\core\DataLayer::i()->propertiesConfiguration;
		foreach ( $_properties as $k => $property )
		{
			if ( $property['page_level'] AND $property['enabled'] )
			{
				$properties[$k] = array(
					'name'      => $property['formatted_name'],
					'type'      => $property['type'],
					'pii'       => $property['pii'],
					'custom'    => $property['custom'] ?? 0,
					'short'     => $property['short'],
				);
			}
		}

		$propertyBlock              = new \IPS\Helpers\Table\Custom(
			$properties,
			\IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=pageContext' )
		);
		$propertyBlock->classes[]   = 'ipsPadding:none ipsMargin:none';
		$propertyBlock->exclude     = array('custom', 'description');
		$propertyBlock->langPrefix  = 'datalayer_';

		if ( !\IPS\Request::i()->sortby )
		{
			$propertyBlock->sortBy = 'name';
		}

		if ( !\IPS\Request::i()->sortdirection )
		{
			$propertyBlock->sortDirection = 'asc';
		}

		$propertyBlock->parsers = array(
			'pii'   => array( $this, '_yesNo' ),
			'name'  => function ( $val, $row ) {
				$url = \IPS\Http\Url::internal('app=core&module=settings&controller=dataLayer&tab=properties&property_key=' . $val);
				$title = $row['short'];
				return "<span class='ipsType_monospace ipsMargin:none'><a href='$url' data-ipstooltip title='$title'>$val</a></span>";
			},
		);

		return \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->dataLayerContext( $propertyBlock );
	}

	/**
	 * Property settings and configuration
	 *
	 * @return string
	 */
	protected function properties()
	{
		/* Load our properties */
		$properties     = \IPS\core\DataLayer::i()->propertiesConfiguration;
		$property_key   = \IPS\Request::i()->property_key ?: array_keys( $properties )[0];

		/* Do we know the requested property? */
		if ( !isset( $properties[$property_key] ) )
		{
			\IPS\Output::i()->redirect( \IPS\Request::i()->url()->stripQueryString([ 'property_key' ]) );
		}
		$property               = $properties[$property_key];

		/* Sidebar */
		$propertySelector   = \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->dataLayerSelector(
			$properties,
			'properties',
			'property_key',
			'Data Layer Properties',
			$property_key,
			true,
			array( $this, '_truncate' )
		);

		/* Form */
		$form           = new \IPS\Helpers\Form( 'datalayer_properties_' . $property_key, 'save', \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=properties&property_key=' . $property_key ) );
		$form->class    = 'ipsForm_vertical';

		$form->add( new \IPS\Helpers\Form\YesNo( 'datalayer_property_enabled', $property['enabled'], false ) );
		$form->add( new \IPS\Helpers\Form\Text( 'datalayer_property_formatted_name', $property['formatted_name'], false, array(), $this->formattedNameValid('properties', $property_key) ) );

		if ( $property['custom'] ?? 0 )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'datalayer_property_value', $property['value'], true ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'datalayer_property_page_level', $property['page_level'], true ) );
			$form->add( new \IPS\Helpers\Form\Text( 'datalayer_property_short', $property['short'], false, array( 'maxLength' => 50 ) ) );

			$events = \IPS\core\DataLayer::i()->eventConfiguration;
			$options = array();
			foreach ( $events as $key => $data )
			{
				$options[$key]  = $data['formatted_name'];
			}

			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'datalayer_property_event_keys', $property['event_keys'], false, array('options' => $options, 'multiple' => 1) ) );
		}

		/* Update the values as long as the property_key was specified in the request */
		if ( ( $values = $form->values() ) AND \IPS\Request::i()->property_key === $property_key )
		{
			/* Get our submitted values */
			$_values = array();
			if ( isset( $values['datalayer_property_enabled'] ) )
			{
				$_values['enabled'] = (bool) $values['datalayer_property_enabled'];
			}

			if ( isset( $values['datalayer_property_formatted_name'] ) )
			{
				$_values['formatted_name'] = $values['datalayer_property_formatted_name'];
			}

			if ( $property['custom'] ?? 0 )
			{
				foreach ( $values as $field => $value )
				{
					if ( isset( $_values[$field] ) )
					{
						continue;
					}

					$field = str_replace( 'datalayer_property_', '', $field );
					$_values[$field] = $value;
				}
			}

			/* Save the property */
			if ( !empty( $_values ) )
			{
				try
				{
					\IPS\core\DataLayer::i()->savePropertyConfiguration( $property_key, $_values );
				}
				catch ( \InvalidArgumentException $e ) {}
				
				\IPS\Session::i()->log( 'acplog__data_layer_property_edited', array( $property_key => FALSE ) );
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=properties&property_key=' . $property_key ) );
		}

		/* Load our events */
		$_events    = \IPS\core\DataLayer::i()->getPropertyEvents( $property_key );
		$events     = array();
		foreach( $_events as $key => $event )
		{
			$events[$key] = array(
				'name'          => $event['formatted_name'],
				'key'           => $key,
				'enabled'       => $event['enabled'],
				'short'         => $event['short'],
			);
		}

		/* Property's events table */
		if ( empty( $events ) )
		{
			$eventTable = "";
		}
		else
		{
			$eventTable = new \IPS\Helpers\Table\Custom(
				$events,
				\IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=properties&property_key=' . $property_key )
			);



			if ( !\IPS\Request::i()->sortby )
			{
				$eventTable->sortBy = 'name';
			}
			if ( !\IPS\Request::i()->sortdirection )
			{
				$eventTable->sortDirection = 'asc';
			}

			$eventTable->langPrefix = 'datalayer_';
			$eventTable->exclude    = array( 'description', 'key' );
			$eventTable->parsers    = array(
				'name'      => function ( $val, $row )
				{
					$url    = \IPS\Http\Url::internal('app=core&module=settings&controller=dataLayer&tab=events&event_key=' . $row['key']);
					$title  = $row['short'];
					return  "<span class='ipsMargin:none ipsType_monospace'><a href='$url' data-ipstooltip title='$title'>$val</a></span>";
				},
				'enabled'   => array( $this, '_enabledDisabled' ),
			);
		}

		/* Render Content */
		$content = \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->dataLayerTitleContent(
			$property,
			$property_key,
			'property',
			$form,
			(string) $eventTable
		);

		return \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->dataLayerTab( $propertySelector, $content );
	}

	/**
	 * Property settings and configuration
	 *
	 * @return string
	 */
	protected function addProperty()
	{
		\IPS\Output::i()->title = 'Add property';
		$form = new \IPS\Helpers\Form( 'datalayer_add_property' );
		$form->add( new \IPS\Helpers\Form\Text( 'datalayer_property_formatted_name', null, true, array(), $this->formattedNameValid() ) );
		$form->add( new \IPS\Helpers\Form\Text( 'datalayer_property_value', null, true ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'datalayer_property_description', null, true ) );
		$form->add( new \IPS\Helpers\Form\Text( 'datalayer_property_short', null, false, array( 'maxLength' => 50 ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'datalayer_property_page_level', 1, true ) );

		$events = \IPS\core\DataLayer::i()->eventConfiguration;
		$options = array();
		foreach ( $events as $key => $data )
		{
			$options[$key]  = $data['formatted_name'];
		}

		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'datalayer_property_event_keys', array(), false, array('options' => $options, 'multiple' => 1) ) );

		if ( $values = $form->values() )
		{
			\IPS\Session::i()->csrfCheck();
			$_values = array();
			foreach ( $values as $field => $value )
			{
				$field = str_replace( 'datalayer_property_', '', $field );
				$_values[$field] = $value;
			}
			$values = $_values;

			$values['enabled'] = true;
			$key = $values['formatted_name'];

			\IPS\core\DataLayer::i()->savePropertyConfiguration( $key, $values, true );
			
			\IPS\Session::i()->log( 'acplog__data_layer_property_added', array( $key => FALSE ) );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=properties&property_key=' . $key ) );
		}

		\IPS\Output::i()->output = (string) $form;
	}

	/**
	 * Delete a property
	 */
	protected function deleteProperty()
	{
		\IPS\Session::i()->csrfCheck();

		$key = \IPS\Request::i()->property_key;
		$propertiesUrl = \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=properties' );
		if ( empty( $key ) )
		{
			\IPS\Output::i()->redirect( $propertiesUrl );
		}

		$properties = \IPS\core\DataLayer::i()->propertiesConfiguration;
		if ( isset( $properties[$key] ) )
		{
			if ( $properties[$key]['custom'] ?? 0 )
			{
				$setting = json_decode( \IPS\Settings::i()->core_datalayer_properties, true ) ?: array();
				unset( $setting[$key] );
				\IPS\Settings::i()->changeValues([ 'core_datalayer_properties' => json_encode( $setting ) ]);
				\IPS\core\DataLayer::i()->clearCachedConfiguration();
				\IPS\Session::i()->log( 'acplog__data_layer_property_deleted', array( $key => FALSE ) );
				$_SESSION['deleted_datalayer_property'] = 1;
			}
			else
			{
				$propertiesUrl = $propertiesUrl->setQueryString([ 'property_key' => $key ]);
			}
		}
		\IPS\Output::i()->redirect( $propertiesUrl );
	}

	/**
	 * Event settings and configuration
	 *
	 * @return string
	 */
	protected function events()
	{
		/* Load our events */
		$events = \IPS\core\DataLayer::i()->eventConfiguration;
		$event_key = \IPS\Request::i()->event_key ?: 'content_create';

		/* Do we know the recognized one? */
		if ( !isset( $events[$event_key] ) )
		{
			\IPS\Output::i()->redirect( \IPS\Request::i()->url()->stripQueryString([ 'event_key' ]) );
		}
		$event                      = $events[$event_key];

		foreach ( array_keys( $events ) as $_eventKey )
		{
			$events[$_eventKey]['description'] = 'Fires when ' . $events[$_eventKey]['description'];
		}

		/* Create our sidebar */
		$eventSelector  = \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->dataLayerSelector(
			$events,
			'events',
			'event_key',
			'Data Layer Events',
			$event_key,
			true,
			array( $this, '_truncate' )
		);

		/* Form */
		$form           = new \IPS\Helpers\Form( 'datalayer_events_' . $event_key, 'save',  \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=events&event_key=' . $event_key ) );
		$form->class    = 'ipsForm_vertical';

		$form->add( new \IPS\Helpers\Form\YesNo( 'datalayer_event_enabled', $event['enabled'], false ) );
		$form->add( new \IPS\Helpers\Form\Text( 'datalayer_event_formatted_name', $event['formatted_name'], false, array(), $this->formattedNameValid( 'events', $event_key ) ) );

		/* We only want to change values when a real event is specified in the request */
		if ( ( $values = $form->values() ) AND \IPS\Request::i()->event_key === $event_key )
		{
			/* Pull out the values to use */
			$_values = array();
			if ( isset( $values['datalayer_event_enabled'] ) )
			{
				$_values['enabled'] = (bool) $values['datalayer_event_enabled'];
			}

			if ( isset( $values['datalayer_event_formatted_name'] ) )
			{
				$_values['formatted_name'] = $values['datalayer_event_formatted_name'];
			}

			/* Save the event */
			if ( !empty( $_values ) )
			{
				try
				{
					\IPS\core\DataLayer::i()->saveEventConfiguration( $event_key, $_values );
				}
				catch ( \InvalidArgumentException $e ) {}
			}
			
			\IPS\Session::i()->log( 'acplog__data_layer_event_edited', array( $event_key => FALSE ) );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=events&event_key=' . $event_key ) );
		}


		/* Load our properties */
		$_properties    = \IPS\core\DataLayer::i()->getEventProperties( $event_key );
		$properties     = array();
		foreach( $_properties as $key => $property )
		{
			$properties[$key] = array(
				'name'      => $property['formatted_name'],
				'key'       => $key,
				'type'      => $property['type'],
				'enabled'   => $property['enabled'],
				'pii'       => $property['pii'],
				'custom'    => $property['custom'] ?? 0,
				'short'     => $property['short'],
			);
		}

		/* Create our neat properties table */
		if ( empty( $properties ) )
		{
			$propertyTable = "";
		}
		else
		{
			$propertyTable = new \IPS\Helpers\Table\Custom(
				$properties,
				\IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=events&event_key=' . $event_key )
			);

			if ( !\IPS\Request::i()->sortby )
			{
				$propertyTable->sortBy  = 'name';
			}

			if ( !\IPS\Request::i()->sortdirection )
			{
				$propertyTable->sortDirection   = 'asc';
			}

			$propertyTable->langPrefix  = 'datalayer_';
			$propertyTable->exclude     = array( 'custom', 'short', 'key' );
			$propertyTable->parsers     = array(
				'enabled'   => array( $this, '_enabledDisabled' ),
				'pii'       => array( $this, '_yesNo' ),
				'name'      => function ( $val, $row )
				{
					$url     = \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=properties&property_key=' . $row['key'] );
					$title   = $row['short'];
					return  "<pre class='ipsMargin:none'><a href='$url' data-ipstooltip title='$title' >$val</a></pre>";
				},
			);
		}

		/* Render Content */
		$event['description'] = "Fires when {$event['description']}";
		$content = \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->dataLayerTitleContent(
			$event,
			$event_key,
			'event',
			(string) $form,
			(string) $propertyTable
		);

		return \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->dataLayerTab( $eventSelector, $content );
	}

	/**
	 * Is the value a valid property or event formatted name?
	 *
	 * @param   string      $group      Either properties or events; the datalayer collection to test the key against
	 * @param   ?string     $current    The key of the property/event that currently has this value set as its formatted name
	 *
	 * @return callback
	 */
	public function formattedNameValid( string $group='properties', ?string $current=null )
	{
		return function( $value ) use ( $group, $current )
		{
			if ( $group === 'properties' AND \strtolower( $value ) === 'event' )
			{
				throw new \InvalidArgumentException( "You cannot name a property '$value' because that key is reserved" );
			}

			if ( preg_match( '/[^a-z,A-Z,\_]/', $value ) )
			{
				throw new \InvalidArgumentException( 'The name can only contain letters and underscores.' );
			}

			$collection = ( $group === 'events' ) ? \IPS\core\DataLayer::i()->eventConfiguration : \IPS\core\DataLayer::i()->propertiesConfiguration;
			foreach ( $collection as $key => $data )
			{
				if ( $key === $current )
				{
					continue;
				}

				if ( isset( $data['formatted_name'] ) AND $data['formatted_name'] === $value )
				{
					throw new \InvalidArgumentException( 'This Data Layer name is already in use' );
				}
			}
		};
	}

	/**
	 * Formatter method to make code cleaner
	 *
	 * @param   mixed   $val
	 *
	 * @return  string
	 */
	public function _enabledDisabled( $val, $row=null )
	{
		return $val ? 'Enabled' : 'Disabled';
	}

	/**
	 * Formatter method to make code cleaner
	 *
	 * @param   mixed   $val
	 *
	 * @return  string
	 */
	public function _yesNo( $val, $row=null )
	{
		return $val ? 'Yes' : 'No';
	}

	/**
	 * Truncates the string and removes tags. Ends in ... if the string is longer than the limit
	 *
	 * @param   string  $_string    The string to truncate
	 * @param   int     $length     The desired length
	 *
	 * @return  string
	 */
	public function _truncate( $_string, $length=100 )
	{
		$_string   = strip_tags( $_string );
		$string    = \substr( $_string, 0, $length );
		$changedLength  = ( \strlen( $_string ) !== \strlen( $string ) );
		$string    = trim( $string, ". \n\r\t\v\x00" );
		if ( $changedLength )
		{
			$string = \trim( \substr( $string, 0, $length - 3 ), ". \n\r\t\v\x00" ) . '...';
		}
		return $string;
	}

}