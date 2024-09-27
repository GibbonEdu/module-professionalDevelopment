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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestApproversGateway;

$page->breadcrumbs
    ->add(__('Manage Approvers'), 'requests_manageApprovers.php')
    ->add(__('Add Approver'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_addApprover.php')) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->return->setEditLink($session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module') . '/requests_manageApprovers.php');

    $requestApproversGateway = $container->get(RequestApproversGateway::class);

    $form = Form::create('addApprover', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/requests_addApproverProcess.php', 'post');
    $form->addHiddenValue('address', $session->get('address'));
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setTitle('Add Approver');

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', 'Staff');
        $row->addSelectPerson('gibbonPersonID')
            ->fromArray($requestApproversGateway->selectStaffForApprover())
            ->setRequired(true)
            ->placeholder('Please select...');
            
    $headApproval = $container->get(SettingGateway::class)->getSettingByScope('Professional Development', 'headApproval');

    if($headApproval) {
        $row = $form->addRow();
            $row->addLabel('finalApprover', 'Final Approver');
            $row->addCheckbox('finalApprover');
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    print $form->getOutput();

}
?>	
