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
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\StaffResourceGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('View Resources'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_resources_view.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $search   = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $purpose  = $_GET['purpose'] ?? '';

    // FILTER FORM
    $form = Form::create('resourcesView', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Filters'));
    $form->setClass('noIntBorder w-full');
    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/pd_resources_view.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'))->description(__('Name, description or tags.'));
        $row->addTextField('search')->setValue($search);

    $settingGateway = $container->get(SettingGateway::class);

    $categories = $settingGateway->getSettingByScope('Professional Development', 'resourceCategories');
    $row = $form->addRow();
        $row->addLabel('category', __('Category'));
        $row->addSelect('category')->fromString($categories)->placeholder()->selected($category);

    $purposes = $settingGateway->getSettingByScope('Professional Development', 'resourcePurposes');
    $row = $form->addRow();
        $row->addLabel('purpose', __('Purpose'));
        $row->addSelect('purpose')->fromString($purposes)->placeholder()->selected($purpose);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    $staffResourceGateway = $container->get(StaffResourceGateway::class);

    // QUERY
    $criteria = $staffResourceGateway->newQueryCriteria(true)
        ->searchBy($staffResourceGateway->getSearchableColumns(), $search)
        ->filterBy('category', $category)
        ->filterBy('purpose', $purpose)
        ->sortBy('timestampModified', 'DESC')
        ->fromPOST();

    $resources = $staffResourceGateway->queryResources($criteria);

    // TABLE
    $table = DataTable::createPaginated('pdResourcesView', $criteria);
    $table->setTitle(__('Resources'));

    $table->addExpandableColumn('contents')
        ->format(function ($resource) {
            return formatExpandableSection(__('Description'), $resource['description']);
        });

    $table->addColumn('name', __('Name'))
        ->description(__('Contributor'))
        ->format(function ($resource) {
            return '<a href="'.htmlspecialchars($resource['content']).'" target="_blank" style="font-weight: bold" rel="noopener noreferrer">'.htmlspecialchars($resource['name']).'</a>'
                .'<br/>'.Format::small(Format::name($resource['title'], $resource['preferredName'], $resource['surname'], 'Staff', false, true));
        });

    $table->addColumn('category', __('Category'));
    $table->addColumn('purpose', __('Purpose'));

    $table->addColumn('tags', __('Tags'))
        ->format(function ($resource) {
            if (empty($resource['tags'])) return '';
            $tags = explode(',', $resource['tags']);
            natcasesort($tags);
            return implode(', ', array_map('trim', $tags));
        });

    $table->addActionColumn()
        ->format(function ($resource, $actions) {
            $actions->addAction('view', __('Open'))
                ->setExternalURL($resource['content']);
        });

    echo $table->render($resources);
}
