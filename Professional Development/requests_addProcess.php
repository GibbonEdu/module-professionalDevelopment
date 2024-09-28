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
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestApproversGateway;

require_once '../../gibbon.php';
require_once  './moduleFunctions.php';

//$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML']);

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module');

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

    // Move attached file, if there is one
    if (!empty($_FILES['supportingEvidenceFile']['tmp_name'])) {
        $fileUploader = new Gibbon\FileUploader($pdo, $session);

        $file = $_FILES['supportingEvidenceFile'] ?? null;

        // Upload the file, return the /uploads relative path
        $requestData['supportingEvidence'] = $fileUploader->uploadFromPost($file, $requestData['eventTitle']);

        if (empty($requestData['supportingEvidence'])) {
            $partialFail = true;
        }
    } else {
        $requestData['supportingEvidence'] = $_POST['supportingEvidence'] ?? '';
    }

    if ($mode != 'edit') {
        $requestData['gibbonPersonIDCreated'] = $gibbonPersonID;
        $requestData['gibbonSchoolYearID'] = $gibbonSchoolYearID;
    }

    if ($saveMode == 'Draft' && (empty($pdRequest) || $isDraft)) {
        $requestData['status'] = 'Draft';
    } else if ($saveMode != 'Draft' && $isDraft) {
        $requestData['status'] = 'Requested';
    }

    //Begin Transaction
    $requestsGateway->beginTransaction();

    //Insert Request Data without the date, cost and people
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

    //Add or edit Request Days
    $requestDaysGateway = $container->get(RequestDaysGateway::class);

    $dateIDs = [];
    $dateTimeOrder = $_POST['dateTimeOrder'] ?? [];

    foreach ($dateTimeOrder as $order) {
        $day = $_POST['dateTime'][$order];

        if (!$day['startDate'] || !$day['endDate']) {
            $partialFail = true;
            $returnCode = 'warning7';
            continue;
        }

        $data = [
            'professionalDevelopmentRequestID' => $professionalDevelopmentRequestID,
            'startDate' => Format::dateConvert($day['startDate']) ?? '',
            'endDate'   => Format::dateConvert($day['endDate']) ?? '',
        ];

        $professionalDevelopmentRequestDaysID = $day['professionalDevelopmentRequestDaysID'] ?? '';

        if (!empty($professionalDevelopmentRequestDaysID)) {
            $partialFail &= !$requestDaysGateway->update($professionalDevelopmentRequestDaysID, $data);
        } else {
            $professionalDevelopmentRequestDaysID = $requestDaysGateway->insert($data);
            $partialFail &= !$professionalDevelopmentRequestDaysID;
        }

        $dateIDs[] = str_pad($professionalDevelopmentRequestDaysID, 10, '0', STR_PAD_LEFT);
    }

    //Cleanup dates that have been deleted
    $requestDaysGateway->deleteDatesNotInList($professionalDevelopmentRequestID, $dateIDs);

    //Add or edit Request Cost
    $requestCostGateway = $container->get(RequestCostGateway::class);

    $costIDs = [];
    $costOrder = $_POST['costOrder'] ?? [];

    foreach ($costOrder as $order) {
        $cost = $_POST['cost'][$order];
 
        if (empty($cost['title']) || empty($cost['cost']) || $cost['cost'] < 0) {
            $partialFail = true;
            $returnCode = 'warning5';
        }

        $data = [
            'professionalDevelopmentRequestID' => $professionalDevelopmentRequestID,
            'title'                            => $cost['title'] ?? '',
            'description'                      => $cost['description']  ?? '',
            'cost'                             => $cost['cost']  ?? ''
        ];

        $professionalDevelopmentRequestCostID = $cost['professionalDevelopmentRequestCostID'] ?? '';

        if (!empty($professionalDevelopmentRequestCostID)) {
            $partialFail &= !$requestCostGateway->update($professionalDevelopmentRequestCostID, $data);
        } else {
        $professionalDevelopmentRequestCostID = $requestCostGateway->insert($data);
        $partialFail &= !$professionalDevelopmentRequestCostID;
        }

        $costIDs[] = str_pad($professionalDevelopmentRequestCostID, 10, '0', STR_PAD_LEFT);
    }

    //Cleanup cost records that have been deleted
    $requestCostGateway->deleteCostsNotInList($professionalDevelopmentRequestID, $costIDs);

    //Load Trip People
    $requestPersonGateway = $container->get(RequestPersonGateway::class);

    $personIDs = [];
    $participantOrder = $_POST['participantOrder'] ?? [];

    foreach ($participantOrder as $order) {
        $participant = $_POST['participant'][$order];

        $data = [
            'professionalDevelopmentRequestID' => $professionalDevelopmentRequestID,
            'gibbonPersonID' => $participant['gibbonPersonID'] ?? ''
        ];

        $professionalDevelopmentRequestPersonID = $participant['professionalDevelopmentRequestPersonID'] ?? '';

        if (!empty($professionalDevelopmentRequestPersonID)) {
            $partialFail &= !$requestPersonGateway->update($professionalDevelopmentRequestPersonID, $data);
        } else {
        $professionalDevelopmentRequestPersonID = $requestPersonGateway->insert($data);
        $partialFail &= !$professionalDevelopmentRequestCostID;
        }

        $personIDs[] = str_pad($professionalDevelopmentRequestPersonID, 10, '0', STR_PAD_LEFT);
    }

     //Cleanup participant records that have been deleted
     $requestPersonGateway->deleteParticipantsNotInList($professionalDevelopmentRequestID, $personIDs);

    if ($saveMode != 'Draft') {
        $requestLogGateway = $container->get(RequestLogGateway::class);
        $requestLogGateway->insert([
            'professionalDevelopmentRequestID' => $professionalDevelopmentRequestID,
            'gibbonPersonID'       => $gibbonPersonID,
            'comment'              => $_POST['changeSummary'] ?? '',
            'requestStatus'               => $edit && !$isDraft ? 'Edit' : 'Request'
        ]);
    }

    $requestsGateway->commit();

    //Send Notifications when a request is submitted
    if ($saveMode != 'Draft' && ($isDraft || !$edit)) {
        $notificationGateway = $container->get(NotificationGateway::class);
        $notificationSender = new NotificationSender($notificationGateway, $session);

        $event = new NotificationEvent('Professional Development', 'New Request');

        $event->setNotificationText(__('{person} has submitted a new PD Request: {request}', ['person' => $personName, 'request' => $requestData['eventTitle']]));
        $event->setActionLink('/index.php?q=/modules/Professional Development/requests_approve.php&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID);

        $requestApprovalType = $settingGateway->getSettingByScope('Professional Development', 'requestApprovalType');
        $requestApproversGateway = $container->get(RequestApproversGateway::class);

        if ($requestApprovalType == 'Chain Of All') {
            $firstApprover = $requestApproversGateway->selectNextApprover($professionalDevelopmentRequestID);
            if ($firstApprover->isNotEmpty()) {
                $event->addRecipient($firstApprover->fetch()['gibbonPersonID']);
            }
        } else {
            $approverCriteria = $requestApproversGateway->newQueryCriteria();
            $approvers = $requestApproversGateway->queryApprovers($approverCriteria);
            foreach ($approvers as $approver) {
                $event->addRecipient($approver['gibbonPersonID']);
            }
        }

        //Send all notifications
        $event->pushNotifications($notificationGateway, $notificationSender);

        // Add a notification for the trip owner
        $notificationSender->addNotification($gibbonPersonID, __('You have submitted a new PD Request (pending approval): {request}', ['request' => $requestData['eventTitle']]), 'Professional Development', '/index.php?q=/modules/Professional Development/requests_view.php&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID);

        $notificationSender->sendNotifications();
    }

    if ($partialFail) {
        $URL .= '&return='.$returnCode.'&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID . ($edit ? '&mode=edit' : '');
        header("Location: {$URL}");
        exit;
    }

    $URL .= '&return=success0&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID . ($edit ? '&mode=edit' : '');
    header("Location: {$URL}");
    exit;
}
