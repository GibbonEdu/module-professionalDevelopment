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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestCostGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestDaysGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestPersonGateway;

require_once __DIR__ . '/moduleFunctions.php';

//Checking if editing mode should be enabled
$edit = false;
$prefix = 'Submit';

$mode = $_REQUEST['mode'] ?? '';
$professionalDevelopmentRequestID = $_REQUEST['professionalDevelopmentRequestID'] ?? '';

//Check if a mode and Request ID are given
if (!empty($mode) && !empty($professionalDevelopmentRequestID)) {
    //Get PD request from gateway
    $requestsGateway = $container->get(RequestsGateway::class);
    $pdRequest = $requestsGateway->getByID($professionalDevelopmentRequestID);    

    //If the PD request exists, set to edit mode
    if (!empty($pdRequest)) {
        $edit = true;
        $prefix = 'Edit';
    }
}

$isDraft = !empty($pdRequest) && $pdRequest['status'] == 'Draft';

$page->breadcrumbs->add(__($prefix . ' Request'));

$gibbonPersonID = $session->get('gibbonPersonID');
$highestAction = getHighestGroupedAction($guid, '/modules/Professional Development/requests_manage.php', $connection2);

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_add.php') || ($edit && $highestAction != 'Manage Requests_full' && $pdRequest['gibbonPersonIDCreated'] != $gibbonPersonID)) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else if ((isset($pdRequest) && empty($pdRequest)) || (!empty($mode) && !$edit)) {
    $page->addError(__('Invalid Trip.'));
} else {

    // Proceed
    $moduleName = $session->get('module');
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    $settingGateway = $container->get(SettingGateway::class);

    //Return Messages
    $page->return->addReturns([
        'warning3' => __('Your request was successful, but some required fields were missing. Please update your request data.'),
        'warning4' => __('Your request was successful, but there are no dates set for this request. Please add dates and update your request.'),
        'warning5' => __('Your request was successful, but there was a problem saving the cost details. Please check the costs and update your request.'),
        'warning6' => __('Your request was successful, but no participants have been added to the request. Please check the participants list and update your request.'),
    ]);

   if (!$edit && !empty($professionalDevelopmentRequestID)) {
      $page->return->setEditLink($session->get('absoluteURL').'/index.php?q=/modules/Professional Development/requests_view.php&professionalDevelopmentRequestID='.$professionalDevelopmentRequestID);
   }

   //Submit Request Form
   $form = Form::create('requestForm', $session->get('absoluteURL').'/modules/'.$moduleName.'/requests_addProcess.php');

   $form->setFactory(DatabaseFormFactory::create($pdo));
   $form->addHiddenValue('address', $session->get('address'));
   $form->addHiddenValue('saveMode', 'Submit');

   $form->setTitle(__('Submit Request'));

   //Basic Information Section
   $row = $form->addRow();
        $row->addHeading('Basic Information');

    $row = $form->addRow();
        $row->addLabel('eventType', __('Event Type'));
        $row->addSelect('eventType')->fromArray(['Internal' => __('Internal'), 'External' => __('External')])->required();

    $row = $form->addRow();
        $row->addLabel('eventFocus', __('Area of Focus (conference or training)'));
        $row->addSelect('eventFocus')->fromArray(['IB' => __('IB'), 'IGCSE' => __('IGCSE'), 'SEN' => __('SEN'),'Other' => __('Other')])->required();

    $form->toggleVisibilityByClass('eventFocus')->onSelect('eventFocus')->when('Other');
    $row = $form->addRow()->addClass('eventFocus');
        $row->addContent(__('If you selected "Other" above,  please provide the relevant focus area'));
        $row->addTextArea('eventFocus')->setRows(1)->required();

    $row = $form->addRow();
        $row->addLabel('attendeeRole', __('Participant(s) Role'))->description(__('Are you presenting or an attendee?'));
        $row->addSelect('attendeeRole')->fromArray(['Attendee' => __('Attendee'), 'Presenting' => __('Presenting'), 'Both' => __('Both')])->required();

    $row = $form->addRow();
        $row->addLabel('attendeeCount', __('No. of Particpants'))->description(__m('Total number of people joining the event'));
        $row->addNumber('attendeeCount')->onlyInteger(true)->minimum(0)->maximum(999)->maxLength(3)->required();

    //Cover Amount
    $options = getCoverAmountArray();
    $row = $form->addRow();
        $row->addLabel('coverAmount', __('Cover Amount'))->description(__('Cover Required: If "yes", please provide an estimate of the amount of cover required. Please tick all that apply'));
        $row->addCheckbox('coverAmount')
            ->fromArray($options)
            ->addClass('md:max-w-md')
            ->required();

    $row = $form->addRow();
        $row->addLabel('eventTitle', 'Event Name');
        $row->addTextfield('eventTitle')
            ->setRequired(true);

    $row = $form->addRow();
        $row->addLabel('eventLocation', 'Location');
        $row->addTextfield('eventLocation')
            ->setRequired(true);

    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('eventDescription', 'Event Description');
        $col->addEditor("eventDescription", $guid)
            ->setRequired(true)
            ->showMedia(true)
            ->setRows(2);

    //Further Information Section
    $row = $form->addRow();
        $row->addHeading('Further Information');

    $col = $form->addRow()->addColumn();
        $col->addLabel('personalRational', __('PERSONAL RATIONAL'))->description(__('How does the course/conference reflect your personal interests, professional goals, or career path?'));
        $col->addTextArea('personalRational')->setRows(2)->setRequired(true);
    
    $col = $form->addRow()->addColumn();
        $col->addLabel('departmentImpact', __('DEPARTMENTAL AND SCHOOL IMPACT'))->description(__('How will this training or course reflect the strategic plan of the school or the development of your department?'));
        $col->addTextArea('departmentImpact')->setRows(2)->setRequired(true);

    $col = $form->addRow()->addColumn();
        $col->addLabel('schoolSharing', __('SCHOOL SHARING'))->description(__('A requirement of the school’s support will be that you return some of what you learn to the staff at ICHK whether that be at departmental level and/or a whole school level during a PD session. How do you envisage sharing the knowledge/information/resources you glean?'));
        $col->addTextArea('schoolSharing')->setRows(2)->setRequired(true);

        if(!$edit) {
            $row = $form->addRow();
                $row->addLabel('supportingEvidence', __('Supporting Evidence (If applicable)'))->description(__('Please upload any supporting evidence that you think might be useful in assessing your application'));
                $row->addFileUpload('supportingEvidence');
        } else {
            $row = $form->addRow();
                $row->addLabel('supportingEvidence', __('Supporting Evidence (If applicable)'))->description(__('Please upload any supporting evidence that you think might be useful in assessing your application'));
                $row->addFileUpload('supportingEvidenceFile')
                    ->setAttachment('supportingEvidence', $gibbon->session->get('absoluteURL'), $pdRequest['supportingEvidence']);
        }

    $row = $form->addRow()->addClass('notes');
        $row->addLabel('notes', __('Comments/Notes'));
        $row->addTextArea('notes')->setRows(5);

    //Date Section
    $row = $form->addRow();
        $row->addHeading(__('Date'));
    
    //Template for Date Block
    $dateTimeBlock = $form->getFactory()->createTable()->setClass('blank');

        $row = $dateTimeBlock->addRow();
            $row->addLabel('date', 'Start and End Dates for this event.')
                ->addClass('font-bold');

        $row = $dateTimeBlock->addRow();
                $row->addLabel('startDate', __('Start Date'));
                $row->addDate('startDate')
                    ->isRequired()
                    ->placeholder('Start Date');

                $row->addLabel('endDate', __('End Date'));
                $row->addDate('endDate')
                    ->isRequired()
                    ->placeholder('End Date')
                    ->append("<input type='hidden' id='professionalDevelopmentRequestDaysID' name='professionalDevelopmentRequestDaysID' value=''/>");

        $dateTimeBlock->addRow()->addClass('h-2');

    $addDateTimeBlockButton = $form->getFactory()->createButton(__('Add Date'))->addClass('addBlock');
    
    //Creating Custom Blocks using the template of Date Block
    $row = $form->addRow();
        $dateBlocks = $row->addCustomBlocks('dateTime', $session)
            ->fromTemplate($dateTimeBlock)
            ->settings([
                'placeholder' => __('Dates will appear here...'),
                'sortable' => true,
                'orderName' => 'dateTimeOrder'
            ])
            ->addToolInput($addDateTimeBlockButton);


    //Cost Section
    $row = $form->addRow();
        $row->addHeading(__('Costs'));

    //Template for Cost Block
    $costBlock = $form->getFactory()->createTable()->setClass('blank');
        $row = $costBlock->addRow();
            $row->addLabel('title', __('Cost Name'));
            $row->addTextfield('title')
                ->isRequired()
                ->addClass('floatLeft');
        
            $row->addLabel('cost', __('Value'));
            $row->addCurrency('cost')
                ->isRequired()
                ->addClass('floatNone')
                ->minimum(0)
                ->append("<input type='hidden' id='professionalDevelopmentRequestCostID' name='professionalDevelopmentRequestCostID' value=''/>");

        $row = $costBlock->addRow()->addClass('showHide w-full');
            $col = $row->addColumn();
                $col->addTextArea('description')
                    ->setRows(2)
                    ->setClass('fullWidth floatNone')
                    ->placeholder(__('Cost Description'));
      
        //Tool Button
        $addCostBlockButton = $form->getFactory()
            ->createButton(__("Add Cost"))
            ->addClass('addBlock');
    
        //Custom Blocks for Cost
        $row = $form->addRow();
            $costBlocks = $row->addCustomBlocks("cost", $session)
                ->fromTemplate($costBlock)
                ->settings([
                    'placeholder' => __('Cost will appear here...'),
                    'sortable' => true,
                    'orderName' => 'costOrder'
                ])
                ->addBlockButton('showHide', 'Show/Hide', 'plus.png')
                ->addToolInput($addCostBlockButton);
    
    //Participants section
    $row = $form->addRow();
        $row->addHeading(__('Participants'));

    //Template for participant Blocks
    $participantBlock = $form->getFactory()->createTable()->setClass('blank');
    $row = $participantBlock->addRow()->addClass('w-full flex justify-between items-center mt-1 ml-2');
    $row->addSelectStaff('gibbonPersonID')->photo(false)
            ->setClass('flex-1 mr-1')->required()
            ->placeholder()
            ->append("<input type='hidden' id='professionalDevelopmentRequestPersonID' name='professionalDevelopmentRequestPersonID' value=''/>");
    
    //Tool Button
    $addParticipantBlockButton = $form->getFactory()->createButton(__('Add Participant'))->addClass('addBlock');

    //Custom Blocks for participants
    $row = $form->addRow();
        $participantBlocks = $row->addCustomBlocks('participant', $session)
        ->fromTemplate($participantBlock)
        ->settings([
            'placeholder' => __('Participants will appear here...'),
            'sortable' => true,
            'orderName' => 'participantOrder'
            ])
        ->addToolInput($addParticipantBlockButton);

    if ($edit) {
        //Add parameters for editing
        $form->addHiddenValue('mode', 'edit');
        $form->addHiddenValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);

        //Add view Header
        $form->addHeaderAction('view', __('View'))
            ->setURL('/modules/' . $moduleName . '/requests_view.php')
            ->addParam('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->displayLabel();
        
        //Load values into form
        $pdRequest['coverAmount'] = unserialize($pdRequest['coverAmount']);

        $form->loadAllValuesFrom($pdRequest);

         //Get Cost Data and add to CostBlocks
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
                'professionalDevelopmentRequestCostID' => $cost['professionalDevelopmentRequestCostID']
            ]);
         }

         //Get Days Data and add to DateBlocks
        $requestDaysGateway = $container->get(RequestDaysGateway::class);
        $daysCriteria = $requestDaysGateway->newQueryCriteria()
            ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->sortBy(['professionalDevelopmentRequestDaysID']);

        $days = $requestDaysGateway->queryRequestDays($daysCriteria);

        foreach ($days as $day) {            
            $dateBlocks->addBlock($day['professionalDevelopmentRequestDaysID'], [
            'startDate' => Format::date($day['startDate']),
            'endDate'   => Format::date($day['endDate']),
            'professionalDevelopmentRequestDaysID' => $day['professionalDevelopmentRequestDaysID']
            ]);
        }
        
        //Get People Data and add to DataBlocks
        $requestPersonGateway = $container->get(RequestPersonGateway::class);
        $requestPersonCriteria = $requestPersonGateway->newQueryCriteria()
        ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);

        $tripPeople = $requestPersonGateway->queryRequestPeople($requestPersonCriteria);

        foreach ($tripPeople as $person) {  
            $participantBlocks->addBlock($person['professionalDevelopmentRequestPersonID'], [
            'gibbonPersonID' => $person['gibbonPersonID'],
            'professionalDevelopmentRequestPersonID' => $person['professionalDevelopmentRequestPersonID']
            ]);
        }

    }

    if ($edit && !$isDraft) {
        $form->addRow()->addHeading(__('Log'));
        $col = $form->addRow()->addColumn();
            $col->addLabel('changeSummary', __('Change Summary'))->description(__('Please briefly describe the changes you have made to this request. This summary will be added to the request log.'));
            $col->addTextarea('changeSummary')->setRows(2)->required();
    }

    $form->addRow()->addHeading('Agreement', __('PLEASE READ'));
    $form->addRow()->addContent("Please note that completion of this application will not always guarantee confirmation. When and if necessary, consideration will need to be given to the number of staff wishing to attend at any given time, support available to cover classes, limits to registrations available for individual schools, and perceived areas of need within the school.  Please be assured, however, that all decisions will be discussed with you in a timely fashion. *No registration or airline/hotel booking will be executed if this application is not approved by the Head of School. The application should be submitted at least 15 working days prior to the conference/workshop’s registration deadline.");

    $row = $form->addRow();
    $row->addLabel('agreement', __('I acknowledge that I understand the points above and I have discussed this application with my Head of Department.'));
    $row->addCheckbox('agreement')->description(__('Yes'))->required();
    
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

    //This javascript is for the Date Blocks
    var date = 'input[id*="Date"]';

    $(document).on('click', '.addBlock', function () {
        $(date).removeClass('hasDatepicker').datepicker({'timeFormat': 'H:i', onSelect: function(){$(this).blur();}, onClose: function(){$(this).change();} });
    });

    $(document).ready(function(){
        $(date).removeClass('hasDatepicker').datepicker({onSelect: function(){$(this).blur();}, onClose: function(){$(this).change();} });

        //Ensure that loaded dates have correct max and min dates.
        $('input[id^=startDate]').each(function() {
            var endDate = $('#' + $(this).prop('id').replace('start', 'end'));
        });

    });

    $(document).on('change', 'input[id^=startDate]', function() {
        var endDate = $('#' + $(this).prop('id').replace('start', 'end'));
        if (endDate.val() == "" || $(this).val() > endDate.val()) {
            endDate.val($(this).val());
        }
        endDate.datepicker('option', {'minDate': $(this).val()});
    });

    $(document).on('change', 'input[id^=endDate]', function() {
        var startDate = $('#' + $(this).prop('id').replace('end', 'start'));
        if (startDate.val() == "" || $(this).val() < startDate.val()) {
            startDate.val($(this).val());
        }
        startDate.datepicker('option', {'maxDate': $(this).val()});
    });

    function saveDraft() {
            $('option', '#teachers').each(function() {
                $(this).prop('selected', true);
            });

            var form = LiveValidationForm.getInstance(document.getElementById('requestForm'));

            if (LiveValidation.massValidate(form.fields)) {
                $('button[id="Save Draft"]').prop('disabled', true);
                setTimeout(function() {
                    $('button[id="Save Draft"]').wrap('<span class="submitted"></span>');
                }, 500);
                $('input[name="saveMode"]').val('Draft');
                document.getElementById('requestForm').submit();
            }
    }

</script>
