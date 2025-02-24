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
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestApproversGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Manage Approvers'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_manageApprovers.php')) {
    // Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $moduleName = $session->get('module');
    $settingGateway = $container->get(SettingGateway::class);
    $requestApproversGateway = $container->get(RequestApproversGateway::class);

    $highestAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_manageApprovers.php', $connection2);

    $canEdit = $highestAction == 'Manage Approvers_full';

    $criteria = $requestApproversGateway->newQueryCriteria()
        ->sortBy(['sequenceNumber'])
        ->fromPOST();

    $table = DataTable::createPaginated('approvers', $criteria);
    $table->setTitle('Approvers');

    $requestApprovalType = $settingGateway->getSettingByScope($moduleName, 'requestApprovalType');
    $chainOfAll = $requestApprovalType == 'Chain Of All';

    if ($chainOfAll) {
        $description = 'Note, the order shown below is the sequence order of approval.';

        if ($canEdit) {
            $description .= ' You may rearrange the approvers by dragging the rows up or down.';
            $table->addDraggableColumn('professionalDevelopmentRequestApproversID', $session->get('absoluteURL') . '/modules/' . $moduleName . '/pd_manageApproversEditOrderAjax.php');
        }
        $table->setDescription(__($description));
    }

    if ($canEdit) {
        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/' . $session->get('module') . '/pd_addApprover.php')
            ->displayLabel(); 
    } 

    $table->addColumn('name', __('Name'))
        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]))
        ->sortable(!$chainOfAll);

    $headApproval = $settingGateway->getSettingByScope($moduleName, 'headApproval');
    if ($headApproval) {
        $table->addColumn('finalApprover', __('Is a Final Approver?'))
        ->format(function ($approver) {
            return __($approver['finalApprover'] ? 'Yes' : 'No');
        })
        ->sortable(!$chainOfAll);
    }

    if ($canEdit) {
        $table->addActionColumn()
            ->addParam('professionalDevelopmentRequestApproversID')
            ->format(function ($approver, $actions) use ($moduleName) {
                
                $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/' . $moduleName . '/pd_editApprover.php')
                        ->modalWindow();
                
                $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/' . $moduleName . '/pd_deleteApprover.php');
                
                
            });
    }
    echo $table->render($requestApproversGateway->queryApprovers($criteria));
}
