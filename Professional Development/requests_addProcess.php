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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Data\Validator;
use Gibbon\Services\Format;
use Gibbon\Comms\NotificationEvent;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestLogGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestCostGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestDaysGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestPersonGateway;

require_once '../../gibbon.php';
require_once  './moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML']);

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/Professional Development/requests_add.php';

//Checking if editing mode should be enabled
$edit = false;

$mode = $_REQUEST['mode'] ?? '';
$saveMode = $_REQUEST['saveMode'] ?? 'Submit';
$professionalDevelopmentRequestID = $_REQUEST['professionalDevelopmentRequestID'] ?? '';

$requestsGateway = $container->get(RequestsGateway::class);

//Check if a mode and id are given
if (!empty($mode) && !empty($professionalDevelopmentRequestID)) {
    
    //Get the request from gateway
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);    

    //If the request exists, set to edit mode
    if (!empty($pdRequest)) {
        $edit = true;
    }
}

$isDraft = !empty($pdRequest) && $pdRequest['status'] == 'Draft';

$gibbonPersonID = $session->get('gibbonPersonID');
$personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

$highestAction = getHighestGroupedAction($guid, '/modules/Professional Development/requests_manage.php', $connection2);

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_add.php') || ($edit && $highestAction != 'Manage Requests_full' && $pdRequest['gibbonPersonIDCreated'] != $gibbonPersonID)) {
    $URL .= '/requests_manage.php&return=error0';
    header("Location: {$URL}");
    exit;
} else if ((isset($pdRequest) && empty($pdRequest)) || (!empty($mode) && !$edit)) {
    $URL .= '/requests_add.php&return=error1&reason=a';
    header("Location: {$URL}");
    exit;
} else {
    $URL .= '/requests_add.php&professionalDevelopmentRequestID='.$professionalDevelopmentRequestID.'&mode='.$mode;

    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    $settingGateway = $container->get(SettingGateway::class);

    $partialFail = false;
    $returnCode = '';

    //Load Request Data
    //Format: Key => Required Flag
    $requestData = [
        'eventType'             => true,
        'eventFocus'            => true,
        'attendeeRole'          => true,
        'attendeeCount'         => true,
        'eventTitle'            => true,
        'eventDescription'      => true,
        'eventLocation'         => true,
        'personalRational'      => true,
        'departmentImpact'      => true,
        'schoolSharing'         => true,
        'supportingEvidence'    => false,
        'notes'                 => false,
    ];

    foreach ($requestData as $key => $required) {
        if (!empty($_POST[$key])) {
            $requestData[$key] = $_POST[$key];
        } else if ($required) {
            $partialFail = true;
            $returnCode = 'warning3';
        }
    }

    if ($mode != 'edit') {
        $requestData['gibbonPersonIDCreated'] = $gibbonPersonID;
        $requestData['gibbonSchoolYearID'] = $gibbonSchoolYearID;
        $requestData['timestampCreated'] = date('Y-m-d H:i:s');
    }

    if ($saveMode == 'Draft' && (empty($pdRequest) || $isDraft)) {
        $requestData['status'] = 'Draft';
    } else if ($saveMode != 'Draft' && $isDraft) {
        $requestData['status'] = 'Requested';
    }

    //Load Trip People
    $tripPeople = [];

    $teachers = $_POST['teachers'] ?? [];
    foreach ($teachers as $person) {
        $tripPeople[] = ['gibbonPersonID' => $person];
    }

    if (empty($tripPeople)) {
        $partialFail = true;
        $returnCode = 'warning6';
    }

    //Load Trip Days
    $tripDays = [];

    $dateFormat = 'd/m/Y';

    $dateTimeOrder = $_POST['dateTimeOrder'] ?? [];
    foreach ($dateTimeOrder as $order) {
        $day = $_POST['dateTime'][$order];

        $startDate = Format::createDateTime($day['startDate'], $dateFormat);
        $endDate = Format::createDateTime($day['endDate'], $dateFormat);

        if (!$startDate || !$endDate) {
            $partialFail = true;
            $returnCode = 'warning7';
            continue;
        } 

        $day['startDate'] = $startDate->format('Y-m-d');
        $day['endDate'] = $endDate->format('Y-m-d');

        $tripDays[] = $day;
    }

    //If no days have been added, throw an error.
    if (empty($tripDays)) {
        $partialFail = true;
        $returnCode = 'warning4';
    }

    //Load Trip Costs
    $tripCosts = [];

    $costOrder = $_POST['costOrder'] ?? [];
    foreach ($costOrder as $order) {
        $cost = $_POST['cost'][$order];

        if (empty($cost['title']) || empty($cost['cost']) || $cost['cost'] < 0) {
            $partialFail = true;
            $returnCode = 'warning5';
        }

        $tripCosts[] = $cost;
    }

    //Begin Transaction
    $requestsGateway->beginTransaction();

    //Insert Request Data

    if ($edit) {
        if (!$requestsGateway->update($professionalDevelopmentRequestID, $requestData)) {
            $professionalDevelopmentRequestID = null;
        }
    } else {
        $professionalDevelopmentRequestID = $requestsGateway->insert($requestData);
    }

    //If no PD Request, rollback and return error
    if (empty($professionalDevelopmentRequestID)) {
        $requestsGateway->rollBack();
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
    
    //Insert new Request Days data and remove old data (if exists).
    $requestDaysGateway = $container->get(RequestDaysGateway::class);
    $requestDaysGateway->deleteWhere(['professionalDevelopmentRequestID' => $professionalDevelopmentRequestID]);
    $requestDaysGateway->bulkInsert($professionalDevelopmentRequestID, $tripDays);

    //Insert new Request Cost data and remove old data (if exists).
    $requestCostGateway = $container->get(RequestCostGateway::class);
    $requestCostGateway->deleteWhere(['professionalDevelopmentRequestID' => $professionalDevelopmentRequestID]);
    $requestCostGateway->bulkInsert($professionalDevelopmentRequestID, $tripCosts);

     //Insert new Request Person data and remove old data (if exists).
     $requestPersonGateway = $container->get(RequestPersonGateway::class);
     $requestPersonGateway->deleteWhere(['professionalDevelopmentRequestID' => $professionalDevelopmentRequestID]);
     $requestPersonGateway->bulkInsert($professionalDevelopmentRequestID, $tripPeople);

     if ($saveMode != 'Draft') {
        $requestLogGateway = $container->get(RequestLogGateway::class);
        $requestLogGateway->insert([
            'professionalDevelopmentRequestID' => $professionalDevelopmentRequestID,
            'gibbonPersonID'       => $gibbonPersonID,
            'comment'              => $_POST['changeSummary'] ?? '',
            'action'               => $edit && !$isDraft ? 'Edit' : 'Request'
        ]);
    }

    $requestsGateway->commit();

    //TO-DO Add sending Notificarion features when a request is submitted

    if ($partialFail) {
        $URL .= '&return='.$returnCode.'&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID . ($edit ? '&mode=edit' : '');
        header("Location: {$URL}");
        exit;
    }

    $URL .= '&return=success0&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID . ($edit ? '&mode=edit' : '');
    header("Location: {$URL}");
    exit;
}


