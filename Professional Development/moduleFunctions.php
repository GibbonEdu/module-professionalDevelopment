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
use Gibbon\Domain\User\UserGateway;
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

function getStatuses()
{
    return [
        'Requested',
        'Approved',
        'Rejected',
        'Cancelled',
        'Awaiting Final Approval',
    ];
}

function hasAccess(ContainerInterface $container, $professionalDevelopmentRequestID, $gibbonPersonID, $highestAction)
{

    // Check if user has full access
    if ($highestAction == 'Manage Applications_full') {
        return true;
    }
    // Check if user has read-only access?
    if ($highestAction == 'Manage Applications_my') {
        return true;
    }
    // Check if user is the creator of the form
    $requestsGateway = $container->get(RequestsGateway::class);
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);

    if (!empty($pdRequest) && $pdRequest['gibbonPersonIDCreated'] == $gibbonPersonID) {
        return true;
    }

    // Check if the user is participating in the PD event
    $requestPersonGateway = $container->get(RequestPersonGateway::class);
    if ($requestPersonGateway->isInvolved($professionalDevelopmentRequestID, $gibbonPersonID)) {
        return true;
    }
    // Check if the user is an Approver
    if (needsApproval($container, $gibbonPersonID, $professionalDevelopmentRequestID)) {
        return true;
    }

    // Check if the user is HOD
    $departmentGateway = $container->get(DepartmentGateway::class);
    $headOfDepartments = array_column($departmentGateway->selectDepartmentsByPerson($gibbonPersonID, 'Coordinator')->fetchAll(), 'gibbonDepartmentID');
    $tripOwnerDepartments = array_column($departmentGateway->selectDepartmentsByPerson($pdRequest['gibbonPersonIDCreated'])->fetchAll(), 'gibbonDepartmentID');

    return !empty(array_intersect($headOfDepartments, $tripOwnerDepartments));
}

