<?php
/**
 * Fabrik Admin Form Model
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 * @since       1.6
 */

namespace Fabrik\Admin\Models;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\Utilities\ArrayHelper;
use Fabrik\Helpers\Worker;
use \Joomla\Registry\Registry as JRegistry;
use \JComponentHelper as JComponentHelper;
use \JFactory as JFactory;
use \JFolder as JFolder;
use \JProfiler as JProfiler;
use \JFilterInput as JFilterInput;
use \FabrikString as FabrikString;
use \FabrikHelperHTML as FabrikHelperHTML;
use Joomla\String\String as String;
use FText as FText;
use \stdClass as stdClass;
use \JFile as JFile;
use \JUri;
use \JRoute;

interface ModelFormFormInterface
{
	/**
	 * Save the form
	 *
	 * @param   array  $data  posted jform data
	 *
	 * @return  bool
	 */
	public function save($data);

}

/**
 * Fabrik Admin Form Model
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @since       3.5
 */
class Form extends View implements ModelFormFormInterface
{
	/**
	 * The prefix to use with controller messages.
	 *
	 * @var  string
	 */
	protected $text_prefix = 'COM_FABRIK_FORM';

	/**
	 * The plugin type?
	 *
	 * @deprecated - don't think this is used
	 *
	 * @var  string
	 */
	protected $pluginType = 'Form';

	/**
	 * If editable if 0 then show view only version of form
	 *
	 * @var bol true
	 */
	public $editable = true;

	/**
	 * Parameters
	 *
	 * @var JRegistry
	 */
	protected $params = null;

	/**
	 * Form errors
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * The form running as a mambot or module(true)
	 *
	 * @var bool
	 */
	public $isMambot = false;

	/**
	 * id
	 * @var int
	 */
	public $id = null;

	/**
	 * Set to -1 if form in ajax module, set to 1+ if in package
	 *
	 * @var int
	 */
	public $packageId = 0;

	/**
	 * Form's group elements
	 *
	 * @var array
	 */
	protected $elements = null;

	/**
	 * List model associated with form
	 *
	 * @var FabrikFEModelList
	 */
	protected $listModel = null;

	/**
	 * Group ids that are actually tablejoins [groupid->joinid]
	 *
	 * @var array
	 */
	public $aJoinGroupIds = array();

	/**
	 * Validation rule classes
	 *
	 * @var array
	 */
	protected $validationRuleClasses = null;

	/**
	 * Join objects for the form
	 *
	 * @var array
	 */
	protected $aJoinObjs = array();

	/**
	 * Concat string to create full element names
	 *
	 * @var string
	 */
	public $joinTableElementStep = '___';

	/**
	 * Row id to submit
	 *
	 * @var int
	 */
	public $rowId = null;

	/**
	 * Submitted as ajax
	 *
	 * @since 3.0
	 * @var bool
	 */
	public $ajax = null;

	/**
	 * Form table
	 *
	 * @var JTable
	 */
	public $form = null;

	/**
	 * Last current element found in hasElement()
	 *
	 * @var object
	 */
	protected $currentElement = null;

	/**
	 * Form Data
	 *
	 * @var array
	 */
	public $formData = null;

	/**
	 * Uploader helper
	 *
	 * @var UploaderHelper
	 */
	protected $uploader = null;

	/**
	 * Pages (array containing group ids for each page in the form)
	 *
	 * @var array
	 */
	protected $pages = null;

	/**
	 * Session model deals with storing incomplete pages
	 *
	 * @var FabrikFEModelFormsession
	 */
	public $sessionModel = null;

	/**
	 * Modified data by any validation rule that uses replace functionality
	 *
	 * @var array
	 */
	public $modifiedValidationData = null;

	/**
	 * Group Models
	 *
	 * @var array
	 */
	public $groups = null;

	/**
	 * Store the form's previous data when processing
	 *
	 * @var array
	 */
	public $origData = null;

	/**
	 * Stores elements not shown in the list view
	 * @var array
	 */
	protected $elementsNotInList = null;

	/**
	 * Form data
	 *
	 * @var array
	 */
	public $data = null;

	/**
	 * Form data - ready for use in template. Contains HTML output for listname___elementname
	 * and raw value for listname___elementname_raw
	 *
	 * @var array
	 */
	public $tmplData = array();

	/**
	 * Form data - keys use the full element name (listname___elementname)
	 * @var unknown_type
	 */
	public $formDataWithTableName = null;

	/**
	 * Should the form store the main row? Set to false in juser
	 * plugin if fabrik table is also #__users
	 *
	 * @var bool
	 */
	public $storeMainRow = true;

	/**
	 * Query used to load form record.
	 *
	 * @var string
	 */
	public $query = null;

	/**
	 * Specifies element name that have been overridden from a form plugin,
	 * so encrypted RO data should be ignored
	 *
	 * @var array
	 */
	protected $pluginUpdatedElements = array();

	/**
	 * Linked fabrik lists
	 *
	 * @var array
	 */
	protected $linkedFabrikLists = null;

	/**
	 * Are we copying a row?  i.e. using form's Copy button.  Plugin manager needs to know.
	 *
	 * @var bool
	 */
	public $copyingRow = false;

	/**
	 * Container string for form plugin JS ini code
	 *
	 * @since 3.1b
	 *
	 * @var string
	 */
	public $formPluginJS = '';

	/**
	 * Form plugin files to load
	 *
	 * @since 3.1b
	 *
	 * @var array
	 */
	public $formPluginShim = array();

	/**
	 * JS options on load, only used when calling onJSOpts plugin
	 * so plugin code can access and modify them
	 *
	 * @since 3.2
	 *
	 * @var array
	 */
	public $jsOpts = null;

	/**
	 * Use this lastInsertId to store the main table's lastInsertId, so we can use this rather
	 * than the list model lastInsertId, which could be for the last joined table rather than
	 * the form's main table.
	 *
	 * @since 3.3
	 *
	 * @var mixed
	 */
	public $lastInsertId = null;

	/**
	 * Save the form
	 *
	 * @param   array $post The jform part of the request data pertaining to the list.
	 *
	 * @return bool
	 * @throws RuntimeException
	 */
	public function save($post)
	{
		$view = ArrayHelper::getValue($post, 'view');
		$this->set('id', $view);
		$item = $this->getItem();
		$groups = $item->get('form.groups');

		$post = $this->prepareSave($post, 'form');
		$selectedGroups = ArrayHelper::fromObject($post->get('form.current_groups'));

		$newGroups = new \stdClass;

		foreach ($groups as $group)
		{
			if (in_array($group->id, $selectedGroups))
			{
				$name = $group->name;
				$newGroups->$name = $group;
			}
		}

		$post->set('form.groups', $newGroups);

		return parent::save($post);
	}

	/**
	 * Get JS
	 *
	 * @return string
	 */
	public function getJs()
	{
		$js[] = "\twindow.addEvent('domready', function () {";
		$plugins = json_encode($this->getPlugins());
		$js[] = "\t\tFabrik.controller = new PluginManager($plugins, '" . $this->getItem()->get('id') . "', 'form');";
		$js[] = "\t})";

		return implode("\n", $js);
	}

	/**
	 * Reinsert the groups ids into formgroup rows
	 *
	 * @param   array  $data           jform post data
	 * @param   array  $currentGroups  group ids
	 *
	 * @return  void
	 */
	protected function _makeFormGroups($data, $currentGroups)
	{
		// FIXME for json view
		echo "_makeFormGroups not workee ";exit;
		$formId = $this->get($this->getName() . '.id');
		$db = Worker::getDbo(true);
		$query = $db->getQuery(true);
		ArrayHelper::toInteger($currentGroups);

		$query->delete('#__fabrik_formgroup')->where('form_id = ' . (int) $formId);

		if (!empty($currentGroups))
		{
			$query->where('group_id NOT IN (' . implode($currentGroups, ', ') . ')');
		}

		$db->setQuery($query);

		// Delete the old form groups
		$db->execute();

		// Get previously saved form groups
		// FIXME - jsonify
		$query->clear()->select('id, group_id')->from('#__fabrik_formgroup')->where('form_id = ' . (int) $formId);
		$db->setQuery($query);
		$groupIds = $db->loadObjectList('group_id');
		$orderId = 1;
		$currentGroups = array_unique($currentGroups);

		foreach ($currentGroups as $group_id)
		{
			if ($group_id != '')
			{
				$group_id = (int) $group_id;
				$query->clear();

				if (array_key_exists($group_id, $groupIds))
				{
					// FIXME - jsonify
					$query->update('#__fabrik_formgroup')
					->set('ordering = ' . $orderId)->where('id =' . $groupIds[$group_id]->id);
				}
				else
				{
					// FIXME - jsonify
					$query->insert('#__fabrik_formgroup')
					->set(array('form_id =' . (int) $formId, 'group_id = ' . $group_id, 'ordering = ' . $orderId));
				}

				$db->setQuery($query);
				$db->execute();
				$orderId++;
			}
		}
	}

	/**
	 * Validate the form
	 *
	 * @param   array   $data   The data to validate.
	 *
	 * @return mixed  false or data
	 */

	public function validate($data)
	{
		$params = $data['params'];
		$ok = parent::validate($data);

		// Standard jform validation failed so we shouldn't test further as we can't be sure of the data
		if (!$ok)
		{
			return false;
		}

		// Hack - must be able to add the plugin xml fields file to $form to include in validation but cant see how at the moment
		$data['params'] = $params;

		return $data;
	}

	/**
	 * Delete form and form groups
	 *
	 * @param   array  &$cids  to delete
	 *
	 * @return  bool
	 */
/*	public function delete(&$cids)
	{
		$res = parent::delete($cids);

		if ($res)
		{
			foreach ($cids as $cid)
			{
				$item = FabTable::getInstance('FormGroup', 'FabrikTable');
				$item->load(array('form_id' => $cid));
				$item->delete();
			}
		}

		return $res;
	}*/

	/**
	 * Are we creating a new record or editing an existing one?
	 * Put here to ensure compat when we go from 3.0 where rowid = 0 = new, to row id '' = new
	 *
	 * @since   3.0.9
	 *
	 * @return  boolean
	 */
	public function isNewRecord()
	{
		return $this->getRowId() === '';
	}

	/**
	 * Get the current records row id
	 * setting a rowid of -1 will load in the current users record (used in
	 * conjunction with usekey variable
	 *
	 * setting a rowid of -2 will load in the last created record
	 *
	 * @return  string  rowid
	 */
	public function getRowId()
	{
		if (isset($this->rowId))
		{
			return $this->rowId;
		}

		$input = $this->app->input;
		$usersConfig = JComponentHelper::getParams('com_fabrik');
		$user = $this->user;
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');

		// $$$rob if we show a form module when in a fabrik form component view - we shouldn't use
		// the request rowid for the mambot as that value is destined for the component
		if ($this->isMambot && $input->get('option') == 'com_' . $package)
		{
			$this->rowId = $usersConfig->get('rowid');
		}
		else
		{
			$this->rowId = Worker::getMenuOrRequestVar('rowid', $usersConfig->get('rowid'), $this->isMambot);

			if ($this->rowId == -2)
			{
				// If the default was set to -2 (load last row) then a pagination form plugin's row id should override menu settings
				$this->rowId = Worker::getMenuOrRequestVar('rowid', $usersConfig->get('rowid'), $this->isMambot, 'request');
			}
		}

		if ($this->getListModel()->getParams()->get('sef-slug', '') !== '')
		{
			$this->rowId = explode(':', $this->rowId);
			$this->rowId = array_shift($this->rowId);
		}
		// $$$ hugh - for some screwed up reason, when using SEF, rowid=-1 ends up as :1
		// $$$ rob === compare as otherwise 0 == ":1" which meant that the users record was loaded
		if ($this->isUserRowId())
		{
			$this->rowId = '-1';
		}
		// Set rowid to -1 to load in the current users record
		switch ($this->rowId)
		{
			case '-1':
				// New rows (no logged in user) should be ''
				$this->rowId = $user->get('id') == 0 ? '' : $user->get('id');
				break;
			case '-2':
				// Set rowid to -2 to load in the last recorded record
				$this->rowId = $this->getMaxRowId();
				break;
		}

		/**
		 * $$$ hugh - added this as a Hail Mary sanity check, make sure
		 * rowId is an empty string if for whatever reason it's still null,
		 * as we have code in various place that checks for $this->rowId === ''
		 * to detect adding new form.  So if at this point rowid is null, we have
		 * to assume it's a new form, and set rowid to empty string.
		 */
		if (is_null($this->rowId))
		{
			$this->rowId = '';
		}

		/**
		 * $$$ hugh - there's a couple of places, like calendar viz, that add &rowid=0 to
		 * query string for new form, so check for that and set to empty string.
		 */
		if ($this->rowId === '0')
		{
			$this->rowId = '';
		}

		Worker::getPluginManager()->runPlugins('onSetRowId', $this);

		return $this->rowId;
	}

	/**
	 * Should the form load up rowid=-1 usekey=foo
	 *
	 * @param   string  $priority  Request priority menu or request
	 *
	 * @return boolean
	 */

	protected function isUserRowId($priority = 'menu')
	{
		$rowId = Worker::getMenuOrRequestVar('rowid', '', $this->isMambot, $priority);

		return $rowId === '-1' || $rowId === ':1';
	}

	/**
	 * Checks if the params object has been created and if not creates and returns it
	 *
	 * @return  object  params
	 */

	public function getParams()
	{
		if (!isset($this->params))
		{
			$item = $this->getItem();
			$this->params = new JRegistry($item->get('form.params'));
		}

		return $this->params;
	}

	/**
	 * Does the form contain user errors
	 *
	 * @return  bool
	 */
	public function hasErrors()
	{
		$errorsFound = false;

		foreach ($this->errors as $field => $errors)
		{
			if (!empty($errors))
			{
				foreach ($errors as $error)
				{
					if (!empty($error[0]))
					{
						$errorsFound = true;
					}
				}
			}
		}

		if ($this->saveMultiPage(false))
		{
			$sessionRow = $this->getSessionData();
			/*
			 * Test if its a resumed paged form
			 * if so _arErrors will be filled so check all elements had no errors
			 */
			$multiPageErrors = false;

			if ($sessionRow->data != '')
			{
				foreach ($this->errors as $err)
				{
					if (!empty($err[0]))
					{
						$multiPageErrors = true;
					}
				}

				if (!$multiPageErrors)
				{
					$errorsFound = false;
				}
			}
		}

		return $errorsFound;
	}

	/**
	 * Checks if user is logged in and form multipage settings to determine
	 * if the form saves to the session table on multipage navigation
	 *
	 * @param   bool  $useSessionOn  Return true if JSession contains session.on - used in confirmation
	 * plugin to re-show the previously entered form data. Not used in $this->hasErrors() otherwise logged in users
	 * can not get the confirmation plugin to work
	 *
	 * @return  bool
	 */

	public function saveMultiPage($useSessionOn = true)
	{
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		$params = $this->getParams();

		// Set in plugins such as confirmation plugin
		$pluginManager = Worker::getPluginManager();
		$pluginManager->runPlugins('usesSession', $this, 'form');

		if (in_array(true, $pluginManager->data))
		{
			if ($this->session->get('com_' . $package . '.form.' . $this->getId() . '.' . $this->getRowId() . '.session.on') == true && $useSessionOn)
			{
				return true;
			}
		}

		$save = (int) $params->get('multipage_save', 0);

		if ($this->user->get('id') !== 0)
		{
			return $save === 0 ? false : true;
		}
		else
		{
			return $save === 2 ? true : false;
		}
	}

	/**
	 * Get the template name
	 *
	 * @since 3.0
	 *
	 * @return string tmpl name
	 */
	public function getTmpl()
	{
		$input = $this->app->input;
		$params = $this->getParams();
		$item = $this->getItem();
		$tmpl = '';
		$default = 'bootstrap';
		$document = JFactory::getDocument();

		if ($document->getType() === 'pdf')
		{
			$tmpl = $params->get('pdf_template', '') !== '' ? $params->get('pdf_template') : $default;
		}
		else
		{
			if ($this->app->isAdmin())
			{
				$tmpl = $this->isEditable() ? $params->get('admin_form_template') : $params->get('admin_details_template');
				$tmpl = $tmpl == '' ? $default : $tmpl;
			}

			if ($tmpl == '')
			{
				if ($this->isEditable())
				{
					$tmpl = $item->get('form.form_template') == '' ? $default : $item->get('form.form_template');
				}
				else
				{
					$tmpl = $item->get('form.view_only_template') == '' ? $default : $item->get('form.view_only_template');
				}
			}
		}

		$tmpl = Worker::getMenuOrRequestVar('fabriklayout', $tmpl, $this->isMambot);

		// Finally see if the options are overridden by a querystring var
		$baseTmpl = $tmpl;
		$tmpl = $input->get('layout', $tmpl);

		// Test it exists - otherwise revert to baseTmpl tmpl
		$folder = $this->isEditable() ? 'form' : 'details';

		if (!JFolder::exists(JPATH_SITE . '/components/com_fabrik/views/' . $folder . '/tmpl/' . $tmpl))
		{
			$tmpl = $baseTmpl;
		}

		$this->isEditable() ? $item->set('form.form_template', $tmpl) : $item->set('form.view_only_template', $tmpl);

		return $tmpl;
	}

