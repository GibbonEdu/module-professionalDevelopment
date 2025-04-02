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