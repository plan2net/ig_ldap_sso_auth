<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Xavier Perseguers <xavier@typo3.org>
 *  (c) 2007-2013 Michael Gagnon <mgagnon@infoglobe.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

// DEFAULT initialization of a module [BEGIN]

$GLOBALS['LANG']->includeLLFile('EXT:ig_ldap_sso_auth/Resources/Private/Language/locallang_mod1.xml');

// This checks permissions and exits if the users has no permission for entry.
$GLOBALS['BE_USER']->modAccess($MCONF, 1);

// DEFAULT initialization of a module [END]

/**
 * Module 'LDAP configuration' for the 'ig_ldap_sso_auth' extension.
 *
 * @author     Xavier Perseguers <xavier@typo3.org>
 * @author     Michael Gagnon <mgagnon@infoglobe.ca>
 * @package    TYPO3
 * @subpackage ig_ldap_sso_auth
 */
class tx_igldapssoauth_module1 extends t3lib_SCbase {

	const FUNCTION_SHOW_STATUS = 1;
	const FUNCTION_SEARCH_WIZARD = 2;
	const FUNCTION_IMPORT_GROUPS_BE = 3;
	const FUNCTION_IMPORT_GROUPS_FE = 4;
	const FUNCTION_IMPORT_USERS_BE = 5;
	const FUNCTION_IMPORT_USERS_FE = 6;

	var $pageinfo;
	var $lang;

	/**
	 * @var string
	 */
	protected $extKey = 'ig_ldap_sso_auth';

	/**
	 * @var array
	 */
	protected $config;

	/**
	 * @var array Currently selected LDAP configuration
	 */
	protected $ldapConfiguration;

