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

use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestLogGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs
        ->add(__('Manage Professional Development Requests'), 'requests_manage.php')
        ->add(__('Approve Request'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_manage.php')) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $gibbonPersonID = $session->get('gibbonPersonID');

    $requestsGateway = $container->get(RequestsGateway::class);

    $professionalDevelopmentRequestID = $_GET['professionalDevelopmentRequestID'] ?? '';
    $request = $requestsGateway->getByID($professionalDevelopmentRequestID);

    if (empty($professionalDevelopmentRequestID) || empty($request)) {
        $page->addError(__('Invalid Request Selected.'));
    } else if ($request['gibbonPersonIDCreated'] == $session->get('gibbonPersonID')) {
        $page->addError(__('A request cannot be approved by the same person who created it.'));
    } else {

        $approval = $container->get(RequestLogGateway::class)->selectBy([
            'professionalDevelopmentRequestID' => $request['professionalDevelopmentRequestID'],
            'gibbonPersonID' => $gibbonPersonID,
            'requestStatus' => 'Approval - Partial'
        ]);

        if (needsApproval($container, $gibbonPersonID, $professionalDevelopmentRequestID)) {
            renderRequest($container, $professionalDevelopmentRequestID, true);
        } else if ($approval->isNotEmpty()) {
            $page->addMessage(__('You have already approved this trip, it is currently pending additional approval from other users.'));
            renderRequest($container, $professionalDevelopmentRequestID, false);
        } else if ($request['status'] == 'Rejected'){
            $page->addMessage(__('This trip has been rejected. No further edits or approval can be made to it.'));
        } elseif ($request['status'] != 'Approved'){
            $page->addError(__('You do not have access to this action.'));
        }
    }
}