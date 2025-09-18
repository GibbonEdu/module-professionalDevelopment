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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

$sql = [];
$count = 0;

// v0.0.01
$count++;
$sql[$count][0] = "0.0.01";
$sql[$count][1] = "";

// v0.0.02
$count++;
$sql[$count][0] = "0.0.02";
$sql[$count][1] = "
ALTER TABLE `professionalDevelopmentRequests` CHANGE `coverAmount` `expenseRequest` VARCHAR(60) NOT NULL;end
ALTER TABLE `professionalDevelopmentRequestPerson` ADD `gibbonFinanceExpenseID` INT(14) UNSIGNED ZEROFILL NULL AFTER `role`;end
INSERT INTO `gibbonNotificationEvent` (`event`, `moduleName`, `actionName`, `type`, `scopes`, `active`) VALUES ('Expense Request Notifications', 'Professional Development', 'New Application_my', 'Additional', 'All', 'Y');end
";

// v0.0.03
$count++;
$sql[$count][0] = "0.0.03";
$sql[$count][1] = "";

// v0.0.04
$count++;
$sql[$count][0] = "0.0.04";
$sql[$count][1] = "
CREATE TABLE `professionalDevelopmentPortfolio` (`professionalDevelopmentPortfolioID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT, `professionalDevelopmentRequestID` INT(10) UNSIGNED ZEROFILL DEFAULT NULL,`gibbonSchoolYearID` VARCHAR(3) NOT NULL, `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL, `status` VARCHAR(60) NOT NULL, `role` VARCHAR(60) NOT NULL, `type` VARCHAR(60) NOT NULL, `title` VARCHAR(60) NOT NULL, `completionDate` DATE NOT NULL, `timeSpent` DECIMAL(3,2) NOT NULL, `keyFocus` VARCHAR(100) NOT NULL, `resourcesLinks` VARCHAR(100) DEFAULT NULL, `keyTakeaways` VARCHAR(100) NOT NULL, `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`professionalDevelopmentPortfolioID`)) ENGINE = InnoDB CHARSET=utf8 COLLATE=utf8_general_ci;end
CREATE TABLE `professionalDevelopmentPortfolioTag` (`professionalDevelopmentPortfolioTagID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT, `tag` varchar(60) NOT NULL, PRIMARY KEY (`professionalDevelopmentPortfolioTagID`), UNIQUE KEY `tag` (`tag`)) ENGINE=InnoDB CHARSET=utf8 COLLATE=utf8_general_ci;end
";

// v0.0.05
$count++;
$sql[$count][0] = "0.0.05";
$sql[$count][1] = "
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `helpURL`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES((SELECT gibbonModuleID FROM gibbonModule WHERE name='Professional Development'), 'New Portfolio Record_my', 0, 'Portfolio', 'Allows users to add records to their portfolio.', NULL, 'pd_portfolio_addRecord.php', 'pd_portfolio_addRecord.php', 'Y', 'Y', 'Y', 'Y', 'N', 'N', 'Y', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `helpURL`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES((SELECT gibbonModuleID FROM gibbonModule WHERE name='Professional Development'), 'New Portfolio Record_all', 1, 'Portfolio', 'Allows users to add records to their portfolio.', NULL, 'pd_portfolio_addRecord.php', 'pd_portfolio_addRecord.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
";

// v0.0.06
$count++;
$sql[$count][0] = "0.0.06";
$sql[$count][1] = "
INSERT INTO `gibbonNotificationEvent` (`event`, `moduleName`, `actionName`, `type`, `scopes`, `active`) VALUES ('New Portfolio Record', 'Professional Development', 'New Portfolio Record_all', 'Additional', 'All', 'Y');end
";

// v0.0.07
$count++;
$sql[$count][0] = "0.0.07";
$sql[$count][1] = "
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `helpURL`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES((SELECT gibbonModuleID FROM gibbonModule WHERE name='Professional Development'), 'Manage Portfolio_my', 0, 'Portfolio', 'Allows users to manage their portfolio records.', NULL, 'pd_portfolio_manage.php', 'pd_portfolio_manage.php', 'Y', 'Y', 'Y', 'Y', 'N', 'N', 'Y', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `helpURL`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES((SELECT gibbonModuleID FROM gibbonModule WHERE name='Professional Development'), 'Manage Portfolio_full', 1, 'Portfolio', 'Allows users to manage all portfolio records.', NULL, 'pd_portfolio_manage.php', 'pd_portfolio_manage.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Professional Development' AND gibbonAction.name='New Portfolio Record_all'));end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Professional Development' AND gibbonAction.name='Manage Portfolio_full'));end
";

// v0.0.08
$count++;
$sql[$count][0] = "0.0.08";
$sql[$count][1] = "";

// v0.0.09
$count++;
$sql[$count][0] = "0.0.09";
$sql[$count][1] = "
ALTER TABLE `professionalDevelopmentPortfolio` CHANGE `timeSpent` `timeSpent` DECIMAL(7,5) NOT NULL;end
INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Professional Development', 'pdTypes', 'PD Types', 'A comma separated list of available types for PD.', 'Conference,Training');end
";

// v0.0.10
$count++;
$sql[$count][0] = "0.0.10";
$sql[$count][1] = "";

// v0.0.11
$count++;
$sql[$count][0] = "0.0.11";
$sql[$count][1] = "
UPDATE `professionalDevelopmentPortfolio` SET `professionalDevelopmentRequestID` = NULL WHERE `professionalDevelopmentRequestID` = 0000000000;end
";

// v0.0.12
$count++;
$sql[$count][0] = "0.0.12";
$sql[$count][1] = "
ALTER TABLE `professionalDevelopmentPortfolio` CHANGE `keyTakeaways` `keyTakeaways` TEXT NOT NULL;end
";

// v0.1.00
$count++;
$sql[$count][0] = "0.1.00";
$sql[$count][1] = "";

// v0.1.01
$count++;
$sql[$count][0] = "0.1.01";
$sql[$count][1] = "";

// v0.1.02
$count++;
$sql[$count][0] = "0.1.02";
$sql[$count][1] = "
ALTER TABLE `professionalDevelopmentPortfolio` CHANGE `resourcesLinks` `resourcesLinks` VARCHAR(255) DEFAULT NULL;end
";