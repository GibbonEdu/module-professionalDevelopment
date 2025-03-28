<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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
$entryURL    = "pd_manage.php";   // The landing page for the unit, used in the main menu
$type        = "Additional";
$category    = 'Other';
$version     = '0.0.02';
$author      = 'Gibbon Foundation';
$url         = 'https://github.com/GibbonEdu/module-professionalDevelopment';

// Module tables & gibbonSettings entries
$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequests` (
    `professionalDevelopmentRequestID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonIDCreated` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Requested','Approved','Rejected','Cancelled','Awaiting Final Approval','Draft') DEFAULT 'Requested' NOT NULL,
    `eventType` VARCHAR(60) NOT NULL,
    `eventFocus` VARCHAR(60) NOT NULL,
    `attendeeRole` VARCHAR(60) NOT NULL,
    `attendeeCount` INT(10) NOT NULL,
    `coverAmount` TEXT NOT NULL,
    `eventTitle` VARCHAR(60) NOT NULL,
    `eventDescription` TEXT NOT NULL,
    `eventLocation` TEXT NOT NULL,
    `personalRational` TEXT NOT NULL,
    `departmentImpact` TEXT NOT NULL,
    `schoolSharing` TEXT NOT NULL,
    `supportingEvidence` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT,
    PRIMARY KEY (`professionalDevelopmentRequestID`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestDays` (
  `professionalDevelopmentRequestDaysID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` INT(10) UNSIGNED ZEROFILL NOT NULL,
  `date` date NOT NULL,
  `allDay` ENUM('Y','N') DEFAULT 'Y' NOT NULL,
  PRIMARY KEY (`professionalDevelopmentRequestDaysID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestCost` (
  `professionalDevelopmentRequestCostID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` INT(10) UNSIGNED ZEROFILL NOT NULL,
  `title` VARCHAR(60) NOT NULL,
  `description` TEXT NOT NULL,
  `cost` decimal(12, 2) NOT NULL,
  `quantity` INT(2) DEFAULT '1',
  PRIMARY KEY (`professionalDevelopmentRequestCostID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestPerson` (
  `professionalDevelopmentRequestPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` INT(10) UNSIGNED ZEROFILL NOT NULL,
  `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
  `role` VARCHAR(60) NOT NULL,
  PRIMARY KEY (`professionalDevelopmentRequestPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestLog` (
  `professionalDevelopmentRequestLogID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `professionalDevelopmentRequestID` INT(10) UNSIGNED ZEROFILL NOT NULL,
  `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
  `requestStatus` enum('Request','Cancellation','Approval - Partial','Approval - Final','Rejection','Comment','Edit') NOT NULL,
  `comment` TEXT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`professionalDevelopmentRequestLogID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `professionalDevelopmentRequestApprovers` (
  `professionalDevelopmentRequestApproversID` INT(4) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
  `sequenceNumber` INT(4) DEFAULT NULL,
  `finalApprover` TINYINT(1) DEFAULT '0',
  PRIMARY KEY (`professionalDevelopmentRequestApproversID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Add gibbonSettings entries
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'requestApprovalType', 'Request Approval Type', 'The type of approval that a request has to go through.', 'One Of')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'headApproval', 'Head Approval', 'A Final Approval is required before the request becomes approved.', '1')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'expiredUnapprovedFilter', 'Disable View of Exipired Unapproved Requests', 'If selected then any request which has not been approved and has passed the initial start date will no longer be shown.', '0')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'eventTypes', 'Event Types', 'A comma separated list of available options.', 'Internal,External - Local,External - Overseas')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'areasOfFocus', 'Areas of Focus', 'A comma separated list of available options.', 'IB,IGCSE,SEN,Other')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'expenseOptions', 'Expense Options', 'A comma separated list of available options.', 'Registration Fee,Flight,Hotel (Recommended by organiser),Hotel (independent),Other cost (please provide details)')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'agreementDescription', 'Agreement Description', 'Additional text and information to display in this section of the application', 'Please note that completion of this application will not always guarantee confirmation. When and if necessary, consideration will need to be given to the number of staff wishing to attend at any given time, support available to cover classes, limits to registrations available for individual schools, and perceived areas of need within the school.  Please be assured, however, that all decisions will be discussed with you in a timely fashion. *No registration or airline/hotel booking will be executed if this application is not approved by the Head of School. The application should be submitted at least 15 working days prior to the conference/workshop’s registration deadline.')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'agreementAcknowledgment', 'Agreement Acknowledgment', 'Text displayed next to the Agreement checkbox', 'I acknowledge that I understand the points above and I have discussed this application with my Head of Department.')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'participantsBlurb', 'Participant Instructions', 'Additional text and information to display in this section of the application', '')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'expensesBlurb', 'Expenses Instructions', 'Additional text and information to display in this section of the application', '')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'participantRoles', 'Participant Roles', 'A comma separated list of available options.', 'Attendee,Presenter,Organiser,Other')";

// Add Notification Events
$gibbonSetting[] = "INSERT INTO `gibbonNotificationEvent` (`event`, `moduleName`, `actionName`, `type`, `scopes`, `active`)
VALUES
('Request Approval', 'Professional Development', 'Manage Applications_full', 'Additional', 'All', 'Y'),
('New Request', 'Professional Development', 'Manage Applications_full', 'Additional', 'All', 'Y');";


// Action rows 
// One array per action

$actionRows[] = [
    'name'                      => 'Manage Applications_my', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Applications', // Optional: subgroups for the right hand side module menu
    'description'               => 'Manage Professional Development Applications', // Text description
    'URLList'                   => 'pd_manage.php',
    'entryURL'                  => 'pd_manage.php', // The landing action for the page.
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
  'name'                      => 'Manage Applications_full',
  'precedence'                => '1',
  'category'                  => 'Applications',
  'description'               => 'Manage Professional Development Applications',
  'URLList'                   => 'pd_manage.php',
  'entryURL'                  => 'pd_manage.php', 
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
  'name'                      => 'New Application_my', 
  'precedence'                => '0',
  'category'                  => 'Applications', 
'description'                 => 'Submit an application for Professional Development',
  'URLList'                   => 'pd_add.php',
  'entryURL'                  => 'pd_add.php',
  'defaultPermissionAdmin'    => 'Y',
  'defaultPermissionTeacher'  => 'Y',
  'defaultPermissionStudent'  => 'N',
  'defaultPermissionParent'   => 'N',
  'defaultPermissionSupport'  => 'N',
  'categoryPermissionStaff'   => 'Y',
  'categoryPermissionStudent' => 'N',
  'categoryPermissionParent'  => 'N',
  'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
  'name'                      => 'New Application_all',
  'precedence'                => '1',
  'category'                  => 'Applications',
  'description'               => 'Submit an application for Professional Development',
  'URLList'                   => 'pd_add.php',
  'entryURL'                  => 'pd_add.php', 
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
  'category'                  => 'Administration',
  'description'               => 'Manage request approvers',
  'URLList'                   => 'pd_manageApprovers.php',
  'entryURL'                  => 'pd_manageApprovers.php', 
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
  'precedence'                => '1',
  'category'                  => 'Administration',
  'description'               => 'Manage request approvers',
  'URLList'                   => 'pd_manageApprovers.php, pd_addApprover.php, pd_editApprover.php, pd_deleteApproverProcess.php',
  'entryURL'                  => 'pd_manageApprovers.php', 
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
  'name'                      => 'Manage PD Settings',
  'precedence'                => '0',
  'category'                  => 'Administration',
  'description'               => 'Manage Request Settings',
  'URLList'                   => 'pd_manageSettings.php',
  'entryURL'                  => 'pd_manageSettings.php', 
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
  'name'                      => "Today's PD",
  'precedence'                => '0',
  'category'                  => 'Reports',
  'description'               => 'Displays PD Requests scheduled for today with the status requested, approved or awaiting final approval',
  'URLList'                   => 'pd_reportToday.php',
  'entryURL'                  => 'pd_reportToday.php', 
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
