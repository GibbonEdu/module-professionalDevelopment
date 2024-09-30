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
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Request Archive'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_archive.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $gibbonPersonID = $session->get('gibbonPersonID');

    //Settings
    $settingGateway = $container->get(SettingGateway::class);
    
    $expiredUnapproved = $settingGateway->getSettingByScope('Professional Development', 'expiredUnapprovedFilter');

    // Select school year
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    //Requests Data
    $requestsGateway = $container->get(RequestsGateway::class);
    $criteria = $requestsGateway->newQueryCriteria(true)
        ->sortBy('firstDayOfTrip', 'DESC')
        ->filterBy('showActive', 'Y')
        ->filterBy('status', 'Approved')
        ->fromPOST();

    $requests = $requestsGateway->queryRequests($criteria, $gibbonSchoolYearID, null, null, $expiredUnapproved);

    //Requests Table
    $table = DataTable::createPaginated('requests', $criteria);
    $table->setTitle(__('Past Requests'));

    $table->modifyRows(function (&$requests, $row) {
        if ($requests['status'] == 'Approved') $row->addClass('success');
        if ($requests['status'] == 'Draft') $row->addClass('dull');
        if ($requests['status'] == 'Awaiting Final Approval') $row->addClass('message');
        if ($requests['status'] == 'Rejected' || $requests['status'] == 'Cancelled') $row->addClass('dull');

        return $row;
    });

    $table->addMetaData('post', ['gibbonSchoolYearID' => $gibbonSchoolYearID]);
    
    $table->addExpandableColumn('contents')
        ->format(function ($requests) {
            return formatExpandableSection(__('Description'), $requests['eventDescription']);
        });

    $table->addColumn('eventTitle', __('Event Title'))
        ->format(function ($requests) {
            return $requests['eventTitle'];
        });

    $table->addColumn('owner', __('Owner'))
        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]))
        ->sortable('surname');
    
    $table->addColumn('firstDayOfTrip', __('First Day of Trip'))
        ->format(Format::using('dateReadable', ['firstDayOfTrip']));

    $table->addActionColumn()
        ->addParam('professionalDevelopmentRequestID')
        ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->format(function ($requests, $actions) {
            if ($requests['status'] != 'Approved') return;

            $actions->addAction('view', __('View Details'))
             ->setURL('/modules/Professional Development/requests_archiveView.php');
    });
    
    echo $table->render($requests);

}