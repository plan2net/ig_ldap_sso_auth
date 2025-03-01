﻿.. _development-getgroupsprocessing:

Post-processing the attached groups of a user
================================================

This hook lets you post-process the typo3_groups that will be attached to a
user.

In your extension (in the :file:`ext_localconf.php` file), register the hook
using a code like:

.. code-block:: php

	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ig_ldap_sso_auth']['getGroupsProcessing'][]
		= \VendorName\Extension\YourClass::class;

Your class has to implement the
:code:`\Causal\IgLdapSsoAuth\Utility\GetGroupsProcessorInterface` interface.
This implies implementing a method called :code:`getUserGroups` which will
receive the following arguments:

$groupTable
	The name of the db table for the groups (be_groups vs. fe_groups).

$ldapUser
	The full data from ldap of the currently processed user.

$ldapGroups
	The array of the groups that have been calculated for this user. passed as
	reference so you can add/remove items from the list.

.. info::

   This hook is deprecated since version 4.1. You should migrate your code by
   listening to the PSR-14 events `AfterComputeUserGroupsEvent` instead.
