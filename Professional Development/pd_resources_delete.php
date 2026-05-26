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

use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Module\ProfessionalDevelopment\Domain\StaffResourceGateway;

$page->breadcrumbs->add(__('Manage Resources'), 'pd_resources_manage.php');

$professionalDevelopmentResourceID = $_GET['professionalDevelopmentResourceID'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_resources_delete.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    if (empty($professionalDevelopmentResourceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $staffResourceGateway = $container->get(StaffResourceGateway::class);

    if ($highestAction == 'Manage Resources_all') {
        $exists = $staffResourceGateway->exists($professionalDevelopmentResourceID);
    } else {
        $result = $staffResourceGateway->selectBy([
            'professionalDevelopmentResourceID' => $professionalDevelopmentResourceID,
            'gibbonPersonIDCreated'             => $session->get('gibbonPersonID'),
        ]);
        $exists = $result->isNotEmpty();
    }

    if (!$exists) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/Professional Development/pd_resources_deleteProcess.php', true, false);
    $form->addRow()->addConfirmSubmit();

    echo $form->getOutput();
}