<?php
declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\IgLdapSsoAuth\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Causal\IgLdapSsoAuth\Exception\ImportUsersException;
use Causal\IgLdapSsoAuth\Domain\Repository\Typo3UserRepository;
use Causal\IgLdapSsoAuth\Library\Authentication;
use Causal\IgLdapSsoAuth\Library\Configuration;
use Causal\IgLdapSsoAuth\Library\Ldap;

/**
 * Centralizes the code for importing users from LDAP/AD sources.
 *
 * @author     Francois Suter <typo3@cobweb.ch>
 * @package    TYPO3
 * @subpackage ig_ldap_sso_auth
 */
class UserImportUtility
{
    /**
     * Selected LDAP configuration.
     */
    protected readonly array $configuration;

    /**
     * Which table to import users into.
     *
     * @var string
     */
    protected readonly string $userTable;

    /**
     * Which table to import groups into.
     *
     * @var string
     */
    protected readonly string $groupTable;

    /**
     * Total users added (for reporting).
     *
     * @var int
     */
    protected int $usersAdded = 0;

    /**
     * Total users updated (for reporting).
     *
     * @var int
     */
    protected int $usersUpdated = 0;

    /**
     * Default constructor.
     *
     * @param \Causal\IgLdapSsoAuth\Domain\Model\Configuration $configuration
     * @param string $context
     */
    public function __construct(
        \Causal\IgLdapSsoAuth\Domain\Model\Configuration $configuration,
        protected string $context
    )
    {
        // Load the configuration
        Configuration::initialize($context, $configuration);

        // Store current context and get related configuration
        $this->context = strtolower($context);
        $this->configuration = $this->context === 'fe'
            ? Configuration::getFrontendConfiguration()
            : Configuration::getBackendConfiguration();
        // Define related tables
        if ($this->context === 'be') {
            $this->userTable = 'be_users';
            $this->groupTable = 'be_groups';
        } else {
            $this->userTable = 'fe_users';
            $this->groupTable = 'fe_groups';
        }
    }

    /**
     * @return string
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * @return string
     */
    public function getUserTable(): string
    {
        return $this->userTable;
    }

    /**
     * Disables all users related to the current configuration.
     *
     * @return int[] List of uids of users who got disabled
     */
    public function disableUsers(): array
    {
        return Typo3UserRepository::disableForConfiguration(
            $this->userTable,
            Configuration::getUid()
        );
    }

    /**
     * Deletes all users related to the current configuration.
     *
     * @return int[] List of uids of users who got deleted
     */
    public function deleteUsers(): array
    {
        return Typo3UserRepository::deleteForConfiguration(
            $this->userTable,
            Configuration::getUid()
        );
    }

    /**
     * Fetches all possible LDAP/AD users for a given configuration and context.
     *
     * @param bool $continueLastSearch
     * @param Ldap|null $ldapInstance
     * @return array
     */
    public function fetchLdapUsers(bool $continueLastSearch = false, ?Ldap $ldapInstance = null): array
    {
        // Get the users from LDAP/AD server
        $ldapUsers = [];
        if ($ldapInstance === null) {
            return $ldapUsers;
        }

        if (!empty($this->configuration['users']['basedn'])) {
            $filter = Configuration::replaceFilterMarkers($this->configuration['users']['filter']);
            if (Configuration::hasExtendedMapping($this->configuration['users']['mapping'])) {
                // Fetch all attributes so that hooks may do whatever they want on any LDAP attribute
                $attributes = [];
            } else {
                // Optimize the LDAP call by retrieving only attributes in use for the mapping
                $attributes = Configuration::getLdapAttributes($this->configuration['users']['mapping']);
            }
            $ldapUsers = $ldapInstance->search(
                $this->configuration['users']['basedn'],
                $filter,
                $attributes,
                false,
                0,
                $continueLastSearch
            );
            unset($ldapUsers['count']);
        }

        return $ldapUsers;
    }

    /**
     * Returns true is a previous call to.
     *
     * @param Ldap|null $ldapInstance
     * @return bool
     * @see fetchLdapUsers() returned a partial result set
     */
    public function hasMoreLdapUsers(?Ldap $ldapInstance = null): bool
    {
        $hasMoreLdapUsers = false;

        if ($ldapInstance !== null) {
            $hasMoreLdapUsers = $ldapInstance->isPartialSearchResult();
        }

        return $hasMoreLdapUsers;
    }