	/**
	 * Is the form editable
	 *
	 * @return  bool
	 */
	public function isEditable()
	{
		return $this->editable;
	}

	/**
	 * Get an list of elements that aren't shown in the table view
	 *
	 * @return  array  of element table objects
	 */
	public function getElementsNotInTable()
	{
		if (!isset($this->elementsNotInList))
		{
			$this->elementsNotInList = array();
			$groups = $this->getGroupsHierarchy();

			foreach ($groups as $group)
			{
				$elements = $group->getPublishedElements();

				foreach ($elements as $elementModel)
				{
					if ($elementModel->canView() || $elementModel->canUse())
					{
						$element = $elementModel->getElement();
						$show = $element->get('show_in_list_summary', '');

						if ($show)
						{
							$this->elementsNotInList[] = $element;
						}
					}
				}
			}
		}

		return $this->elementsNotInList;
	}

	/**
	 * Collates data to write out the form
	 *
	 * @return  mixed  bool
	 */
	public function render()
	{
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		$profiler = JProfiler::getInstance('Application');
		JDEBUG ? $profiler->mark('form model render: start') : null;

		// $$$rob required in paolo's site when rendering modules with ajax option turned on
		$this->listModel = null;

		$rowId = $this->getRowId();
		$this->setRowId($rowId);

		$this->getListModel();
		/*
		 * $$$ hugh - need to call this here as we set $this->editable here, which is needed by some plugins
		 * , this means that getData() is being called from checkAccessFromListSettings(),
		 * so plugins running onBeforeLoad will have to unset($formModel->_data) if they want to
		 * do something funky like change the rowid being loaded.  Not a huge problem, but caught me out
		 * when a custom PHP onBeforeLoad plugin I'd written for a client suddenly broke.
		 */
		$this->checkAccessFromListSettings();
		$pluginManager = Worker::getPluginManager();
		$res = $pluginManager->runPlugins('onBeforeLoad', $this);

		if (in_array(false, $res))
		{
			return false;
		}

		JDEBUG ? $profiler->mark('form model render: getData start') : null;
		$data = $this->getData();
		JDEBUG ? $profiler->mark('form model render: getData end') : null;
		$res = $pluginManager->runPlugins('onLoad', $this);

		if (in_array(false, $res))
		{
			return false;
		}

		JDEBUG ? $profiler->mark('formmodel render end') : null;

		$this->session->set('com_' . $package . '.form.' . $this->getId() . '.data', $this->data);

		// $$$ rob return res - if its false the the form will not load
		return $res;
	}

	/**
	 * Set row id
	 *
	 * @param   string  $id  primary key value
	 *
	 * @since   3.0.7
	 *
	 * @return  void
	 */
	public function setRowId($id)
	{
		$this->rowId = $id;
	}

	/**
	 * Makes sure that the form is not viewable based on the list's access settings
	 *
	 * Also sets the form's editable state, if it can record in to a db table
	 *
	 * @return  int  0 = no access, 1 = view only , 2 = full form view, 3 = add record only
	 */
	public function checkAccessFromListSettings()
	{
		$item = $this->getItem();

		if ($item->get('form.record_in_database') == 0)
		{
			return 2;
		}

		$listModel = $this->getListModel();

		if (!is_object($listModel))
		{
			return 2;
		}

		$data = $this->getData();
		$ret = 0;

		if ($listModel->canViewDetails())
		{
			$ret = 1;
		}

		$isUserRowId = $this->isUserRowId();

		// New form can we add?
		if ($this->getRowId() === '' || $isUserRowId)
		{
			// If they can edit can they also add
			if ($listModel->canAdd())
			{
				$ret = 3;
			}
			// $$$ hugh - corner case for rowid=-1, where they DON'T have add perms, but DO have edit perms
			elseif ($isUserRowId && $listModel->canEdit($data))
			{
				$ret = 2;
			}
		}
		else
		{
			// Editing from - can we edit
			if ($listModel->canEdit($data))
			{
				$ret = 2;
			}
		}
		// If no access (0) or read only access (1) set the form to not be editable
		$editable = ($ret <= 1) ? false : true;
		$this->setEditable($editable);

		if ($this->app->input->get('view', 'form') == 'details')
		{
			$this->setEditable(false);
		}

		return $ret;
	}

	/**
	 * Populate the Model state
	 *
	 * @return  void
	 */
	protected function populateState()
	{
		$input = $this->app->input;

		if (!$this->app->isAdmin())
		{
			// Load the menu item / component parameters.
			$params = $this->app->getParams();
			$this->set('params', $params);

			// Load state from the request.
			$pk = $input->getString('id', $params->get('id'));
		}
		else
		{
			$pk = $input->getString('id');
		}

		$this->set('id', $pk);
	}

	/**
	 * Main method to get the data to insert into the form
	 *
	 * @return  array  Form's data
	 */
	public function getData()
	{
		// If already set return it. If not was causing issues with the juser form plugin
		// when it tried to modify the form->data info, from within its onLoad method, when sync user option turned on.

		if (isset($this->data))
		{
			return $this->data;
		}

		$this->getRowId();
		$input = $this->app->input;
		$profiler = JProfiler::getInstance('Application');
		JDEBUG ? $profiler->mark('formmodel getData: start') : null;
		$this->data = array();
		$f = JFilterInput::getInstance();

		/*
		 * $$$ hugh - we need to remove any elements from the query string,
		 * if the user doesn't have access, otherwise ACL's on elements can
		 * be bypassed by just setting value on form load query string!
		 */

		$clean_request = $f->clean($_REQUEST, 'array');

		foreach ($clean_request as $key => $value)
		{
			$test_key = FabrikString::rtrimword($key, '_raw');
			$elementModel = $this->getElement($test_key, false, false);

			if ($elementModel !== false)
			{
				if (!$elementModel->canUse())
				{
					unset($clean_request[$key]);
				}
			}
		}

		$data = $clean_request;
		$item = $this->getItem();
		$aGroups = $this->getGroupsHierarchy();
		JDEBUG ? $profiler->mark('formmodel getData: groups loaded') : null;

		if (!$item->get('form.record_in_database'))
		{
			FabrikHelperHTML::debug($data, 'form:getData from $_REQUEST');
			$data = $f->clean($_REQUEST, 'array');
		}
		else
		{
			JDEBUG ? $profiler->mark('formmodel getData: start get list model') : null;
			$listModel = $this->getListModel();
			JDEBUG ? $profiler->mark('formmodel getData: end get list model') : null;
			$fabrikDb = $listModel->getDb();
			JDEBUG ? $profiler->mark('formmodel getData: db created') : null;
			$this->aJoinObjs = $listModel->getJoins();
			JDEBUG ? $profiler->mark('formmodel getData: joins loaded') : null;

			if ($this->hasErrors())
			{
				// $$$ hugh - if we're a mambot, reload the form session state we saved in
				// process() when it banged out.
				if ($this->isMambot)
				{
					$sessionRow = $this->getSessionData();
					$this->sessionModel->last_page = 0;

					if ($sessionRow->data != '')
					{
						$data = ArrayHelper::toObject(unserialize($sessionRow->data), 'stdClass', false);
						JFilterOutput::objectHTMLSafe($data);
						$data = array($data);
						FabrikHelperHTML::debug($data, 'form:getData from session (form in Mambot and errors)');
					}
				}
				else
				{
					// $$$ rob - use setFormData rather than $_GET
					// as it applies correct input filtering to data as defined in article manager parameters
					$data = $this->setFormData();
					$data = ArrayHelper::toObject($data, 'stdClass', false);

					// $$$rob ensure "<tags>text</tags>" that are entered into plain text areas are shown correctly
					JFilterOutput::objectHTMLSafe($data);
					$data = ArrayHelper::fromObject($data);
					FabrikHelperHTML::debug($data, 'form:getData from POST (form not in Mambot and errors)');
				}
			}
			else
			{
				$sessionLoaded = false;

				// Test if its a resumed paged form
				if ($this->saveMultiPage())
				{
					$sessionRow = $this->getSessionData();
					JDEBUG ? $profiler->mark('formmodel getData: session data loaded') : null;

					if ($sessionRow->data != '')
					{
						$sessionLoaded = true;
						/*
						 * $$$ hugh - this chunk should probably go in setFormData, but don't want to risk any side effects just now
						 * problem is that later failed validation, non-repeat join element data is not formatted as arrays,
						 * but from this point on, code is expecting even non-repeat join data to be arrays.
						 */
						$tmp_data = unserialize($sessionRow->data);
						$groups = $this->getGroupsHierarchy();

						foreach ($groups as $groupModel)
						{
							if ($groupModel->isJoin() && !$groupModel->canRepeat())
							{
								foreach ($tmp_data['join'][$groupModel->getJoinId()] as &$el)
								{
									$el = array($el);
								}
							}
						}

						$bits = $data;
						$bits = array_merge($tmp_data, $bits);
						$data = array(ArrayHelper::toObject($bits));
						FabrikHelperHTML::debug($data, 'form:getData from session (form not in Mambot and no errors');
					}
				}

				if (!$sessionLoaded)
				{
					/* Only try and get the row data if its an active record
					 * use !== '' as rowid may be alphanumeric.
					 * Unlike 3.0 rowId does equal '' if using rowid=-1 and user not logged in
					 */
					$useKey = Worker::getMenuOrRequestVar('usekey', '', $this->isMambot);

					if (!empty($useKey) || $this->rowId !== '')
					{
						// $$$ hugh - once we have a few join elements, our select statements are
						// getting big enough to hit default select length max in MySQL.
						$listModel->setBigSelects();

						// Otherwise lets get the table record
						$opts = $input->get('task') == 'form.inlineedit' ? array('ignoreOrder' => true) : array();
						$sql = $this->buildQuery($opts);
						$fabrikDb->setQuery($sql);
						FabrikHelperHTML::debug($fabrikDb->getQuery(), 'form:render');
						$rows = $fabrikDb->loadObjectList();

						if (is_null($rows))
						{
							JError::raiseWarning(500, $fabrikDb->getErrorMsg());
						}

						JDEBUG ? $profiler->mark('formmodel getData: rows data loaded') : null;

						// $$$ rob Ack above didn't work for joined data where there would be n rows returned for "this rowid = $this->rowId  \n";
						if (!empty($rows))
						{
							// Only do this if the query returned some rows (it wont if usekey on and userid = 0 for example)
							$data = array();

							foreach ($rows as &$row)
							{
								if (empty($data))
								{
									// If loading in a rowid=-1 set the row id to the actual row id
									$this->rowId = isset($row->__pk_val) ? $row->__pk_val : $this->rowId;
								}

								$row = empty($row) ? array() : ArrayHelper::fromObject($row);
								$request = $clean_request;
								$request = array_merge($row, $request);
								$data[] = ArrayHelper::toObject($request);
							}
						}

						FabrikHelperHTML::debug($data, 'form:getData from querying rowid= ' . $this->rowId . ' (form not in Mambot and no errors)');

						// If empty data return and trying to edit a record then show error
						JDEBUG ? $profiler->mark('formmodel getData: empty test') : null;

						// Was empty($data) but that is never empty. Had issue where list prefilter meant record was not loaded, but no message shown in form
						if (empty($rows) && $this->rowId != '')
						{
							// $$$ hugh - special case when using -1, if user doesn't have a record yet
							if ($this->isUserRowId())
							{
								return;
							}
							else
							{
								// If no key found set rowid to 0 so we can insert a new record.
								if (empty($useKey) && !$this->isMambot && in_array($input->get('view'), array('form', 'details')))
								{
									$this->rowId = '';
									/**
									 * runtime exception is a little obtuse for people getting here from legitimate links,
									 * like from an email, but aren't logged in so run afoul of a pre-filter, etc
									 * So do the 3.0 thing, and raise a warning
									 */
									//throw new RuntimeException(FText::_('COM_FABRIK_COULD_NOT_FIND_RECORD_IN_DATABASE'));
									JError::raiseWarning(500, FText::_('COM_FABRIK_COULD_NOT_FIND_RECORD_IN_DATABASE'));
								}
								else
								{
									// If we are using usekey then there's a good possibility that the record
									// won't yet exist - so in this case suppress this error message
									$this->rowId = '';
								}
							}
						}
					}
				}
				// No need to setJoinData if you are correcting a failed validation
				if (!empty($data))
				{
					$this->setJoinData($data);
				}
			}
		}

		$this->data = $data;
		FabrikHelperHTML::debug($data, 'form:data');
		JDEBUG ? $profiler->mark('queryselect: getData() end') : null;

		return $this->data;
	}

