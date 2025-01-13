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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Tables\View\GridView;
use Psr\Container\ContainerInterface;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Module\ProfessionalDevelopment\Data\SettingFactory;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestLogGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestCostGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestDaysGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestPersonGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestApproversGateway;

function getStatuses() {
    return [
        'Requested',
        'Approved',
        'Rejected',
        'Cancelled',
        'Awaiting Final Approval',
    ];
}

function hasAccess(ContainerInterface $container, $professionalDevelopmentRequestID, $gibbonPersonID, $highestAction) {

    //Has full access?
    if ($highestAction == 'Manage Requests_full') {
        return true;
    }

    //Has read-only access?
    if ($highestAction == 'Manage Requests_view') {
        return true;
    }

    //Is Owner?
    $requestsGateway = $container->get(RequestsGateway::class);
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);

    if (!empty($pdRequest) && $pdRequest['gibbonPersonIDCreated'] == $gibbonPersonID) {
        return true;
    }

    //Is Involved?
    $requestPersonGateway = $container->get(RequestPersonGateway::class);

    if ($requestPersonGateway->isInvolved($professionalDevelopmentRequestID, $gibbonPersonID)) {
        return true;
    }

    //Is Approver (and needs their approval)?
    if (needsApproval($container, $gibbonPersonID, $professionalDevelopmentRequestID)) {
        return true;
    }

    //Is HOD?
    $departmentGateway = $container->get(DepartmentGateway::class);
    $headOfDepartments = array_column($departmentGateway->selectDepartmentsByPerson($gibbonPersonID, 'Coordinator')->fetchAll(), 'gibbonDepartmentID');
    $tripOwnerDepartments = array_column($departmentGateway->selectDepartmentsByPerson($pdRequest['gibbonPersonIDCreated'])->fetchAll(), 'gibbonDepartmentID');

    return !empty(array_intersect($headOfDepartments, $tripOwnerDepartments));
}

function needsApproval(ContainerInterface $container, $gibbonPersonID, $professionalDevelopmentRequestID) {

    $requestsGateway = $container->get(RequestsGateway::class);
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);

    if (empty($pdRequest)) {
        return false;
    }

    $requestApproversGateway = $container->get(RequestApproversGateway::class);

    $approver = $requestApproversGateway->selectApproverByPerson($gibbonPersonID);

    $isApprover = !empty($approver);
    $finalApprover = $isApprover ? $approver['finalApprover'] : false;

    if ($pdRequest['status'] == 'Requested' && $isApprover) {
        $settingGateway = $container->get(SettingGateway::class);
        $requestApprovalType = $settingGateway->getSettingByScope('Professional Development', 'requestApprovalType');

        if ($requestApprovalType == 'Two Of') {
            //Check if the user has already approved
            $requestLogGateway = $container->get(RequestLogGateway::class);
            $approval = $requestLogGateway->selectBy([
                'professionalDevelopmentRequestID' => $pdRequest['professionalDevelopmentRequestID'],
                'gibbonPersonID' => $gibbonPersonID,
                'requestStatus' => 'Approval - Partial'
            ]);

            if ($approval->isNotEmpty()) {
                return false;
            }
        } else if ($requestApprovalType == 'Chain Of All') {
            //Check if user is in line to approve
            $nextApprover = $requestApproversGateway->selectNextApprover($pdRequest['professionalDevelopmentRequestID']);
            if ($nextApprover->isNotEmpty()) {
                $nextApprover = $nextApprover->fetch();
                if ($gibbonPersonID != $nextApprover['gibbonPersonID']) {
                    return false;
                }
            } else {
                return false;
            }
        }
    } else if ($pdRequest['status'] != 'Awaiting Final Approval' || !$finalApprover) {
        return false;
    }

    return true;
}

function formatExpandableSection($title, $content) {
    $output = '';

    $output .= '<h6>' . $title . '</h6></br>';
    $output .= nl2brr($content);

    return $output;
}

function requestCommentNotifications($professionalDevelopmentRequestID, $gibbonPersonID, $personName, $requestLogGateway, $request, $comment, $notificationSender) {
    $text = __('{person} has commented on a PD request: {request}', ['person' => $personName, 'request' => $request['eventTitle']]).'<br/><br/><b>'.__('Comment').':</b><br/>'.$comment;
    $notificationURL = '/index.php?q=/modules/Professional Development/requests_view.php&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID;

    $people = $requestLogGateway->selectLoggedPeople($professionalDevelopmentRequestID);
    while ($row = $people->fetch()) {
        //Skip current user
        if ($row['gibbonPersonID'] == $gibbonPersonID) continue;
        $notificationSender->addNotification($row['gibbonPersonID'], $text, 'Professional Development', $notificationURL);
    }
}

