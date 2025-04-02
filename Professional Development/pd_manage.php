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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestApproversGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Manage Applications'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_manage.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
	$highestAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_manage.php', $connection2);

	if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;   
    }

    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);
	
    $gibbonPersonID = $session->get('gibbonPersonID');
    $gibbonDepartmentID = $_POST['gibbonDepartmentID'] ?? []; 
    $search = $_POST['search'] ?? ''; 

    // Settings
    $settingGateway = $container->get(SettingGateway::class);

    $requestApprovalType = $settingGateway->getSettingByScope('Professional Development', 'requestApprovalType');
    $headApproval = $settingGateway->getSettingByScope('Professional Development', 'headApproval');
    $expiredUnapproved = $settingGateway->getSettingByScope('Professional Development', 'expiredUnapprovedFilter');

    // Permissions
    $requestApproversGateway = $container->get(RequestApproversGateway::class);

    $approver = $requestApproversGateway->selectApproverByPerson($gibbonPersonID);
    $isApprover = !empty($approver);
    $finalApprover = $isApprover ? boolval($approver['finalApprover']) : false;

    $checkAwaitingApproval = ($isApprover && $requestApprovalType == 'Chain Of All') || ($headApproval && $finalApprover);

    // SEARCH
    if ($highestAction == 'Manage Applications_full') {
        // Department Data
        $departmentGateway = $container->get(DepartmentGateway::class);
        $departmentsList = $departmentGateway->selectDepartmentsByPerson($gibbonPersonID, 'Coordinator');
        
        $departments = array_reduce($departmentsList->fetchAll(), function ($group, $department) {
            $group[$department['gibbonDepartmentID']] = $department['name'];
            return $group;
        }, []);

        // Filter Form
        $form = Form::create('requestFilters', $gibbon->session->get('absoluteURL') . '/index.php?q=' . $_GET['q']);
        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->setTitle(__('Filter'));
        $form->setClass('noIntBorder fullWidth');

        $row = $form->addRow();
            $row->addLabel('search', 'Search');
            $row->addTextField('search')->setValue($search);

        if (!empty($departments)) {
            $row = $form->addRow();
                $row->addLabel('gibbonDepartmentID', 'Department');
                $row->addSelect('gibbonDepartmentID')
                    ->fromArray($departments)
                    ->placeholder()
                    ->selected($gibbonDepartmentID);
        }

        $row = $form->addRow();
            $row->addSearchSubmit($session);
            
        echo $form->getOutput(); 
    }

    // Professional Development Request Data
    $requestsGateway = $container->get(RequestsGateway::class);
    $criteria = $requestsGateway->newQueryCriteria(true)
        ->searchBy($requestsGateway->getSearchableColumns(), $search)
        ->sortBy('firstDayOfTrip', 'DESC')
        ->fromPOST();

    $gibbonPersonIDFilter = $highestAction == 'Manage Applications_full' ? null : $gibbonPersonID;
    $requests = $requestsGateway->queryRequests($criteria, $gibbonSchoolYearID, $gibbonPersonIDFilter, $gibbonDepartmentID, $expiredUnapproved);

    // Get all the participants of a PD request
    $requestIDs = $requests->getColumn('professionalDevelopmentRequestID');
    $participants = $requestsGateway->selectParticipantsByRequest($requestIDs)->fetchGrouped();
    $requests->joinColumn('professionalDevelopmentRequestID', 'participants', $participants);

    $requests->transform(function (&$request) use ($container, $gibbonPersonID, $checkAwaitingApproval) {
        $request['canApprove'] = 'N';

        if ($checkAwaitingApproval) {
            if (needsApproval($container, $gibbonPersonID, $request['professionalDevelopmentRequestID'])) {
                $request['canApprove'] = 'Y';
            }
        }
    });

    // Requests Table
    $table = DataTable::createPaginated('requests', $criteria);
    $table->setTitle($highestAction == 'Manage Applications_full' ? __('All Applications') : __('My Applications'));

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
    
    $table->addMetaData('post', ['gibbonSchoolYearID' => $gibbonSchoolYearID]);
    $table->addMetaData('filterOptions', $filters);
    
    $table->addHeaderAction('add', __('New Application'))
        ->displayLabel()
        ->setURL('/modules/Professional Development/pd_add.php');
    
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

$table->addColumn('firstDayOfTrip', __('First Day'))
    ->format(Format::using('dateReadable', ['firstDayOfTrip']));