	/**
	 * Is the page a multi-page form?
	 *
	 * @return  bool
	 */
	public function isMultiPage()
	{
		$groups = $this->getGroupsHierarchy();

		/* @var $groupModel \Fabrik\Admin\Models\Group */
		foreach ($groups as $groupModel)
		{
			$params = $groupModel->getParams();

			if ($params->get('split_page'))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Query all active form plugins to see if they inject custom html into the top
	 * or bottom of the form
	 *
	 * @return  array  plugin top html, plugin bottom html (inside <form>) plugin end (after form)
	 */

	public function getFormPluginHTML()
	{
		$pluginManager = Worker::getPluginManager();
		$pluginManager->getPlugInGroup('form');

		$pluginManager->runPlugins('getBottomContent', $this, 'form');
		$pluginBottom = implode("<br />", array_filter($pluginManager->data));

		$pluginManager->runPlugins('getTopContent', $this, 'form');
		$pluginTop = implode("<br />", array_filter($pluginManager->data));

		// Inserted after the form's closing </form> tag
		$pluginManager->runPlugins('getEndContent', $this, 'form');
		$pluginEnd = implode("<br />", array_filter($pluginManager->data));

		return array($pluginTop, $pluginBottom, $pluginEnd);
	}

	/**
	 * Determines if the form can be published
	 *
	 * @return  bool  true if publish dates are ok
	 */
	public function canPublish()
	{
		$db = Worker::getDbo();
		$item = $this->getItem();
		$nullDate = $db->getNullDate();
		$up = $item->get('form.publish_up');
		$down = $item->get('form.publish_down');
		$publishUp = JFactory::getDate($up)->toUnix();
		$publishDown = JFactory::getDate($down)->toUnix();
		$now = JFactory::getDate()->toUnix();

		if ($item->get('form.published') == '1')
		{
			if ($now >= $publishUp || $up == '' || $up == $nullDate)
			{
				if ($now <= $publishDown ||$down == '' || $down == $nullDate)
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * loads form's css files
	 * Checks : custom css file, template css file. Including them if found
	 *
	 * @return  void
	 */

	public function getFormCss()
	{
		$input = $this->app->input;
		$tmpl = $this->getTmpl();
		$v = $this->isEditable() ? 'form' : 'details';

		// Check for a form template file (code moved from view)
		if ($tmpl != '')
		{
			$qs = '?c=' . $this->getId();
			$qs .= '&amp;rowid=' . $this->getRowId();

			/* $$$ need &amp; for pdf output which is parsed through xml parser otherwise fails
			 * If FabrikHelperHTML::styleSheetajax loaded then don't do &amp;
			 */
			$view = $this->isEditable() ? 'form' : 'details';

			if (FabrikHelperHTML::cssAsAsset())
			{
				$qs .= '&view=' . $v;
				$qs .= '&rowid=' . $this->getRowId();
			}
			else
			{
				$qs .= '&amp;view=' . $v;
				$qs .= '&amp;rowid=' . $this->getRowId();
			}

			$tmplPath = 'templates/' . $this->app->getTemplate() . '/html/com_fabrik/' . $view . '/' . $tmpl . '/template_css.php' . $qs;

			if (!FabrikHelperHTML::stylesheetFromPath($tmplPath))
			{
				$ok = FabrikHelperHTML::stylesheetFromPath('components/com_fabrik/views/' . $view . '/tmpl/' . $tmpl . '/template_css.php' . $qs);
			}

			/* $$$ hugh - as per Skype convos with Rob, decided to re-instate the custom.css convention.  So I'm adding two files:
			 * custom.css - for backward compat with existing 2.x custom.css
			 * custom_css.php - what we'll recommend people use for custom css moving forward.
			 */

			if (!FabrikHelperHTML::stylesheetFromPath('templates/' . $this->app->getTemplate() . '/html/com_fabrik/' . $view . '/' . $tmpl . '/custom.css' . $qs))
			{
				FabrikHelperHTML::stylesheetFromPath('components/com_fabrik/views/' . $view . '/tmpl/' . $tmpl . '/custom.css' . $qs);
			}

			$path = 'templates/' . $this->app->getTemplate() . '/html/com_fabrik/' . $view . '/' . $tmpl . '/custom_css.php' . $qs;

			if (!FabrikHelperHTML::stylesheetFromPath($path))
			{
				FabrikHelperHTML::stylesheetFromPath('components/com_fabrik/views/' . $view . '/tmpl/' . $tmpl . '/custom_css.php' . $qs);
			}
		}

		if ($this->app->isAdmin() && $input->get('tmpl') === 'components')
		{
			FabrikHelperHTML::stylesheet('administrator/templates/system/css/system.css');
		}
	}

	/**
	 * Load the JS files into the document
	 *
	 * @param   array  &$srcs  js script srcs to load in the head
	 *
	 * @return null
	 */

	public function getCustomJsAction(&$srcs)
	{
		// $$$ hugh - added ability to use form_XX, as am adding custom list_XX
		$view = $this->isEditable() ? 'form' : 'details';

		if (JFile::exists(COM_FABRIK_FRONTEND . '/js/' . $this->getId() . '.js'))
		{
			$srcs[] = 'components/com_fabrik/js/' . $this->getId() . '.js';
		}
		elseif (JFile::exists(COM_FABRIK_FRONTEND . '/js/' . $view . '_' . $this->getId() . '.js'))
		{
			$srcs[] = 'components/com_fabrik/js/' . $view . '_' . $this->getId() . '.js';
		}
	}

	/**
	 * Set the browser title
	 *
	 * @param   string  $title  Default browser title set by menu items' 'page_title' property
	 *
	 * @return	string	Browser title
	 */
	public function getPageTitle($title = '')
	{
		$title = $title == '' ? $this->getLabel() : $title;
		$groups = $this->getGroupsHierarchy();

		/* @var $groupModel \Fabrik\Admin\Models\Group */
		foreach ($groups as $groupModel)
		{
			$elementModels = $groupModel->getPublishedElements();

			/* @var $elementModel \Fabrik\Plugins\Element */
			foreach ($elementModels as $elementModel)
			{
				$element = $elementModel->getElement();

				if ($element->get('use_in_page_title') == '1')
				{
					$title .= ' ' . $elementModel->getTitlePart($this->data);
				}
			}
		}

		return $title;
	}

	/**
	 * Compares the forms table with its groups to see if any of the groups are in fact table joins
	 *
	 * @param   array  $joins  tables joins
	 *
	 * @return	array	array(group_id =>join_id)
	 */

	public function getJoinGroupIds($joins = null)
	{
		$listModel = $this->getlistModel();

		if (is_null($joins))
		{
			$joins = $listModel->getJoins();
		}

		$arJoinGroupIds = array();
		$groups = $this->getGroupsHierarchy();

		foreach ($groups as $groupModel)
		{
			foreach ($joins as $join)
			{
				if ($join->element_id == 0 && $groupModel->getGroup()->id == $join->group_id)
				{
					$arJoinGroupIds[$groupModel->getId()] = $join->id;
				}
			}
		}

		$this->aJoinGroupIds = $arJoinGroupIds;

		return $arJoinGroupIds;
	}

	/**
	 * Gets the javascript actions the forms elements
	 *
	 * @return  array  javascript actions
	 */

	public function getJsActions()
	{
		if (isset($this->jsActions))
		{
			return $this->jsActions;
		}

		$this->jsActions = array();
		$aJsActions = array();
		$aElIds = array();
		$groups = $this->getGroupsHierarchy();

		$actions = array();

		foreach ($groups as $groupModel)
		{
			$elementModels = $groupModel->getPublishedElements();

			foreach ($elementModels as $elementModel)
			{
				$element = $elementModel->getElement();
				/* $$$ hugh - only needed getParent when we weren't saving changes to parent params to child
				 * which we should now be doing ... and getParent() causes an extra table lookup for every child
				 * element on the form.
				 */
				$aJsActions[$elementModel->getElement()->get('id')] = array();
				$aElIds[] = $elementModel->getElement()->get('id');
				$actions  = array_merge($actions, $element->get('jsevents', array()));
			}
		}

		foreach ($actions as $r)
		{
			// Merge the js attribs back into the array
			$a = json_decode($r->params);

			foreach ($a as $k => $v)
			{
				$r->$k = $v;
			}

			unset($r->params);

			if (!isset($r->js_published) || (int) $r->js_published === 1)
			{
				$this->jsActions[$r->element_id][] = $r;
			}
		}

		return $this->jsActions;
	}

	/**
	 * Get the ids of all the groups in the form
	 *
	 * @return  array  group ids
	 */

	public function getGroupIds()
	{
		$groups = $this->getPublishedGroups();

		return array_keys($groups);
	}

	/**
	 * Force load in the group ids
	 * separate from getGroupIds as you need to force load these
	 * when saving the table
	 *
	 * @deprecated - not used?
	 *
	 * @return  array  group ids
	 */

	protected function _loadGroupIds()
	{
		unset($this->_publishedformGroups);

		return $this->getGroupIds();
	}

	/**
	 * Gets each element in the form along with its group info
	 *
	 * @param   bool  $excludeUnpublished  included unpublished elements in the result
	 *
	 * @return  array  element objects
	 */

	public function getFormGroups($excludeUnpublished = true)
	{
		$params = $this->getParams();
		$db = Worker::getDbo(true);
		$query = $db->getQuery(true);

		// FIXME - jsonify
		$query
			->select(
				'*, #__fabrik_groups.params AS gparams, #__fabrik_elements.id as element_id
		, #__fabrik_groups.name as group_name, RAND() AS rand_order')->from('#__fabrik_formgroup')
			->join('LEFT', '#__fabrik_groups	ON #__fabrik_formgroup.group_id = #__fabrik_groups.id')
			->join('LEFT', '#__fabrik_elements ON #__fabrik_groups.id = #__fabrik_elements.group_id')
			->where('#__fabrik_formgroup.form_id = ' . (int) $this->getState('form.id'));

		if ($excludeUnpublished)
		{
			$query->where('#__fabrik_elements.published = 1');
		}

		if ($params->get('randomise_groups') == 1)
		{
			$query->order('rand_order, #__fabrik_elements.ordering');
		}
		else
		{
			$query->order('#__fabrik_formgroup.ordering, #__fabrik_formgroup.group_id, #__fabrik_elements.ordering');
		}

		$db->setQuery($query);
		$groups = $db->loadObjectList();
		$this->elements = $groups;

		return $groups;
	}

	/**
	 * This checks to see if the form has a file upload element
	 * and returns the correct encoding type for the form
	 *
	 * @return  string  form encoding type
	 */

	public function getFormEncType()
	{
		$groups = $this->getGroupsHierarchy();

		foreach ($groups as $groupModel)
		{
			$elementModels = $groupModel->getPublishedElements();

			foreach ($elementModels as $elementModel)
			{
				if ($elementModel->isUpload())
				{
					return "multipart/form-data";
				}
			}
		}

		return "application/x-www-form-urlencoded";
	}

	/**
	 * Run a method on all the element plugins in the form
	 *
	 * @param   string  $method  method to call
	 * @param   array   $data    posted form data
	 *
	 * @deprecated - not used?
	 *
	 * @return  void
	 */

	public function runElementPlugins($method, $data)
	{
		$groups = $this->getGroupsHierarchy();

		foreach ($groups as $groupModel)
		{
			$elementModels = $groupModel->getPublishedElements();

			foreach ($elementModels as $elementModel)
			{
				$params = $elementModel->getParams();

				if (method_exists($elementModel, $method))
				{
					$elementModel->$method($params, $data);
				}
			}
		}
	}

	/**
	 * Get the plugin manager
	 *
	 * @deprecated use return Worker::getPluginManager(); instead since 3.0b
	 *
	 * @return  object  plugin manager
	 */

	public function getPluginManager()
	{
		return Worker::getPluginManager();
	}

	/**
	 * When the form is submitted we want to get the original record it
	 * is updating - this is used in things like the fileupload element
	 * to check for changes in uploaded files and process the difference
	 *
	 * @return	array
	 */

	protected function setOrigData()
	{
		$input = $this->app->input;

		if ($this->isNewRecord() || !$this->getItem()->get('form.record_in_database'))
		{
			$this->_origData = array(new stdClass);
		}
		else
		{
			/*
			 * $$$ hugh - when loading origdata on editing of a rowid=-1/usekey form,
			 * the rowid will be set to the actual form tables's rowid, not the userid,
			 * so we need to unset 'usekey', otherwise we end up with the wrong row.
			 * I thought we used to take care of this elsewhere?
			 */

			$isUserRow = $this->isUserRowId();

			if ($isUserRow)
			{
				$orig_usekey = $input->get('usekey', '');
				$input->set('usekey', '');
			}

			$listModel = $this->getListModel();
			$fabrikDb = $listModel->getDb();
			$sql = $this->buildQuery();
			$fabrikDb->setQuery($sql);
			$this->_origData = $fabrikDb->loadObjectList();

			if ($isUserRow)
			{
				$input->set('usekey', $orig_usekey);
			}
		}
	}

	/**
	 * Get the form record's original data - before any alterations were made to it
	 * in the form
	 *
	 * @return  array
	 */

	public function getOrigData()
	{
		if (!isset($this->_origData))
		{
			$this->setOrigData();
		}

		return $this->_origData;
	}

	/**
	 * test if orig data is empty.  Made this a function, as it's not a simple test
	 * for empty(), and code outside the model shouldn't need to know it'll be a one
	 * entry array with an empty stdClass in it.
	 *
	 * @return  bool
	 */

	public function origDataIsEmpty()
	{
		if (!isset($this->_origData))
		{
			$this->setOrigData();
		}

		return (empty($this->_origData) || (count($this->_origData) == 1 && count((array) $this->_origData[0]) == 0));
	}

	/**
	 * Are we copying a row?  Usually set in controller process().
	 *
	 * @param   bool  $set  if true, set copyingRow to true
	 *
	 * @return	bool
	 */

	public function copyingRow($set = false)
	{
		if ($set)
		{
			$this->copyingRow = true;
		}

		return $this->copyingRow;
	}

	/**
	 * Processes the form data and decides what action to take
	 *
	 * @return  bool  false if one of the plugins returns an error otherwise true
	 */

	public function process()
	{
		$profiler = JProfiler::getInstance('Application');
		JDEBUG ? $profiler->mark('process: start') : null;

		$input = $this->app->input;

		error_reporting(error_reporting() ^ (E_WARNING | E_NOTICE));
		@set_time_limit(300);
		require_once COM_FABRIK_FRONTEND . '/helpers/uploader.php';
		$item = $this->getItem();
		$pluginManager = Worker::getPluginManager();

		$sessionModel = new FormSession;
		$sessionModel->setFormId($this->getId());
		$sessionModel->setRowId($this->rowId);
		/* $$$ rob rowId can be updated by juser plugin so plugin can use check (for new/edit)
		 * now looks at origRowId
		 */
		$this->origRowId = $this->rowId;

		JDEBUG ? $profiler->mark('process, getGroupsHierarchy: start') : null;
		$this->getGroupsHierarchy();

		if ($item->get('form.record_in_database') == '1')
		{
			JDEBUG ? $profiler->mark('process, setOrigData: start') : null;
			$this->setOrigData();
		}

		/*
		 * $$$ hugh - we do this prior to processToDb(), but turns out we need formDataWithTableName in
		 * some plugins, like 'php', which run $formModel->getProcessData().  But it's kind of a chicken
		 * and egg, because those same plugins my change $formData.  Anyway, only solution for now is
		 * set up $this->formDataWithTaleName here, so they at least have the posted data to work with,
		 * then do it again after all the plugins have run.  So, rule of thumb ... plugins running onBeforeProcess
		 * or onBeforeStore need to modify formData, not formDataWithTableName.
		 */
		$this->formDataWithTableName = $this->formData;

		JDEBUG ? $profiler->mark('process, onBeforeProcess plugins: start') : null;
		if (in_array(false, $pluginManager->runPlugins('onBeforeProcess', $this)))
		{
			return false;
		}

		$this->removeEmptyNoneJoinedGroupData($this->formData);
		JDEBUG ? $profiler->mark('process, setFormData: start') : null;
		$this->setFormData();

		JDEBUG ? $profiler->mark('process, _doUpload: start') : null;
		if (!$this->_doUpload())
		{
			return false;
		}

		/** $$$ rob 27/10/2011 - moved above _doUpload as code in there is trying to update formData which is not yet set
		 * this->setFormData();
		 */

		JDEBUG ? $profiler->mark('process, onBeforeStore plugins: start') : null;
		if (in_array(false, $pluginManager->runPlugins('onBeforeStore', $this)))
		{
			return false;
		}

		$this->formDataWithTableName = $this->formData;

		if ($item->get('form.record_in_database') == '1')
		{
			$this->processToDB();
		}

		// Clean the cache.
		$cache = JFactory::getCache($input->get('option'));
		$cache->clean();

		// $$$rob run this before as well as after onAfterProcess (ONLY for redirect plugin)
		// so that any redirect urls are available for the plugin (e.g twitter)
		JDEBUG ? $profiler->mark('process, onLastProcess plugins: start') : null;
		$pluginManager->runPlugins('onLastProcess', $this);

		JDEBUG ? $profiler->mark('process, onAfterProcess plugins: start') : null;
		if (in_array(false, $pluginManager->runPlugins('onAfterProcess', $this)))
		{
			// $$$ rob this no longer stops default redirect (not needed any more)
			// returning false here stops the default redirect occurring
			return false;
		}
		// Need to remove the form session before redirect plugins occur
		$sessionModel->remove();

		// $$$rob used ONLY for redirect plugins
		JDEBUG ? $profiler->mark('process, onLastProcess plugins: start') : null;
		if (in_array(false, $pluginManager->runPlugins('onLastProcess', $this)))
		{
			// $$$ rob this no longer stops default redirect (not needed any more)
			// returning false here stops the default redirect occurring
			return false;
		}

		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');

		// Clean both admin and front end cache.
		parent::cleanCache('com_' . $package, 1);
		parent::cleanCache('com_' . $package, 0);

		JDEBUG ? $profiler->mark('process: end') : null;

		return true;
	}

	/**
	 * Perform file uploads
	 *
	 * @return bool
	 */

	protected function _doUpload()
	{
		$oUploader = $this->getUploader();
		$oUploader->upload();

		if ($oUploader->moveError)
		{
			return false;
		}

		return true;
	}

	/**
	 * Update the data that gets posted via the form and stored by the form
	 * model. Used in elements to modify posted data see fabrikfileupload
	 *
	 * @param   string  $key          in key.dot.format to set a recursive array
	 * @param   string  $val          value to set to
	 * @param   bool    $update_raw   automatically update _raw key as well
	 * @param   bool    $override_ro  update data even if element is RO
	 *
	 * @return  void
	 */

	public function updateFormData($key, $val, $update_raw = false, $override_ro = false)
	{
		if (strstr($key, '.'))
		{
			$nodes = explode('.', $key);
			$count = count($nodes);
			$pathNodes = $count - 1;

			if ($pathNodes < 0)
			{
				$pathNodes = 0;
			}

			$ns = &$this->formData;

			for ($i = 0; $i <= $pathNodes; $i++)
			{
				// If any node along the registry path does not exist, create it
				if (!isset($ns[$nodes[$i]]))
				{
					$ns[$nodes[$i]] = array();
				}

				$ns = &$ns[$nodes[$i]];
			}

			$ns = $val;

			// $$$ hugh - changed name of $ns, as re-using after using it to set by reference was borking things up!
			$ns_table = &$this->formDataWithTableName;

			for ($i = 0; $i <= $pathNodes; $i++)
			{
				// If any node along the registry path does not exist, create it
				if (!isset($ns_table[$nodes[$i]]))
				{
					$ns_table[$nodes[$i]] = array();
				}

				$ns_table = &$ns_table[$nodes[$i]];
			}

			$ns_table = $val;

			// $$$ hugh - changed name of $ns, as re-using after using it to set by reference was borking things up!
			$ns_full = &$this->fullFormData;

			for ($i = 0; $i <= $pathNodes; $i++)
			{
				// If any node along the registry path does not exist, create it
				if (!isset($ns_full[$nodes[$i]]))
				{
					$ns_full[$nodes[$i]] = array();
				}

				$ns_full = &$ns_full[$nodes[$i]];
			}

			$ns_full = $val;

			// $$$ hugh - FIXME - nope, this won't work!  We don't know which path node is the element name.
			// $$$ hugh again - should now work, with little preg_replace hack, if last part is numeric, then second to last will be element name
			if ($update_raw)
			{
				if (preg_match('#\.\d+$#', $key))
				{
					$key = preg_replace('#(.*)(\.\d+)$#', '$1_raw$2', $key);
				}
				else
				{
					$key .= '_raw';
				}

				$nodes = explode('.', $key);
				$count = count($nodes);
				$pathNodes = $count - 1;

				if ($pathNodes < 0)
				{
					$pathNodes = 0;
				}

				$ns_raw = &$this->formData;

				for ($i = 0; $i <= $pathNodes; $i++)
				{
					// If any node along the registry path does not exist, create it
					if (!isset($ns_raw[$nodes[$i]]))
					{
						$ns_raw[$nodes[$i]] = array();
					}

					$ns_raw = &$ns_raw[$nodes[$i]];
				}

				$ns_raw = $val;

				$ns_raw_full = $this->_fullFormData;

				for ($i = 0; $i <= $pathNodes; $i++)
				{
					// If any node along the registry path does not exist, create it
					if (!isset($ns_raw_full[$nodes[$i]]))
					{
						$ns_raw_full[$nodes[$i]] = array();
					}

					$ns_raw_full = &$ns_raw_full[$nodes[$i]];
				}

				$ns_raw_full = $val;
			}
		}
		else
		{
			if (isset($this->formData))
			{
				$this->formData[$key] = $val;
				$this->formDataWithTableName[$key] = $val;
			}
			// Check if set - for case where you have a fileupload element & confirmation plugin - when plugin is trying to update non-existent data
			if (isset($this->_fullFormData))
			{
				$this->_fullFormData[$key] = $val;
			}
			/*
			 * Need to allow RO (encrypted) elements to be updated.  Consensus is that
			 * we should actually modify the actual encrypted element in the $_REQUEST,
			 * but turns out this is a major pain in the butt (see _cryptViewOnlyElements() in the
			 * form view for details!).  Main problem is we need to know if it's a join and/or repeat group,
			 * which means loading up the element model.  So for now, just going to add the element name to a
			 * class array, $this->pluginUpdatedElements[], which we'll check in addDefaultDataFromRO()
			 * in the table model, or wherever else we need it.
			 */
			/*
			 if (array_key_exists('fabrik_vars', $_REQUEST)
			&& array_key_exists('querystring', $_REQUEST['fabrik_vars'])
			&& array_key_exists($key, $_REQUEST['fabrik_vars']['querystring'])) {
			$crypt = Worker::getCrypt();
			// turns out it isn't this simple, of course!  see above
			$_REQUEST['fabrik_vars']['querystring'][$key] = $crypt->encrypt($val);
			}
			 */
			// add element name to this array, which will then cause this element to be skipped
			// during the RO data phase of writing the row.  Don't think it really matter what we set it to,
			// might as well be the value.  Note that we need the new $override_ro arg, as some elements
			// use updateFormData() as part of normal operation, which should default to NOT overriding RO.

			if ($override_ro)
			{
				$this->pluginUpdatedElements[$key] = $val;
			}

			if ($update_raw)
			{
				$key .= '_raw';
				$this->formData[$key] = $val;
				$this->formDataWithTableName[$key] = $val;

				if (isset($this->_fullFormData))
				{
					$this->_fullFormData[$key] = $val;
				}

				if ($override_ro)
				{
					$this->pluginUpdatedElements[$key] = $val;
				}
			}
		}
	}

	/**
	 * Intended for use by things like PHP form plugin code, PHP validations, etc.,
	 * so folk don't have to access formData directly.
	 *
	 * @param   string  $fullName     full element name
	 * @param   bool    $raw          get raw data
	 * @param   mixed   $default      value
	 * @param   string  $repeatCount  repeat count if needed
	 *
	 * @since	3.0.6
	 *
	 * @return mixed
	 */
	public function getElementData($fullName, $raw = false, $default = '', $repeatCount = null)
	{
		$data = isset($this->formData) ? $this->formData : $this->data;
		$value = null;

		if ($raw)
		{
			$fullName .= '_raw';
		}
		// Simplest case, element name exists in main group
		if (is_array($data) && array_key_exists($fullName, $data))
		{
			$value = $data[$fullName];
		}
		/* Maybe we are being called from onAfterProcess hook, or somewhere else
		 * running after store, when non-joined data names have been reduced to short
		 * names in formData, so peek in _fullFormData
		 */
		elseif (isset($this->fullFormData) && array_key_exists($fullName, $this->fullFormData))
		{
			$value = $this->fullFormData[$fullName];
		}

		if (isset($value) && isset($repeatCount) && is_array($value))
		{
			$value = ArrayHelper::getValue($value, $repeatCount, $default);
		}

		// If we didn't find it, set to default
		if (!isset($value))
		{
			$value = $default;
		}

		return $value;
	}

	/**
	 * This will strip the html from the form data according to the
	 * filter settings applied from article manager->parameters
	 * see here - http://forum.joomla.org/index.php/topic,259690.msg1182219.html#msg1182219
	 *
	 * @return  array  form data
	 */

	public function &setFormData()
	{
		if (isset($this->formData))
		{
			return $this->formData;
		}

		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		list($this->dofilter, $this->filter) = Worker::getContentFilter();

		$this->ajaxPost = $this->app->input->getBool('fabrik_ajax');

		// Set up post data, and copy values to raw (for failed form submissions)
		$data = $_POST;
		$this->copyToRaw($data);

		/**
		 * $$$ hugh - quite a few places in code that runs after this want __pk_val,
		 * so if it doesn't exist, grab it from the PK element.
		 */
		if (!array_key_exists('__pk_val', $data))
		{
			/**
			 * $$$ hugh - There HAS to be an easier way of getting the PK element name, that doesn't involve calling getPrimaryKeyAndExtra(),
			 * which is a horribly expensive operation.
			 */
			$primaryKey = FabrikString::safeColNameToArrayKey($this->getItem()->get('list.db_primary_key'));
			$data['__pk_val'] = ArrayHelper::getValue($data, $primaryKey . '_raw', ArrayHelper::getValue($data, $primaryKey, ''));
		}

		// Apply querystring values if not already in post (so qs values doesn't overwrite the submitted values for dbjoin elements)
		$data = array_merge($data, $_REQUEST);
		array_walk_recursive($data, array($this, '_clean'));

		// Set here so element can call formModel::updateFormData()
		$this->formData = $data;
		$this->fullFormData = $this->formData;
		$this->session->set('com_' . $package . '.form.data', $this->formData);

		return $this->formData;
	}

	/**
	 * Called from setFormData to clean up posted data from either ajax or posted form
	 * used in array_walk_recursive() method
	 *
	 * @param   mixed  &$item  (string or array)
	 *
	 * @return  void
	 */

	protected function _clean(&$item)
	{
		if (is_array($item))
		{
			array_walk_recursive($item, array($this, '_clean'));
		}
		else
		{
			if ($this->dofilter)
			{
				//$item = preg_replace('/%([0-9A-F]{2})/mei', "chr(hexdec('\\1'))", $item);
				$item = preg_replace_callback('/%([0-9A-F]{2})/mi',  function ($matches) { return chr(hexdec($matches[1])); }, $item);
				if ($this->ajaxPost)
				{
					$item = rawurldecode($item);
				}

				if ($this->dofilter)
				{
					@$item = $this->filter->clean($item);
				}
			}
			else
			{
				if ($this->ajaxPost)
				{
					$item = rawurldecode($item);
				}
			}
		}
	}

	/**
	 * Loop over elements and call their preProcess() method
	 *
	 * @return  void
	 */

	private function callElementPreprocess()
	{
		$input = $this->app->input;
		$repeatTotals = $input->get('fabrik_repeat_group', array(0), 'post', 'array');
		$groups = $this->getGroupsHierarchy();

		// Currently this is just used by calculation elements
		/* @var $groupModel \Fabrik\Admin\Models\Group */
		foreach ($groups as $groupModel)
		{
			$group = $groupModel->getGroup();
			$repeatedGroupCount = ArrayHelper::getValue($repeatTotals, $group->id, 0, 'int');
			$elementModels = $groupModel->getPublishedElements();

			for ($c = 0; $c < $repeatedGroupCount; $c++)
			{
				foreach ($elementModels as $elementModel)
				{
					$elementModel->preProcess($c);
				}
			}
		}
	}

	/**
	 * Without this the first groups repeat data was always being saved (as it was posted but hidden
	 * on the form.
	 *
	 * @param   array  &$data  posted form data
	 *
	 * @return  void
	 */

	protected function removeEmptyNoneJoinedGroupData(&$data)
	{
		$repeats = ArrayHelper::getValue($data, 'fabrik_repeat_group', array());
		$groups = $this->getGroups();

		foreach ($repeats as $groupid => $c)
		{
			if ($c == 0)
			{
				$group = $groups[$groupid];

				if ($group->isJoin())
				{
					continue;
				}

				$elements = $group->getPublishedElements();

				foreach ($elements as $elementModel)
				{
					$name = $elementModel->getElement()->get('name');
					$data[$name] = '';
					$data[$name . '_raw'] = '';
				}
			}
		}
	}

	/**
	 * Prepare the submitted form data for copying
	 *
	 * @return  string  Original records reference
	 */
	protected function prepareForCopy()
	{
		$item = $this->getItem();
		$k = $item->get('list.db_primary_key');
		$k = FabrikString::safeColNameToArrayKey($k);
		$origId = ArrayHelper::getValue($this->formData, $k, '');

		// COPY function should create new records
		if (array_key_exists('Copy', $this->formData))
		{
			$this->rowId = '';
			$this->formData[$k] = '';
			$this->formData['rowid'] = '';
		}

		return $origId;
	}

	/**
	 * As part of the form process we may need to update the referring url if making a copy
	 *
	 * @param   string  $origId    Original record ref
	 * @param   string  $insertId  New insert reference
	 *
	 * @return  void
	 */
	protected function updateRefferrer($origId, $insertId)
	{
		$input = $this->app->input;

		// Set the redirect page to the form's url if making a copy and set the id to the new insertid
		if (array_key_exists('Copy', $this->formData))
		{
			$u = str_replace('rowid=' . $origId, 'rowid=' . $insertId, $input->get('HTTP_REFERER', '', 'string'));
			$input->set('fabrik_referrer', $u);
		}
	}

	/**
	 * Set various request / input arrays with the main records insert id
	 *
	 * @param   string  $insertId  The records insert id
	 *
	 * @return  void
	 */

	public function setInsertId($insertId)
	{
		$input = $this->app->input;
		$item = $this->getItem();
		$pk = $item->get('list.db_primary_key');
		$tmpKey = str_replace("`", "", $pk);
		$tmpKey = str_replace('.', '___', $tmpKey);
		$this->formData[$tmpKey] = $insertId;
		$this->formData[$tmpKey . '_raw'] = $insertId;
		$this->formData[FabrikString::shortColName($pk)] = $insertId;
		$this->formData[FabrikString::shortColName($pk) . '_raw'] = $insertId;

		$this->fullFormData[$tmpKey] = $insertId;
		$this->fullFormData[$tmpKey . '_raw'] = $insertId;
		$this->fullFormData['rowid'] = $insertId;
		$this->formData['rowid'] = $insertId;
		$this->formDataWithTableName[$tmpKey] = $insertId;
		$this->formDataWithTableName[$tmpKey . '_raw'] = $insertId;
		$this->formDataWithTableName['rowid'] = $insertId;

		$input->set($tmpKey, $insertId);
		$input->set('rowid', $insertId);

		// $$$ hugh - pretty sure we need to unset 'usekey' now, as it is not relevant to joined data,
		// and it messing with storeRow of joins
		$input->set('usekey', '');
	}

	/**
	 * Process groups when the form is submitted
	 *
	 *@param   int  $parentId  insert ID of parent table
	 *
	 * @return  void
	 */

	protected function processGroups($parentId = null)
	{
		$groupModels = $this->getGroups();

		/* @var $groupModel \Fabrik\Admin\Models\Group */
		foreach ($groupModels as $groupModel)
		{
			// Jaanus: if group is visible
			if ($groupModel->canView() && $groupModel->canEdit())
			{
				$groupModel->process($parentId);
			}
		}
	}

	/**
	 * Process individual elements when submitting the form
	 * Used for multi-select join elements which need to store data in
	 * related tables
	 *
	 * @since   3.1rc2
	 *
	 * @return  void
	 */

	protected function processElements()
	{
		$groups = $this->getGroupsHierarchy();

		/* @var $groupModel \Fabrik\Admin\Models\Group */
		foreach ($groups as $groupModel)
		{
			$elementModels = $groupModel->getPublishedElements();

			/* @var $elementModel \Fabrik\Plugins\Element */
			foreach ($elementModels as $elementModel)
			{
				$elementModel->onFinalStoreRow($this->formData);
			}
		}
	}

	/**
	 * Process the form to the database
	 *
	 * @return void
	 */
	public function processToDB()
	{
		$profiler = JProfiler::getInstance('Application');
		JDEBUG ? $profiler->mark('processToDb: start') : null;

		$pluginManager = Worker::getPluginManager();
		$listModel = $this->getListModel();
		$origId = $this->prepareForCopy();
		$this->formData = $listModel->removeTableNameFromSaveData($this->formData, '___');

		JDEBUG ? $profiler->mark('processToDb, submitToDatabase: start') : null;
		$insertId = $this->storeMainRow ? $this->submitToDatabase($this->rowId) : $this->rowId;

		$this->updateRefferrer($origId, $insertId);
		$this->setInsertId($insertId);

		// Store join data
		JDEBUG ? $profiler->mark('processToDb, processGroups: start') : null;
		$this->processGroups($insertId);

		// Enable db join checkboxes in repeat groups to save data
		JDEBUG ? $profiler->mark('processToDb, processElements: start') : null;
		$this->processElements();

		JDEBUG ? $profiler->mark('processToDb, onBeforeCalculations plugins: start') : null;
		if (in_array(false, $pluginManager->runPlugins('onBeforeCalculations', $this)))
		{
			return $insertId;
		}

		JDEBUG ? $profiler->mark('processToDb, doCalculations: start') : null;
		$this->listModel->doCalculations();

		JDEBUG ? $profiler->mark('processToDb: end') : null;
		return $insertId;
	}

	/**
	 * Saves the form data to the database
	 *
	 * @param   mixed  $rowId  If '' then insert a new row - otherwise update this row id
	 *
	 * @return	mixed	insert id (or rowid if updating existing row) if ok, else string error message
	 */

	protected function submitToDatabase($rowId = '')
	{
		$this->getGroupsHierarchy();
		$groups = $this->getGroupsHierarchy();
		$listModel = $this->getListModel();
		$listModel->encrypt = array();
		$data = array();

		foreach ($groups as $groupModel)
		{
			// Joined groups stored in groupModel::process();
			if ($groupModel->isJoin())
			{
				continue;
			}

			$elementModels = $groupModel->getPublishedElements();

			foreach ($elementModels as $elementModel)
			{
				if ($elementModel->encryptMe())
				{
					$listModel->encrypt[] = $elementModel->getElement()->get('name');
				}
				// Following line added to fix importcsv where data from first row is used for every row.
				$elementModel->defaults = null;
				$elementModel->onStoreRow($data);
			}
		}

		$listModel = $this->getListModel();
		$listModel->setFormModel($this);
		$listModel->storeRow($data, $rowId);
		$this->lastInsertId = $listModel->lastInsertId;
		$useKey = $this->app->input->get('usekey', '');

		if (!empty($useKey))
		{
			return $listModel->lastInsertId;
		}
		else
		{
			return ($rowId == '') ? $listModel->lastInsertId : $rowId;
		}
	}

	/**
	 * Get list model
	 *
	 * @depreciated as of fabrik 3.0 - use getListModel instead
	 *
	 * @return  object  list model
	 */

	public function getTableModel()
	{
		return $this->getListModel();
	}

	/**
	 * Get the class names for each of the validation rules
	 *
	 * @deprecated (was only used in element label)
	 *
	 * @return	array	(validationruleid => classname )
	 */

	public function loadValidationRuleClasses()
	{
		if (is_null($this->validationRuleClasses))
		{
			$validationRules = Worker::getPluginManager()->getPlugInGroup('validationrule');
			$classes = array();

			foreach ($validationRules as $rule)
			{
				$classes[$rule->name] = $rule->name;
			}

			$this->validationRuleClasses = $classes;
		}

		return $this->validationRuleClasses;
	}

	/**
	 * Add in any encrypted stuff, in case we fail validation ...
	 * otherwise it won't be in $data when we rebuild the page.
	 * Need to do it here, so _raw fields get added in the next chunk 'o' code.
	 *
	 * @param   array  &$post  posted form data passed by reference
	 *
	 * @return	null
	 */

	public function addEncrytedVarsToArray(&$post)
	{
		if (array_key_exists('fabrik_vars', $_REQUEST) && array_key_exists('querystring', $_REQUEST['fabrik_vars']))
		{
			$groups = $this->getGroupsHierarchy();
			$crypt = Worker::getCrypt();
			$w = new Worker;

			foreach ($groups as $g => $groupModel)
			{
				$elementModels = $groupModel->getPublishedElements();

				foreach ($elementModels as $elementModel)
				{
					foreach ($_REQUEST['fabrik_vars']['querystring'] as $key => $encrypted)
					{
						if ($elementModel->getFullName(true, false) == $key)
						{
							/* 	$$$ rob - don't test for !canUse() as confirmation plugin dynamically sets this
							 * if ($elementModel->canView())
							 * $$$ hugh - testing adding non-viewable, non-editable elements to encrypted vars
							 */

							if (is_array($encrypted))
							{
								// Repeat groups
								$v = array();

								foreach ($encrypted as $e)
								{
									// $$$ rob urldecode when posting from ajax form
									$e = urldecode($e);
									$e = empty($e) ? '' : $crypt->decrypt($e);
									$e = Worker::JSONtoData($e);
									$v[] = $w->parseMessageForPlaceHolder($e, $post);
								}
							}
							else
							{
								// $$$ rob urldecode when posting from ajax form
								$encrypted = urldecode($encrypted);
								$v = empty($encrypted) ? '' : $crypt->decrypt($encrypted);

								/*
								 * $$$ hugh - things like elementlist elements (radios, etc) seem to use
								 * their JSON data for encrypted read only vals, need to decode.
								 */

								if (is_subclass_of($elementModel, 'PlgFabrik_ElementList'))
								{
									$v = Worker::JSONtoData($v, true);
								}

								$v = $w->parseMessageForPlaceHolder($v, $post);
							}

							$elementModel->setGroupModel($groupModel);
							$elementModel->setValuesFromEncryt($post, $key, $v);
							/* $$ rob set both normal and rawvalues to encrypted - otherwise validate method doesn't
							 * pick up decrypted value
							 */
							$elementModel->setValuesFromEncryt($post, $key . '_raw', $v);
						}
					}
				}
			}
		}
	}

	/**
	 * When submitting data copy values to _raw equivalent
	 *
	 * @param   array  &$post     Form data
	 * @param   bool   $override  Override existing raw data when copying to raw
	 *
	 * @return	null
	 */

	public function copyToRaw(&$post, $override = false)
	{
		$this->copyToFromRaw($post, 'toraw', $override);
	}

	/**
	 * Copy raw data to non-raw data
	 *
	 * @param   array  &$post     Form data
	 * @param   bool   $override  Override existing raw data when copying from raw
	 *
	 * @return	null
	 */

	public function copyFromRaw(&$post, $override = false)
	{
		$this->copyToFromRaw($post, 'fromraw', $override);
	}

	/**
	 * Copy raw data to non-raw data OR none-raw to raw
	 *
	 * @param   array   &$post      Form data
	 * @param   string  $direction  Either - toraw OR fromraw - defines which data to copy to where raw/none-raw
	 * @param   bool    $override   Override existing raw data when copying from raw
	 *
	 * @return	null
	 */

	protected function copyToFromRaw(&$post, $direction = 'toraw', $override = false)
	{
		$groups = $this->getGroupsHierarchy();
		$input = $this->app->input;

		foreach ($groups as $groupModel)
		{
			$elementModels = $groupModel->getPublishedElements();

			foreach ($elementModels as $elementModel)
			{
				$elName2 = $elementModel->getFullName(true, false);
				$elName2Raw = $elName2 . '_raw';

				if ($direction === 'toraw')
				{
					if (!array_key_exists($elName2Raw, $post) || $override)
					{
						// Post required getValue() later on
						$input->set($elName2Raw, @$post[$elName2]);
						$post[$elName2Raw] = @$post[$elName2];
					}
				}
				else
				{
					if (!array_key_exists($elName2 . '_raw', $post) || $override)
					{
						// Post required getValue() later on
						$input->set($elName2, @$post[$elName2Raw]);
						$post[$elName2] = @$post[$elName2Raw];
					}
				}
			}
		}
	}

	/**
	 * Has the form failed a validation
	 *
	 * @return bool
	 */

	public function failedValidation()
	{
		return $this->hasErrors();
	}

	/**
	 * Validate the form
	 * modifies post data to include validation replace data
	 *
	 * @return  bool  true if form validated ok
	 *
	 *                // FIXME - call this when the form is submitted (had to change the function ame from validate to
	 *                avoid clash with admin form validate method.
	 */

	public function validateForm()
	{
		$input = $this->app->input;

		if ((bool) $input->getBool('fabrik_ignorevalidation', false) === true)
		{
			// Put in when saving page of form
			return true;
		}

		require_once COM_FABRIK_FRONTEND . '/helpers/uploader.php';
		$pluginManager = new PluginManager;
		$pluginManager->getPlugInGroup('validationrule');

		$post = $this->setFormData();

		// Contains any data modified by the validations
		$this->modifiedValidationData = array();
		$w = new Worker;
		$ok = true;

		// $$$ rob 01/07/2011 fileupload needs to examine records previous data for validations on editing records
		$this->setOrigData();

		// $$$ rob copy before addEncrytedVarsToArray as well as after
		// so that any placeholders(.._raw) contained in the encrypted vars are correctly replaced
		$this->copyToRaw($post);

		/* $$$ rob for PHP 5.2.1 (and potential up to before 5.2.6) $post is not fully associated with formData -
		 * so the above copToRaw does not update $this->formData.
		 * $$$ hugh - had to add the &, otherwise replace validations weren't work, as modifying
		 * $post wasn't modifying $this->formData.  Which is weird, as I thought all array assignments
		 * were by reference?
		 * $$$ hugh - FIXME - wait ... what ... hang on ... we assign $this->formData in $this->setFormData(),
		 * which we assigned to $post a few lines up there ^^.  Why are we now assigning $post back to $this->formData??
		 */
		$this->formData = &$post;

		/* $$$ hugh - add in any encrypted stuff, in case we fail validation ...
		 * otherwise it won't be in $data when we rebuild the page.
		 * Need to do it here, so _raw fields get added in the next chunk 'o' code.
		 */
		$this->addEncrytedVarsToArray($post);

		// $$$ hugh - moved this to after addEncryptedVarsToArray(), so read only data is
		// available to things like calcs running in preProcess phase.
		$this->callElementPreprocess();

		// Add in raw fields - the data is already in raw format so just copy the values
		$this->copyToRaw($post);

		$groups = $this->getGroupsHierarchy();
		$repeatTotals = $input->get('fabrik_repeat_group', array(0), 'array');
		$ajaxPost = $input->getBool('fabrik_ajax');
		$joinData = array();

		foreach ($groups as $groupModel)
		{
			$groupCounter = $groupModel->getGroup()->id;
			$elementModels = $groupModel->getPublishedElements();
			$elDbValues = array();

			foreach ($elementModels as $elementModel)
			{
				// If the user can't view or edit the element, then don't validate it. Otherwise user sees failed validation but no indication of what failed
				if (!$elementModel->canUse() && !$elementModel->canView())
				{
					continue;
				}

				$elDbValues = array();
				$validation_rules = $elementModel->validator->findAll();

				// $$ rob incorrect for ajax validation on joined elements
				// $elName = $elementModel->getFullName(true, false);
				$elName = $input->getBool('fabrik_ajax') ? $elementModel->getHTMLId(0) : $elementModel->getFullName(true, false);
				$this->errors[$elName] = array();
				$elName2 = $elementModel->getFullName(true, false);

				// $$$rob fix notice on validation of multipage forms
				if (!array_key_exists($groupCounter, $repeatTotals))
				{
					$repeatTotals[$groupCounter] = 1;
				}

				for ($c = 0; $c < $repeatTotals[$groupCounter]; $c++)
				{
					$this->errors[$elName][$c] = array();

					// $$$ rob $this->formData was $_POST, but failed to get anything for calculation elements in php 5.2.1
					$formData = $elementModel->getValue($this->formData, $c, array('runplugins' => 0, 'use_default' => false, 'use_querystring' => false));

					if (get_magic_quotes_gpc())
					{
						if (is_array($formData))
						{
							foreach ($formData as &$d)
							{
								if (is_string($d))
								{
									$d = stripslashes($d);

									if ($ajaxPost)
									{
										$d = rawurldecode($d);
									}
								}
							}
						}
						else
						{
							$formData = stripslashes($formData);

							if ($ajaxPost)
							{
								$formData = rawurldecode($formData);
							}
						}
					}

					// Internal element plugin validations
					if (!$elementModel->validate(@$formData, $c))
					{
						$ok = false;
						$this->errors[$elName][$c][] = $elementModel->getValidationErr();
					}

					/**
					 * $$$ rob 11/04/2012 was stopping multiselect/chx dbjoin elements from saving in normal group.
					 * if ($groupModel->canRepeat() || $elementModel->isJoin())
					 */
					if ($groupModel->canRepeat())
					{
						// $$$ rob for repeat groups no join setting to array() means that $_POST only contained the last repeat group data
						// $elDbValues = array();
						$elDbValues[$c] = $formData;
					}
					else
					{
						$elDbValues = $formData;
					}
					// Validations plugins attached to elements
					if (!$elementModel->mustValidate())
					{
						continue;
					}

					foreach ($validation_rules as $plugin)
					{
						$plugin->formModel = $this;

						if ($plugin->shouldValidate($formData, $c))
						{
							if (!$plugin->validate($formData, $c))
							{
								$this->errors[$elName][$c][] = $w->parseMessageForPlaceHolder($plugin->getMessage());
								$ok = false;
							}

							if (method_exists($plugin, 'replace'))
							{
								if ($groupModel->canRepeat())
								{
									$elDbValues[$c] = $formData;
									$testReplace = $plugin->replace($elDbValues[$c], $c);

									if ($testReplace != $elDbValues[$c])
									{
										$elDbValues[$c] = $testReplace;
										$this->modifiedValidationData[$elName][$c] = $testReplace;
										$joinData[$elName2 . '_raw'][$c] = $testReplace;
										$post[$elName . '_raw'][$c] = $testReplace;
									}
								}
								else
								{
									$testReplace = $plugin->replace($elDbValues, $c);

									if ($testReplace != $elDbValues)
									{
										$elDbValues = $testReplace;
										$this->modifiedValidationData[$elName] = $testReplace;
										$input->set($elName . '_raw', $elDbValues);
										$post[$elName . '_raw'] = $elDbValues;
									}
								}
							}
						}
					}
				}

				if ($groupModel->isJoin() || $elementModel->isJoin())
				{
					$joinData[$elName2] = $elDbValues;
				}
				else
				{
					$input->set($elName, $elDbValues);
					$post[$elName] = $elDbValues;
				}
				// Unset the defaults or the orig submitted form data will be used (see date plugin mysql vs form format)
				$elementModel->defaults = null;
			}
		}
		// Insert join data into request array
		foreach ($joinData as $key => $val)
		{
			$input->set($key, $val);
			$post[$key] = $val;
		}

		if (!empty($this->errors))
		{
			Worker::getPluginManager()->runPlugins('onError', $this);
		}

		FabrikHelperHTML::debug($this->errors, 'form:errors');
		$this->setErrors($this->errors);

		return $ok;
	}

	/**
	 * Get form validation errors - if empty test session for errors
	 * 31/01/13 - no longer restoring from session errors - see http://fabrikar.com/forums/showthread.php?t=31377
	 * 19/02/13 - Changed from http_referer test to this->isMambot to restore session errors when redirecting from a non-ajax form
	 * in module that has failed validation - see http://fabrikar.com/forums/showthread.php?t=31870
	 *
	 * @return  array  errors
	 */

	public function getErrors()
	{
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		$context = 'com_' . $package . '.form.' . $this->getId() . '.' . $this->getRowId() . '.';

		// Store errors in local array as clearErrors() removes $this->errors
		$errors = array();

		if (empty($this->errors))
		{
			if ($this->isMambot)
			{
				$errors = $this->session->get($context . 'errors', array());
			}
		}
		else
		{
			$errors = $this->errors;
		}
		$this->clearErrors();
		$this->errors = $errors;

		return $this->errors;
	}

	/**
	 * Clear form validation errors
	 *
	 * @return  void
	 */

	public function clearErrors()
	{
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		$this->errors = array();
		$context = 'com_' . $package . '.form.' . $this->getId() . '.' . $this->getRowId() . '.';
		$this->session->clear($context . 'errors');
		/* $$$ rob this was commented out, but putting back in to test issue that if we have ajax validations on
		 * and a field is validated, then we don't submit the form, and go back to add the form, the previously validated
		 * values are shown in the form.
		 */
		$this->session->set($context . 'session.on', false);
	}

	/**
	 * Set form validation errors in session
	 *
	 * @param   array  $errors  error messages
	 *
	 * @return void
	 */

	public function setErrors($errors)
	{
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		$context = 'com_' . $package . '.form.' . $this->getId() . '.' . $this->getRowId() . '.';
		$this->session->set($context . 'errors', $errors);
		$this->session->set($context . 'session.on', true);
	}

	/**
	 * Get a JSON encoded string of error and modified data messages
	 *
	 * @return string
	 */
	public function getJsonErrors()
	{
		$data = array('modified' => $this->modifiedValidationData, 'errors' => $this->errors);

		return json_encode($data);
	}

	/**
	 * Should the form do a spoof check
	 *
	 * @return	bool
	 */
	public function spoofCheck()
	{
		$fbConfig = JComponentHelper::getParams('com_fabrik');

		return $this->getParams()->get('spoof_check', $fbConfig->get('spoofcheck_on_formsubmission', true));
	}

	/**
	 * Get an instance of the uploader object
	 *
	 * @return  object  uploader
	 */
	public function &getUploader()
	{
		if (is_null($this->uploader))
		{
			$this->uploader = new UploaderHelper($this);
		}

		return $this->uploader;
	}

	/**
	 * Get the forms table name
	 *
	 * @deprecated - not used?
	 *
	 * @return  string  table name
	 */
	public function getTableName()
	{
		$this->getListModel();

		return $this->getListModel()->getTable()->get('list.db_table_name');
	}

	/**
	 * Get the form row
	 *
	 * @param   string  $name     table name
	 * @param   string  $prefix   table name prefx
	 * @param   array   $options  initial state options
	 *
	 * @return object form row
	 */
	public function getTable($name = '', $prefix = 'Table', $options = array())
	{
		if (is_null($this->form))
		{
			$this->form = parent::getTable('Form', 'FabrikTable');
		}

		$id = $this->getId();

		if ($this->form->id != $id)
		{
			$this->form->load($id);
		}

		return $this->form;
	}

	/**
	 * Sets the variable of each of the form's group's elements to the value
	 * specified
	 *
	 * @param   string  $varName  variable name
	 * @param   string  $varVal   variable value
	 *
	 * @deprecated  not used
	 *
	 * @return  bool  false if update error occurs
	 */
	public function setElementVars($varName, $varVal)
	{
		if ($this->elements == null)
		{
			$this->getFormGroups();
		}

		foreach ($this->elements as $el)
		{
			$element = FabTable::getInstance('Element', 'FabrikTable');
			$element->load($el->id);

			if (!$element->set($varName, $varVal))
			{
				return false;
			}

			$element->store();
		}

		return true;
	}

	/**
	 * Get an array of the form's element's ids
	 *
	 * @param   array  $ignore  ClassNames to ignore e.g. array('FabrikModelFabrikCascadingdropdown')
	 * @param   array  $opts    Property 'includePublised' can be set to 0; @since 3.0.7
	 *                          Property 'loadPrefilters' @since 3.0.7.1 - used to ensure that prefilter elements are loaded in inline edit
	 *
	 * @return  array  ints ids
	 */
	public function getElementIds($ignore = array(), $opts = array())
	{
		$aEls = array();
		$groups = $this->getGroupsHierarchy();

		foreach ($groups as $groupModel)
		{
			$elementModels = $groupModel->getPublishedElements();

			foreach ($elementModels as $elementModel)
			{
				$this->getElementIds_check($elementModel, $ignore, $opts, $aEls);
			}
		}

		if (ArrayHelper::getValue($opts, 'loadPrefilters', false))
		{
			$listModel = $this->getListModel();
			list($afilterFields, $afilterConditions, $afilterValues, $afilterAccess, $afilterEval, $afilterJoins) = $listModel->prefilterSetting();

			foreach ($afilterFields as $name)
			{
				$raw = preg_match("/_raw$/", $name) > 0;
				$name = $name ? FabrikString::rtrimword($name, '_raw') : $name;
				$elementModel = $this->getElement($name);
			}
		}

		return $aEls;
	}

	/**
	 * Helper function for getElementIds(), test if the element should be added
	 *
	 * @param   plgFabrik_Element  $elementModel  Element model
	 * @param   array              $ignore        ClassNames to ignore e.g. array('FabrikModelFabrikCascadingdropdown')
	 * @param   array              $opts          Filter options
	 * @param   array              &$aEls         Array of element ids to load
	 *
	 * @return  void
	 */
	private function getElementIds_check($elementModel, $ignore, $opts, &$aEls)
	{
		$class = get_class($elementModel);

		if (!in_array($class, $ignore))
		{
			$element = $elementModel->getElement();

			if (!(ArrayHelper::getValue($opts, 'includePublised', true) && $element->get('published') == 0))
			{
				$aEls[] = (int) $element->id;
			}
		}
	}

	/**
	 * Called via ajax nav
	 *
	 * @param   int  $dir  1 - move forward, 0 move back
	 *
	 * @return  bool  new row id loaded.
	 */
	public function paginateRowId($dir)
	{
		$db = Worker::getDbo();
		$input = $this->app->input;
		$c = $dir == 1 ? '>=' : '<=';
		$intLimit = $dir == 1 ? 2 : 0;
		$listModel = $this->getListModel();
		$item = $this->getItem();
		$rowId = $input->getString('rowid', '', 'string');
		$query = $db->getQuery(true);
		$pk = $item->get('list.db_primary_key');
		$query->select($pk . ' AS ' . FabrikString::safeColNameToArrayKey($pk))->from($item->get('list.db_table_name'))
			->where($pk . ' ' . $c . ' ' . $db->q($rowId));
		$query = $listModel->buildQueryOrder($query);
		$db->setQuery($query, 0, $intLimit);
		$ids = $db->loadColumn();

		if ($dir == 1)
		{
			if (count($ids) >= 2)
			{
				$input->set('rowid', $ids[$dir]);

				return true;
			}
			else
			{
				return false;
			}
		}

		if (count($ids) - 2 >= 0)
		{
			$input->set('rowid', $ids[count($ids) - 2]);

			return true;
		}

		return false;
	}

	/**
	 * Get the last insert id, for situations where we need the 'rowid' for newly inserted forms,
	 * and can't use getRowId() because it caches rowid as empty.  For example, in plugins running
	 * onAfterProcess, like upsert.
	 *
	 * Note that $this->lastInsertId is getting set in the
	 */
	public function getInsertId()
	{
		return $this->lastInsertId;
	}

	/**
	 * Get the max row id - used when requesting rowid=-2 to return the last recorded detailed view
	 *
	 * @return  int  max row id
	 */

	protected function getMaxRowId()
	{
		if (!$this->getItem()->get('form.record_in_database'))
		{
			return $this->rowId;
		}

		$listModel = $this->getListModel();
		$db = $listModel->getDb();
		$item = $this->getItem();
		$k = $db->qn($item->get('list.db_primary_key'));
		$table = $db->qn($item->get('list.db_table_name'));
		$query = $db->getQuery(true);
		$query->select('MAX(' . $k . ')')->from($table);
		$query = $listModel->buildQueryWhere(true, $query);
		$db->setQuery($query);

		return $db->loadResult();
	}

	/**
	 * If editing a record which contains repeated join data then on start $data is an
	 * array with each records being a row in the database.
	 *
	 * We need to take this structure and convert it to the same format as when the form
	 * is submitted
	 *
	 * @param   array  &$data  form data
	 *
	 * @return  void
	 */

	public function setJoinData(&$data)
	{
		$this->_joinDefaultData = array();

		if (empty($data))
		{
			return;
		}

		// No joins so leave !
		if (!is_array($this->aJoinObjs) || $this->rowId === '')
		{
			return;
		}

		if (!array_key_exists(0, $data))
		{
			$data[0] = new stdClass;
		}

		$groups = $this->getGroupsHierarchy();
		/**
		 * $$$ hugh - adding the "PK's seen" stuff, otherwise we end up adding multiple
		 * rows when we have multiple repeat groups.  For instance, if we had two repeated
		 * groups, one with 2 repeats and one with 3, we ended up with 6 repeats for each
		 * group, with 3 and 2 copies of each respectively.  So we need to track which
		 * instances of each repeat we have already copied into the main row.
		 *
		 * So $join_pks_seen will be indexed by $join_pks_seen[groupid][elementid]
		 */
		$join_pks_seen = array();
		/**
		 * Have to copy the data for the PK's seen stuff, as we're modifying the original $data
		 * as we go, which screws up the PK logic once we've modified the PK value itself in the
		 * original $data.  Probably only needed for $data[0], as that's the only row we actually
		 * modify, but for now I'm just copying the whole thing, which then gets used for doing the ...
		 * $join_pk_val = $data_copy[$row_index]->$join_pk;
		 * ... inside the $data iteration below.
		 *
		 * PS, could probably just do a $data_copy = $data, as our usage of the copy isn't going to
		 * involve nested arrays (which get copied by reference when using =), but I've been burned
		 * so many times with array copying, I'm going to do a "deep copy" using serialize/unserialize!
		 */
		$data_copy = unserialize(serialize($data));

		foreach ($groups as $groupID => $groupModel)
		{
			$group = $groupModel->getGroup();
			$join_pks_seen[$groupID] = array();
			$elementModels = $groupModel->getMyElements();

			foreach ($elementModels as $elementModelID => $elementModel)
			{
				if ($groupModel->isJoin() || $elementModel->isJoin())
				{
					if ($groupModel->isJoin())
					{
						$joinModel = $groupModel->getJoinModel();
						$join_pk = $joinModel->getForeignID();
						$join_pks_seen[$groupID][$elementModelID] = array();
					}

					$names = $elementModel->getJoinDataNames();

					foreach ($data as $row_index => $row)
					{
						// Might be a string if new record ?
						$row = (object) $row;

						if ($groupModel->isJoin())
						{
							/**
							 * If the join's PK element isn't published or for any other reason not
							 * in $data, we're hosed!
							 */
							if (!isset($data_copy[$row_index]->$join_pk))
							{
								continue;
							}

							$join_pk_val = $data_copy[$row_index]->$join_pk;
							/**
							 * if we've seen the PK value for this element's row before, skip it.
							 * Check for empty as well, just in case - as we're loading existing data,
							 * it darn well should have a value!
							 */
							if (empty($join_pk_val) || in_array($join_pk_val, $join_pks_seen[$groupID][$elementModelID]))
							{
								continue;
							}
						}

						for ($i = 0; $i < count($names); $i ++)
						{
							$name = $names[$i];

							if (array_key_exists($name, $row))
							{
								$v = $row->$name;
								$v = Worker::JSONtoData($v, $elementModel->isJoin());

								// New record or csv export
								if (!isset($data[0]->$name))
								{
									$data[0]->$name = $v;
								}

								if (!is_array($data[0]->$name))
								{
									if ($groupModel->isJoin() && $groupModel->canRepeat())
									{
										$v = array($v);
									}

									$data[0]->$name = $v;
								}
								else
								{
									if ($groupModel->isJoin() && $groupModel->canRepeat())
									{
										$n =& $data[0]->$name;
										$n[] = $v;
									}
								}
							}
						}

						if ($groupModel->isJoin())
						{
							/**
							 * Make a Note To Self that we've now handled the data for this element's row,
							 * and can skip it from now on.
							 */
							$join_pks_seen[$groupID][$elementModelID][] = $join_pk_val;
						}
					}
				}
			}
		}

		// Remove the additional rows - they should have been merged into [0] above. if no [0] then use main array
		$data = ArrayHelper::fromObject(ArrayHelper::getValue($data, 0, $data));
	}

	/**
	 * Get the forms session data (used when using multipage forms)
	 *
	 * @return  object	session data
	 */

	protected function getSessionData()
	{
		$params = $this->getParams();
		$this->sessionModel = new FormSession;
		$this->sessionModel->setFormId($this->getId());
		$this->sessionModel->setRowId($this->rowId);
		$useCookie = (int) $params->get('multipage_save', 0) === 2 ? true : false;

		if (!$useCookie)
		{
			// In case a plugin is using cookie session (e.g. confirmation plugin)
			$useCookie = $this->sessionModel->canUseCookie();
		}

		$this->sessionModel->useCookie($useCookie);

		return $this->sessionModel->load();
	}

	/**
	 * Create the sql query to get the rows data for insertion into the form
	 *
	 * @param   array  $opts  key: ignoreOrder ignores order by part of query
	 *                        Needed for inline edit, as it only selects certain fields, order by on a db join element returns 0 results
	 *
	 * @deprecated	use buildQuery() instead
	 *
	 * @return  string	sql query to get row
	 */

	public function _buildQuery($opts = array())
	{
		return $this->buildQuery($opts = array());
	}

	/**
	 * Create the sql query to get the rows data for insertion into the form
	 *
	 * @param   array  $opts  key: ignoreOrder ignores order by part of query
	 *                        Needed for inline edit, as it only selects certain fields, order by on a db join element returns 0 results
	 *
	 * @return  string  query
	 */

	public function buildQuery($opts = array())
	{
		if (isset($this->query))
		{
			return $this->query;
		}

		$db = Worker::getDbo();
		$input = $this->app->input;
		$item = $this->getItem();

		if (!$item->get('form.record_in_database'))
		{
			return;
		}

		$listModel = $this->getListModel();
		$item = $this->getItem();
		$query = $this->getDb()->getQuery();
		$query = $listModel->buildQuerySelect('form', $query);
		$query = $listModel->buildQueryJoin($query);
		//$sql = (string) $query;
		$emptyRowId = $this->rowId === '' ? true : false;
		$random = $input->get('random');
		$useKey = Worker::getMenuOrRequestVar('usekey', '', $this->isMambot, 'var');

		if ($useKey != '')
		{
			$useKey = explode('|', $useKey);

			foreach ($useKey as &$tmpk)
			{
				$tmpk = !strstr($tmpk, '.') ? $item->get('list.db_table_name') . '.' . $tmpk : $tmpk;
				$tmpk = FabrikString::safeColName($tmpk);
			}

			if (!is_array($this->rowId))
			{
				$aRowIds = explode('|', $this->rowId);
			}
		}

		$comparison = $input->get('usekey_comparison', '=');
		$viewPk = $input->get('view_primary_key');

		// $$$ hugh - changed this to !==, as in rowid=-1/usekey situations, we can have a rowid of 0
		// I don't THINK this will have any untoward side effects, but ...
		if ((!$random && !$emptyRowId) || !empty($useKey))
		{
			$sql .= '';

			if (!empty($useKey))
			{
				$sql .= '(';
				$parts = array();

				for ($k = 0; $k < count($useKey); $k++)
				{
					/**
					 *
					 * For gory reasons, we have to assume that an empty string cannot be a valid rowid
					 * when using usekey, so just create a 1=-1 if it is.
					 */
					if ($aRowIds[$k] === '')
					{
						$parts[] = ' 1=-1';
						continue;
					}
					// Ensure that the key value is not quoted as we q() afterwards
					if ($comparison == '=')
					{
						$parts[] = ' ' . $useKey[$k] . ' = ' . $db->q($aRowIds[$k]);
					}
					else
					{
						$parts[] = ' ' . $useKey[$k] . ' LIKE ' . $db->q('%' . $aRowIds[$k] . '%');
					}
				}

				$sql .= implode(' AND ', $parts);
				$sql .= ')';

				$query->where($sql);
			}
			else
			{
				$query->where($item->get('list.db_primary_key') . ' = ' . $db->q($this->rowId));
			}
		}
		else
		{
			if ($viewPk != '')
			{
				$query->where($viewPk);
			}
			elseif ($random)
			{
				// $$$ rob Should this not go after prefilters have been applied ?
				$query->order('RAND() LIMIT 1');
			}
		}
		// Get pre-filter conditions from table and apply them to the record
		// the false, ignores any filters set by the table
		$query = $listModel->buildQueryWhere(true, $query);

		/*if (strstr($sql, 'WHERE'))
		{
			// Do it this way as queries may contain sub-queries which we want to keep the where
			$firstWord = String::substr($where, 0, 5);

			if ($firstWord == 'WHERE')
			{
				$where = String::substr_replace($where, 'AND', 0, 5);
			}
		}*/
		// Set rowId to -2 to indicate random record
		if ($random)
		{
			$this->setRowId(-2);
		}

		// $$$ rob ensure that all prefilters are wrapped in brackets so that
		// only one record is loaded by the query - might need to set $word = and?
		/*if (trim($where) != '')
		{
			$where = explode(' ', $where);
			$word = array_shift($where);
			$sql .= $word . ' (' . implode(' ', $where) . ')';
		}*/

		if (!$random && ArrayHelper::getValue($opts, 'ignoreOrder', false) === false)
		{
			// $$$ rob if showing joined repeat groups we want to be able to order them as defined in the table
			$query = $listModel->buildQueryOrder($query);
		}

		$this->query = (string) $query;

		return $this->query;
	}

	/**
	 * Attempts to determine if the form contains the element
	 *
	 * @param   string  $searchName  Element name to search for
	 * @param   bool    $checkInt    Check search name against element id
	 * @param   bool    $checkShort  Check short element name
	 *
	 * @return  bool  true if found, false if not found
	 */
	public function hasElement($searchName, $checkInt = false, $checkShort = true)
	{
		$groups = $this->getGroupsHierarchy();

		foreach ($groups as $groupModel)
		{
			$groupModel->getMyElements();

			if (!is_array($groupModel->elements))
			{
				continue;
			}

			foreach ($groupModel->elements as $elementModel)
			{
				$element = $elementModel->getElement();

				if ($checkInt)
				{
					if ($searchName == $element->get('id'))
					{
						$this->currentElement = $elementModel;

						return true;
					}
				}

				if ($searchName == $element->get('name') && $checkShort)
				{
					$this->currentElement = $elementModel;

					return true;
				}

				if ($searchName == $elementModel->getFullName(true, false))
				{
					$this->currentElement = $elementModel;

					return true;
				}

				if ($searchName == $elementModel->getFullName(false, false))
				{
					$this->currentElement = $elementModel;

					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get an element
	 *
	 * @param   string  $searchName  Name to search for
	 * @param   bool    $checkInt    Check search name against element id
	 * @param   bool    $checkShort  Check short element name
	 *
	 * @return  mixed  ok: element model not ok: false
	 */
	public function getElement($searchName = '', $checkInt = false, $checkShort = true)
	{
		return $this->hasElement($searchName, $checkInt, $checkShort) ? $this->currentElement : false;
	}

	/**
	 * Set the list model
	 *
	 * @param   object  &$listModel  List model
	 *
	 * @return  void
	 */
	public function setListModel(&$listModel)
	{
		$this->listModel = $listModel;
	}

	/**
	 * Get an object of pages, keyed on page counter and containing an array of the page's group ids
	 *
	 * @return  object
	 */

	public function getPages()
	{
		if (!is_null($this->pages))
		{
			return $this->pages;
		}

		$this->pages = new stdClass;
		$pageCounter = 0;
		$groups = $this->getGroupsHierarchy();
		$c = 0;

		foreach ($groups as $groupModel)
		{
			$params = $groupModel->getParams();

			if ($params->get('split_page') && $c != 0 && $groupModel->canView())
			{
				$pageCounter++;
			}

			if ($groupModel->canView())
			{
				if (!isset($this->pages->$pageCounter))
				{
					$this->pages->$pageCounter = array();
				}

				array_push($this->pages->$pageCounter, $groupModel->getId());
			}

			$c++;
		}

		return $this->pages;
	}

	/**
	 * Should the form submit via ajax or not?
	 *
	 * @return  bool
	 */

	public function isAjax()
	{
		if (is_null($this->ajax))
		{
			$this->ajax = $this->app->input->getBool('ajax', false);
		}

		return (bool) $this->ajax;
	}

	/**
	 * Used in special case where you have 2 + n-n joins in a single table
	 * In this case the sql query will most likely create four rows of data for
	 * each combination of possibilities
	 *
	 * E.g.
	 *
	 * tbl classes (id, label)
	 *
	 * left joined to:
	 * tbl student_classes (id, label, student_id)
	 *
	 * left joined to
	 * tbl student_teachers (id, label, teacher_id)
	 *
	 * entering one records with 2 students and 2 teachers gives you 4 rows in the query
	 *
	 * classid  student_id, teacher_id
	 * 1        1           1
	 * 1        2	          1
	 * 1        1	          2
	 * 1        2           2
	 *
	 * @since   2.0rc1
	 *
	 * @return  void
	 */

	protected function _reduceDataForXRepeatedJoins()
	{
		$groups = $this->getGroupsHierarchy();
		$listModel = $this->getListModel();
		$pkField = '';

		foreach ($groups as $groupModel)
		{
			/**
			 * $$$ hugh - we need to do this for non-repeat joins as well
			 */
			if ($groupModel->isJoin())
			{
				$joinModel = $groupModel->getJoinModel();
				$tblJoin = $joinModel->getJoin();

				// $$$ hugh - slightly modified these lines so we don't create $this->data['join'] if there is no
				// join data, because that then messes up code subsequent code that checks for empty($this->data)
				if (!isset($this->data['join']))
				{
					// $this->data['join'] = array();
					return;
				}

				if (!array_key_exists($tblJoin->id, $this->data['join']))
				{
					continue;
				}

				if ($tblJoin->table_join == '')
				{
					continue;
				}

				$jdata = &$this->data['join'][$tblJoin->id];
				$db = $listModel->getDb();
				$fields = $db->getTableColumns($tblJoin->table_join, false);
				$keyCount = 0;
				unset($pkField);

				foreach ($fields as $f)
				{
					if ($f->Key == 'PRI')
					{
						if (!isset($pkField))
						{
							$pkField = $tblJoin->table_join . '___' . $f->Field;
						}

						$keyCount ++;
					}
				}

				if (!isset($pkField))
				{
					$pkField = '';
				}
				/*
				 * Corner case if you link to #__user_profile - its primary key is made of 2 elements, so
				 * simply checking on the user_id (the first col) will find duplicate results and incorrectly
				 * merge down.
				 */
				if ($keyCount > 1)
				{
					return;
				}

				$usedkeys = array();

				if (!empty($jdata) && array_key_exists($pkField, $jdata))
				{
					foreach ($jdata[$pkField] as $key => $value)
					{
						/*
						 * $$$rob
						 * added : || ($value === '' && !empty($this->errors))
						 * this was incorrectly reducing empty data
						 * when re-viewing form after failed validation
						 * with a form with repeating groups (with empty data in the key fields
						 *
						 */
						if (!in_array($value, $usedkeys) || ($value === '' && !empty($this->errors)))
						{
							$usedkeys[$key] = $value;
						}
					}
				}

				$keystokeep = array_keys($usedkeys);

				// Remove unneeded data from array
				foreach ($jdata as $key => $value)
				{
					foreach ($value as $key2 => $v)
					{
						if (!in_array($key2, $keystokeep))
						{
							unset($jdata[$key][$key2]);
						}
					}
				}
				// Reduce the keys so that we don't have keys of 0, 2
				foreach ($jdata as $key => $array)
				{
					if ($groupModel->canRepeat())
					{
						$jdata[$key] = array_values($array);
					}
					else
					{
						// $$$ hugh - if it's a one-to-one, it should be a single value
						$aVals = array_values($array);
						$jdata[$key] = ArrayHelper::getValue($aVals, 0, '');
					}
				}
			}
		}
	}

	/**
	 * Presuming that our introduction looks like this:
	 *
	 * {new:this is an intro}
	 * {edit:You're editing a record}
	 * some more text
	 *
	 * creating a new form record will show the intro text as:
	 *
	 * this is an intro
	 * some more text
	 *
	 * and editing an existing record will show:
	 *
	 * You're editing a record
	 * some more text
	 *
	 * @return string modified intro
	 */

	public function getIntro()
	{
		$intro = $this->getItem()->get('form.intro');

		return $this->parseIntroOutroPlaceHolders($intro);
	}

	/**
	 * Parse into and outro text
	 *
	 * @param   string  $text  Text to parse
	 *
	 * @since   3.0.7
	 *
	 * @return  string
	 */

	protected function parseIntroOutroPlaceHolders($text)
	{

		if (!$this->isEditable())
		{
			$remove = "/{new:\s*.*?}/i";
			$text = preg_replace($remove, '', $text);
			$remove = "/{edit:\s*.*?}/i";
			$text = preg_replace($remove, '', $text);
			$match = "/{details:\s*.*?}/i";
			$text = preg_replace_callback($match, array($this, '_getIntroOutro'), $text);
			$text = str_replace('[', '{', $text);
			$text = str_replace(']', '}', $text);
		}
		else
		{
			$match = $this->isNewRecord() ? 'new' : 'edit';
			$remove = $this->isNewRecord()  ? 'edit' : 'new';
			$match = "/{" . $match . ":\s*.*?}/i";
			$remove = "/{" . $remove . ":\s*.*?}/i";
			$text = preg_replace_callback($match, array($this, '_getIntroOutro'), $text);
			$text = preg_replace($remove, '', $text);
			$text = str_replace('[', '{', $text);
			$text = str_replace(']', '}', $text);
			$text = preg_replace("/{details:\s*.*?}/i", '', $text);
		}

		$w = new Worker;
		$text = $w->parseMessageForPlaceHolder($text, $this->data, true);

		// Jaanus: to remove content plugin code from intro and/or outro when plugins are not processed
		$params = $this->getParams();
		$jplugins = (int) $params->get('process-jplugins', '2');

		if ($jplugins === 0 || ($jplugins === 2 && $this->isEditable()))
		{
			$text = preg_replace("/{\s*.*?}/i", '', $text);
		}

		$text = FabrikString::translate($text);

		return $text;
	}

	/**
	 * Used from getIntro as preg_replace_callback function to strip
	 * undeisred text from form label intro
	 *
	 * @param   array  $match  Preg matched strings
	 *
	 * @return  string  intro text
	 */

	private function _getIntroOutro($match)
	{
		$m = explode(":", $match[0]);
		array_shift($m);
		$m = implode(":", $m);
		return FabrikString::rtrimword($m, "}");
	}

	/**
	 * Jaanus: see text above about intro
	 *
	 * @return  string  Outro
	 */

	public function getOutro()
	{
		$params = $this->getParams();
		$outro = $params->get('outro');

		return $this->parseIntroOutroPlaceHolders($outro);
	}

	/**
	 * Get the form's label
	 *
	 * @return  string  Label
	 */

	public function getLabel()
	{
		$label = $this->getItem()->get('form.label');

		if (!$this->isEditable())
		{
			return str_replace("{Add/Edit}", '', $label);
		}

		if (String::stristr($label, "{Add/Edit}"))
		{
			$replace = $this->isNewRecord() ? FText::_('COM_FABRIK_ADD') : FText::_('COM_FABRIK_EDIT');
			$label = str_replace("{Add/Edit}", $replace, $label);
		}

		return FText::_($label);
	}

	/**
	 * Method to copy one or more records.
	 *
	 * @param  array  &$ids  Ids to copy
	 * @param  array  $names  Old to new name map.
	 *
	 * @throws \Exception
	 *
	 * @return  boolean    True if successful, false if an error occurs.
	 */
	public function copy(&$ids, $names)
	{
		// FIXME - copy needs to be updated for 3.5
		throw new \Exception('form copy needs to be updated for 3.5');
		// Array key = old id value new id
		$this->groupidmap = array();
		$input = $this->app->input;
		$groupModels = $this->getGroups();
		$this->form = null;
		$form = $this->getTable();
		$form->id = false;

		// $$$ rob newFormLabel set in table copy
		if ($input->get('newFormLabel', '') !== '')
		{
			$form->label = $input->get('newFormLabel', '', '', 'string');
		}

		$res = $form->store();
		$newElements = array();

		foreach ($groupModels as $groupModel)
		{
			$oldid = $groupModel->getId();

			// $$$rob use + rather than array_merge to preserve keys
			$groupModel->_newFormid = $form->id;
			$newElements = $newElements + $groupModel->copy();
			$this->groupidmap[$oldid] = $groupModel->getGroup()->id;
		}
		// Need to do finalCopyCheck() on form elements
		$pluginManager = Worker::getPluginManager();

		// @TODO something not right here when copying a cascading dropdown element in a join group
		foreach ($newElements as $origId => $newId)
		{
			$plugin = $pluginManager->getElementPlugin($newId);
			$plugin->finalCopyCheck($newElements);
		}
		// Update the model's table to the copied one
		$this->form = $form;
		$this->setId($form->id);
		$this->newElements = $newElements;

		return $form;
	}

	/**
	 * Get the related lists (relations defined by db join foreign keys)
	 *
	 * @return  array  Links to view the related lists
	 */

	public function getRelatedTables()
	{
		$input = $this->app->input;
		$links = array();
		$params = $this->getParams();

		if (!$params->get('show-referring-table-releated-data', false))
		{
			return $links;
		}

		$listModel = $this->getListModel();
		$referringTable = new Lizt;

		// $$$ rob - not sure that referring_table is anything other than the form's table id
		// but for now just defaulting to that if no other variable found (e.g when links in sef urls)
		$tid = $input->getInt('referring_table', $input->getInt('listid', $listModel->getTable()->id));
		$referringTable->setId($tid);
		$tmpKey = '__pk_val';
		$tableParams = $referringTable->getParams();
		$table = $referringTable->getTable();
		$joinsToThisKey = $referringTable->getJoinsToThisKey();
		$linksToForms = $referringTable->getLinksToThisKey();
		$row = $this->getData();
		$facetedLinks = $tableParams->get('facetedlinks', null);

		if (is_null($facetedLinks))
		{
			return;
		}

		$linkedLists = $facetedLinks->linkedlist;
		$aExisitngLinkedForms = $facetedLinks->linkedform;
		$linkedform_linktype = $facetedLinks->linkedform_linktype;
		$linkedtable_linktype = $facetedLinks->linkedlist_linktype;
		$f = 0;

		foreach ($joinsToThisKey as $joinKey => $element)
		{
			$key = $element->list_id . '-' . $element->form_id . '-' . $element->element_id;

			if (isset($linkedLists->$key) && $linkedLists->$key != 0)
			{
				$qsKey = $referringTable->getTable()->get('list.db_table_name') . '___' . $element->name;
				$val = $input->get($qsKey);

				if ($val == '')
				{
					// Default to row id if we are coming from a main link (and not a related data link)
					$val = $input->get($qsKey . '_raw', '', 'string');

					if (empty($val))
					{
						$thisKey = $this->getItem()->get('list.db_table_name') . '___' . $element->join_key_column . '_raw';
						$val = ArrayHelper::getValue($this->data, $thisKey, $val);

						if (empty($val))
						{
							$val = $input->get('rowid');
						}
					}
				}

				/* $$$ tom 2012-09-14 - If we don't have a key value, get all.  If we have a key value,
				 * use it to restrict the count to just this entry.
				 */

				$pks = array();

				if (!empty($val))
				{
					$pks[] = $val;
				}

				$recordCounts = $referringTable->getRecordCounts($element, $pks);

				// Jaanus - 18.10.2013 - get correct element fullnames as link keys
				$linkKey = $recordCounts['linkKey'];

				/* $$$ hugh - changed to use _raw as key, see:
				 * http://fabrikar.com/forums/showthread.php?t=20020
				 */

				$linkKeyRaw = $linkKey . '_raw';
				$popUpLink = ArrayHelper::getValue($linkedtable_linktype->$key, $f, false);
				$count = is_array($recordCounts) && array_key_exists($val, $recordCounts) ? $recordCounts[$val]->total : 0;
				$label = $facetedLinks->linkedlistheader->$key == '' ? $element->listlabel : $facetedLinks->linkedlistheader->$key;
				$links[$element->list_id][] = $label . ': ' . $referringTable->viewDataLink($popUpLink, $element, null, $linkKey, $val, $count, $f);
			}

			$f++;
		}

		$f = 0;

		// Create columns containing links which point to forms associated with this table

		foreach ($linksToForms as $element)
		{
			if ($element !== false)
			{
				$key = $element->list_id . '-' . $element->form_id . '-' . $element->element_id;
				$linkedForm = $aExisitngLinkedForms->$key;
				$popUpLink = $linkedform_linktype->$key;

				if ($linkedForm !== '0')
				{
					if (is_object($element))
					{
						$linkKeyData = $referringTable->getRecordCounts($element, $pks);
						$linkKey = $linkKeyData['linkKey'];
						$val = $input->get($linkKey, '', 'string');

						if ($val == '')
						{
							$val = $input->get($qsKey . '_raw', $input->get('rowid'));
						}

						// Jaanus: when no link to list and no form headers then people still know where they add data
						$fkey = $facetedLinks->linkedformheader->$key;
						$label = $fkey != '' ? ': ' . $fkey : (isset($linkedLists->$key) && $linkedLists->$key != 0 ? '' : ': ' . $element->listlabel);

						// Jaanus: label after add link if no list link helps to make difference between data view links and only add links.
						$links[$element->list_id][] = $referringTable->viewFormLink($popUpLink, $element, null, $linkKey, $val, false, $f) . $label;
					}
				}

				$f++;
			}
		}

		return $links;
	}

	/**
	 * Create the form's html class name.
	 * Based on column counts etc. as to whether form-horizontal applied
	 *
	 * @return  string
	 */

	public function getFormClass()
	{
		$params = $this->getParams();
		$class = array('fabrikForm');

		/*
		$horiz = true;
		$groups = $this->getGroupsHierarchy();

		foreach ($groups as $gkey => $groupModel)
		{
			$groupParams = $groupModel->getParams();

			if ($groupParams->get('group_columns', 1) > 1)
			{
				$horiz = false;
			}
		}

		if ($horiz
			&& (($this->isEditable() && $params->get('labels_above', 0) != 1)
			|| (!$this->isEditable() && $params->get('labels_above_details', 0) != 1)))
		{
			$class[] = 'form-horizontal';
		}
		*/

		return implode(' ', $class);
	}

	/**
	 * Strip out any element names from url qs vars
	 *
	 * @param   string  $url  URL
	 *
	 * @return  string
	 */
	protected function stripElementsFromUrl($url)
	{
		$url = explode('?', $url);

		if (count($url) == 1)
		{
			return $url;
		}

		$filtered = array();
		$bits = explode('&', $url[1]);

		foreach ($bits as $bit)
		{
			$parts = explode('=', $bit);
			$key = $parts[0];
			$key = FabrikString::rtrimword($key, '_raw');

			if (!$this->hasElement($key))
			{
				$filtered[] = implode('=', $parts);
			}
		}

		$url = $url[0] . '?' . implode('&', $filtered);

		return $url;
	}

	/**
	 * Get the url to use as the form's action property
	 *
	 * @return	string	Url
	 */

	public function getAction()
	{
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		$option = $this->app->input->get('option');
		$router = $this->app->getRouter();

		if ($this->app->isAdmin())
		{
			$action = ArrayHelper::getValue($_SERVER, 'REQUEST_URI', 'index.php');
			$action = $this->stripElementsFromUrl($action);
			$action = str_replace("&", "&amp;", $action);

			return $action;
		}

		if ($option === 'com_' . $package)
		{
			$page = 'index.php?';

			// Get array of all querystring vars
			$uri = JURI::getInstance();

			/**
			 * Was $router->parse($uri);
			 * but if you had a module + form on a page using sef urls and
			 * Joomla's language switcher - calling parse() would re-parse the url and
			 * mung it well good and proper like.
			 *
			 */
			$queryvars = $router->getVars();

			if ($this->isAjax())
			{
				$queryvars['format'] = 'raw';
				unset($queryvars['view']);
				$queryvars['task'] = 'form.process';
			}

			$qs = array();

			foreach ($queryvars as $k => $v)
			{
				if ($k == 'rowid')
				{
					$v = $this->getRowId();
				}
				/* $$$ hugh - things get weird if we have been passed a urlencoded URL as a qs arg,
				 * which the $router->parse() above will have urldecoded, and it gets used as part of the URI path
				 * when we JRoute::_() below.  So we need to re-urlencode stuff and junk.
				 * Ooops, make sure it isn't an array, which we'll get if they have something like
				 * &table___foo[value]=bar
				 */
				if (!is_array($v))
				{
					$v = urlencode($v);
					$qs[] = $k . '=' . $v;
				}
				else
				{
					foreach ($v as $subV)
					{
						$qs[] = $k . '[]=' . urlencode($subV);
					}
				}
			}

			$action = $page . implode("&amp;", $qs);
			$action = JRoute::_($action);
		}
		else
		{
			// In plugin & SEF URLs
			if ((int) $router->getMode() === (int) JROUTER_MODE_SEF)
			{
				// $$$ rob if embedding a form in a form, then the embedded form's url will contain
				// the id of the main form - not sure if its an issue for now
				$action = ArrayHelper::getValue($_SERVER, 'REQUEST_URI', 'index.php');
			}
			else
			{
				// In plugin and no sef (routing dealt with in form controller)
				$action = 'index.php';
			}
		}

		return $action;
	}

	/**
	 * If the group is a joined group we want to ensure that
	 * its id field is contained with in the group's elements
	 *
	 * @param   JRegistry  &$groupTable  Group data
	 *
	 * @return	string	HTML hidden field
	 */

	protected function _makeJoinIdElement(&$groupTable)
	{
		$listModel = $this->getListModel();
		$joinId = $this->aJoinGroupIds[$groupTable->get('id')];
		$element = new stdClass;

		// Add in row id for join data
		$element->label = '';
		$element->error = '';
		$element->value = '';
		$element->id = '';
		$element->startRow = 0;
		$element->endRow = 0;
		$element->errorTag = '';
		$element->column = '';
		$element->className = '';
		$element->containerClass = '';

		foreach ($listModel->getJoins() as $oJoin)
		{
			if ($oJoin->id == $joinId)
			{
				$key = $oJoin->table_join . $this->joinTableElementStep . $oJoin->table_join_key;

				if (array_key_exists('join', $this->data))
				{
					// $$$ rob if join element is a db join the data $key contains label and not foreign key value
					if (@array_key_exists($key . '_raw', $this->data['join'][$joinId]))
					{
						$val = $this->data['join'][$joinId][$key . '_raw'];
					}
					else
					{
						$val = @$this->data['join'][$joinId][$key];
					}

					if (is_array($val))
					{
						$val = array_key_exists(0, $val) ? $val[0] : '';
					}
				}
				else
				{
					$val = '';
				}

				if ($val == '')
				{
					// Something's gone wrong - lets take the main table's key
					$k = $oJoin->join_from_table . $this->joinTableElementStep . $oJoin->table_key;
					$val = @$this->data[$k];
				}

				if (is_array($val))
				{
					$val = array_shift($val);
				}

				$element->value = $val;
				$element->element = '<input type="hidden" id="join.' . $joinId . '.rowid" name="join[' . $joinId . '][rowid]" value="' . $val
					. '" />';
				$element->hidden = true;
				$element->containerClass = 'fabrikElementContainer  fabrikHide';
			}
		}

		return $element;
	}

	/**
	 * Get an array of read only values
	 *
	 * @return  array
	 */

	public function getreadOnlyVals()
	{
		return $this->readOnlyVals;
	}

	/**
	 * Prepare the elements for rendering
	 *
	 * @param   string  $tmpl  Form template
	 *
	 * @since   3.0
	 *
	 * @return  array
	 */

	public function getGroupView($tmpl = '')
	{
		if (isset($this->groupView))
		{
			return $this->groupView;
		}

		$params = $this->getParams();
		$input = $this->app->input;

		// $$$rob - do regardless of whether form is editable as $data is required for hidden encrypted fields
		// and not used anywhere else (avoids a warning message)
		$data = array();
		/* $$$ rob - 3.0 for some reason just using $this->data was not right as join data was empty when editing existing record
		 * $$$ hugh - commented this out, as a) running getData() twice is expensive, and b) it blows away any changes onLoad plugins
		 * make to _data, like the juser plugin
		 * Ran this change for a couple of weeks before committing, seems to work without it.
		 *unset($this->data);
		 */
		$origData = $this->getData();

		foreach ($origData as $key => $val)
		{
			if (is_string($val))
			{
				$data[$key] = htmlspecialchars($val, ENT_QUOTES);
			}
			else
			{
				// Not sure what the htmlspecialchars is for above but if we don't assign here we loose join data
				$data[$key] = $val;
			}
		}

		$this->tmplData = $data;
		$this->groupView = array();
		$this->readOnlyVals = array();

		// $$$ hugh - temp foreach fix
		$groups = $this->getGroupsHierarchy();

		foreach ($groups as $gkey => $groupModel)
		{
			$groupTable = $groupModel->getGroup();
			$group = $groupModel->getGroupProperties($this);
			$groupParams = $groupModel->getParams();
			$aElements = array();

			// Check if group is actually a table join
			if (array_key_exists($groupTable->get('id'), $this->aJoinGroupIds))
			{
				$aElements[] = $this->_makeJoinIdElement($groupTable);
			}

			$repeatGroup = 1;
			$foreignKey = null;
			$startHidden = false;

			if ($groupModel->canRepeat())
			{
				$joinTable = $groupModel->getJoinModel()->getJoin();
				$foreignKey = '';

				if (is_object($joinTable))
				{
					$repeatGroup = $groupModel->repeatCount();

					if (!$groupModel->fkPublished())
					{
						$startHidden = false;
					}
				}
			}
			// Test failed validated forms, repeat group counts are in request
			$repeatGroups = $input->get('fabrik_repeat_group', array(), 'array');

			if (!empty($repeatGroups))
			{
				$repeatGroup = ArrayHelper::getValue($repeatGroups, $gkey, $repeatGroup);

				if ($repeatGroup == 0)
				{
					$repeatGroup = 1;
					$startHidden = true;
				}
			}

			$groupModel->repeatTotal = $startHidden ? 0 : $repeatGroup;
			$aSubGroups = array();

			for ($c = 0; $c < $repeatGroup; $c++)
			{
				$aSubGroupElements = array();
				$elCount = 0;
				$elementModels = $groupModel->getPublishedElements();

				foreach ($elementModels as $elementModel)
				{
					/* $$$ rob ensure that the element is associated with the correct form (could occur if n plugins rendering form
					 * and detailed views of the same form.
					 */
					$elementModel->setFormModel($this);
					$elementModel->tmpl = $tmpl;

					/* $$$rob test don't include the element in the form is we can't use and edit it
					 * test for captcha element when user logged in
					 */
					if (!$this->isEditable())
					{
						$elementModel->inDetailedView = true;
					}

					if (!$this->isEditable() && !$elementModel->canView())
					{
						continue;
					}

					$elementModel->_foreignKey = $foreignKey;
					$elementModel->_repeatGroupTotal = $repeatGroup - 1;
					$element = $elementModel->preRender($c, $elCount, $tmpl);

					// $$$ hugh - experimenting with adding non-viewable, non-editable to encrypted vars
					// if (!$element || ($elementModel->canView() && !$elementModel->canUse()))
					if (!$element || !$elementModel->canUse())
					{
						/* $$$ hugh - $this->data doesn't seem to always have what we need in it, but $data does.
						 * can't remember exact details, was chasing a nasty issue with encrypted 'user' elements.
						 */

						// $$$ rob HTMLName seems not to work for joined data in confirmation plugin
						$elementModel->getValuesToEncrypt($this->readOnlyVals, $data, $c);
						/**
						 * $$$ hugh - need to decode it if it's a string, 'cos we encoded $data up there ^^ somewhere, which
						 * then causes read only data to get changed to htmlencoded after submission.  See this thread for gory details:
						 * http://fabrikar.com/forums/index.php?threads/how-to-avoid-changes-to-an-element-with-a-read-only-link.37656/#post-192437
						 */
						$elName = $elementModel->getFullName(true, false);

						if (!is_array($this->readOnlyVals[$elName]['data']))
						{
							$this->readOnlyVals[$elName]['data'] = htmlspecialchars_decode($this->readOnlyVals[$elName]['data']);
						}

						$this->readOnlyVals[$elName]['repeatgroup'] = $groupModel->canRepeat();
						$this->readOnlyVals[$elName]['join'] = $groupModel->isJoin();
					}

					if ($element)
					{
						$elementModel->stockResults($element, $aElements, $this->data, $aSubGroupElements);
					}

					if ($element && !$element->hidden)
					{
						$elCount++;
					}
				}
				// If its a repeatable group put in subgroup
				if ($groupModel->canRepeat())
				{
					// Style attribute for group columns (need to occur after randomisation of the elements otherwise clears are not ordered correctly)
					$rowix = -1;

					foreach ($aSubGroupElements as $elKey => $element)
					{
						$rowix = $groupModel->setColumnCss($element, $rowix);
					}

					$aSubGroups[] = $aSubGroupElements;
				}
			}

			$groupModel->randomiseElements($aElements);

			// Style attribute for group columns (need to occur after randomisation of the elements otherwise clears are not ordered correctly)
			$rowix = -1;

			// Don't double setColumnCss otherwise weirdness ensues
			if (!$groupModel->canRepeat())
			{
				foreach ($aElements as $elKey => $element)
				{
					$rowix = $groupModel->setColumnCss($element, $rowix);
				}
			}

			$group->elements = $aElements;
			$group->subgroups = $aSubGroups;
			$group->startHidden = $startHidden;

			$group->class[] = 'fabrikGroup';

			if ((int) $groupParams->get('group_columns', 1) == 1)
			{
				if (($this->isEditable() && $params->get('labels_above', 0) != 1)
					|| (!$this->isEditable() && $params->get('labels_above_details', 0) != 1))
				{
					$group->class[] = 'form-horizontal';
				}
			}

			$group->class = implode(' ', $group->class);

			// Only create the group if there are some element inside it
			if (count($aElements) != 0 && $groupModel->canView() !== false)
			{
				// 28/01/2011 $$$rob and if it is published
				$showGroup = (int) $groupParams->get('repeat_group_show_first');

				if ($showGroup !== 0)
				{
					// $$$ - hugh - testing new 'hide if no usable elements' option (4)
					// Jaanus: if not form view with "details only" option and not details view with "form only" option
					if (!($showGroup == 2 && $this->isEditable()) && !($showGroup == 3 && $input->get('view', 'form') == 'details')
						&& !($showGroup == 4 && !$groupModel->canView()))
					{
						$this->groupView[$group->name] = $group;
					}
				}
			}
		}

		return $this->groupView;
	}

	/**
	 * Get any fabrik tables that link to the join table
	 *
	 * @param   string  $table  Table name
	 *
	 * @return  array
	 */

	public function getLinkedFabrikLists($table)
	{
		if (!isset($this->linkedFabrikLists))
		{
			$this->linkedFabrikLists = array();
		}

		if (!array_key_exists($table, $this->linkedFabrikLists))
		{
			$db = Worker::getDbo(true);

			if (trim($table == ''))
			{
				return array();
			}
			else
			{
				// FIXME - need to change for 3.5.
				$query = $db->getQuery(true);
				$query->select('*')->from('#__fabrik_lists')->where('db_table_name = ' . $db->q($table));
				$db->setQuery($query);
			}

			$this->linkedFabrikLists[$table] = $db->loadColumn();
		}

		return $this->linkedFabrikLists[$table];
	}

	/**
	 * Used to see if something legitimate in the submission process, like a form plugin,
	 * has modified an RO element value and wants to override the RO/origdata.
	 *
	 * If $value is set, then this method additionally adds the modified value to the updated array.
	 *
	 * @param   string  $fullName  Full element name
	 * @param   mixed   $value     Optional value, states that a plugin update the readonly value of $fullName
	 *
	 * @return bool
	 */
	public function updatedByPlugin($fullName = '', $value = null)
	{
		if (isset($value))
		{
			$this->pluginUpdatedElements[$fullName] = $value;
		}

		return array_key_exists($fullName, $this->pluginUpdatedElements);
	}

	/**
	 * Set editable state
	 *
	 * @param   bool  $editable  Editable state
	 *
	 * @since 3.0.7
	 *
	 * @return  void
	 */

	public function setEditable($editable)
	{
		$this->editable = $editable;
	}

	/**
	 * Helper method to get the session redirect key. Redirect plugin stores this
	 * other form plugins such as twitter or paypal may need to query the session to perform the final redirect
	 * once the user has returned from those sites.
	 *
	 * @return  string  Session key to store redirect information (note: ends in '.')
	 */

	public function getRedirectContext()
	{
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');

		return 'com_' . $package . '.form.' . $this->getId() . '.redirect.';
	}

	/**
	 * Resets cached form data.
	 *
	 * @param   bool  $unset_groups  Also reset group and element model cached data
	 *
	 * @return  void
	 */

	public function unsetData($unset_groups = false)
	{
		unset($this->data);
		unset($this->query);

		if ($unset_groups)
		{
			/* $$$ hugh - unset group published elements list, and clear each
			 * element's default data.  Needed from content plugin, otherwise if
			 * we render the same form more than once with different rowids, we end up
			 * rendering the first copy's element data X times.
			 * Not sure if we need to actually unset the group published elements list,
			 * but for the moment I'm just using a Big Hammer to get the content plugin working!
			 */
			$groups = $this->getGroupsHierarchy();

			foreach ($groups as $groupModel)
			{
				$groupModel->resetPublishedElements();
				$elementModels = $groupModel->getPublishedElements();

				foreach ($elementModels as $elementModel)
				{
					$elementModel->reset();
				}
			}

			unset($this->groups);
			$pluginManager = Worker::getPluginManager();
			$pluginManager->clearFormPlugins($this);
		}
	}

	/**
	 * Reset form's cached data, i.e. from content plugin, where we may be rendering the same
	 * form twice, with different row data.
	 *
	 * @return  void
	 */

	public function reset()
	{
		$this->unsetData(true);
	}

	/**
	 * Get redirect URL
	 *
	 * @param   bool  $incSession  Set url in session?
	 * @param   bool  $isMambot    Is Mambot
	 *
	 * @return   array  url: string  Redirect url, baseRedirect (True: default redirect, False: plugin redirect)
	 *
	 * @since 3.0.6 (was in form controller)
	 */

	public function getRedirectURL($incSession = true, $isMambot = false)
	{
		$input = $this->app->input;
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');

		if ($this->app->isAdmin())
		{
			// Admin always uses option com_fabrik
			if (array_key_exists('apply', $this->formData))
			{
				$url = 'index.php?option=com_fabrik&task=form.view&formid=' . $input->getString('formid') . '&rowid=' . $input->getString('rowid', '', 'string');
			}
			else
			{
				$url = 'index.php?option=com_fabrik&task=list.view&listid=' . $this->getListModel()->getId();
			}
		}
		else
		{
			if (array_key_exists('apply', $this->formData))
			{
				$url = 'index.php?option=com_' . $package . '&view=form&formid=' . $input->getString('formid') . '&rowid=' . $input->getString('rowid', '', 'string')
					. '&listid=' . $input->getString('listid');
			}
			else
			{
				if ($isMambot)
				{
					// Return to the same page
					$url = ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', 'index.php');
				}
				else
				{
					// Return to the page that called the form
					$url = urldecode($input->post->get('fabrik_referrer', 'index.php', 'string'));
				}

				$itemId = (int) Worker::itemId();

				if ($url == '')
				{
					if ($itemId !== 0)
					{
						$url = 'index.php?' . http_build_query($this->app->getMenu('site')->getActive()->query) . '&Itemid=' . $itemId;
					}
					else
					{
						// No menu link so redirect back to list view
						$url = 'index.php?option=com_' . $package . '&view=list&listid=' . $input->getString('listid');
					}
				}
			}

			$config = JFactory::getConfig();

			if ($config->get('sef'))
			{
				$url = JRoute::_($url);
			}
		}
		// 3.0 need to distinguish between the default redirect and redirect plugin
		$baseRedirect = true;

		if (!$incSession)
		{
			return array('url' => $url, 'baseRedirect' => $baseRedirect);
		}

		$formdata = $this->session->get('com_' . $package . '.form.data');
		$context = $this->getRedirectContext();

		// If the redirect plug-in has set a url use that in preference to the default url
		$surl = $this->session->get($context . 'url', array());

		if (!empty($surl))
		{
			$baseRedirect = false;
		}

		if (!is_array($surl))
		{
			$surl = array($surl);
		}

		if (empty($surl))
		{
			$surl[] = $url;
		}

		$url = array_shift($surl);
		$this->session->set($context . 'url', $surl);

		// Redirect URL which set prefilters of < were converted to &lt; which then gave mySQL error
		$url = htmlspecialchars_decode($url);

		return array('url' => $url, 'baseRedirect' => $baseRedirect);
	}

	/**
	 * Should we show success messages
	 *
	 * @since  3.0.7
	 *
	 * @return boolean
	 */

	public function showSuccessMsg()
	{
		$mode = $this->getParams()->get('suppress_msgs', '0');

		return ($mode == 0 || $mode == 2);
	}

	/**
	 * Get the success message
	 *
	 * @return  string
	 */
	public function getSuccessMsg()
	{
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		$registry = $this->session->get('registry');

		// $$$ rob 30/03/2011 if using as a search form don't show record added message
		if ($registry && $registry->get('com_' . $package . '.searchform.fromForm') != $this->get('id'))
		{
			if (!$this->showSuccessMsg())
			{
				return '';
			}

			$params = $this->getParams();

			return FText::_($params->get('submit-success-msg', 'COM_FABRIK_RECORD_ADDED_UPDATED'));
		}
		else
		{
			return '';
		}
	}

	/**
	 * Should we show ACL messages
	 *
	 * @since  3.0.7
	 *
	 * @return boolean
	 */

	public function showACLMsg()
	{
		$mode = $this->getParams()->get('suppress_msgs', '0');

		return $mode == 0 || $mode == 1;
	}

	/**
	 * If trying to add/edit a record when the user doesn't have rights to do so,
	 * what message, if any should we show.
	 *
	 * @since  3.0.7
	 *
	 * @return string
	 */

	public function aclMessage()
	{
		if (!$this->showACLMsg())
		{
			return '';
		}

		$input = $this->app->input;
		$msg = $input->get('rowid', '', 'string') == 0 ? 'COM_FABRIK_NOTICE_CANT_ADD_RECORDS' : 'COM_FABRIK_NOTICE_CANT_EDIT_RECORDS';

		return FText::_($msg);
	}

	/**
	 * Say a form is embedded in an article, and is set to redirect on same/new page (so not in popup)
	 * Then we need to grab and re-apply the redirect/thanks message
	 *
	 * @return  void
	 */

	public function applyMsgOnce()
	{
		if (!$this->app->input->get('isMambot'))
		{
			// Don't apply if not isMambot
			return;
		}

		// Proceed, isMambot set in PlgFabrik_FormRedirect::buildJumpPage()
		$context = $this->getRedirectContext();
		$msg = $this->session->get($context . 'msg', array());

		if (!empty($msg))
		{
			$msg = ArrayHelper::getValue($msg, 0);
			$this->app->enqueueMessage($msg);
		}
		// Ensure its only shown once even if page is refreshed with isMambot in querystring
		$this->session->clear($context . 'msg');
	}

	/**
	 * Get redirect message
	 *
	 * @return  string  Redirect message
	 *
	 * @since   3.0.6 (was in form controller)
	 */
	public function getRedirectMessage()
	{
		if (!$this->showSuccessMsg())
		{
			return '';
		}

		$msg = $this->getSuccessMsg();
		$context = $this->getRedirectContext();
		$messages = $this->session->get($context . 'msg', array($msg));

		if (!is_array($messages))
		{
			$messages = array($messages);
		}

		if (empty($messages))
		{
			$messages[] = $msg;
		}

		/**
		 * $$$ rob Was using array_shift to set $msg, not to really remove it from $messages
		 * without the array_shift the custom message is never attached to the redirect page.
		 * Use-case: redirect plugin with jump page pointing to a J page and thanks message selected.
		 */
		$customMessage = array_keys($messages);
		$customMessage = array_shift($customMessage);
		$customMessage = ArrayHelper::getValue($messages, $customMessage);

		if ($customMessage != '')
		{
			$msg = $customMessage;
		}

		$q = $this->app->getMessageQueue();
		$found = false;

		foreach ($q as $m)
		{
			// Custom message already queued - unset default msg
			if ($m['type'] == 'message' && trim($m['message']) !== '')
			{
				$found = true;
				break;
			}
		}

		if ($found)
		{
			$msg = null;
		}

		$showMessage = null;
		$this->session->set($context . 'msg', $messages);
		$showMessage = (array) $this->session->get($context . 'showsystemmsg', array(true));

		if (is_array($showMessage))
		{
			$showMessage = array_shift($showMessage);
		}

		$msg = $showMessage == 1 ? $msg : '';

		// $$$ hugh - testing allowing placeholders in success msg
		$w = new Worker;
		$msg = $w->parseMessageForPlaceHolder($msg, $this->data);

		return $msg;
	}

	/**
	 * Build the JS key that the model uses in the view. This key is assigned to Fabrik.blocks
	 *
	 * @since   3.1rc1
	 *
	 * @return  string
	 */

	public function jsKey()
	{
		$id = FabrikString::clean($this->getId());
		$key = $this->isEditable() ? 'form_' . $id : 'details_' . $id;

		if ($this->getRowId() != '')
		{
			$key .= '_' . $this->getRowId();
		}

		return $key;
	}

	/**
	 * Get a subset of the model's data with non accessible values removed
	 *
	 * @param   string  $view  View
	 *
	 * @return  array data
	 */
	public function accessibleData($view = 'form')
	{
		$accessibleData = $this->data;

		$groups = $this->getGroupsHierarchy();

		foreach ($groups as $groupModel)
		{
			$elementModels = $groupModel->getPublishedElements();

			foreach ($elementModels as $elementModel)
			{
				switch ($view)
				{
					case 'form':
						$accessible = $elementModel->canUse($view);
						break;
					case 'details':
						$accessible = $elementModel->canView('form');
						break;
					case 'list':
						$accessible = $elementModel->canView('list');
						break;
				}

				if (!$accessible)
				{
					$name = $elementModel->getFullName(true, false);
					unset($accessibleData[$name]);
					unset($accessibleData[$name . '_raw']);
				}
			}
		}

		return $accessibleData;
	}

}