	/**
	 * Default constructor
	 */
	public function __construct() {
		$config = $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey];
		$this->config = $config ? unserialize($config) : array();
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return void
	 */
	public function menuConfig() {
		$this->MOD_MENU = array(
			'function' => array(
				self::FUNCTION_SHOW_STATUS => $GLOBALS['LANG']->getLL('show_status'),
				self::FUNCTION_SEARCH_WIZARD => $GLOBALS['LANG']->getLL('search_wizard'),
				self::FUNCTION_IMPORT_USERS_BE => $GLOBALS['LANG']->getLL('import_users_be'),
				self::FUNCTION_IMPORT_GROUPS_BE => $GLOBALS['LANG']->getLL('import_groups_be'),
				self::FUNCTION_IMPORT_USERS_FE => $GLOBALS['LANG']->getLL('import_users_fe'),
				self::FUNCTION_IMPORT_GROUPS_FE => $GLOBALS['LANG']->getLL('import_groups_fe'),
			)
		);

		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	 *
	 * @return void
	 */
	public function main() {
		if (!version_compare(TYPO3_version, '4.5.99', '>')) {
			// See bug http://forge.typo3.org/issues/31697
			$GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = 1;
		}
		if (version_compare(TYPO3_version, '6.1.0', '>=')) {
			$this->doc = t3lib_div::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
		} else {
			$this->doc = t3lib_div::makeInstance('template');
		}
		if (version_compare(TYPO3_branch, '6.2', '>=')) {
			$this->doc->setModuleTemplate(t3lib_extMgm::extPath($this->extKey) . 'mod1/mod_template.html');
		} else {
			$this->doc->setModuleTemplate(t3lib_extMgm::extPath($this->extKey) . 'mod1/mod_template_v45-61.html');
		}

		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->inDocStylesArray[] = <<<CSS
table.typo3-dblist td > ul {
	margin: 0;
	padding-left: 1.4em;
}
table.typo3-dblist caption {
	caption-side: bottom;
	text-align: left;
	margin-top: .5em;
}
table.typo3-dblist caption ul {
	list-style: none;
	padding-left: 0;
}
table.typo3-dblist tr.deleted-ldap-group td, .square-deleted {
	background-color: #f00 !important;
	color: #fff;
}
table.typo3-dblist tr.local-ldap-group td, .square-local {
	background-color: #093 !important;
	color: #fff;
}
table.typo3-dblist tr.deleted-ldap-group td,
table.typo3-dblist tr.local-ldap-group td {
	border-bottom: 1px solid;
}
.square-deleted, .square-local {
	display: inline-block;
	width: 12px;
	height: 12px;
}
CSS;

		$docHeaderButtons = $this->getButtons();

		if ($GLOBALS['BE_USER']->user['admin']) {
			$this->doc->form = '<form action="" method="post">';

			if (version_compare(TYPO3_branch, '6.2', '<')) {
				// override the default jumpToUrl
				$this->doc->JScodeArray['jumpToUrl'] = '
					function jumpToUrl(URL) {
						window.location.href = URL;
					}
				';
			}

			// Render content:
			$this->moduleContent();
		} else {
			// If no access or if ID == zero
			$docHeaderButtons['save'] = '';
			$this->content .= $this->doc->spacer(10);
		}

		// Compile document
		$markers['FUNC_MENU'] = $this->doc->funcMenu('', t3lib_BEfunc::getFuncMenu($this->id, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function']));
		$markers['CONTENT'] = $this->content;
		$this->content = '';

		// Build the <body> for the module
		$this->content .= $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
		$this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		$this->content .= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
	}

	/**
	 * Generates the module content.
	 *
	 * @return void
	 */
	protected function moduleContent() {
		$thisUrl = t3lib_BEfunc::getModuleUrl($this->MCONF['name']);

		/** @var Tx_IgLdapSsoAuth_Domain_Repository_ConfigurationRepository $configurationRepository */
		$configurationRepository = t3lib_div::makeInstance('Tx_IgLdapSsoAuth_Domain_Repository_ConfigurationRepository');
		$configurationRecords = $configurationRepository->fetchAll();

		if (count($configurationRecords) === 0) {
			$newUrl = 'alt_doc.php?returnUrl=' . urlencode($thisUrl) . '&amp;edit[tx_igldapssoauth_config][0]=new';

			$message = sprintf(
				$GLOBALS['LANG']->getLL('configuration_missing.message'),
				'http://docs.typo3.org/typo3cms/extensions/ig_ldap_sso_auth/AdministratorManual/Index.html',
				$newUrl
			);
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				$message,
				$GLOBALS['LANG']->getLL('configuration_missing.title'),
				t3lib_FlashMessage::WARNING,
				TRUE
			);
			t3lib_FlashMessageQueue::addMessage($flashMessage);
			return;
		}

		$this->content .= $this->doc->header($GLOBALS['LANG']->getLL('title'));

		$config = t3lib_div::_GET('config');
		// Reset selected configuration
		$this->ldapConfiguration = NULL;

		if (count($configurationRecords) === 1) {
			$configurationSelector = htmlspecialchars($configurationRecords[0]['name']);
		} else {
			$thisFullUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . 'typo3/' . $thisUrl;
			$configurationSelector = '<select onchange="document.location=this.value;">';

			foreach ($configurationRecords as $configurationRecord) {
				$configurationSelector .= '<option value="' . htmlspecialchars($thisFullUrl . '&config=' . $configurationRecord['uid']) . '"';
				if ($config == $configurationRecord['uid']) {
					$this->ldapConfiguration = $configurationRecord;
					$configurationSelector .= ' selected="selected"';
				}
				$configurationSelector .= '>' . htmlspecialchars($configurationRecord['name']) . '</option>';
			}

			$configurationSelector .= '</select>';
		}
		if ($this->ldapConfiguration === NULL) {
			$this->ldapConfiguration = $configurationRecords[0];
		}

		$uid = $this->ldapConfiguration['uid'];
		tx_igldapssoauth_config::init(TYPO3_MODE, $uid);

		$thisUrl .= '&config=' . $uid;
		$editUrl = 'alt_doc.php?returnUrl=' . urlencode($thisUrl) . '&amp;edit[tx_igldapssoauth_config][' . $uid . ']=edit';
		$editLink = sprintf(
			' <a href="%s" title="uid=%s">' . t3lib_iconWorks::getSpriteIcon('actions-document-open') . '</a>',
			$editUrl,
			$uid
		);

		$this->content .= '<h2>' . $GLOBALS['LANG']->getLL('show_status_title', TRUE) . ' ' . $configurationSelector . ' ' . $editLink . '</h2>';

		$this->content .= '<hr />';

		switch ((string)$this->MOD_SETTINGS['function']) {
			case self::FUNCTION_SHOW_STATUS:
				$this->show_status();
				break;
			case self::FUNCTION_SEARCH_WIZARD:
				$this->search_wizard(t3lib_div::_GP('search'));
				break;
			case self::FUNCTION_IMPORT_GROUPS_BE:
				$this->import_groups('be');
				break;
			case self::FUNCTION_IMPORT_GROUPS_FE:
				$this->import_groups('fe');
				break;
			case self::FUNCTION_IMPORT_USERS_BE:
				$this->importUsers('be');
				break;
			case self::FUNCTION_IMPORT_USERS_FE:
				$this->importUsers('fe');
				break;
		}
	}

	/**
	 * Creates the panel of buttons for submitting the form or otherwise perform operations.
	 *
	 * @return array All available buttons as an assoc.
	 */
	protected function getButtons() {
		$buttons = array(
			'csh' => '',
			'shortcut' => '',
			'close' => '',
			'save' => '',
			'save_close' => '',
		);

		// CSH
		$buttons['csh'] = t3lib_BEfunc::cshItem('_MOD_web_func', '', $GLOBALS['BACK_PATH']);

		// Shortcut
		if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
			$buttons['shortcut'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
		}

		return $buttons;
	}

	/**
	 * FUNCTION MENU: Status
	 *
	 * @return void
	 */
	protected function show_status() {
		$feConfiguration = tx_igldapssoauth_config::getFeConfiguration();
		$beConfiguration = tx_igldapssoauth_config::getBeConfiguration();

		$domains = tx_igldapssoauth_config::getDomains();
		if (count($domains) > 0) {
			$this->content .= '<p><strong>' . $GLOBALS['LANG']->getLL('show_status_ldap_domains') . '</strong> ' . implode(', ', $domains) . '</p>';
		}

		// LDAP
		$title = $GLOBALS['LANG']->getLL('show_status_ldap');
		$ldapConfiguration = tx_igldapssoauth_config::getLdapConfiguration();
		if ($ldapConfiguration['host']) {

			$ldapConfiguration['server'] = tx_igldapssoauth_config::get_server_name($ldapConfiguration['server']);

			try {
				tx_igldapssoauth_ldap::connect($ldapConfiguration);
			} catch (Exception $e) {
				// Possible known exception: 1409566275, LDAP extension is not available for PHP
				$flashMessage = t3lib_div::makeInstance(
					't3lib_FlashMessage',
					$e->getMessage(),
					'Error ' . $e->getCode(),
					t3lib_FlashMessage::ERROR,
					TRUE
				);
				t3lib_FlashMessageQueue::addMessage($flashMessage);
			}

			$ldapConfiguration['password'] = $ldapConfiguration['password'] ? '********' : NULL;

			$this->content .= $this->exportArrayAsTable($ldapConfiguration, $title);

			$title = $GLOBALS['LANG']->getLL('show_status_ldap_connection_status');
			$this->content .= $this->exportArrayAsTable(tx_igldapssoauth_ldap::get_status(), $title);
		} else {
			$this->content .= $this->exportArrayAsTable($GLOBALS['LANG']->getLL('show_status_ldap_disable'), $title);
			return;
		}

		// BE
		$title = $GLOBALS['LANG']->getLL('show_status_backend_authentication');
		if ($beConfiguration['LDAPAuthentication']) {
			$configuration = $beConfiguration;
		} else {
			$configuration = $GLOBALS['LANG']->getLL('show_status_backend_authentication_disable');
		}
		$this->exportArrayAsTable($configuration, $title, 'BE');

		// FE
		$title = $GLOBALS['LANG']->getLL('show_status_frontend_authentication');
		if ($feConfiguration['LDAPAuthentication']) {
			$configuration = $feConfiguration;
		} else {
			$configuration = $GLOBALS['LANG']->getLL('show_status_frontend_authentication_disable');
		}
		$this->content .= $this->exportArrayAsTable($configuration, $title, 'FE');
	}

	/**
	 * Converts the configuration array to a HTML table.
	 *
	 * @param array|string $configuration
	 * @param string $title
	 * @param string $typo3_mode
	 * @return string
	 */
	protected function exportArrayAsTable($configuration, $title, $typo3_mode = '') {
		$groupKeys = array('requiredLDAPGroups', 'updateAdminAttribForGroups', 'assignGroups');
		$iconPath = t3lib_extMgm::extRelPath($this->extKey) . 'Resources/Public/Icons/';

		$out = array();
		$out[] = '<table cellspacing="0" cellpadding="0" border="0" class="typo3-dblist">';
		$out[] = '<tbody>';
		$out[] = '<tr class="c-table-row-spacer">';
		$out[] = '<td nowrap="nowrap" class="col-icon"><img width="1" height="8" alt="" src="clear.gif"></td>';
		$out[] = '<td nowrap="nowrap" class=""></td>';
		$out[] = '</tr>';
		$out[] = '<tr class="t3-row-header">';
		$out[] = '<td nowrap="nowrap" colspan="2"><span class="c-table">' . htmlspecialchars($title) . '</span></td>';
		$out[] = '</tr>';

		if (is_array($configuration)) {
			foreach ($configuration as $key => $value) {
				$out[] = '<tr class="db_list_normal">';
				$out[] = '<td style="width: 20em"><strong>' . htmlspecialchars($key) . '</strong></td>';

				if (!empty($key) && in_array($key, $groupKeys)) {
					$table = $typo3_mode === 'BE' ? 'be_groups' : 'fe_groups';
					if ($value != '0') {
						$uids = t3lib_div::intExplode(',', $value, TRUE);
						$rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
							'uid, title',
							$table,
							'uid IN (' . implode(',', $uids) . ')'
						);
						$value = '<ul>';
						foreach ($rows as $row) {
							$value .= '<li>' . htmlspecialchars(sprintf('%s [%s]', $row['title'], $row['uid'])) . '</li>';
						}
						$value .= '</ul>';
					} else {
						$value = '<em>' . htmlspecialchars($GLOBALS['LANG']->getLL('show_status_title_no_group')) . '</em>';
					}
				} elseif (is_array($value)) {
					$value = t3lib_utility_Debug::viewArray($value);
				} elseif ($typo3_mode === 'BE' || $typo3_mode === 'FE' || $key === 'tls') {
					// This is a boolean flag
					$value = $value ? '1' : '0';

					if ($value) {
						$icon = $iconPath . 'tick.png';
					} else {
						$icon = $iconPath . 'cross.png';
					}

					$value = sprintf('<img src="%s" alt="%s" />', $icon, $value);
				} else {
					$value = htmlspecialchars($value);
				}

				$out[] = '<td>' . $value . '</td>';
				$out[] = '</tr>';
			}
		} else {
			$out[] = '<tr class="db_list_normal">';
			$out[] = '<td colspan="2"><strong>' . htmlspecialchars($configuration) . '</strong></td>';
			$out[] = '</tr>';
		}

		$out[] = '</tbody>';
		$out[] = '</table>';

		return implode(LF, $out);
	}

	/**
	 * FUNCTION MENU: Search wizard
	 *
	 * @param array $search
	 * @return void
	 */
	protected function search_wizard($search = array()) {

		switch ($search['action']) {
			case 'select':

				list($typo3_mode, $type) = explode('_', $search['table']);
				$config = ($typo3_mode === 'be')
					? tx_igldapssoauth_config::getBeConfiguration()
					: tx_igldapssoauth_config::getFeConfiguration();

				$search['basedn'] = $config[$type]['basedn'];
				$search['filter'] = tx_igldapssoauth_config::replace_filter_markers($config[$type]['filter']);
				$attributes = tx_igldapssoauth_config::get_ldap_attributes($config[$type]['mapping']);
				if ($type === 'users') {
					if (strpos($config['groups']['filter'], '{USERUID}') !== FALSE) {
						$attributes[] = 'uid';
						$attributes = array_unique($attributes);
					}
				}
				$search['attributes'] = $search['first_entry'] ? '' : implode(',', $attributes);
				break;

			case 'search':
				break;

			default:
				$search['table'] = 'be_users';

				list($typo3_mode, $type) = explode('_', $search['table']);
				$config = ($typo3_mode === 'be')
					? tx_igldapssoauth_config::getBeConfiguration()
					: tx_igldapssoauth_config::getFeConfiguration();

				$search['first_entry'] = TRUE;
				$search['see_status'] = FALSE;
				$search['basedn'] = $config[$type]['basedn'];
				$search['filter'] = tx_igldapssoauth_config::replace_filter_markers($config[$type]['filter']);
				$attributes = tx_igldapssoauth_config::get_ldap_attributes($config['users']['mapping']);
				if (strpos($config['groups']['filter'], '{USERUID}') !== FALSE) {
					$attributes[] = 'uid';
					$attributes = array_unique($attributes);
				}
				$search['attributes'] = $search['first_entry'] ? '' : implode(',', $attributes);
				break;
		}

		$this->content .= '<h2>' . $GLOBALS['LANG']->getLL('search_wizard_title') . '</h2>';
		$this->content .= '<hr />';

		try {
			$success = tx_igldapssoauth_ldap::connect(tx_igldapssoauth_config::getLdapConfiguration());
		} catch (Exception $e) {
			// Possible known exception: 1409566275, LDAP extension is not available for PHP
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				$e->getMessage(),
				'Error ' . $e->getCode(),
				t3lib_FlashMessage::ERROR,
				TRUE
			);
			t3lib_FlashMessageQueue::addMessage($flashMessage);
			$success = FALSE;
		}
		if ($success) {
			if (is_array($search['basedn'])) {
				$search['basedn'] = implode('||', $search['basedn']);
			}
			$first_entry = $search['first_entry'] ? 'checked="checked"' : "";
			$see_status = $search['see_status'] ? 'checked="checked"' : "";
			$be_users = ($search['table'] == 'be_users') ? 'checked="checked"' : "";
			$be_groups = ($search['table'] == 'be_groups') ? 'checked="checked"' : "";
			$fe_users = ($search['table'] == 'fe_users') ? 'checked="checked"' : "";
			$fe_groups = ($search['table'] == 'fe_groups') ? 'checked="checked"' : "";

			$this->content .= '<form action="" method="post" name="search">';

			$this->content .= '<fieldset>';

			$this->content .= '
				<div>
					<input type="radio" name="search[table]" id="table-beusers" value="be_users" ' . $be_users . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="table-beusers"><strong>' . $GLOBALS['LANG']->getLL('search_wizard_radio_be_users') . '</strong></label>
				</div>';
			$this->content .= '
				<div>
					<input type="radio" name="search[table]" id="table-begroups" value="be_groups" ' . $be_groups . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="table-begroups"><strong>' . $GLOBALS['LANG']->getLL('search_wizard_radio_be_groups') . '</strong></label>
				</div>';
			$this->content .= '
				<div>
					<input type="radio" name="search[table]" id="table-feusers" value="fe_users" ' . $fe_users . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="table-feusers"><strong>' . $GLOBALS['LANG']->getLL('search_wizard_radio_fe_users') . '</strong></label>
				</div>';
			$this->content .= '
				<div>
					<input type="radio" name="search[table]" id="table-fegroups" value="fe_groups" ' . $fe_groups . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="table-fegroups"><strong>' . $GLOBALS['LANG']->getLL('search_wizard_radio_fe_groups') . '</strong></label>
				</div>';
			$this->content .= '<br />';

			$this->content .= '
				<div>
					<input type="checkbox" name="search[first_entry]" id="first-entry" value="true" ' . $first_entry . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="first-entry"><strong>' . $GLOBALS['LANG']->getLL('search_wizard_checkbox_first_entry') . '</strong></label>
				</div>';
			$this->content .= '
				<div>
					<input type="checkbox" name="search[see_status]" id="see-status" value="true" ' . $see_status . ' onclick="this.form.elements[\'search[action]\'].value=\'select\';submit();return false;" />
					<label for="see-status"><strong>' . $GLOBALS['LANG']->getLL('search_wizard_checkbox_see_status') . '</strong></label>
				</div>';
			$this->content .= '<br />';

			$this->content .= '<div><strong>' . $GLOBALS['LANG']->getLL('search_wizard_input_base_dn') . '</strong>&nbsp;<input type="text" name="search[basedn]" value="' . htmlspecialchars($search['basedn']) . '" size="50" /></div><br />';
			$this->content .= '<div><strong>' . $GLOBALS['LANG']->getLL('search_wizard_input_filter') . '</strong>&nbsp;<textarea name="search[filter]" cols="50" rows="3">' . htmlspecialchars($search['filter']) . '</textarea></div><br />';
			$this->content .= $search['attributes'] ? '<div><strong>' . $GLOBALS['LANG']->getLL('search_wizard_input_attributes') . '</strong>&nbsp;<input type="text" name="search[attributes]" value="' . htmlspecialchars($search['attributes']) . '" size="50" /></div><br />' : '';

			$this->content .= '<input type="hidden" name="search[action]" value="' . $search['action'] . '" />';
			$this->content .= '<input type="submit" value="' . $GLOBALS['LANG']->getLL('search_wizard_submit_search') . '" onclick="this.form.elements[\'search[action]\'].value=\'search\';" />';

			$this->content .= '</fieldset>';

			$this->content .= '</form><br />';

			$attributes = array();

			if (!$search['first_entry'] || !empty($search['attributes'])) {
				$attributes = explode(',', $search['attributes']);
			}

			$search['basedn'] = explode('||', $search['basedn']);
			$result = tx_igldapssoauth_ldap::search($search['basedn'], $search['filter'], $attributes, $search['first_entry'], 100);
			if (!$result) {
				$result = $GLOBALS['LANG']->getLL('search_wizard_no_result');
			}

			if ($search['see_status']) {
				$title = $GLOBALS['LANG']->getLL('search_wizard_ldap_status');
				$this->content .= $this->exportArrayAsTable(tx_igldapssoauth_ldap::get_status(), $title);
			}

			$title = $GLOBALS['LANG']->getLL('search_wizard_result');

			if ($search['first_entry'] && is_array($result) && count($result) > 1) {
				list($mode, $configKey) = explode('_', $search['table']);
				$configuration = $mode === 'fe'
					? tx_igldapssoauth_config::getFeConfiguration()
					: tx_igldapssoauth_config::getBeConfiguration();
				if ($configKey === 'users') {
					$mapping = $configuration['users']['mapping'];
					$blankTypo3Record = tx_igldapssoauth_typo3_user::create($search['table']);
				} else {
					$mapping = $configuration['groups']['mapping'];
					$blankTypo3Record = tx_igldapssoauth_typo3_group::create($search['table']);
				}
				$record = tx_igldapssoauth_auth::merge($result, $blankTypo3Record, $mapping);

				// Remove empty lines
				$keys = array_keys($record);
				foreach ($keys as $key) {
					if (empty($record[$key])) {
						unset($record[$key]);
					}
				}

				$this->content .= $this->exportArrayAsTable($record, $GLOBALS['LANG']->getLL('search_wizard_preview'));
			}

			// With PHP 5.4 and above this could be renamed as
			// ksort_recursive($result, SORT_NATURAL)
			if (is_array($result)) {
				$this->uksort_recursive($result, 'strnatcmp');
			}
			$this->content .= $this->exportArrayAsTable($result, $title);

			tx_igldapssoauth_ldap::disconnect();

		} else {

			$this->content .= '<h2>' . $GLOBALS['LANG']->getLL('search_wizard_ldap_status') . '</h2><hr />' . t3lib_utility_Debug::viewArray(tx_igldapssoauth_ldap::get_status());

		}

	}

