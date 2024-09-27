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
use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestApproversGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Manage Professional Development Requests'));


if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_manage.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {

	$highestAction = getHighestGroupedAction($guid, '/modules/Professional Development/requests_manage.php', $connection2);

	if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;   
    }

	$gibbonPersonID = $session->get('gibbonPersonID');

     //Settings
     $settingGateway = $container->get(SettingGateway::class);
    
     $requestApprovalType = $settingGateway->getSettingByScope('Professional Development', 'requestApprovalType');
     $expiredUnapproved = $settingGateway->getSettingByScope('Professional Development', 'expiredUnapprovedFilter');

     //Permissions

    $requestApproversGateway = $container->get(RequestApproversGateway::class);

    $approver = $requestApproversGateway->selectApproverByPerson($gibbonPersonID);
    $isApprover = !empty($approver);
    $finalApprover = $isApprover ? boolval($approver['finalApprover']) : false;

    $checkAwaitingApproval = ($isApprover && $requestApprovalType == 'Chain Of All') || $finalApprover;

     //Department Data
     $departmentGateway = $container->get(DepartmentGateway::class);
     $departmentsList = $departmentGateway->selectDepartmentsByPerson($gibbonPersonID, 'Coordinator');
     
     $departments = array_reduce($departmentsList->fetchAll(), function ($group, $department) {
         $group[$department['gibbonDepartmentID']] = $department['name'];
         return $group;
     }, []);

     //Filters
    $gibbonDepartmentID = $_POST['gibbonDepartmentID'] ?? []; 
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    //Filter Form
    $form = Form::create('requestFilters', $gibbon->session->get('absoluteURL') . '/index.php?q=' . $_GET['q']);
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    if (!empty($departments)) {
        $row = $form->addRow();
            $row->addLabel('gibbonDepartmentID', 'Department');
            $row->addSelect('gibbonDepartmentID')
                ->fromArray($departments)
                ->placeholder()
                ->selected($gibbonDepartmentID);
    }

    $row = $form->addRow();
        $row->addLabel('gibbonSchoolYearID', 'Year');
        $row->addSelectSchoolYear('gibbonSchoolYearID')
            ->selected($gibbonSchoolYearID);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();
        
    print $form->getOutput(); 

    //Professional Development Request Data

    $requestsGateway = $container->get(RequestsGateway::class);
    $criteria = $requestsGateway->newQueryCriteria(true)
        ->sortBy('firstDayOfTrip', 'DESC')
        ->filterBy('showActive', 'Y')
        ->fromPOST();

    $gibbonPersonIDFilter = $highestAction == 'Manage Requests_full' || $highestAction == 'Manage Requests_view'
        ? null
        : $gibbonPersonID;

    $requests = $requestsGateway->queryRequests($criteria, $gibbonSchoolYearID, $gibbonPersonIDFilter, $gibbonDepartmentID, $expiredUnapproved);

    $requests->transform(function (&$request) use ($container, $gibbonPersonID, $checkAwaitingApproval) {
        $request['canApprove'] = 'N';

        if ($checkAwaitingApproval) {
            if (needsApproval($container, $gibbonPersonID, $request['professionalDevelopmentRequestID'])) {
                $request['canApprove'] = 'Y';
            }
        }
    });

    //Requests Table

    $table = DataTable::createPaginated('requests', $criteria);
    $table->setTitle(__('Requests'));

    $table->modifyRows(function (&$request, $row) {
        if ($request['status'] == 'Approved') $row->addClass('success');
        if ($request['status'] == 'Draft') $row->addClass('dull');
        if ($request['status'] == 'Awaiting Final Approval') $row->addClass('message');
        if ($request['status'] == 'Rejected' || $request['status'] == 'Cancelled') $row->addClass('dull');

        return $row;
    });

    $filters = array_reduce(getStatuses(), function($filters, $status) {
        $filters['status:' . $status] = __('Status') . ': ' . __($status);
        return $filters;
    });

    $filters['showActive:Y'] = __m('Upcoming / Approved Trips');
    
    $table->addMetaData('post', ['gibbonSchoolYearID' => $gibbonSchoolYearID]);
    $table->addMetaData('filterOptions', $filters);
    
    $table->addHeaderAction('add', __('Submit Request'))
        ->displayLabel()
        ->setURL('/modules/Professional Development/requests_add.php');
    
    $table->addExpandableColumn('contents')
        ->format(function ($request) {
            return formatExpandableSection(__('Description'), $request['eventDescription']);
        });

    $table->addColumn('eventTitle', __('Title'))
    ->format(function ($request) {
        return $request['eventTitle'].($request['status'] == 'Draft' ? Format::tag(__('Draft'), 'message ml-2') : '');
    });

$table->addColumn('owner', __('Owner'))
    ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]))
    ->sortable('surname');

$table->addColumn('firstDayOfTrip', __('First Day of Trip'))
    ->format(Format::using('dateReadable', ['firstDayOfTrip']));

$table->addColumn('status', __('Status'))->format(function($request) {
    $output = $request['status'];
    $output .= $request['canApprove'] == 'Y' && $request['status'] == 'Requested' 
        ? Format::tag(__m('Awaiting Approval'), 'message ml-2') 
        : '';

    return $output;
});

$table->addActionColumn()
        ->addParam('professionalDevelopmentRequestID')
        ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->format(function ($request, $actions) use ($container, $gibbonPersonID, $highestAction)  {

            if (needsApproval($container, $gibbonPersonID, $request['professionalDevelopmentRequestID'])) {
                $actions->addAction('approve', __('Approve/Reject'))
                    ->setURL('/modules/Professional Development/requests_approve.php')
                    ->setIcon('iconTick');
            }

            $actions->addAction('view', __('View Details'))
                ->setURL('/modules/Professional Development/requests_view.php');

            if (($highestAction == 'Manage Requests_full' || $gibbonPersonID == $request['gibbonPersonIDCreated']) && !in_array($request['status'], ['Cancelled', 'Rejected'])) {
                $actions->addAction('edit', __('Edit'))
                    ->addParam('mode', 'edit')
                    ->setURL('/modules/Professional Development/requests_add.php');
            }
    });

    echo $table->render($requests);

}