    /**
     * Fetches all existing TYPO3 users related to the given LDAP/AD users.
     *
     * @param array $ldapUsers List of LDAP/AD users
     * @return array
     */
    public function fetchTypo3Users(array $ldapUsers): array
    {
        // Populate an array of TYPO3 users records corresponding to the LDAP users
        // If a given LDAP user has no associated user in TYPO3, a fresh record
        // will be created so that $ldapUsers[i] <=> $typo3Users[i]
        $typo3UserPid = Configuration::getPid($this->configuration['users']['mapping']);
        $typo3Users = Authentication::getTypo3Users(
            $ldapUsers,
            $this->configuration['users']['mapping'],
            $this->userTable,
            $typo3UserPid
        );
        return $typo3Users;
    }

    /**
     * Imports a given user to the TYPO3 database.
     *
     * @param array $user Local user information
     * @param array $ldapUser LDAP user information
     * @param string $restoreBehavior How to restore users (only for update)
     * @param string $disableField
     * @return array Modified user data
     * @throws ImportUsersException
     */
    public function import(
        array $user,
        array $ldapUser,
        string $restoreBehavior = 'both',
        string $disableField = ''
    ): array
    {
        // Store the extra data for later restore and remove it
        if (isset($user['__extraData'])) {
            $extraData = $user['__extraData'];
            unset($user['__extraData']);
        }

        $restoreDisableValue = 0;
        if (!empty($disableField) && isset($user['__' . $disableField])) {
            $restoreDisableValue = $user['__' . $disableField];
            unset($user['__' . $disableField]);
        }

        $typo3Groups = Authentication::getUserGroups($ldapUser, $this->configuration, $this->groupTable);
        if ($typo3Groups === null) {
            // Required LDAP groups are missing: quit!
            return $user;
        }

        if (empty($user['uid'])) {
            // Set other necessary information for a new user
            // First make sure to be acting in the right context
            Configuration::setMode($this->context);
            $user['username'] = Typo3UserRepository::setUsername($user['username']);
            $user['password'] = Typo3UserRepository::setRandomPassword();
            $user = Typo3UserRepository::setUserGroups($user, $typo3Groups, $this->groupTable);
            $user = Typo3UserRepository::add($this->userTable, $user);
            $this->usersAdded++;
        } else {
            // Restore user that may have been previously deleted or disabled, depending on chosen behavior
            // (default to both undelete and re-enable)
            switch ($restoreBehavior) {
                case 'enable':
                    $user[$GLOBALS['TCA'][$this->userTable]['ctrl']['enablecolumns']['disabled']] = $restoreDisableValue;
                    break;
                case 'undelete':
                    $user[$GLOBALS['TCA'][$this->userTable]['ctrl']['delete']] = 0;
                    break;
                case 'nothing':
                    break;
                default:
                    $user[$GLOBALS['TCA'][$this->userTable]['ctrl']['enablecolumns']['disabled']] = $restoreDisableValue;
                    $user[$GLOBALS['TCA'][$this->userTable]['ctrl']['delete']] = 0;
            }
            $user = Typo3UserRepository::setUserGroups($user, $typo3Groups, $this->groupTable);
            $success = Typo3UserRepository::update($this->userTable, $user);
            if ($success) {
                $this->usersUpdated++;
            }
        }

        // Restore the extra data and trigger a signal
        if (isset($extraData)) {
            $user['__extraData'] = $extraData;

            // Hook for processing the extra data
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ig_ldap_sso_auth']['extraDataProcessing'] ?? null)) {
                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ig_ldap_sso_auth']['extraDataProcessing'] as $className) {
                    trigger_error(
                        'Hook extraDataProcessing is deprecated since version 4.1. Please migrate '
                            . $className . ' to listen to the PSR-14 events "UserAddedEvent" and "UserUpdatedEvent".',
                        E_USER_DEPRECATED
                    );

                    /** @var \Causal\IgLdapSsoAuth\Utility\ExtraDataProcessorInterface $postProcessor */
                    $postProcessor = GeneralUtility::makeInstance($className);
                    if ($postProcessor instanceof \Causal\IgLdapSsoAuth\Utility\ExtraDataProcessorInterface) {
                        $postProcessor->processExtraData($this->userTable, $user);
                    } else {
                        throw new ImportUsersException(
                            sprintf(
                                'Invalid post-processing class %s. It must implement the \\Causal\\IgLdapSsoAuth\\Utility\\ExtraDataProcessorInterface interface',
                                $className
                            ),
                            1414136057
                        );
                    }
                }
            }
        }

        return $user;
    }

    /**
     * Returns the current configuration.
     *
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * Returns the number of users added during the importer's lifetime.
     *
     * @return int
     */
    public function getUsersAdded(): int
    {
        return $this->usersAdded;
    }

    /**
     * Returns the number of users updated during the importer's lifetime.
     *
     * @return int
     */
    public function getUsersUpdated(): int
    {
        return $this->usersUpdated;
    }
}