//Get the PD request details from the DB and put into the form
function renderRequest(ContainerInterface $container, $professionalDevelopmentRequestID, $approveMode, $readOnly = false, $showLogs = true) {
    global $gibbon;

    $gibbonPersonID = $gibbon->session->get('gibbonPersonID');
    $moduleName = $gibbon->session->get('module');

    $requestsGateway = $container->get(RequestsGateway::class);
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);

    $link = $gibbon->session->get('absoluteURL') . '/modules/' . $moduleName . '/requests_' . ($approveMode ? "approve" : "view") . 'Process.php';
    $form = Form::create('requestForm', $link);
    $form->addHiddenValue('address', $gibbon->session->get('address'));
    $form->addHiddenValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);

    if ($gibbonPersonID == $pdRequest['gibbonPersonIDCreated']) {
        //Edit
        $form->addHeaderAction('edit', __('Edit'))
            ->setURL('/modules/' . $moduleName . '/requests_add.php')
            ->addParam('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->addParam('mode', 'edit')
            ->displayLabel();
    }

    if ($approveMode) {
        //View
        $form->addHeaderAction('view', __('View'))
            ->setURL('/modules/' . $moduleName . '/requests_view.php')
            ->addParam('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->displayLabel();
    } else if (needsApproval($container, $gibbonPersonID, $professionalDevelopmentRequestID)) {
        //Approve
        $form->addHeaderAction('approve', __('Approve'))
            ->setIcon('iconTick')
            ->setURL('/modules/' . $moduleName . '/requests_approve.php')
            ->addParam('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->displayLabel();
    }

    $on = './themes/'.$gibbon->session->get("gibbonThemeName").'/img/minus.png';
    $off = './themes/'.$gibbon->session->get("gibbonThemeName").'/img/plus.png';

    function toggleSection(&$row, $section, $icon) {
        $row->addWebLink(sprintf('<img title=%1$s src="%2$s" style="margin-right:4px;" />', __('Show/Hide'), $icon))
            ->setURL('#')
            ->onClick('toggleSection($(this), "'.$section.'"); return false;');
    }

    $row = $form->addRow();
        $row->addHeading(__('Basic Information'));
        toggleSection($row, 'basicInfo', $on);

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('eventTypeLabel', Format::bold(__('Event Type')));
        $row->addTextfield('eventType')
            ->readonly();

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('eventFocusLabel', Format::bold(__('Area of Focus')));
            $row->addTextfield('eventFocus')
                ->readonly();

    $row = $form->addRow()->addClass('basicInfo');
                $row->addLabel('attendeeRoleLabel', Format::bold(__('Participant(s) Role')));
                    $row->addTextfield('attendeeRole')
                        ->readonly();

    $row = $form->addRow()->addClass('basicInfo');
                $row->addLabel('attendeeCountLabel', Format::bold(__('No. of Particpants')));
                $row->addTextfield('attendeeCount')
                        ->readonly();

    $coverAmount = unserialize($pdRequest['coverAmount']);
    $row = $form->addRow()->addClass('basicInfo');
                $row->addLabel('coverAmountLabel', Format::bold(__('Cover Amount')));
                    $row->addCheckbox('coverAmount')
                            ->fromArray($coverAmount)
                            ->readonly();
                            
                            
    $row = $form->addRow()->addClass('basicInfo');
                $row->addLabel('eventTitleLabel', Format::bold(__('Event Name')));
                $row->addTextfield('eventTitle')
                    ->readonly();

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('eventLocationLabel', Format::bold(__('Location')));
        $row->addTextfield('eventLocation')
            ->readonly();

    $row = $form->addRow()->addClass('basicInfo');
        $col = $row->addColumn();
            $col->addLabel('eventDescriptionLabel', Format::bold(__('Event Description')));
            $col->addContent($pdRequest['eventDescription']);

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('statusLabel', Format::bold(__('Status')));
        $row->addTextfield('status')
            ->readOnly();

    $row = $form->addRow();
        $row->addHeading(__('Further Information'));
        toggleSection($row, 'furtherInfo', $on);

    $row = $form->addRow()->addClass('furtherInfo');
        $col = $row->addColumn();
            $col->addLabel('personalRationalLabel', Format::bold(__('PERSONAL RATIONAL')));
            $col->addContent($pdRequest['personalRational']);

    $row = $form->addRow()->addClass('furtherInfo');
        $col = $row->addColumn();
            $col->addLabel('departmentImpactLabel', Format::bold(__('DEPARTMENTAL AND SCHOOL IMPACT')));
            $col->addContent($pdRequest['departmentImpact']);

    $row = $form->addRow()->addClass('furtherInfo');
        $col = $row->addColumn();
            $col->addLabel('schoolSharingLabel', Format::bold(__('SCHOOL SHARING')));
            $col->addContent($pdRequest['schoolSharing']);

    $row = $form->addRow()->addClass('furtherInfo');
        $row->addLabel('supportingEvidenceLabel', __m('Supporting Evidence (If applicable)'))->description(__m('Please upload any supporting evidence that you think might be useful in assessing your application'));
        $row->addFileUpload('supportingEvidence')
            ->setAttachment('supportingEvidence', $gibbon->session->get('absoluteURL'), $pdRequest['supportingEvidence']);

    $row = $form->addRow()->addClass('furtherInfo');
        $col = $row->addColumn();
            $col->addLabel('notesLabel', Format::bold(__('Comments/Notes')));
            $col->addContent($pdRequest['notes']);

    $row = $form->addRow();
        $row->addHeading(__('Date'));
        toggleSection($row, 'dateTime', $on);

    $row = $form->addRow()->addClass('dateTime');

        $requestDaysGateway = $container->get(RequestDaysGateway::class);
        $dayCriteria = $requestDaysGateway->newQueryCriteria()
            ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);

        $table = DataTable::create('dateTime');

        $table->addColumn('startDate', __('Start Date'))
            ->format(Format::using('date', ['startDate']));
        $table->addColumn('endDate', __('End Date'))
            ->format(Format::using('date', ['endDate']));

        $row->addContent($table->render($requestDaysGateway->queryRequestDays($dayCriteria)));

    $row = $form->addRow();
        $row->addHeading(__('Participants'));
        toggleSection($row, 'participants', $on);

    $row = $form->addRow()->addClass('participants');
        $col = $row->addColumn();
            $col->addLabel('teacherLabel', Format::bold(__('Teachers/Staff')));

            $requestPersonGateway = $container->get(RequestPersonGateway::class);
            $peopleCriteria = $requestPersonGateway->newQueryCriteria()
                ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
                ->sortBy(['surname', 'preferredName'])
                ->pageSize(0);

            $gridRenderer = new GridView($container->get('twig'));
            $table = $container->get(DataTable::class)->setRenderer($gridRenderer);

            $table->addMetaData('gridClass', 'rounded-sm bg-blue-100 border py-2');
            $table->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/5 my-2 text-center');

            $table->addColumn('image_240')
                ->format(Format::using('userPhoto', ['image_240', 'sm', '']));

            $table->addColumn('name')
                ->setClass('text-xs font-bold mt-1')
                ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, false]));

            $col->addContent($table->render($requestPersonGateway->queryRequestPeople($peopleCriteria)));

    $row = $form->addRow();
        $row->addHeading(__('Cost Breakdown'));
        toggleSection($row, 'costBreakdown', $on);

    $row = $form->addRow()->addClass('costBreakdown');

        $requestCostGateway = $container->get(RequestCostGateway::class);
        $costCriteria = $requestCostGateway->newQueryCriteria()
            ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
        $requestCosts = $requestCostGateway->queryRequestCost($costCriteria);

        $totalCost = array_sum($requestCosts->getColumn('cost'));

        $table = DataTable::create('costBreakdown');

        $table->addColumn('title', __('Cost Name'));

        $table->addColumn('description', __('Cost Description'));

        $table->addColumn('cost', __('Cost'))
            ->format(Format::using('currency', ['cost']));

        $row->addContent($table->render($requestCosts));

    $row = $form->addRow()->addClass('costBreakdown');
        $row->addLabel('totalCostLabel', Format::bold(__('Total Cost')));
        $row->addTextfield('totalCost')
            ->setValue(Format::currency($totalCost))
            ->readOnly();

    if ($showLogs) {

        $row = $form->addRow();
            $row->addHeading(__('Log'));
            toggleSection($row, 'logs', $on);

        $row = $form->addRow()->addClass('logs');

        $requestLogGateway = $container->get(RequestLogGateway::class);
        $logCiteria = $requestLogGateway->newQueryCriteria()
            ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->sortBy(['timestamp']);

        $table = DataTable::create('logs');

        $table->addExpandableColumn('contents')
            ->format(function ($log) {
                $output = '';

                if (!empty($log['comment'])) {
                    $output .= formatExpandableSection(__('Comment'), $log['comment']);
                }
                return $output;
            });

        $table->addColumn('person', __('Person'))
            ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]));

        $table->addColumn('timestamp', __('Date & Time'))
            ->format(Format::using('dateTime', ['timestamp']));

        $table->addColumn('requestStatus', __('Event'));

        $row->addContent($table->render($requestLogGateway->queryRequestLogs($logCiteria)));
    }

    if ($approveMode) {
        $row = $form->addRow();
            $row->addLabel('requestStatusLabel', __('Update the Request Status'));
            $row->addSelect('requestStatus')
                ->fromArray(['Approval', 'Rejection', 'Comment']);
    }

    if (!$readOnly) {
        $row = $form->addRow();
            $col = $row->addColumn();
                $col->addLabel('commentLabel', __('Comment'));
                $col->addTextarea('comment');

        $row = $form->addRow();
            $row->addSubmit();
    }

    $form->loadAllValuesFrom($pdRequest);
    echo $form->getOutput();

    ?>
    <script type="text/javascript">
        function toggleSection(button, section) {
            var rows = $('.' + section);
            if (rows.hasClass('showHide')) {
                button.find('img').attr('src', '<?php echo $on ?>');
                rows.removeClass('showHide');
                rows.show();
            } else {
                button.find('img').attr('src', '<?php echo $off ?>');
                rows.addClass('showHide');
                rows.hide();
            }
        }
    </script>
    <?php
}