function needsApproval(ContainerInterface $container, $gibbonPersonID, $professionalDevelopmentRequestID)
{
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
            // Check if the user has already approved the request
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
            // Check if user is the next in line to approve the request
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

function formatExpandableSection($title, $content)
{
    $output = '';

    $output .= '<h6>' . $title . '</h6></br>';
    $output .= nl2br($content);

    return $output;
}

function requestCommentNotifications($professionalDevelopmentRequestID, $gibbonPersonID, $personName, $requestLogGateway, $request, $comment, $notificationSender)
{
    $text = __('{person} has commented on a PD request: {request}', ['person' => $personName, 'request' => $request['eventTitle']]) . '<br/><br/><b>' . __('Comment') . ':</b><br/>' . $comment;
    $notificationURL = '/index.php?q=/modules/Professional Development/pd_view.php&professionalDevelopmentRequestID=' . $professionalDevelopmentRequestID;

    $people = $requestLogGateway->selectLoggedPeople($professionalDevelopmentRequestID);
    while ($row = $people->fetch()) {
        // Skip current user
        if ($row['gibbonPersonID'] == $gibbonPersonID) continue;
        $notificationSender->addNotification($row['gibbonPersonID'], $text, 'Professional Development', $notificationURL);
    }
}

// Get the PD request details from the DB and put it into the form
function renderRequest(ContainerInterface $container, $professionalDevelopmentRequestID, $approveMode, $readOnly = false, $showLogs = true)
{
    global $session;
    $gibbonPersonID = $session->get('gibbonPersonID') ?? "";
    $moduleName = $session->get('module') ?? "";
    $requestsGateway = $container->get(RequestsGateway::class);
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);
    $applicant = $container->get(UserGateway::class)->getByID($pdRequest['gibbonPersonIDCreated'], ['preferredName', 'surname']);
    $link = $session->get('absoluteURL') . '/modules/' . $moduleName . '/pd_' . ($approveMode ? "approve" : "view") . 'Process.php';
    $form = Form::create('requestForm', $link);
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);

    if ($gibbonPersonID == $pdRequest['gibbonPersonIDCreated']) {
        // Edit Page
        $form->addHeaderAction('edit', __('Edit'))
            ->setURL('/modules/' . $moduleName . '/pd_add.php')
            ->addParam('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->addParam('mode', 'edit')
            ->displayLabel();
    }

    if ($approveMode) {
        // View Page
        $form->addHeaderAction('view', __('View'))
            ->setURL('/modules/' . $moduleName . '/pd_view.php')
            ->addParam('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->displayLabel();
    } else if (needsApproval($container, $gibbonPersonID, $professionalDevelopmentRequestID)) {
        // Approve Page
        $form->addHeaderAction('approve', __('Approve'))
            ->setIcon('iconTick')
            ->setURL('/modules/' . $moduleName . '/pd_approve.php')
            ->addParam('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->displayLabel();
    }

    $on = './themes/' . $session->get("gibbonThemeName") . '/img/minus.png';
    $off = './themes/' . $session->get("gibbonThemeName") . '/img/plus.png';

    function toggleSection(&$row, $section, $icon)
    {
        $row->addWebLink(sprintf('<img title=%1$s src="%2$s" style="margin-right:4px;" />', __('Show/Hide'), $icon))
            ->setURL('#')
            ->onClick('toggleSection($(this), "' . $section . '"); return false;');
    }

    $row = $form->addRow();
        $row->addHeading(__('Basic Information'));
        toggleSection($row, 'basicInfo', $on);

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('eventTypeLabel', __('Event Type'));
        $row->addTextField('eventType')
            ->readonly();

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('eventFocusLabel', __('Area of Focus'));
        $row->addTextField('eventFocus')
            ->readonly();

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('applicant', __('PD Applicant'));
        $row->addContent(Format::nameLinked($pdRequest['gibbonPersonIDCreated'], '', $applicant['preferredName'], $applicant['surname'], 'Staff', false, true))
            ->wrap('<div class="text-left w-full text-sm">', '</div>');

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('statusLabel', __('Status'));
        $row->addTextField('status')
            ->readOnly();

    $row = $form->addRow();
        $row->addHeading('Conference/Event Details', __('Conference/Event Details'));
        toggleSection($row, 'eventInfo', $on);

    $row = $form->addRow()->addClass('eventInfo');
        $row->addLabel('eventTitleLabel', __('Event Name'));
        $row->addTextField('eventTitle')
            ->readonly();

    $row = $form->addRow()->addClass('eventInfo');
        $row->addLabel('eventLocationLabel', __('Location'));
        $row->addTextField('eventLocation')
            ->readonly();

    $row = $form->addRow()->addClass('eventInfo');
        $col = $row->addColumn();
        $col->addLabel('eventDescriptionLabel', __('Event Description'));
        $col->addContent($pdRequest['eventDescription']);

    $col = $form->addRow()->addClass('eventInfo')->addColumn();
        $col->addLabel('dates', __('Event Dates'));
        $requestDaysGateway = $container->get(RequestDaysGateway::class);
        $dayCriteria = $requestDaysGateway->newQueryCriteria()
            ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);

    $table = DataTable::create('dateTime');
        $table->addColumn('date', __('Date'))
            ->format(Format::using('date', ['date']));

    $col->addContent($table->render($requestDaysGateway->queryRequestDays($dayCriteria)));

    $row = $form->addRow();
        $row->addHeading(__('Further Information'));
        toggleSection($row, 'furtherInfo', $on);

    $row = $form->addRow()->addClass('furtherInfo');
        $col = $row->addColumn();
        $col->addLabel('personalRationalLabel', __('Personal Rational'));
        $col->addTextArea('personalRational')->setValue($pdRequest['personalRational'])->setRows(4)->readonly();

    $row = $form->addRow()->addClass('furtherInfo');
        $col = $row->addColumn();
        $col->addLabel('departmentImpactLabel', __('Departmental and School Impact'));
        $col->addTextArea('departmentImpact')->setValue($pdRequest['departmentImpact'])->setRows(4)->readonly();

    $row = $form->addRow()->addClass('furtherInfo');
        $col = $row->addColumn();
        $col->addLabel('schoolSharingLabel', __('School Sharing'));
        $col->addTextArea('schoolSharing')->setValue($pdRequest['schoolSharing'])->setRows(4)->readonly();

    $row = $form->addRow()->addClass('furtherInfo');
        $row->addLabel('supportingEvidenceLabel', __m('Supporting Evidence (If applicable)'))->description(__m('Please upload any supporting evidence that you think might be useful in assessing your application'));
        $row->addFileUpload('supportingEvidence')
            ->setAttachment('supportingEvidence', $session->get('absoluteURL'), $pdRequest['supportingEvidence']);

    $row = $form->addRow()->addClass('furtherInfo');
        $col = $row->addColumn();
        $col->addLabel('notesLabel', __('Comments/Notes'));
        $col->addTextArea('notes')->setValue($pdRequest['notes'])->setRows(4)->readonly();

    $row = $form->addRow();
        $row->addHeading(__('Participants'));
        toggleSection($row, 'participants', $on);

    $requestPersonGateway = $container->get(RequestPersonGateway::class);
    $peopleCriteria = $requestPersonGateway->newQueryCriteria()
        ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
        ->sortBy(['surname', 'preferredName'])
        ->pageSize(0);
    $participants = $requestPersonGateway->queryRequestPeople($peopleCriteria);
    
    $row = $form->addRow()->addClass('participants');
        $gridRenderer = new GridView($container->get('twig'));
        $table = $container->get(DataTable::class)->setRenderer($gridRenderer);

    $table->addMetaData('gridClass', 'rounded-sm bg-blue-100 border py-2');
    $table->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/5 my-2 text-center');

    $table->addColumn('image_240')
        ->format(Format::using('userPhoto', ['image_240', 'sm', '']));

    $table->addColumn('name')
        ->setClass('text-xs font-bold mt-1')
        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, false]));

    $table->addColumn('role')
        ->setClass('text-xxs');

    $row->addContent($table->render($participants));

    if ($gibbonPersonID == $pdRequest['gibbonPersonIDCreated']) {
        $applicantPartcipant = in_array($pdRequest['gibbonPersonIDCreated'], array_column($participants->toArray(), 'gibbonPersonID'));

        if(!$applicantPartcipant) {
            $form->addRow()->addContent(Format::alert(__m('As the PD applicant, you have not added yourself as a participant. If you wish to include this record in your own PD Portfolio, please edit this PD application and add yourself as a participant.'), 'warning'));
        }
    }

    $row = $form->addRow();
        $row->addHeading(__('Cost Breakdown'));
        toggleSection($row, 'costBreakdown', $on);

    $row = $form->addRow()->addClass('costBreakdown');

    $requestCostGateway = $container->get(RequestCostGateway::class);
    $costCriteria = $requestCostGateway->newQueryCriteria()
        ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
    $requestCosts = $requestCostGateway->queryRequestCost($costCriteria);

    $totalCost = array_reduce($requestCosts->toArray(), function ($group, $item) {
        $group += floatval($item['cost']) * floatval($item['quantity']);
        return $group;
    }, 0);

    $table = DataTable::create('costBreakdown');

    $table->addColumn('title', __('Cost Name'));
    $table->addColumn('description', __('Cost Description'));
    $table->addColumn('quantity', __('Quantity'));
    $table->addColumn('cost', __('Cost'))
        ->format(Format::using('currency', ['cost']));

    $row->addContent($table->render($requestCosts));

    $row = $form->addRow()->addClass('costBreakdown');
        $row->addLabel('totalCostLabel', Format::bold(__('Total Cost')));
        $row->addTextField('totalCost')
            ->setValue(Format::currency($totalCost))
            ->readOnly();

    $row = $form->addRow()->addClass('costBreakdown');
        $row->addLabel('expenseRequestLabel', __('Expense Request Application By'))
            ->description(__('Who will submit the expense request?'));
        $row->addTextField('expenseRequest')
            ->setValue($pdRequest['expenseRequest'])
            ->readonly();
    
    if ($pdRequest['expenseRequest'] != "Not Required") {
        $row = $form->addRow();
            $row->addHeading(__('Expense Request Application Details'));
            toggleSection($row, 'expenseRequestDetails', $on);
             
        $row = $form->addRow()->addClass('expenseRequestDetails');
            $table = DataTable::create('expenseRequestDetails');
    
        $table->addColumn('name', __('Participant'))
            ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]));
    
        $table->addColumn('expenseRequestStatus', __('Expense Request Submission Status'))
            ->format(function ($participant) {
                return !empty($participant['gibbonFinanceExpenseID']) 
                ? Format::tooltip(icon('solid', 'check', 'size-6 fill-current text-green-600'), __('Submitted'))
                : Format::tooltip(icon('solid', 'cross', 'size-6 fill-current text-red-700'), __('Pending'));
            });

        if ($pdRequest['expenseRequest'] == "Individual") {
            $row->addContent($table->render($participants));
        } else {
            $participant = array_filter($participants->toArray(), function($participant) use ($pdRequest) {
                return $participant['gibbonPersonID'] == $pdRequest['gibbonPersonIDCreated'];
            });
            $row->addContent($table->render($participant));
        }
    }

    if ($showLogs) {
        $row = $form->addRow();
            $row->addHeading(__('Log'));
            toggleSection($row, 'logs', $on);

        $row = $form->addRow()->addClass('logs');

        $requestLogGateway = $container->get(RequestLogGateway::class);
        $logCriteria = $requestLogGateway->newQueryCriteria()
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

        $row->addContent($table->render($requestLogGateway->queryRequestLogs($logCriteria)));
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
            $col->addLabel('comment', __('Comment'));

        if (!$approveMode) {
            $col->addTextarea('comment')->required();
        } else {
            $col->addTextarea('comment');
        }

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

function getSettings(ContainerInterface $container, $guid)
{
    $requestsGateway = $container->get(RequestsGateway::class);

    $requestApprovalOptions = ['One Of', 'Two Of', 'Chain Of All'];

    $settingFactory = new SettingFactory();

    $settingFactory->addSetting('requestApprovalType')
        ->setRenderer(function ($data, $row) use ($requestApprovalOptions) {
            $row->addSelect($data['name'])
                ->fromArray($requestApprovalOptions)
                ->selected($data['value'])
                ->required();
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
            return $data !== null ? 1 : 0;
        });

    $settingFactory->addSetting('expiredUnapprovedFilter')
        ->setRenderer(function ($data, $row) {
            $row->addCheckBox($data['name'])
                ->checked(boolval($data['value']));
        })
        ->setProcessor(function ($data) {
            return $data === null ? 0 : 1;
        });

    $settingFactory->addSetting('eventTypes')
        ->setRenderer(function ($data, $row) {
            $row->addTextArea($data['name'])
                ->setRows(2)
                ->required()
                ->setValue($data['value'] ?? '');
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('pdTypes')
    ->setRenderer(function ($data, $row) {
        $row->addTextArea($data['name'])
            ->setRows(2)
            ->required()
            ->setValue($data['value'] ?? '');
    })
    ->setProcessor(function ($data) {
        return $data ?? '';
    });

    $settingFactory->addSetting('areasOfFocus')
        ->setRenderer(function ($data, $row) {
            $row->addTextArea($data['name'])
                ->setRows(2)
                ->required()
                ->setValue($data['value'] ?? '');
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('participantsBlurb')
        ->setRow(false)
        ->setRenderer(function ($data, $row) use ($guid) {
            $row->addEditor($data['name'], $guid)
                ->setRows(4)
                ->setValue($data['value'] ?? '');
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('participantRoles')
        ->setRenderer(function ($data, $row) {
            $row->addTextArea($data['name'])
                ->setRows(2)
                ->required()
                ->setValue($data['value'] ?? '');
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('expensesBlurb')
        ->setRow(false)
        ->setRenderer(function ($data, $row) use ($guid) {
            $row->addEditor($data['name'], $guid)
                ->setRows(4)
                ->setValue($data['value'] ?? '');
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('expenseOptions')
        ->setRenderer(function ($data, $row) {
            $row->addTextArea($data['name'])
                ->setRows(2)
                ->required()
                ->setValue($data['value'] ?? '');
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('agreementDescription')
        ->setRow(false)
        ->setRenderer(function ($data, $row) use ($guid) {
            $row->addEditor($data['name'], $guid)
                ->setValue($data['value'] ?? '');
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('agreementAcknowledgment')
        ->setRenderer(function ($data, $row) {
            $row->addTextArea($data['name'])
                ->setRows(2)
                ->setValue($data['value'] ?? '');
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    return $settingFactory->getSettings();
}

?>