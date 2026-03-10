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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

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
use Gibbon\Contracts\Filesystem\FileHandler;

require_once '../../gibbon.php';
require_once  './moduleFunctions.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module');

// Checking if editing mode should be enabled
$edit = false;

$mode = $_REQUEST['mode'] ?? '';
$saveMode = $_REQUEST['saveMode'] ?? 'Submit';
$professionalDevelopmentRequestID = $_REQUEST['professionalDevelopmentRequestID'] ?? '';
$gibbonPersonID = $session->get('gibbonPersonID') ?? '';
$gibbonSchoolYearID = $session->get('gibbonSchoolYearID') ?? '';

$requestsGateway = $container->get(RequestsGateway::class);
$settingGateway = $container->get(SettingGateway::class);
$requestDaysGateway = $container->get(RequestDaysGateway::class);
$requestPersonGateway = $container->get(RequestPersonGateway::class);
$requestCostGateway = $container->get(RequestCostGateway::class);

// Check if a mode and id are given
if (!empty($mode) && !empty($professionalDevelopmentRequestID)) {
    
    // Get the request from gateway
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);    

    // If the request exists, set to edit mode
    if (!empty($pdRequest)) {
        $edit = true;
    }
}

$isDraft = !empty($pdRequest) && $pdRequest['status'] == 'Draft';
$personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

$highestAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_manage.php', $connection2);

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_add.php') || ($edit && $highestAction != 'Manage Applications_full' && $pdRequest['gibbonPersonIDCreated'] != $gibbonPersonID)) {
    $URL .= '/pd_manage.php&return=error0';
    header("Location: {$URL}");
    exit;
} else if ((isset($pdRequest) && empty($pdRequest)) || (!empty($mode) && !$edit)) {
    $URL .= '/pd_add.php&return=error1&reason=a';
    header("Location: {$URL}");
    exit;
} else {
    $URL .= '/pd_add.php&professionalDevelopmentRequestID='.$professionalDevelopmentRequestID.'&mode='.$mode;

    
    $partialFail = false;
    $returnCode = '';

    $requestData = [
        'eventType'             => true,
        'eventFocus'            => true,
        'eventTitle'            => true,
        'eventDescription'      => true,
        'eventLocation'         => true,
        'personalRational'      => true,
        'departmentImpact'      => true,
        'schoolSharing'         => true,
        'expenseRequest'        => true,
        'supportingEvidence'    => false,
        'notes'                 => false,
    ];   

    foreach ($requestData as $key => $required) {
        $requestData[$key] = $_POST[$key] ?? '';

        if ($key == 'eventFocus' && $requestData[$key] == 'Other') {
            $requestData[$key] = $_POST['eventFocusOther'] ?? '';
        }

        if ($required && empty($requestData[$key])) {
            $partialFail = true;
            $returnCode = 'warning3';
        }
    }

    // Move attached file, if there is one
    $fileMetaData = null;
    if (!empty($_FILES['supportingEvidenceFile']['tmp_name'])) {
        $fileUploader = new Gibbon\FileUploader($pdo, $session);

        $file = $_FILES['supportingEvidenceFile'] ?? null;

        // Upload the file, return the /uploads relative path
        $requestData['supportingEvidence'] = $fileUploader->uploadFromPost($file, $requestData['eventTitle']);

        if (empty($requestData['supportingEvidence'])) {
            $partialFail = true;
        } else {
            $fileMetaData = $fileUploader->getFileMetaData($requestData['supportingEvidence']);
        }
    } elseif (empty($_POST['supportingEvidence'])) {
        $requestData['supportingEvidence'] = '';
    } else {
        unset($requestData['supportingEvidence']);
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

    // Begin Transaction
    $requestsGateway->beginTransaction();

    // Insert Request Data without the date, cost and people
    if ($edit) {
        if (!$requestsGateway->update($professionalDevelopmentRequestID, $requestData)) {
            $professionalDevelopmentRequestID = null;
        }

        // Handle file deletion when user removes logo
        if (empty($requestData['supportingEvidence']) && !empty($pdRequest['supportingEvidence'])) {
            $deleted = $container->get(FileHandler::class)->deleteFile('professionalDevelopmentRequest', $professionalDevelopmentRequestID, 'supportingEvidence');
        }
    } else {
        $professionalDevelopmentRequestID = $requestsGateway->insert($requestData);
    }

    // If no PD Request, rollback and return error
    if (empty($professionalDevelopmentRequestID)) {
        $requestsGateway->rollBack();
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Record file tracking (only if file uploaded)
    if (!empty($fileMetaData) && !empty($professionalDevelopmentRequestID)) {
        $gibbonFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'professionalDevelopmentRequest', $professionalDevelopmentRequestID, 'supportingEvidence');
        
        if (empty($gibbonFileID)) {
            $partialFail = true;
        }
    }

    // Add or edit Request Days
    $dateIDs = [];
    $dateTimeOrder = $_POST['dateTimeOrder'] ?? [];

    if (empty($dateTimeOrder)) {
        $partialFail = true;
        $returnCode = 'warning4';
    }

    foreach ($dateTimeOrder as $order) {
        $day = $_POST['dateTime'][$order];

        if (!$day['date']) {
            $partialFail = true;
            $returnCode = 'warning4';
            continue;
        }

        $data = [
            'professionalDevelopmentRequestID' => $professionalDevelopmentRequestID,
            'date' => $day['date'] ?? '',
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

    // Cleanup dates that have been deleted
    $requestDaysGateway->deleteDatesNotInList($professionalDevelopmentRequestID, $dateIDs);

    // Add or edit Request Cost
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
            'cost'                             => $cost['cost']  ?? '',
            'quantity'                         => $cost['quantity']  ?? '',
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

    // Cleanup cost records that have been deleted
    $requestCostGateway->deleteCostsNotInList($professionalDevelopmentRequestID, $costIDs);

    // Load People in the PD Request
    $personIDs = [];
    $participantOrder = $_POST['participantOrder'] ?? [];

    foreach ($participantOrder as $order) {
        $participant = $_POST['participant'][$order];

        $data = [
            'professionalDevelopmentRequestID' => $professionalDevelopmentRequestID,
            'gibbonPersonID'                   => $participant['gibbonPersonID'] ?? '',
            'role'                             => $participant['role'] ?? '',
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

     // Cleanup participant records that have been deleted
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

    // Send Notifications when a request is submitted
    if ($saveMode != 'Draft' && ($isDraft || !$edit)) {
        $notificationGateway = $container->get(NotificationGateway::class);
        $notificationSender = new NotificationSender($notificationGateway, $session);

        $event = new NotificationEvent('Professional Development', 'New Request');

        $event->setNotificationText(__('{person} has submitted a new PD Request: {request}', ['person' => $personName, 'request' => $requestData['eventTitle']]));
        $event->setActionLink('/index.php?q=/modules/Professional Development/pd_approve.php&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID);

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

        // Send all notifications
        $event->pushNotifications($notificationGateway, $notificationSender);

        // Add a notification for the trip owner
        $notificationSender->addNotification($gibbonPersonID, __('You have submitted a new PD Request (pending approval): {request}', ['request' => $requestData['eventTitle']]), 'Professional Development', '/index.php?q=/modules/Professional Development/pd_view.php&professionalDevelopmentRequestID='.$professionalDevelopmentRequestID);

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
