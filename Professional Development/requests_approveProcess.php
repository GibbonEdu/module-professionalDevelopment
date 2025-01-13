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

use Gibbon\Services\Format;
use Gibbon\Comms\NotificationEvent;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestLogGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestApproversGateway;

require_once '../../gibbon.php';
require_once "./moduleFunctions.php";

$_POST['address'] = '/modules/Professional Development/requests_manage.php';

$absoluteURL = $session->get('absoluteURL');
$moduleName = $session->get('module');
$URL = $absoluteURL . '/index.php?q=/modules/' . $moduleName;

$gibbonPersonID = $session->get('gibbonPersonID');
$personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

$requestApproversGateway = $container->get(RequestApproversGateway::class);
$approver = $requestApproversGateway->selectApproverByPerson($gibbonPersonID);
$isApprover = !empty($approver);
$finalApprover = $isApprover ? $approver['finalApprover'] : false;

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_manage.php') || !$isApprover) {
    //Acess denied
    $URL .= '/requests_manage.php&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    $professionalDevelopmentRequestID = $_POST['professionalDevelopmentRequestID'] ?? '';

    $requestsGateway = $container->get(RequestsGateway::class);
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);

    if (!empty($pdRequest)) {
        $settingGateway = $container->get(SettingGateway::class);
        $headApproval = $settingGateway->getSettingByScope('Professional Development', 'headApproval');

        $title = $pdRequest['eventTitle'];
        $status = $pdRequest['status'];
        $owner = $pdRequest['gibbonPersonIDCreated'];

        // Approver cannot approve their own trip
        if ($owner == $session->get('gibbonPersonID')) {
            $URL .= '/requests_manage.php&return=error1';
            header("Location: {$URL}");
            exit();
        }

        if (needsApproval($container, $gibbonPersonID, $professionalDevelopmentRequestID)) {
            $URL .= '/requests_approve.php&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID;

            $requestStatus = $_POST['requestStatus'] ?? '';
            $comment = $_POST['comment'] ?? '';

            if (empty($requestStatus) || (empty($comment) && $requestStatus == 'Comment')) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit();
            }

            $notificationGateway = $container->get(NotificationGateway::class);
            $notificationSender = new NotificationSender($notificationGateway, $session);

            $notificationURL = '/index.php?q=/modules/' . $moduleName . '/requests_view.php&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID;
            $commentText = !empty($comment) ? '<br/><br/><b>'.__('Comment').':</b><br/>'.$comment : '';

            $requestLogGateway = $container->get(RequestLogGateway::class);

           
            if ($requestStatus == 'Approval') {
                if($status == 'Awaiting Final Approval') {
                    $requestStatus .= ' - Final';

                    if (!$requestsGateway->update($professionalDevelopmentRequestID, ['status' => 'Approved'])) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    if ($owner != $gibbonPersonID) {
                        $notificationSender->addNotification($owner, __('Your trip request has been fully approved by {person}.', ['person' => $personName]).$commentText, $moduleName, $notificationURL);
                    }

                } else {
                    $done = false;
                    $requestApprovalType = $settingGateway->getSettingByScope('Professional Development', 'requestApprovalType');

                    if ($requestApprovalType == 'One Of') {
                        $done = true;
                    } else if ($requestApprovalType == 'Two Of') {
                        $approvalLog = $requestLogGateway->selectBy([
                            'professionalDevelopmentRequestID' => $pdRequest['professionalDevelopmentRequestID'],
                            'requestStatus' => 'Approval - Partial'
                        ]);

                        $done = $approvalLog->rowCount() >= 1;

                    } else if ($requestApprovalType == 'Chain Of All') {
                        $nextApprover = $requestApproversGateway->selectNextApprover($professionalDevelopmentRequestID, $gibbonPersonID);
                        $done = $nextApprover->rowCount() == 0;
                    }

                    if ($done) {
                        if($headApproval) {
                            $status = 'Awaiting Final Approval';
                            $requestStatus .= ' - Partial';
                        } else {
                            $requestStatus .= ' - Final';
                            $status = 'Approved';
                        }

                        if (!$requestsGateway->update($professionalDevelopmentRequestID, ['status' => $status])) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        if ($status == 'Approved') {
                            //Custom notifications for final approval
                            $event = new NotificationEvent('Professional Development', 'Request Approval');

                            $notificationText = __('A Professional Development request has been approved by {person}: {request}', ['person' => $personName, 'request' => $pdRequest['eventTitle']]);

                            $event->setNotificationText($notificationText);
                            $event->setActionLink($notificationURL);

                            $event->sendNotifications($pdo, $session);

                            $message = __('Your request has been fully approved by {person}.', ['person' => $personName]).$commentText;
                        } else {
                            $message = __('Your trip request has been partially approved by {person} and is awaiting final approval.', ['person' => $personName]).$commentText;
                        }

                        if ($owner != $gibbonPersonID) {
                            $notificationSender->addNotification($owner, $message, $moduleName, $notificationURL);
                        }
                    } else if (!empty($nextApprover) && $nextApprover->isNotEmpty()) {
                        $requestStatus .= ' - Partial';
                        $nextApprover = $nextApprover->fetch();

                        $notificationSender->addNotification($nextApprover['gibbonPersonID'], __('A Professional Development request is awaiting your approval.'), $moduleName, $absoluteURL . '/index.php?q=/modules/' . $moduleName . '/requests_approve.php&professionalDevelopmentRequestID='. $professionalDevelopmentRequestID);

                        if ($owner != $gibbonPersonID) {
                            $notificationSender->addNotification($owner, __('Your PD request has been partially approved by {person} and is awaiting final approval.', ['person' => $personName]).$commentText, $moduleName, $notificationURL);
                        }
                    } else {
                        $requestStatus .= ' - Partial';

                        if ($owner != $gibbonPersonID) {
                            $notificationSender->addNotification($owner, __('Your PD request has been partially approved by {person} and is awaiting final approval.', ['person' => $personName]).$commentText, $moduleName, $notificationURL);
                        }
                    }
                }
            } else if ($requestStatus == 'Rejection') {
                if (!$requestsGateway->update($professionalDevelopmentRequestID, ['status' => 'Rejected'])) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($owner != $gibbonPersonID) {
                    $notificationSender->addNotification($owner, __('Your trip request has been rejected by {person}. Please see their comments for more details.', ['person' => $personName]).$commentText, $moduleName, $notificationURL);
                }
            } else if ($requestStatus == 'Comment') {
                requestCommentNotifications($professionalDevelopmentRequestID, $gibbonPersonID, $personName, $requestLogGateway, $pdRequest, $comment, $notificationSender);
            } else {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit();
            }

            $professionalDevelopmentRequestLogID = $requestLogGateway->insert([
                'professionalDevelopmentRequestID' => $professionalDevelopmentRequestID,
                'gibbonPersonID' => $gibbonPersonID,
                'requestStatus' => $requestStatus,
                'comment' => $comment
            ]);
           
            if (!$professionalDevelopmentRequestLogID) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            //Send notifications
            $notificationSender->sendNotifications();

            $approval = 'Approval';
            if (substr($requestStatus, 0, strlen($approval)) == $approval) {
                $URL = $absoluteURL . '/index.php?q=/modules/' . $moduleName . '/requests_manage.php';
            }

            $URL .= '&return=success0';
            header("Location: {$URL}");
            exit();
        } else {
            $URL .= '/requests_manage.php&return=error1';
            header("Location: {$URL}");
            exit();
        }
    } else {
        $URL .= '/requests_manage.php&return=error1';
        header("Location: {$URL}");
        exit();
    }
}