	/**
	 * Sort recursively an array by keys using a user-defined comparison function.
	 *
	 * @param array $array The input array
	 * @param callable $key_compare_func The comparison function must return an integer less than, equal to, or greater than zero if the first argument is considered to be respectively less than, equal to, or greater than the second
	 * @return bool Returns TRUE on success or FALSE on failure
	 */
	protected function uksort_recursive(array &$array, $key_compare_func) {
		$ret = uksort($array, $key_compare_func);
		if ($ret) {
			foreach ($array as &$arr) {
				if (is_array($arr) && !$this->uksort_recursive($arr, $key_compare_func)) {
					break;
				}
			}
		}
		return $ret;
	}

	/**
	 * FUNCTION MENU: Import LDAP groups (BE/FE)
	 *
	 * @param string $typo3_mode Either 'be' or 'fe'
	 * @return void
	 */
	protected function import_groups($typo3_mode) {
		// Early return if LDAP connection is not available
		if (!$this->checkLdapConnection()) {
			return;
		}

		$import_groups = t3lib_div::_GP('import_groups');
		if (!is_array($import_groups)) {
			$import_groups = array();
		}

		$config = ($typo3_mode === 'be')
			? tx_igldapssoauth_config::getBeConfiguration()
			: tx_igldapssoauth_config::getFeConfiguration();

		$ldap_groups = array();
		if (!empty($config['groups']['basedn'])) {
			$filter = tx_igldapssoauth_config::replace_filter_markers($config['groups']['filter']);
			$attributes = tx_igldapssoauth_config::get_ldap_attributes($config['groups']['mapping']);
			$ldap_groups = tx_igldapssoauth_ldap::search($config['groups']['basedn'], $filter, $attributes);
			unset($ldap_groups['count']);
		}

		if (count($ldap_groups) === 0) {
			$this->content .= $this->exportArrayAsTable(
				$GLOBALS['LANG']->getLL('import_groups_' . $typo3_mode . '_no_groups_found'),
				$GLOBALS['LANG']->getLL('import_groups_' . $typo3_mode)
			);
			return;
		}

		$this->content .= '<form action="" method="post">';

		$out = array();
		$out[] = '<table cellspacing="0" cellpadding="0" border="0" class="typo3-dblist">';
		$out[] = '<caption>';
		$out[] = '<ul>';
		$out[] = '<li><span class="square-local"></span> ' . $GLOBALS['LANG']->getLL('import_groups_caption_local', TRUE) . '</li>';
		$out[] = '<li><span class="square-deleted"></span> ' . $GLOBALS['LANG']->getLL('import_groups_caption_deleted', TRUE) . '</li>';
		$out[] = '</ul>';
		$out[] = '</caption>';
		$out[] = '<tbody>';
		$out[] = '<tr class="c-table-row-spacer">';
		$out[] = '<td nowrap="nowrap" class=""></td>';
		$out[] = '</tr>';
		$out[] = '<tr class="t3-row-header">';
		$out[] = '<td nowrap="nowrap" colspan="5"><span class="c-table">' . $GLOBALS['LANG']->getLL('import_groups_' . $typo3_mode, TRUE) . '</span></td>';
		$out[] = '</tr>';

		$out[] = '<tr class="c-headLine">';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_groups_table_th_title') . '</td>';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_groups_table_th_dn') . '</td>';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_groups_table_th_pid') . '</td>';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_groups_table_th_uid') . '</td>';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_groups_table_th_import') . '</td>';
		$out[] = '</tr>';

		// Populate an array of TYPO3 group records corresponding to the LDAP groups
		// If a given LDAP group has no associated group in TYPO3, a fresh record
		// will be created so that $ldap_groups[i] <=> $typo3_groups[i]
		$typo3_group_pid = tx_igldapssoauth_config::get_pid($config['groups']['mapping']);
		$table = $typo3_mode === 'be' ? 'be_groups' : 'fe_groups';
		$typo3_groups = tx_igldapssoauth_auth::get_typo3_groups(
			$ldap_groups,
			$config['groups']['mapping'],
			$table,
			$typo3_group_pid
		);

		$groupsAdded = 0;
		$groupsUpdated = 0;

		foreach ($ldap_groups as $index => $ldap_group) {
			$typo3_group = tx_igldapssoauth_auth::merge($ldap_group, $typo3_groups[$index], $config['groups']['mapping']);

			// Import the group using information from LDAP
			if (t3lib_div::inArray($import_groups, $typo3_group['tx_igldapssoauth_dn'])) {
				unset($typo3_group['parentGroup']);
				if ($typo3_group['uid'] == 0) {
					$typo3_group = tx_igldapssoauth_typo3_group::add($table, $typo3_group);
					$groupsAdded++;
				} else {
					// Restore group that may have been previously deleted
					$typo3_group['deleted'] = 0;
					$success = tx_igldapssoauth_typo3_group::update($table, $typo3_group);
					if ($success) {
						$groupsUpdated++;
					}
				}

				if (!empty($config['groups']['mapping']['parentGroup'])) {
					$fieldParent = $config['groups']['mapping']['parentGroup'];
					if (preg_match("`<([^$]*)>`", $fieldParent, $attribute)) {
						$fieldParent = $attribute[1];

						if (is_array($ldap_group[$fieldParent])) {
							unset($ldap_group[$fieldParent]['count']);

							$this->setParentGroup(
								$ldap_group[$fieldParent],
								$fieldParent,
								$typo3_group['uid'],
								$typo3_group_pid,
								$typo3_mode
							);
						}
					}
				}
			}

			if ($typo3_group['uid'] == 0) {
				// LDAP group is not yet imported
				$rowClass = '';
				$isChecked = FALSE;
			} elseif ($typo3_group['deleted'] == 1) {
				// LDAP group has been manually deleted
				$rowClass = 'deleted-ldap-group';
				$isChecked = FALSE;
			} else {
				// LDAP group has already been imported
				$rowClass = 'local-ldap-group';
				$isChecked = TRUE;
			}

			$out[] = '<tr class="db_list_normal ' . $rowClass . '">';
			$out[] = '<td>' . ($typo3_group['title'] ? htmlspecialchars($typo3_group['title']) : '&nbsp;') . '</td>';
			$out[] = '<td>' . $typo3_group['tx_igldapssoauth_dn'] . '</td>';
			$out[] = '<td>' . ($typo3_group['pid'] ? $typo3_group['pid'] : 0) . '</td>';
			$out[] = '<td>' . ($typo3_group['uid'] ? $typo3_group['uid'] : 0) . '</td>';
			$out[] = '<td><input type="checkbox" name="import_groups[]" value="' . htmlspecialchars($typo3_group['tx_igldapssoauth_dn']) . '" ' . ($isChecked ? 'checked="checked"' : '') . ' /></td>';
			$out[] = '</tr>';
		}

		if (count($ldap_groups) > 0) {
			$out[] = '<tr class="db_list_normal">';
			$out[] = '<td colspan="4"></td>';
			$toggleAllLabel = $GLOBALS['LANG']->getLL('import_groups_table_select_all', TRUE);
			$out[] = <<<HTML
				<td>
					<input type="checkbox" onclick="toggleImport(this)" id="selectAll" />
					<label for="selectAll">$toggleAllLabel</label>
					<script type="text/javascript">
					function toggleImport(source) {
						checkboxes = document.getElementsByName('import_groups[]');
						for (var i=0, n=checkboxes.length; i<n; i++) {
							checkboxes[i].checked = source.checked;
						}
					}
					</script>
				</td>
HTML;
			$out[] = '</tr>';

			$out[] = '<tr class="db_list_normal">';
			$out[] = '<td colspan="4"></td>';
			$importLabel = $GLOBALS['LANG']->getLL('import_groups_form_submit_value', TRUE);
			$out[] = <<<HTML
				<td>
					<input type="hidden" name="import[action]" value="update" />
					<input type="submit" value="$importLabel" />
				</td>
HTML;
			$out[] = '</tr>';
		}

		$out[] = '</tbody>';
		$out[] = '</table>';
		$this->content .= implode(LF, $out);

		$this->content .= '</form>';

		tx_igldapssoauth_ldap::disconnect();

		if ($groupsAdded > 0 || $groupsUpdated > 0) {
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				sprintf($GLOBALS['LANG']->getLL('import_groups_status'), $groupsAdded, $groupsUpdated),
				$GLOBALS['LANG']->getLL('import_groups_' . $typo3_mode),
				t3lib_FlashMessage::INFO,
				TRUE
			);
			t3lib_FlashMessageQueue::addMessage($flashMessage);
		}
	}

	protected function setParentGroup($parentsLDAPGroups, $fieldParent, $childUid, $typo3_group_pid, $typo3_mode) {
		$subGroupList = array();
		$table = $typo3_mode === 'be' ? 'be_groups' : 'fe_groups';

		foreach ($parentsLDAPGroups as $parentDn) {
			$typo3ParentGroup = tx_igldapssoauth_typo3_group::fetch($table, FALSE, $typo3_group_pid, $parentDn);

			if (is_array($typo3ParentGroup[0])) {
				if (!empty($typo3ParentGroup[0]['subgroup'])) {
					$subGroupList = t3lib_div::trimExplode(',', $typo3ParentGroup[0]['subgroup']);
				}
				//if(!is_array($subGroupList)||!in_array($childUid,$subGroupList)){
				$subGroupList[] = $childUid;
				$subGroupList = array_unique($subGroupList);
				$typo3ParentGroup[0]['subgroup'] = implode(',', $subGroupList);
				tx_igldapssoauth_typo3_group::update($table, $typo3ParentGroup[0]);
				//}
			} else {
				$config = ($typo3_mode === 'be')
					? tx_igldapssoauth_config::getBeConfiguration()
					: tx_igldapssoauth_config::getFeConfiguration();

				$filter = '(&' . tx_igldapssoauth_config::replace_filter_markers($config['groups']['filter']) . '&(distinguishedName=' . $parentDn . '))';
				$attributes = tx_igldapssoauth_config::get_ldap_attributes($config['groups']['mapping']);
				$ldap_groups = tx_igldapssoauth_ldap::search($config['groups']['basedn'], $filter, $attributes);
				unset($ldap_groups['count']);

				if (count($ldap_groups) > 0) {
					$typo3_group_pid = tx_igldapssoauth_config::get_pid($config['groups']['mapping']);

					// Populate an array of TYPO3 group records corresponding to the LDAP groups
					// If a given LDAP group has no associated group in TYPO3, a fresh record
					// will be created so that $ldap_groups[i] <=> $typo3_groups[i]
					$typo3_groups = tx_igldapssoauth_auth::get_typo3_groups(
						$ldap_groups,
						$config['groups']['mapping'],
						$table,
						$typo3_group_pid
					);

					foreach ($ldap_groups as $index => $ldap_group) {
						$typo3_group = tx_igldapssoauth_auth::merge($ldap_group, $typo3_groups[$index], $config['groups']['mapping']);
						unset($typo3_group['parentGroup']);
						$typo3_group['subgroup'] = $childUid;
						$typo3_group = tx_igldapssoauth_typo3_group::add($table, $typo3_group);

						if (is_array($ldap_group[$fieldParent])) {
							unset($ldap_group[$fieldParent]['count']);

							$this->setParentGroup(
								$ldap_group[$fieldParent],
								$fieldParent,
								$typo3_group['uid'],
								$typo3_group_pid,
								$typo3_mode
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Displays all users corresponding to the current LDAP configuration.
	 * Imports those users if selected.
	 *
	 * @param string $typo3Mode Current mode. Should be either "fe" or "be".
	 * @return void
	 */
	protected function importUsers($typo3Mode) {
		// Early return if LDAP connection is not available
		if (!$this->checkLdapConnection()) {
			return;
		}

		// Get list of users to import
		$importUsers = t3lib_div::_GP('import_users');
		if (!is_array($importUsers)) {
			$importUsers = array();
		}

		/** @var Tx_IgLdapSsoAuth_Utility_UserImport $importUtility */
		$importUtility = t3lib_div::makeInstance(
			'Tx_IgLdapSsoAuth_Utility_UserImport',
			$this->ldapConfiguration['uid'],
			$typo3Mode
		);
		$ldapUsers = $importUtility->fetchLdapUsers();
		if (count($ldapUsers) === 0) {
			$this->content .= $this->exportArrayAsTable(
				$GLOBALS['LANG']->getLL('import_users_' . $typo3Mode . '_no_users_found'),
				$GLOBALS['LANG']->getLL('import_users_' . $typo3Mode)
			);
			return;
		}

		$this->content .= '<form action="" method="post">';

		// Assemble the table header
		$out = array();
		$out[] = '<table cellspacing="0" cellpadding="0" border="0" class="typo3-dblist">';
		$out[] = '<caption>';
		$out[] = '<ul>';
		$out[] = '<li><span class="square-local"></span> ' . $GLOBALS['LANG']->getLL('import_users_caption_local', TRUE) . '</li>';
		$out[] = '<li><span class="square-deleted"></span> ' . $GLOBALS['LANG']->getLL('import_users_caption_deleted', TRUE) . '</li>';
		$out[] = '</ul>';
		$out[] = '</caption>';
		$out[] = '<tbody>';
		$out[] = '<tr class="c-table-row-spacer">';
		$out[] = '<td nowrap="nowrap" class=""></td>';
		$out[] = '</tr>';
		$out[] = '<tr class="t3-row-header">';
		$out[] = '<td nowrap="nowrap" colspan="5"><span class="c-table">' . $GLOBALS['LANG']->getLL('import_users_' . $typo3Mode, TRUE) . '</span></td>';
		$out[] = '</tr>';

		$out[] = '<tr class="c-headLine">';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_users_table_th_title') . '</td>';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_users_table_th_dn') . '</td>';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_users_table_th_pid') . '</td>';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_users_table_th_uid') . '</td>';
		$out[] = '<td nowrap="nowrap">' . $GLOBALS['LANG']->getLL('import_users_table_th_import') . '</td>';
		$out[] = '</tr>';

		$config = $importUtility->getConfiguration();
		$numberOfUsers = 0;

		// Loop on all users and display them
		// If a user was selected import if from LDAP
		do {
			$numberOfUsers += count($ldapUsers);
			$typo3Users = $importUtility->fetchTypo3Users($ldapUsers);
			foreach ($ldapUsers as $index => $aUser) {
				// Merge LDAP and TYPO3 information
				$user = tx_igldapssoauth_auth::merge($aUser, $typo3Users[$index], $config['users']['mapping']);

				// Import the user using information from LDAP
				if (in_array($user['tx_igldapssoauth_dn'], $importUsers, TRUE)) {
					$user = $importUtility->import($user, $aUser);
				}

				if ($user['uid'] == 0) {
					// LDAP user is not yet imported
					$rowClass = '';
					$isChecked = FALSE;
				} elseif ($user['deleted'] == 1) {
					// LDAP user has been manually deleted
					$rowClass = 'deleted-ldap-group';
					$isChecked = FALSE;
				} else {
					// LDAP user has already been imported
					$rowClass = 'local-ldap-group';
					$isChecked = TRUE;
				}

				$out[] = '<tr class="db_list_normal ' . $rowClass . '">';
				$out[] = '<td>' . ($user['title'] ? htmlspecialchars($user['title']) : '&nbsp;') . '</td>';
				$out[] = '<td>' . $user['tx_igldapssoauth_dn'] . '</td>';
				$out[] = '<td>' . ($user['pid'] ? $user['pid'] : 0) . '</td>';
				$out[] = '<td>' . ($user['uid'] ? $user['uid'] : 0) . '</td>';
				$out[] = '<td><input type="checkbox" name="import_users[]" value="' . htmlspecialchars($user['tx_igldapssoauth_dn']) . '" ' . ($isChecked ? 'checked="checked"' : '') . ' /></td>';
				$out[] = '</tr>';
			}

			// Free memory before going on
			$typo3Users = NULL;
			$ldapUsers = NULL;
			$ldapUsers = $importUtility->hasMoreLdapUsers() ? $importUtility->fetchLdapUsers(TRUE) : array();
		} while (count($ldapUsers) > 0);

		if ($numberOfUsers > 0) {
			$out[] = '<tr class="db_list_normal">';
			$out[] = '<td colspan="4"></td>';
			$toggleAllLabel = $GLOBALS['LANG']->getLL('import_users_table_select_all', TRUE) . ' (' . $numberOfUsers . ')';
			$out[] = <<<HTML
				<td>
					<input type="checkbox" onclick="toggleImport(this)" id="selectAll" />
					<label for="selectAll">$toggleAllLabel</label>
					<script type="text/javascript">
					function toggleImport(source) {
						checkboxes = document.getElementsByName('import_users[]');
						for (var i=0, n=checkboxes.length; i<n; i++) {
							checkboxes[i].checked = source.checked;
						}
					}
					</script>
				</td>
HTML;
			$out[] = '</tr>';

			$out[] = '<tr class="db_list_normal">';
			$out[] = '<td colspan="4"></td>';
			$importLabel = $GLOBALS['LANG']->getLL('import_users_form_submit_value', TRUE);
			$out[] = <<<HTML
				<td>
					<input type="hidden" name="import[action]" value="update" />
					<input type="submit" value="$importLabel" />
				</td>
HTML;
			$out[] = '</tr>';
		}

		$out[] = '</tbody>';
		$out[] = '</table>';
		$this->content .= implode(LF, $out);

		$this->content .= '</form>';

		tx_igldapssoauth_ldap::disconnect();

		$usersAdded = $importUtility->getUsersAdded();
		$usersUpdated = $importUtility->getUsersUpdated();
		if ($usersAdded > 0 || $usersUpdated > 0) {
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				sprintf(
					$GLOBALS['LANG']->getLL('import_users_status'),
					$usersAdded,
					$usersUpdated
				),
				$GLOBALS['LANG']->getLL('import_users_' . $typo3Mode),
				t3lib_FlashMessage::INFO,
				TRUE
			);
			t3lib_FlashMessageQueue::addMessage($flashMessage);
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return string HTML content
	 */
	public function printContent() {
		echo $this->content;
	}

	/**
	 * Returns the database connection.
	 *
	 * @return t3lib_DB
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Checks the LDAP connection and prepares a Flash message if unavailable.
	 *
	 * @return bool
	 */
	protected function checkLdapConnection() {
		try {
			$success = tx_igldapssoauth_ldap::connect(tx_igldapssoauth_config::getLdapConfiguration());
		} catch (Exception $e) {
			// Possible known exception: 1409566275, LDAP extension is not available for PHP
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				$e->getMessage(),
				'Error ' . $e->getCode(),
				t3lib_FlashMessage::ERROR,
				TRUE
			);
			t3lib_FlashMessageQueue::addMessage($flashMessage);
			return FALSE;
		}
		return $success;
	}
}

// Make instance:
/** @var $SOBE tx_igldapssoauth_module1 */
$SOBE = t3lib_div::makeInstance('tx_igldapssoauth_module1');
$SOBE->init();

// Include files?
foreach ($SOBE->include_once as $INC_FILE) {
	include_once($INC_FILE);
}

$SOBE->main();
$SOBE->printContent();
