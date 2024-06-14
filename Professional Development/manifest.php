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
$url         = 'https://github.com/ali-ichk/module-professionalDevelopment';

// Module tables & gibbonSettings entries
$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequest` (
    `professionalDevelopmentRequestID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
    `gibbonPersonIDCreated` int(10) unsigned zerofill NOT NULL,
    `timestampCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `gibbonPersonIDModified` int(10) unsigned zerofill NOT NULL,
    `timestampModified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status` ENUM('Requested','Approved','Rejected','Cancelled','Awaiting Final Approval','Draft') DEFAULT 'Requested' NOT NULL,
    `eventType` ENUM('Internal', 'External') NOT NULL,
    `eventFocus` varchar(60) NOT NULL,
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

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestPerson` (
  `professionalDevelopmentRequestPersonID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` int(10) unsigned zerofill NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `eventRole` varchar(60) NOT NULL,
  `registrationCost` decimal(12,2),
  `miscellaneousCost` decimal(12,2),
  `costNotes` varchar(60),
  `coverRequired` ENUM('Y', 'N'),
  `coverAmount` varchar(60),
  PRIMARY KEY (`professionalDevelopmentRequestPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestDays` (
  `professionalDevelopmentRequestDaysID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` int(10) unsigned zerofill NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date NOT NULL,
  `allDay` tinyint(1) NOT NULL,
  `startTime` time NOT NULL DEFAULT '00:00:00',
  `endTime` time NOT NULL DEFAULT '00:00:00',
  PRIMARY KEY (`professionalDevelopmentRequestDaysID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestLog` (
  `professionalDevelopmentRequestLogID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` int(10) unsigned zerofill NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `requestStatus` enum('Request','Cancellation','Approval - Partial','Approval - Final','Rejection','Comment','Edit') NOT NULL,
  `comment` text,
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

// Add gibbonSettings entries
//$gibbonSetting[] = '';


// Action rows 
// One array per action

$actionRows[] = [
    'name'                      => 'Manage Requests', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Requests', // Optional: subgroups for the right hand side module menu
    'description'               => 'Manage Professional Development requests.', // Text description
    'URLList'                   => 'requests_manage.php,requests_add.php', // List of pages included in this action
    'entryURL'                  => 'requests_manage.php', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'Y', // Should this action be available to user roles in the Other category?
];

$actionRows[] = [
  'name'                      => 'Submit Request', // The name of the action (appears to user in the right hand side module menu)
  'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
  'category'                  => 'Requests', // Optional: subgroups for the right hand side module menu
  'description'               => 'Submit a request for Professional Development.', // Text description
  'URLList'                   => 'requests_add.php, requests_manage.php', // List of pages included in this action
  'entryURL'                  => 'requests_add.php', // The landing action for the page.
  'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
  'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
  'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
  'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
  'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
  'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
  'defaultPermissionSupport'  => 'Y', // Default permission for built in role Support
  'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
  'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
  'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
  'categoryPermissionOther'   => 'Y', // Should this action be available to user roles in the Other category?
];



// Hooks
//$hooks[] = ''; // Serialised array to create hook and set options. See Hooks documentation online.
