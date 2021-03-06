<?php
/**
 * @package     PK Templates
 * @subpackage  plg.jsfixer
 *
 * @copyright   Copyright (C) 2019 PKWARE, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined( '_JEXEC' ) or die;
/**
 * Plugin that enables loading a different javascript library that the Joomla default,
 * as well as fixing sloppy code in some of the bootstrap core routines.
 */
class PlgSystemJsfixer extends JPlugin
{
	/**
	 * Application object
	 *
	 * @var  JApplicationCms
	 */
	protected $app;
	/**
	 * Array containing information for loaded files
	 *
	 * @var  array
	 */
	protected static $loaded = array();
	/**
	 * Data object holding the params related to jquery overrides
	 *
	 * @var	object
	 */
	protected static $jquery;
	/**
	 * Data object holding the params related to jquery_noconflict overrides
	 *
	 * @var	object
	 */
	protected static $jqueryNoConflict;
	/**
	 * Data object holding the params related to jquery_migrate overrides
	 *
	 * @var	object
	 */
	protected static $jqueryMigrate;
	/**
	 * Data object holding the params related to bootstrap overrides
	 *
	 * @var	object
	 */
	protected static $bootstrap;

	/**
	 * Constructor
	 *
	 *	Needed because we need to translate the params into static variables to be used later.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @since   1.5
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);

		static::$jquery            = $this->createScriptClass();
		static::$jquery->use       = $this->params->get('jquery_use', false);
		static::$jquery->url       = $this->params->get('jquery', '');
		static::$jquery->integrity = $this->params->get('jquery_integrity', '');

		static::$jqueryNoConflict            = $this->createScriptClass();
		static::$jqueryNoConflict->use       = $this->params->get('jquery_noconflict_use', static::$jquery->use);
		static::$jqueryNoConflict->url       = $this->params->get('jquery_noconflict', '');
		static::$jqueryNoConflict->integrity = $this->params->get('jquery_noconflict_integrity', '');

		static::$jqueryMigrate            = $this->createScriptClass();
		static::$jqueryMigrate->use       = $this->params->get('jquery_migrate_use', static::$jquery->use);
		static::$jqueryMigrate->url       = $this->params->get('jquery_migrate', '');
		static::$jqueryMigrate->integrity = $this->params->get('jquery_migrate_integrity', '');

		static::$bootstrap            = $this->createScriptClass();
		static::$bootstrap->use       = $this->params->get('bootstrap_use', false);
		static::$bootstrap->url       = $this->params->get('bootstrap', '');
		static::$bootstrap->load      = $this->params->get('bootstrap_load', 'defer');
		static::$bootstrap->integrity = $this->params->get('bootstrap_integrity', '');
	}

	/**
	 * createScriptClass
	 *
	 * Returns a stdClass with all the requisite data attributes.
	 *
	 * @return	object	A stdClass object with the data fields set to defaults.
	 */
	private function createScriptClass()
	{
		$class            = new stdClass;
		$class->use       = false;
		$class->url       = '';
		$class->integrity = '';

		return $class;
	}

