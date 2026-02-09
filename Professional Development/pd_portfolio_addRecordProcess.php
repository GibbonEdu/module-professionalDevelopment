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

use Gibbon\Data\Validator;
use Gibbon\Services\Format;
use Gibbon\Comms\NotificationEvent;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\PortfolioGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\PortfolioTagGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['resourcesLinks' => 'URL']);
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module');

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_add.php')) {
    $URL .= '/pd_portfolio_addRecord.php&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed
    $requestsGateway = $container->get(RequestsGateway::class);
    $portfolioGateway = $container->get(PortfolioGateway::class);
    $portfolioTagGateway = $container->get(PortfolioTagGateway::class);

    $professionalDevelopmentRequestID = $_POST['professionalDevelopmentRequestID'] ?? '';
    $URL .= '/pd_portfolio_addRecord.php&professionalDevelopmentRequestID='.$professionalDevelopmentRequestID;
    $partialFail = false;

    $portfolioData = [
        'gibbonSchoolYearID'                => $session->get('gibbonSchoolYearID') ?? '',
        'gibbonPersonID'                    => $_POST['gibbonPersonID'] ?? $session->get('gibbonPersonID'),
        'status'                            => $_POST['status'] ?? '',
        'role'                              => $_POST['role'] ?? '',
        'type'                              => $_POST['type'] ?? '',
        'title'                             => $_POST['title'] ?? '',
        'completionDate'                    => $_POST['completionDate'] ?? '',
        'timeSpent'                         => $_POST['timeSpent'] ?? '',
        'keyTakeaways'                      => $_POST['keyTakeaways'] ?? '',
        'gibbonPersonIDCreated'             => $session->get('gibbonPersonID') ?? '',
    ];

    foreach ($portfolioData as $data) {
        if (empty($data)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }
    }

    if (!empty($professionalDevelopmentRequestID)) {
        $portfolioData['professionalDevelopmentRequestID'] = $professionalDevelopmentRequestID;
    }
    
    $portfolioData['resourcesLinks'] = $_POST['resourcesLinks'] ?? '';
    $portfolioData['keyFocus'] = $_POST['keyFocus'] ?? '';

    // Ensure Key Focus tags are uppercase and trimmed
    if (!empty($portfolioData['keyFocus'])) {
        $portfolioData['keyFocus'] = implode(',', array_filter(array_map(function ($item) {
            return trim(ucwords($item));
        }, explode(',', $portfolioData['keyFocus']))));
    }

    // Create the record for portfolio
    $professionalDevelopmentPortfolioID = $portfolioGateway->insert($portfolioData);

    if (empty($professionalDevelopmentPortfolioID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Create the key focus tags
    $tags = array_unique(array_filter(array_merge(explode(',', $portfolioData['keyFocus'] ?? ''))));
    foreach ($tags as $tag) {
        $portfolioTagGateway->insertAndUpdate(['tag' => $tag], ['tag' => $tag]);
    }

    // Send Notification Event when a new record is submitted
    $notificationGateway = $container->get(NotificationGateway::class); 
    $notificationList = !empty($_POST['notificationList'])? explode(',', $_POST['notificationList']) : [];
    $personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

    $notificationSender = new NotificationSender($notificationGateway, $session);
    $event = new NotificationEvent('Professional Development', 'New Portfolio Record');

    $event->setNotificationText(__('{person} has submitted a new record: {request} for their PD Portfolio', ['person' => $personName, 'request' => $portfolioData['title']]));
    $event->setActionLink('/index.php?q=/modules/Professional Development/pd_portfolio_editRecord.php&professionalDevelopmentPortfolioID=' . $professionalDevelopmentPortfolioID);

    // Send notification
    $event->pushNotifications($notificationGateway, $notificationSender);

   // Send notification for the selected people to notify
    foreach ($notificationList as $recipient) {
        $notificationSender->addNotification($recipient, __('{person} has submitted a new record: {request} for their PD Portfolio', ['person' => $personName, 'request' => $portfolioData['title']]), 'Professional Development', '/index.php?q=/modules/Professional Development/pd_portfolio_editRecord.php&professionalDevelopmentPortfolioID='.$professionalDevelopmentPortfolioID);
        $notificationSender->sendNotifications();
    }

    // Send a notification for the user who created the record
    $notificationSender->addNotification($portfolioData['gibbonPersonID'], __('You have submitted a new record: {request} for your PD Portfolio', ['request' => $portfolioData['title']]), 'Professional Development', '/index.php?q=/modules/Professional Development/pd_portfolio_editRecord.php&professionalDevelopmentPortfolioID='.$professionalDevelopmentPortfolioID);
    $notificationSender->sendNotifications();

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0&editID=$professionalDevelopmentPortfolioID";

    header("Location: {$URL}");
}