function getSettings(ContainerInterface $container, $guid) {
 
    $requestsGateway = $container->get(RequestsGateway::class);

    $requestApprovalOptions = ['One Of', 'Two Of', 'Chain Of All'];

    $settingFactory = new SettingFactory();

    $settingFactory->addSetting('requestApprovalType')
        ->setRenderer(function ($data, $row) use ($requestApprovalOptions) {
            $row->addSelect($data['name'])
                ->fromArray($requestApprovalOptions)
                ->selected($data['value'])
                ->setRequired(true);
        })
        ->setProcessor(function ($data) use ($requestApprovalOptions) {
            return in_array($data, $requestApprovalOptions) ? $data : false;
        });

    $settingFactory->addSetting('headApproval')
        ->setRenderer(function ($data, $row) {
            $row->addCheckBox($data['name'])
                ->checked(boolval($data['value']));
        })
        ->setProcessor(function ($data) use ($requestsGateway) {
            $enabled = $data !== null;

            if (!$enabled) {

                $success = $requestsGateway->updateWhere(
                    ['status' => 'Awaiting Final Approval'],
                    ['status' => 'Approved']
                );

                if (!$success) {
                    return false;
                }
            }

            return $enabled ? 1 : 0;
        });

    $settingFactory->addSetting('expiredUnapprovedFilter')
        ->setRenderer(function ($data, $row) {
            $row->addCheckBox($data['name'])
                ->checked(boolval($data['value']));
        })
        ->setProcessor(function ($data) {
            return $data === null ? 0 : 1;
        });

    return $settingFactory->getSettings();
}

function getCoverAmountArray() {
    $options = array();

        $options = array(
            'No Cover Required' => __('No Cover Required'),
            'Tutor group' => __('Tutor group'),
            'Duty' => __('Duty'),
            '1-5 Periods (include year 12 & 13 classes)' => __('1-5 Periods (include year 12 & 13 classes)'),
            '6-10 Periods (include year 12 & 13 classes)' => __('6-10 Periods (include year 12 & 13 classes)'),
            '11-15 Periods (include year 12 & 13 classes)' => __('11-15 Periods (include year 12 & 13 classes)'),
            '16 Periods + (include year 12 & 13 classes)' => __('16 Periods + (include year 12 & 13 classes)')
        );

        return $options;
}

?>

