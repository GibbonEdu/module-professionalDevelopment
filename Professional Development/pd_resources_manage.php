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
use Gibbon\Module\ProfessionalDevelopment\Domain\StaffResourceGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Manage Resources'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_resources_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    $search = $_GET['search'] ?? '';

    // SEARCH FORM
    $form = Form::create('resourcesManage', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Search'));
    $form->setClass('noIntBorder w-full');
    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/pd_resources_manage.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search For'))->description(__('Resource name, description or tags.'));
        $row->addTextField('search')->setValue($search);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Search'));

    echo $form->getOutput();

    $staffResourceGateway = $container->get(StaffResourceGateway::class);

    // QUERY
    $criteria = $staffResourceGateway->newQueryCriteria(true)
        ->searchBy($staffResourceGateway->getSearchableColumns(), $search)
        ->sortBy('timestampModified', 'DESC')
        ->fromPOST();

    $gibbonPersonID = ($highestAction == 'Manage Resources_all') ? null : $session->get('gibbonPersonID');
    $resources = $staffResourceGateway->queryResources($criteria, $gibbonPersonID);

    // DATA TABLE
    $table = DataTable::createPaginated('pdResources', $criteria);
    $table->setTitle($highestAction == 'Manage Resources_all' ? __('All Resources') : __('My Resources'));

    $table->addHeaderAction('add', __('Add Resource'))
        ->setURL('/modules/Professional Development/pd_resources_add.php')
        ->displayLabel();

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
        ->addParam('professionalDevelopmentResourceID')
        ->format(function ($resource, $actions) use ($gibbonPersonID) {
            if ($gibbonPersonID === null || $resource['gibbonPersonIDCreated'] == $gibbonPersonID) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/Professional Development/pd_resources_edit.php');
            }

            $actions->addAction('view', __('Open'))
                ->setExternalURL($resource['content']);

            if ($gibbonPersonID === null || $resource['gibbonPersonIDCreated'] == $gibbonPersonID) {
                $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/Professional Development/pd_resources_delete.php');
            }
        });

    echo $table->render($resources);
}