	/**
	 * Listener for onAfterInitialise event. It will load the requested libraries and
	 * will override the core routines regardless of whether the libraries changed. The
	 * reasoning behind this behavior is that fixing those routines will not affect the
	 * current operation at all, and will improve the patched operation.
	 *
	 * @return  void
	 */
	public function onAfterInitialise()
	{
		// Only for site
		if (!$this->app->isSite())
		{
			return;
		}
		// Register listeners for JHtml helpers
		if (!JHtml::isRegistered('bootstrap.popover'))
		{
			JHtml::register('bootstrap.popover', 'PlgSystemJsfixer::popover');
		}

		if (!JHtml::isRegistered('bootstrap.tooltip'))
		{
			JHtml::register('bootstrap.tooltip', 'PlgSystemJsfixer::tooltip');
		}

		if (!JHtml::isRegistered('jquery.framework') && static::$jquery->use)
		{
			JHtml::register('jquery.framework', 'PlgSystemJsfixer::framework');
		}

		if (!JHtml::isRegistered('bootstrap.framework') && static::$bootstrap->use)
		{
			JHtml::register('bootstrap.framework', 'PlgSystemJsfixer::bootstrapframework');
		}

	}
	/**
	 * Override javascript support for Bootstrap popovers with code that checks to
	 * see if it's needed before trying to add itself, thus avoiding throwing a js error.
	 *
	 * Use element's Title as popover content
	 *
	 * @param   string  $selector  Selector for the popover
	 * @param   array   $params    An array of options for the popover.
	 *                  Options for the popover can be:
	 *                      animation  boolean          apply a css fade transition to the popover
	 *                      html       boolean          Insert HTML into the popover. If false, jQuery's text method will be used to insert
	 *                                                  content into the dom.
	 *                      placement  string|function  how to position the popover - top | bottom | left | right
	 *                      selector   string           If a selector is provided, popover objects will be delegated to the specified targets.
	 *                      trigger    string           how popover is triggered - hover | focus | manual
	 *                      title      string|function  default title value if `title` tag isn't present
	 *                      content    string|function  default content value if `data-content` attribute isn't present
	 *                      delay      number|object    delay showing and hiding the popover (ms) - does not apply to manual trigger type
	 *                                                  If a number is supplied, delay is applied to both hide/show
	 *                                                  Object structure is: delay: { show: 500, hide: 100 }
	 *                      container  string|boolean   Appends the popover to a specific element: { container: 'body' }
	 *
	 * @return  void
	 *
	 * @since   3.0
	 */
	public static function popover($selector = '.hasPopover', $params = array())
	{
		// Only load once
		if (isset(static::$loaded[__METHOD__][$selector]))
		{
			return;
		}

		// Include Bootstrap framework
		JHtml::_('bootstrap.framework');

		$opt['animation'] = isset($params['animation']) ? $params['animation'] : null;
		$opt['html']      = isset($params['html']) ? $params['html'] : true;
		$opt['placement'] = isset($params['placement']) ? $params['placement'] : null;
		$opt['selector']  = isset($params['selector']) ? $params['selector'] : null;
		$opt['title']     = isset($params['title']) ? $params['title'] : null;
		$opt['trigger']   = isset($params['trigger']) ? $params['trigger'] : 'hover focus';
		$opt['content']   = isset($params['content']) ? $params['content'] : null;
		$opt['delay']     = isset($params['delay']) ? $params['delay'] : null;
		$opt['container'] = isset($params['container']) ? $params['container'] : 'body';

		$options = JHtml::getJSObject($opt);

		$initFunction = 'function initPopovers (event, container) { ' .
				'if ($(container || document).find(' . json_encode($selector) . ').popover) {' .
					'$(container || document).find(' . json_encode($selector) . ').popover(' . $options . ');' .
				'}' .
			'}';

		// Attach the popover to the document
		JFactory::getDocument()->addScriptDeclaration(
			'jQuery(function($){ initPopovers(); $("body").on("subform-row-add", initPopovers); ' . $initFunction . ' });'
		);

		static::$loaded[__METHOD__][$selector] = true;

		return;
	}
	/**
	 * Add javascript support for Bootstrap tooltips
	 *
	 * Add a title attribute to any element in the form
	 * title="title::text"
	 *
	 * @param   string  $selector  The ID selector for the tooltip.
	 * @param   array   $params    An array of options for the tooltip.
	 *                             Options for the tooltip can be:
	 *                             - animation  boolean          Apply a CSS fade transition to the tooltip
	 *                             - html       boolean          Insert HTML into the tooltip. If false, jQuery's text method will be used to insert
	 *                                                           content into the dom.
	 *                             - placement  string|function  How to position the tooltip - top | bottom | left | right
	 *                             - selector   string           If a selector is provided, tooltip objects will be delegated to the specified targets.
	 *                             - title      string|function  Default title value if `title` tag isn't present
	 *                             - trigger    string           How tooltip is triggered - hover | focus | manual
	 *                             - delay      integer          Delay showing and hiding the tooltip (ms) - does not apply to manual trigger type
	 *                                                           If a number is supplied, delay is applied to both hide/show
	 *                                                           Object structure is: delay: { show: 500, hide: 100 }
	 *                             - container  string|boolean   Appends the popover to a specific element: { container: 'body' }
	 *
	 * @return  void
	 *
	 * @since   3.0
	 */
	public static function tooltip($selector = '.hasTooltip', $params = array())
	{
		if (!isset(static::$loaded[__METHOD__][$selector]))
		{
			// Include Bootstrap framework
			JHtml::_('bootstrap.framework');

			// Setup options object
			$opt['animation'] = isset($params['animation']) ? (boolean) $params['animation'] : null;
			$opt['html']      = isset($params['html']) ? (boolean) $params['html'] : true;
			$opt['placement'] = isset($params['placement']) ? (string) $params['placement'] : null;
			$opt['selector']  = isset($params['selector']) ? (string) $params['selector'] : null;
			$opt['title']     = isset($params['title']) ? (string) $params['title'] : null;
			$opt['trigger']   = isset($params['trigger']) ? (string) $params['trigger'] : null;
			$opt['delay']     = isset($params['delay']) ? (is_array($params['delay']) ? $params['delay'] : (int) $params['delay']) : null;
			$opt['container'] = isset($params['container']) ? $params['container'] : 'body';
			$opt['template']  = isset($params['template']) ? (string) $params['template'] : null;
			$onShow           = isset($params['onShow']) ? (string) $params['onShow'] : null;
			$onShown          = isset($params['onShown']) ? (string) $params['onShown'] : null;
			$onHide           = isset($params['onHide']) ? (string) $params['onHide'] : null;
			$onHidden         = isset($params['onHidden']) ? (string) $params['onHidden'] : null;

			$options = JHtml::getJSObject($opt);

			// Build the script.
			$conditional = 'if ($(' . json_encode($selector) . ').tooltip) { ';
			$script = array('$(container).find(' . json_encode($selector) . ').tooltip(' . $options . ')');

			if ($onShow)
			{
				$script[] = 'on("show.bs.tooltip", ' . $onShow . ')';
			}

			if ($onShown)
			{
				$script[] = 'on("shown.bs.tooltip", ' . $onShown . ')';
			}

			if ($onHide)
			{
				$script[] = 'on("hide.bs.tooltip", ' . $onHide . ')';
			}

			if ($onHidden)
			{
				$script[] = 'on("hidden.bs.tooltip", ' . $onHidden . ')';
			}

			$initFunction = 'function initTooltips (event, container) { ' .
				'container = container || document;' .
				$conditional .
				implode('.', $script) . ';' .
				'}}';

			// Attach tooltips to document
			JFactory::getDocument()
				->addScriptDeclaration('jQuery(function($){ initTooltips(); $("body").on("subform-row-add", initTooltips); ' . $initFunction . ' });');

			// Set static array
			static::$loaded[__METHOD__][$selector] = true;
		}

		return;
	}
	/**
	 * Method to load the jQuery JavaScript framework into the document head
	 *
	 * If debugging mode is on an uncompressed version of jQuery is included for easier debugging.
	 *
	 * @param   boolean  $noConflict  True to load jQuery in noConflict mode [optional]
	 * @param   mixed    $debug       Is debugging mode on? [optional]
	 * @param   boolean  $migrate     True to enable the jQuery Migrate plugin
	 *
	 * @return  void
	 *
	 * @since   3.0
	 */
	public static function framework($noConflict = true, $debug = null, $migrate = true)
	{
		// Only load once
		if (!empty(static::$loaded[__METHOD__]))
		{
			return;
		}

		// If no debugging value is set, use the configuration setting
		if ($debug === null)
		{
			$debug = (boolean) JFactory::getConfig()->get('debug');
		}

		$script = '//code.jquery.com/jquery-1.12.4.min.js';
		$integrity = 'sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ=';
		if (!empty(static::$jquery->url)) {
			$script = static::$jquery->url;
			$integrity = static::$jquery->integrity;
		}
		$options = array('relative' => false, 'detectDebug' => $debug);
		$attributes = array('integrity' => $integrity, 'crossorigin' => "anonymous");

		JHtml::_('script', $script, $options, $attributes);

		// Check if we are loading in noConflict
		if ($noConflict)
		{
			$script = 'jui/jquery-noconflict.js';
			$options = array('version' => 'auto', 'relative' => true);
			$attributes = array();
			if (!empty(static::$jqueryNoConflict->url)) {
				$script = static::$jqueryNoConflict->url;
			}
			if (!empty(static::$jquery->integrity)) {
				$attributes = array( 'integrity' => static::$jqueryNoConflict->integrity, 'crossorigin' => "anonymous");
				$options = array('relative' => false, 'detectDebug' => $debug);
			}

			JHtml::_('script', $script, $options, $attributes);
		}

		// Check if we are loading Migrate
		if ($migrate)
		{
			$script = '//code.jquery.com/jquery-migrate-1.4.1.min.js';
			$integrity = 'sha256-SOuLUArmo4YXtXONKz+uxIGSKneCJG4x0nVcA0pFzV0=';
			if (!empty(static::$jqueryMigrate->url)) {
				$script = static::$jqueryMigrate->url;
				$integrity = static::$jqueryMigrate->integrity;
			}
			$options = array('relative' => false, 'detectDebug' => $debug);
			$attributes = array('integrity' => $integrity, 'crossorigin' => "anonymous");

			JHtml::_('script', $script, $options, $attributes);
		}

		static::$loaded[__METHOD__] = true;

		return;
	}
	/**
	 * Method to load the Bootstrap JavaScript framework into the document head
	 *
	 * If debugging mode is on an uncompressed version of Bootstrap is included for easier debugging.
	 *
	 * @param   mixed  $debug  Is debugging mode on? [optional]
	 *
	 * @return  void
	 *
	 * @since   3.0
	 */
	public static function bootstrapframework($debug = null)
	{
		// Only load once
		if (!empty(static::$loaded[__METHOD__]))
		{
			return;
		}

		// Load jQuery
		JHtml::_('jquery.framework');

		// If no debugging value is set, use the configuration setting
		if ($debug === null)
		{
			$debug = JDEBUG;
		}

		$script = '//stackpath.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js';
		$integrity = 'sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa';
		if (!empty(static::$bootstrap->url)) {
			$script = static::$bootstrap->url;
			$integrity = static::$bootstrap->integrity;
		}
		$options = array('relative' => false, 'detectDebug' => $debug);
		$attributes = array('integrity' => $integrity,
							'crossorigin' => "anonymous",
							static::$bootstrap->load => static::$bootstrap->load);

		JHtml::_('script', $script, $options, $attributes);
		static::$loaded[__METHOD__] = true;

		return;
	}

}