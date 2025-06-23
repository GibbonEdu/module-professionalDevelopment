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
use Gibbon\Domain\User\UserGateway;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\PortfolioGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestDaysGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\PortfolioTagGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestPersonGateway;

require_once __DIR__ . '/moduleFunctions.php';

$professionalDevelopmentRequestID = $_GET['professionalDevelopmentRequestID'] ?? '';
$gibbonPersonID = $session->get('gibbonPersonID') ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_add.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
    return;
} else {
    // Proceed
    $page->breadcrumbs->add(__m('Add New Record'));

    $highestAddAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_add.php', $connection2);
    $highestManageAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_manage.php', $connection2);
    $moduleName = $session->get('module');

    $settingGateway = $container->get(SettingGateway::class);
    $requestsGateway = $container->get(RequestsGateway::class);
    $requestPersonGateway = $container->get(RequestPersonGateway::class);
    $requestDaysGateway = $container->get(RequestDaysGateway::class);
    $portfolioTagGateway = $container->get(PortfolioTagGateway::class);
    $departmentGateway = $container->get(DepartmentGateway::class);
    $userGateway = $container->get(UserGateway::class);

    if (!empty($professionalDevelopmentRequestID)) {
        // Get PD request data
        $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);

        $isApproved = !empty($pdRequest) && $pdRequest['status'] == 'Approved';

        if ((empty($pdRequest)) && !$isApproved) {
            $page->addError(__('Invalid PD Request or PD Request has not been approved.'));
            return;
        }

        // Get role
        $requestPersonCriteria = $requestPersonGateway->newQueryCriteria()
        ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
        $tripPeople = $requestPersonGateway->queryRequestPeople($requestPersonCriteria);

        foreach ($tripPeople as $person) {  
            if ($person['gibbonPersonID'] == $gibbonPersonID) {
                $role = $person['role'];
                break;
            }
        }

        // Get the final date of the trip
        $daysCriteria = $requestDaysGateway->newQueryCriteria()
            ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->sortBy(['date'], 'DESC');

        $days = $requestDaysGateway->queryRequestDays($daysCriteria)->toArray();
        $lastDay = $days[0]['date'] ?? '';
    }

    $status = empty($professionalDevelopmentRequestID) ? 'Pending' : 'Approved';

    // Form to submit Record
    $form = Form::create('recordForm', $session->get('absoluteURL').'/modules/'.$moduleName.'/pd_portfolio_addRecordProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
    $form->addHiddenValue('gibbonPersonID', $gibbonPersonID);
    $form->addHiddenValue('status', $status);

    $form->setTitle(__('Portfolio Record'));

    $row = $form->addRow();
        $row->addHeading('Record Details', __('Record Details'));

    $pdTypes = $settingGateway->getSettingByScope('Professional Development', 'pdTypes');
    $row = $form->addRow();
        $row->addLabel('type', __('PD Type'));
        $row->addSelect('type')->fromString($pdTypes)->required()->placeholder();

    $row = $form->addRow();
        $row->addLabel('title', __('PD Name'));
        $row->addTextField('title')
            ->setValue($pdRequest['eventTitle'] ?? '')
            ->required();

    $participantRoles = $settingGateway->getSettingByScope('Professional Development', 'participantRoles');
    $row = $form->addRow();
        $row->addLabel('role', __('PD Role'));
        $row->addSelect('role')->fromString($participantRoles)->selected($role ?? '')->required()->placeholder();
        
    $row = $form->addRow();
            $row->addLabel('completionDate', __('Date of Completion'))->description(__('Last date of the activity'));
            $row->addDate('completionDate')->setValue($lastDay ?? '')->required()->placeholder(__('Date'))->setClass('w-auto');

    $row = $form->addRow();
        $row->addLabel('timeSpent', __('Time spent (Hours)'));
        $row->addNumber('timeSpent')->decimalPlaces(2)->minimum(0)->maximum(999)->maxLength(3)->required();

    $tags = $portfolioTagGateway->selectAllKeyFocusTags()->fetchAll(\PDO::FETCH_COLUMN);
    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('keyFocus', __('Key Focus'));
        $col->addFinder('keyFocus')
            ->fromArray($tags)
            ->setParameter('hintText', __('Type a tag...'))
            ->setParameter('allowFreeTagging', true);

    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('keyTakeaways', __m('Key Takeaways'))->description(__('What are your key takeaways from this activity?'));
        $col->addTextArea('keyTakeaways')->setRows(4)->required();

    $row = $form->addRow();
        $row->addLabel('resourcesLinks', __('Resource Link'))
            ->description(__('Share the resource/website link.'));
        $row->addURL('resourcesLinks');

    // NOTIFICATIONS
    $row = $form->addRow();
        $row->addHeading('Notifications', __('Notifications'));

    // Array containing HOD
    $departmentIDs = array_column($departmentGateway->selectDepartmentsByPerson($gibbonPersonID)->fetchAll(), 'gibbonDepartmentID');

    $departmentHeads = [];
    foreach ($departmentIDs as $departmentID) {
        $coordinators = $container->get(PortfolioGateway::class)->selectCoordinatorByDepartmentID($departmentID)->fetchAll();
        $departmentHeads = array_merge($departmentHeads, $coordinators);
    }
    $departmentHeads = array_column($departmentHeads, 'gibbonPersonID');

    $notified = $userGateway->selectNotificationDetailsByPerson($departmentHeads)->fetchGroupedUnique();

    $notified = array_map(function ($token) use ($session) {
        $absoluteURL = $session->get('absoluteURL');
        return [
            'id'       => $token['gibbonPersonID'],
            'name'     => Format::name('', $token['preferredName'], $token['surname'], 'Staff', false, true),
            'jobTitle' => !empty($token['jobTitle']) ? $token['jobTitle'] : $token['type'],
            'image'    => $absoluteURL.'/'.$token['image_240'],
        ];
    }, $notified);

    $row = $form->addRow();
        $row->addLabel('notificationList', __('Notify Additional People'))->description(__('The following people will be notified about this record. You can edit and select who to send out this notification'));
        $row->addFinder('notificationList')
            ->fromAjax($session->get('absoluteURL').'/modules/Staff/staff_searchAjax.php')
            ->selected($notified ?? '')
            ->setParameter('resultsLimit', 10)
            ->resultsFormatter('function(item){ return "<li class=\'\'><div class=\'inline-block bg-cover w-12 h-12 ml-2 rounded-full bg-gray-200 border border-gray-400 bg-no-repeat\' style=\'background-image: url(" + item.image + ");\'></div><div class=\'inline-block px-4 truncate\'>" + item.name + "<br/><span class=\'inline-block opacity-75 truncate text-xxs\'>" + item.jobTitle + "</span></div></li>"; }');
    
    $row = $form->addRow('stickySubmit');
    $col = $row->addColumn()->addClass('items-center');
    $col->addSubmit();

    echo $form->getOutput();
}
?>
