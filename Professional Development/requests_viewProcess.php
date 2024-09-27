<?php

use Gibbon\Services\Format;
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

use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestLogGateway;

include '../../gibbon.php';
include './moduleFunctions.php';

$moduleName = $session->get('module');

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $moduleName;

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_manage.php')) {
    // Access denied
    $URL .= '/requests_manage.php&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    $professionalDevelopmentRequestID = $_POST['professionalDevelopmentRequestID'] ?? '';

    $requestsGateway = $container->get(RequestsGateway::class);

    if (empty($professionalDevelopmentRequestID) || !$requestsGateway->exists($professionalDevelopmentRequestID)) {
        $URL .= '/requests_manage.php&return=error1';
        header("Location: {$URL}");
        exit();
    }

    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);
    $gibbonPersonID = $session->get('gibbonPersonID');
    $personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

    $highestAction = getHighestGroupedAction($guid, '/modules/Professional Development/requests_manage.php', $connection2);
    $readOnly = $highestAction == 'Manage Requests_view';

    if (hasAccess($container, $professionalDevelopmentRequestID, $gibbonPersonID, $highestAction) && !$readOnly) {
        $URL .= '/requests_view.php&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID;

        $comment = $_POST['comment'] ?? '';

        if (empty($comment)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit();
        }

        $requestLogGateway = $container->get(RequestLogGateway::class);

        $professionalDevelopmentRequestLogID = $requestLogGateway->insert([
            'professionalDevelopmentRequestID'  => $professionalDevelopmentRequestID,
            'gibbonPersonID'        => $gibbonPersonID,
            'requestStatus'         => 'Comment',
            'comment'               => $comment
        ]);

        if (!$professionalDevelopmentRequestID) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

    //$notificationGateway = $container->get(NotificationGateway::class);
    //$notificationSender = new NotificationSender($notificationGateway, $session);

    //tripCommentNotifications($tripPlannerRequestID, $gibbonPersonID, $personName, $tripLogGateway, $trip, $comment, $notificationSender);

    //$notificationSender->sendNotifications();

        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit();
    
    } else {
        $URL .= '/requests_manage.php&return=error0';
        header("Location: {$URL}");
        exit();
    }
}

?>