$table->addColumn('status', __('Status'))->format(function($request) {
    $output = $request['status'];
    $output .= $request['canApprove'] == 'Y' && $request['status'] == 'Requested' 
        ? Format::tag(__m('Awaiting Approval'), 'message ml-2') 
        : '';

    return $output;
});

$table->addColumn('expenseSubmission', __('Expenses'))
    ->format(function ($request) {
        $status = __('N/A');
        $tag = 'dull';
        
        if ($request['expenseRequest'] == 'Individual') {
            $status = __('Submitted');
            $tag = 'message';

            foreach ($request['participants'] as $participant) {
                if (empty($participant['gibbonFinanceExpenseID'])) {
                    $status = __('Pending');
                    $tag = 'warning';
                    break;
                }
            }
        } else if ($request['expenseRequest'] == 'Group Leader') {
            $status = __('Pending');
            $tag = 'warning';

            $groupLeaderSubmitted = array_filter($request['participants'], function ($participant) use ($request) {
                return $participant['gibbonPersonID'] == $request['gibbonPersonIDCreated'] && !empty($participant['gibbonFinanceExpenseID']);
            });

            if (!empty($groupLeaderSubmitted)) {
                $status = __('Submitted');
                $tag = 'message';
            }
        }

        return Format::tag($status, $tag);
    });

$table->addActionColumn()
        ->addParam('professionalDevelopmentRequestID')
        ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->format(function ($request, $actions) use ($container, $gibbonPersonID, $highestAction)  {

            if (needsApproval($container, $gibbonPersonID, $request['professionalDevelopmentRequestID'])) {
                $actions->addAction('approve', __('Approve/Reject'))
                    ->setURL('/modules/Professional Development/pd_approve.php')
                    ->setIcon('iconTick');
            }

            $actions->addAction('view', __('View Details'))
                ->setURL('/modules/Professional Development/pd_view.php');

            $newPDExpenseParams = ['professionalDevelopmentRequestID' => $request['professionalDevelopmentRequestID'], 'title' => 'Professional Development - '. $request['eventTitle'], 'eventDescription' => $request['eventDescription'], 'expenseRequest' => $request['expenseRequest']];

            // Check if the user is a participant
            $isParticipant = array_search($gibbonPersonID, array_column($request['participants'], 'gibbonPersonID')) !== false;

            // Check if the user has already submitted an expense request
            $hasSubmitted = array_filter($request['participants'], function ($participant) use ($gibbonPersonID) {
                return $participant['gibbonPersonID'] == $gibbonPersonID && !empty($participant['gibbonFinanceExpenseID']);
            });

            if ($request['status'] == 'Approved' && $isParticipant) {
                if ($request['expenseRequest'] == 'Group Leader' && $gibbonPersonID == $request['gibbonPersonIDCreated']) {
                    if (empty($hasSubmitted)) {
                        $actions->addAction('add', __('Add Expense Request'))
                            ->setIcon('payment')
                            ->addParams($newPDExpenseParams)
                            ->setURL('/modules/Professional Development/pd_addExpenseRequest.php')
                            ->displayLabel();
                    } else {
                        $actions->addAction('viewExpense', __('View Submitted Expense Request'))
                            ->setIcon('check')
                            ->setURL('/modules/Finance/expenseRequest_manage.php')
                            ->displayLabel();
                    }
                } else if ($request['expenseRequest'] == 'Individual') {
                    if (empty($hasSubmitted)) {
                        $actions->addAction('add', __('Add Expense Request'))
                            ->setIcon('payment')
                            ->addParams($newPDExpenseParams)
                            ->setURL('/modules/Professional Development/pd_addExpenseRequest.php')
                            ->displayLabel();
                    } else {
                        $actions->addAction('viewExpense', __('View Submitted Expense Request'))
                            ->setIcon('check')
                            ->setURL('/modules/Finance/expenseRequest_manage.php')
                            ->displayLabel();
                    }
                }
            }
 
            if (($highestAction == 'Manage Applications_full' || $gibbonPersonID == $request['gibbonPersonIDCreated']) && !in_array($request['status'], ['Cancelled', 'Rejected'])) {
                $actions->addAction('edit', __('Edit'))
                    ->addParam('mode', 'edit')
                    ->setURL('/modules/Professional Development/pd_add.php');
            }
    });

    echo $table->render($requests);
}
