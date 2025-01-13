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

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs
        ->add(__('Request Archive'), 'requests_archive.php')
        ->add(__('View Archived Request'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_archiveView.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $professionalDevelopmentRequestID = $_GET['professionalDevelopmentRequestID'] ?? '';

    $requestsGateway = $container->get(RequestsGateway::class);

    if (empty($professionalDevelopmentRequestID) || !$requestsGateway->exists($professionalDevelopmentRequestID)) {
        $page->addError('No request selected.');
        return;
    }

    renderRequest($container, $professionalDevelopmentRequestID, false, true, false);
}