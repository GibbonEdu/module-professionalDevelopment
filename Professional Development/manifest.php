<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http:// www.gnu.org/licenses/>.
*/

// This file describes the module, including database tables

// Basic variables
$name        = 'Professional Development';
$description = 'A Professional Development (PD) module for Gibbon to record Staff PD';
$entryURL    = "requests_manage.php";   // The landing page for the unit, used in the main menu
$type        = "Additional";
$category    = 'Other';
$version     = '0.0.01';
$author      = 'Ali';
$url         = 'https://github.com/GibbonEdu/module-professionalDevelopment';

// Module tables & gibbonSettings entries
$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequests` (
    `professionalDevelopmentRequestID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
    `gibbonPersonIDCreated` int(10) unsigned zerofill NOT NULL,
    `timestampCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Requested','Approved','Rejected','Cancelled','Awaiting Final Approval','Draft') DEFAULT 'Requested' NOT NULL,
    `eventType` ENUM('Internal', 'External') NOT NULL,
    `eventFocus` varchar(60) NOT NULL,
    `attendeeRole` varchar(60) NOT NULL,
    `attendeeCount` int(10) NOT NULL,
    `eventTitle` varchar(60) NOT NULL,
    `eventDescription` text NOT NULL,
    `eventLocation` text NOT NULL,
    `personalRational` text NOT NULL,
    `departmentImpact` text NOT NULL,
    `schoolSharing` text NOT NULL,
    `supportingEvidence` varchar(255) DEFAULT NULL,
    `notes` text,
    PRIMARY KEY (`professionalDevelopmentRequestID`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestDays` (
  `professionalDevelopmentRequestDaysID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` int(10) unsigned zerofill NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date NOT NULL,
  `allDay` tinyint(1) NOT NULL,
  PRIMARY KEY (`professionalDevelopmentRequestDaysID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestCost` (
  `professionalDevelopmentRequestCostID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` int(10) unsigned zerofill NOT NULL,
  `title` varchar(60) NOT NULL,
  `description` text NOT NULL,
  `cost` decimal(12, 2) NOT NULL,
  PRIMARY KEY (`professionalDevelopmentRequestCostID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestPerson` (
  `professionalDevelopmentRequestPersonID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` int(10) unsigned zerofill NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  PRIMARY KEY (`professionalDevelopmentRequestPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestLog` (
  `professionalDevelopmentRequestLogID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` int(10) unsigned zerofill NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `requestStatus` enum('Request','Cancellation','Approval - Partial','Approval - Final','Rejection','Comment','Edit') NOT NULL,
  `comment` text NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`professionalDevelopmentRequestLogID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestApprovers` (
  `professionalDevelopmentRequestApproversID` int(4) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `sequenceNumber` int(4) DEFAULT NULL,
  `finalApprover` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`professionalDevelopmentRequestApproversID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES
(NULL, 'Professional Development', 'requestApprovalType', 'Request Approval Type', 'The type of approval that a request has to go through.', 'One Of'),
(NULL, 'Professional Development', 'headApproval', 'Head Approval', 'A Final Approval is required before the request becomes approved.', '1'),
(NULL, 'Professional Development', 'expiredUnapprovedFilter', 'Disable View of Exipired Unapproved Requests', 'If selected then any request which has not been approved and has passed the initial start date will no longer be shown.', '0')
";

$moduleTables[] = "INSERT INTO `gibbonNotificationEvent` (`event`, `moduleName`, `actionName`, `type`, `scopes`, `active`)
VALUES
('Request Approval', 'Professional Development', 'Manage Requests_full', 'Additional', 'All', 'Y'),
('New Request', 'Professional Development', 'Manage Requests_full', 'Additional', 'All', 'Y');";

// Add gibbonSettings entries
//$gibbonSetting[] = '';


// Action rows 
// One array per action

$actionRows[] = [
    'name'                      => 'Manage Requests', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Requests', // Optional: subgroups for the right hand side module menu
    'description'               => 'Manage Professional Development requests.', // Text description
    'URLList'                   => 'requests_manage.php',
    'entryURL'                  => 'requests_manage.php', // The landing action for the page.
    // 'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    // 'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'N', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'N', // Should this action be available to user roles in the Other category?
];

$actionRows[] = [
  'name'                      => 'Manage Requests_full',
  'precedence'                => '1',
  'category'                  => 'Requests',
  'description'               => 'Manage Professional Development requests.',
  'URLList'                   => 'requests_manage.php',
  'entryURL'                  => 'requests_manage.php', 
  'defaultPermissionAdmin'    => 'Y', 
  'defaultPermissionTeacher'  => 'N', 
  'defaultPermissionStudent'  => 'N',
  'defaultPermissionParent'   => 'N',
  'defaultPermissionSupport'  => 'N',
  'categoryPermissionStaff'   => 'Y',
  'categoryPermissionStudent' => 'N',
  'categoryPermissionParent'  => 'N', 
  'categoryPermissionOther'   => 'N', 
];

$actionRows[] = [
  'name'                      => 'Submit Request', // The name of the action (appears to user in the right hand side module menu)
  'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
  'category'                  => 'Requests', // Optional: subgroups for the right hand side module menu
'description'               => 'Submit a request for Professional Development.', // Text description
  'URLList'                   => 'requests_add.php', // List of pages included in this action
  'entryURL'                  => 'requests_add.php', // The landing action for the page.
  // 'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
  // 'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
  'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
  'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
  'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
  'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
  'defaultPermissionSupport'  => 'N', // Default permission for built in role Support
  'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
  'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
  'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
  'categoryPermissionOther'   => 'N', // Should this action be available to user roles in the Other category?
];

$actionRows[] = [
  'name'                      => 'Submit Request_all',
  'precedence'                => '1',
  'category'                  => 'Requests',
  'description'               => 'Submit a request for Professional Development.',
  'URLList'                   => 'requests_add.php',
  'entryURL'                  => 'requests_add.php', 
  'defaultPermissionAdmin'    => 'Y', 
  'defaultPermissionTeacher'  => 'N', 
  'defaultPermissionStudent'  => 'N',
  'defaultPermissionParent'   => 'N',
  'defaultPermissionSupport'  => 'N',
  'categoryPermissionStaff'   => 'Y',
  'categoryPermissionStudent' => 'N',
  'categoryPermissionParent'  => 'N', 
  'categoryPermissionOther'   => 'N', 
];

$actionRows[] = [
  'name'                      => 'Manage Approvers_view',
  'precedence'                => '0',
  'category'                  => 'Settings',
  'description'               => 'Manage request approvers',
  'URLList'                   => 'requests_manageApprovers.php',
  'entryURL'                  => 'requests_manageApprovers.php', 
  'defaultPermissionAdmin'    => 'Y', 
  'defaultPermissionTeacher'  => 'N', 
  'defaultPermissionStudent'  => 'N',
  'defaultPermissionParent'   => 'N',
  'defaultPermissionSupport'  => 'N',
  'categoryPermissionStaff'   => 'Y',
  'categoryPermissionStudent' => 'N',
  'categoryPermissionParent'  => 'N', 
  'categoryPermissionOther'   => 'N', 
];

$actionRows[] = [
  'name'                      => 'Manage Approvers_add&edit',
  'precedence'                => '1',
  'category'                  => 'Settings',
  'description'               => 'Manage request approvers',
  'URLList'                   => 'requests_manageApprovers.php, requests_addApprover.php, requests_editApprover.php',
  'entryURL'                  => 'requests_manageApprovers.php', 
  'defaultPermissionAdmin'    => 'Y', 
  'defaultPermissionTeacher'  => 'N', 
  'defaultPermissionStudent'  => 'N',
  'defaultPermissionParent'   => 'N',
  'defaultPermissionSupport'  => 'N',
  'categoryPermissionStaff'   => 'Y',
  'categoryPermissionStudent' => 'N',
  'categoryPermissionParent'  => 'N', 
  'categoryPermissionOther'   => 'N', 
];

$actionRows[] = [
  'name'                      => 'Manage Approvers_full',
  'precedence'                => '2',
  'category'                  => 'Settings',
  'description'               => 'Manage request approvers',
  'URLList'                   => 'requests_manageApprovers.php, requests_addApprover.php, requests_editApprover.php, requests_deleteApproverProcess.php',
  'entryURL'                  => 'requests_manageApprovers.php', 
  'defaultPermissionAdmin'    => 'Y', 
  'defaultPermissionTeacher'  => 'N', 
  'defaultPermissionStudent'  => 'N',
  'defaultPermissionParent'   => 'N',
  'defaultPermissionSupport'  => 'N',
  'categoryPermissionStaff'   => 'Y',
  'categoryPermissionStudent' => 'N',
  'categoryPermissionParent'  => 'N', 
  'categoryPermissionOther'   => 'N', 
];

$actionRows[] = [
  'name'                      => 'Manage Request Settings',
  'precedence'                => '0',
  'category'                  => 'Settings',
  'description'               => 'Manage Request Settings',
  'URLList'                   => 'requests_manageSettings.php',
  'entryURL'                  => 'requests_manageSettings.php', 
  'defaultPermissionAdmin'    => 'Y', 
  'defaultPermissionTeacher'  => 'N', 
  'defaultPermissionStudent'  => 'N',
  'defaultPermissionParent'   => 'N',
  'defaultPermissionSupport'  => 'N',
  'categoryPermissionStaff'   => 'Y',
  'categoryPermissionStudent' => 'N',
  'categoryPermissionParent'  => 'N', 
  'categoryPermissionOther'   => 'N', 
];



// Hooks
//$hooks[] = ''; // Serialised array to create hook and set options. See Hooks documentation online.
