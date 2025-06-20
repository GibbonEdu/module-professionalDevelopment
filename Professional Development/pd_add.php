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
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestCostGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestDaysGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestPersonGateway;

require_once __DIR__ . '/moduleFunctions.php';

// Checking if editing mode is enabled
$edit = false;

$mode = $_REQUEST['mode'] ?? '';
$professionalDevelopmentRequestID = $_REQUEST['professionalDevelopmentRequestID'] ?? '';

$requestsGateway = $container->get(RequestsGateway::class);

// Check if a mode and Request ID are given
if (!empty($mode) && !empty($professionalDevelopmentRequestID)) {
    //Get PD request from gateway
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);    

    // If the PD request exists, set to edit mode
    if (!empty($pdRequest)) {
        $edit = true;
    }
}

$isDraft = !empty($pdRequest) && $pdRequest['status'] == 'Draft';

$page->breadcrumbs->add($edit ? __m('Edit Application') : __m('New Application'));

$gibbonPersonID = $session->get('gibbonPersonID');
$highestAddAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_add.php', $connection2);
$highestManageAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_manage.php', $connection2);

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_add.php') || ($edit && $highestManageAction != 'Manage Applications_full' && $pdRequest['gibbonPersonIDCreated'] != $gibbonPersonID)) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else if ((isset($pdRequest) && empty($pdRequest)) || (!empty($mode) && !$edit)) {
    $page->addError(__('Invalid Trip.'));
} else {
    // Proceed
    $moduleName = $session->get('module');
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    $settingGateway = $container->get(SettingGateway::class);
    $requestPersonGateway = $container->get(RequestPersonGateway::class);

    $eventTypes = $settingGateway->getSettingByScope('Professional Development', 'eventTypes');
    $areasOfFocus = $settingGateway->getSettingByScope('Professional Development', 'areasOfFocus');
    $participantsBlurb = $settingGateway->getSettingByScope('Professional Development', 'participantsBlurb');
    $participantRoles = $settingGateway->getSettingByScope('Professional Development', 'participantRoles');
    $expensesBlurb = $settingGateway->getSettingByScope('Professional Development', 'expensesBlurb');
    $expenseOptions = $settingGateway->getSettingByScope('Professional Development', 'expenseOptions');

    // Return Messages
    $page->return->addReturns([
        'warning3' => __('Your request was successful, but some required fields were missing. Please update your request data.'),
        'warning4' => __('Your request was successful, but there are no dates set for this request. Please add dates and update your request.'),
        'warning5' => __('Your request was successful, but there was a problem saving the cost details. Please check the costs and update your request.'),
        'warning6' => __('Your request was successful, but no participants have been added to the request. Please check the participants list and update your request.'),
    ]);

   if (!$edit && !empty($professionalDevelopmentRequestID)) {
      $page->return->setEditLink($session->get('absoluteURL').'/index.php?q=/modules/Professional Development/pd_view.php&professionalDevelopmentRequestID='.$professionalDevelopmentRequestID);
   }

   // Submit Request Form
   $form = Form::create('requestForm', $session->get('absoluteURL').'/modules/'.$moduleName.'/pd_addProcess.php');

   $form->setFactory(DatabaseFormFactory::create($pdo));
   $form->addHiddenValue('address', $session->get('address'));
   $form->addHiddenValue('saveMode', 'Submit');

   $form->setTitle(__('Professional Development Application'));

   if ($edit) $form->removeMeta()->addMeta()->addDefaultContent('editProcess');

   // Basic Information Section
   $row = $form->addRow();
        $row->addHeading('Basic Information');

    $row = $form->addRow();
        $row->addLabel('eventType', __('Event Type'));
        $row->addSelect('eventType')->fromString($eventTypes)->required();

    $row = $form->addRow();
        $row->addLabel('eventFocus', __('Area of Focus'))->description(__m('Focus of conference or training'));
        $row->addSelect('eventFocus')->fromString($areasOfFocus)->required();

    $form->toggleVisibilityByClass('eventFocus')->onSelect('eventFocus')->when('Other');
    $row = $form->addRow()->addClass('eventFocus');
        $row->addContent(__m('If you selected "Other" above,  please provide the relevant focus area'));
        $row->addTextField('eventFocusOther')->required();

    $row = $form->addRow();
    $row->addLabel('gibbonPersonID', __('PD Applicant'))->description(__m('For group applications, this is the staff member organsing the PD group'));
    $row->addSelectStaff('gibbonPersonID')
        ->required()
        ->placeholder()
        ->selected($session->get('gibbonPersonID'))
        ->readOnly($highestAddAction != 'New Application_all');   

    $row = $form->addRow();
        $row->addHeading('Conference/Event Details', __('Conference/Event Details'));

    $row = $form->addRow();
        $row->addLabel('eventTitle', __m('Event Name'));
        $row->addTextField('eventTitle')
            ->required();

    $row = $form->addRow();
        $row->addLabel('eventLocation', __m('Full Address'));
        $row->addTextField('eventLocation')
            ->required();

    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('eventDescription', __m('Event Description'));
        $col->addTextArea('eventDescription')->setRows(3)->required();

    // Template for Date Block
    $dateTimeBlock = $form->getFactory()->createTable()->setClass('blank');

    $row = $dateTimeBlock->addRow();
            $row->addDate('date')
                ->required()
                ->placeholder(__('Date'))
                ->setClass('w-auto');
            $row->addContent('')->setClass('w-24')->append("<input type='hidden' id='professionalDevelopmentRequestDaysID' name='professionalDevelopmentRequestDaysID' value=''/>");

    $dateTimeBlock->addRow()->addClass('h-2');

    $addDateTimeBlockButton = $form->getFactory()->createButton(__('Add Date'))->addClass('addBlock');
    
    // Creating Custom Blocks using the template of Date Block
    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('dateTime', 'Event Dates');
        $dateBlocks = $col->addCustomBlocks('dateTime', $session)
            ->fromTemplate($dateTimeBlock)
            ->settings([
                'placeholder' => __m('Add the event dates here...'),
                'sortable' => true,
                'orderName' => 'dateTimeOrder'
            ])
            ->addToolInput($addDateTimeBlockButton);

    // Participants section
    $row = $form->addRow();
    $row->addHeading(__('Participants'))->append($participantsBlurb);

    // Template for participant Blocks
    $participantBlock = $form->getFactory()->createTable()->setClass('blank');
    $row = $participantBlock->addRow()->addClass('w-full flex justify-between items-center mt-1 ml-2');
    $row->addSelectStaff('gibbonPersonID')->photo(false)
        ->setClass('flex-1 mr-1')
        ->required()
        ->placeholder()
        ->append("<input type='hidden' id='professionalDevelopmentRequestPersonID' name='professionalDevelopmentRequestPersonID' value=''/>");
    $row->addSelect('role')
        ->fromString($participantRoles)
        ->setClass('flex-1 mr-1');

    // Tool Button
    $addParticipantBlockButton = $form->getFactory()->createButton(__('Add Participant'))->addClass('addBlock');

    // Custom Blocks for participants
    $row = $form->addRow();
    $participantBlocks = $row->addCustomBlocks('participant', $session)
    ->fromTemplate($participantBlock)
    ->settings([
        'placeholder' => '',
        'sortable' => true,
        'orderName' => 'participantOrder'
        ])
    ->addToolInput($addParticipantBlockButton);

    // Cost Section
    $row = $form->addRow()->addHeading(__('Expenses'))->append($expensesBlurb);

    // Template for Cost Block
    $costBlock = $form->getFactory()->createTable()->setClass('blank');
        $row = $costBlock->addRow();
            $row->addSelect('title')
                ->fromString($expenseOptions)
                ->required()
                ->setClass('');
        
            $row->addLabel('quantity', __('Qty.'));
            $row->addNumber('quantity')
                ->onlyInteger(true)
                ->required()
                ->setClass('w-12')
                ->setValue('1');

            $row->addLabel('cost', __('Amount'));
            $row->addCurrency('cost')
                ->required()
                ->addClass('')
                ->minimum(0)
                ->append("<input type='hidden' id='professionalDevelopmentRequestCostID' name='professionalDevelopmentRequestCostID' value=''/>");

        $row = $costBlock->addRow();
            $col = $row->addColumn();
                $col->addTextArea('description')
                    ->setRows(2)
                    ->setClass('w-full mt-2')
                    ->placeholder(__('Expense Description'));
      
        // Tool Button
        $addCostBlockButton = $form->getFactory()
            ->createButton(__("Add Expense"))
            ->addClass('addBlock');
    
        // Custom Blocks for Cost
        $row = $form->addRow();
            $costBlocks = $row->addCustomBlocks("cost", $session)
                ->fromTemplate($costBlock)
                ->settings([
                    'placeholder' => '',
                    'sortable' => true,
                    'orderName' => 'costOrder'
                ])
                ->addToolInput($addCostBlockButton);
    
        $expenseRequestOptions = ['Individual' => 'Individual', 'Group Leader' => 'Group Leader', 'Not Required' => 'Not Required'];
        $row = $form->addRow();
            $row->addLabel('expenseRequest', __('Expense Request Application By'))->description(__('Please advise who will be submitting the application for expense requisition to the Finance Department'));
            $row->addSelect('expenseRequest')->fromArray($expenseRequestOptions)->required()
            ->placeholder();

    // Further Information Section
    $row = $form->addRow();
        $row->addHeading('Further Information');

    $col = $form->addRow()->addColumn();
        $col->addLabel('personalRational', __('Personal Rational'))->description(__('How does the course/conference reflect your personal interests, professional goals, or career path?'));
        $col->addTextArea('personalRational')->setRows(2)->required();
    
    $col = $form->addRow()->addColumn();
        $col->addLabel('departmentImpact', __('Departmental And School Impact'))->description(__('How will this training or course reflect the strategic plan of the school or the development of your department?'));
        $col->addTextArea('departmentImpact')->setRows(2)->required();

    $col = $form->addRow()->addColumn();
        $col->addLabel('schoolSharing', __('School Sharing'))->description(__('A requirement of the school’s support will be that you return some of what you learn to the staff at our school, whether that be at departmental level and/or a whole school level during a PD session. How do you envisage sharing the knowledge/information/resources you glean?'));
        $col->addTextArea('schoolSharing')->setRows(2)->required();

        if(!$edit) {
            $row = $form->addRow();
                $row->addLabel('supportingEvidence', __('Supporting Evidence (If applicable)'))->description(__('Please upload any supporting evidence that you think might be useful in assessing your application'));
                $row->addFileUpload('supportingEvidenceFile');
        } else {
            $row = $form->addRow();
                $row->addLabel('supportingEvidence', __('Supporting Evidence (If applicable)'))->description(__('Please upload any supporting evidence that you think might be useful in assessing your application'));
                $row->addFileUpload('supportingEvidenceFile')
                    ->setAttachment('supportingEvidence', $session->get('absoluteURL'), $pdRequest['supportingEvidence']);
        }

    $row = $form->addRow()->addClass('notes');
        $row->addLabel('notes', __('Comments/Notes'));
        $row->addTextArea('notes')->setRows(3);

    if ($edit) {
        // Add parameters for editing
        $form->addHiddenValue('mode', 'edit');
        $form->addHiddenValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);

        // Add view Header
        $form->addHeaderAction('view', __('View'))
            ->setURL('/modules/' . $moduleName . '/pd_view.php')
            ->addParam('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->displayLabel();
            
        $form->loadAllValuesFrom($pdRequest);

         // Get Cost Data and add to CostBlocks
         $requestCostGateway = $container->get(RequestCostGateway::class);
         $costCriteria = $requestCostGateway->newQueryCriteria()
             ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
             ->sortBy(['professionalDevelopmentRequestCostID']);
 
         $costs = $requestCostGateway->queryRequestCost($costCriteria);

         foreach ($costs as $cost) {
             $costBlocks->addBlock($cost['professionalDevelopmentRequestCostID'], [
                'title'       => $cost['title'],
                'description' => $cost['description'],
                'cost'        => $cost['cost'],
                'quantity'    => $cost['quantity'],
                'professionalDevelopmentRequestCostID' => $cost['professionalDevelopmentRequestCostID']
            ]);
         }

         // Get Days Data and add to DateBlocks
        $requestDaysGateway = $container->get(RequestDaysGateway::class);
        $daysCriteria = $requestDaysGateway->newQueryCriteria()
            ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->sortBy(['professionalDevelopmentRequestDaysID']);

        $days = $requestDaysGateway->queryRequestDays($daysCriteria);

        foreach ($days as $day) {            
            $dateBlocks->addBlock($day['professionalDevelopmentRequestDaysID'], [
            'date' =>$day['date'],
            'professionalDevelopmentRequestDaysID' => $day['professionalDevelopmentRequestDaysID']
            ]);
        }
        
        // Get People Data and add to DataBlocks
        $requestPersonCriteria = $requestPersonGateway->newQueryCriteria()
        ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);

        $tripPeople = $requestPersonGateway->queryRequestPeople($requestPersonCriteria);

        foreach ($tripPeople as $person) {  
            $participantBlocks->addBlock($person['professionalDevelopmentRequestPersonID'], [
            'gibbonPersonID' => $person['gibbonPersonID'],
            'role' => $person['role'] ?? '',
            'professionalDevelopmentRequestPersonID' => $person['professionalDevelopmentRequestPersonID']
            ]);
        }
    } else if (!$edit && $highestAddAction == 'New Application_my') {
        $participantBlocks->addBlock('', [
            'gibbonPersonID' => $session->get('gibbonPersonID'),
            'professionalDevelopmentRequestPersonID' => ''
        ]);
    }

    if ($edit && !$isDraft) {
        $form->addRow()->addHeading(__('Log'));
        $col = $form->addRow()->addColumn();
            $col->addLabel('changeSummary', __('Change Summary'))->description(__('Please briefly describe the changes you have made to this application. This summary will be added to the change log.'));
            $col->addTextarea('changeSummary')->required()->setRows(2);
    } else {
        $agreementDescription = $settingGateway->getSettingByScope('Professional Development', 'agreementDescription');
        $agreementAcknowledgment = $settingGateway->getSettingByScope('Professional Development', 'agreementAcknowledgment');

        $form->addRow()->addHeading('Agreement', __('Agreement'));
        $form->addRow()->addContent($agreementDescription);

        $row = $form->addRow();
        $row->addLabel('agreement', $agreementAcknowledgment)->addClass('flex-grow');
        $row->addCheckbox('agreement')->description(__('Yes'))->required()->addClass('flex-1');
    }
    
    $row = $form->addRow('stickySubmit');
    if (!$edit || $isDraft) {
        $col = $row->addColumn()->addClass('items-center');
        $col->addButton(__('Save Draft'))->onClick('saveDraft()')->addClass('rounded-sm w-auto mr-2');
    }
    $col = $row->addColumn()->addClass('items-center');
    $col->addSubmit();

    echo $form->getOutput();
}

?>
<script>
    function saveDraft() {
            $('input[name="saveMode"]').val('Draft');
            document.getElementById('requestForm').submit();
    }
</script>
