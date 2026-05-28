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

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\StaffResourceTagGateway;

$page->breadcrumbs
    ->add(__('Manage Resources'), 'pd_resources_manage.php')
    ->add(__('Add Resource'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_resources_add.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    if (!empty($_GET['editID'])) {
        $editLink = $session->get('absoluteURL').'/index.php?q=/modules/Professional Development/pd_resources_edit.php&professionalDevelopmentResourceID='.$_GET['editID'];
        $page->return->setEditLink($editLink);
    }

    $settingGateway = $container->get(SettingGateway::class);
    $staffResourceTagGateway = $container->get(StaffResourceTagGateway::class);

    $form = Form::create('resourceForm', $session->get('absoluteURL').'/modules/Professional Development/pd_resources_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));

    $form->addRow()->addHeading('Resource Details', __('Resource Details'));

    $row = $form->addRow();
        $row->addLabel('name', __('Name'));
        $row->addTextField('name')->required()->maxLength(255);

    $row = $form->addRow();
        $row->addLabel('description', __('Description'));
        $row->addTextArea('description')->setRows(4);

    $categories = $settingGateway->getSettingByScope('Professional Development', 'resourceCategories');
    $row = $form->addRow();
        $row->addLabel('category', __('Category'));
        $row->addSelect('category')->fromString($categories)->required()->placeholder();

    $purposes = $settingGateway->getSettingByScope('Professional Development', 'resourcePurposes');
    $row = $form->addRow();
        $row->addLabel('purpose', __('Purpose'));
        $row->addSelect('purpose')->fromString($purposes)->required()->placeholder();

    $tags = $staffResourceTagGateway->selectBy([], ['tag'])->fetchAll(\PDO::FETCH_COLUMN);
    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('tags', __('Tags'));
        $col->addFinder('tags')
            ->fromArray($tags)
            ->setParameter('hintText', __('Type a tag...'))
            ->setParameter('allowFreeTagging', true);

    $form->addRow()->addHeading('Resource Link', __('Resource Link'));

    $row = $form->addRow();
        $row->addLabel('content', __('Resource Link'))->description(__('Link to the resource.'));
        $row->addURL('content')->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}