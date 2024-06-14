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

use Gibbon\Http\Url;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffGateway;
use Gibbon\Forms\DatabaseFormFactory;


require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_add.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
    
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Requests'), 'requests_manage.php')
        ->add(__('Submit Request'));

   if (isset($_GET['editID'])) {
      $page->return->setEditLink($session->get('absoluteURL').'/index.php?q=/modules/Professional Development/requests_edit.php&professionalDevelopmentRequestID='.$_GET['editID']);
   }
 
   $form = Form::create('requestForm', $session->get('absoluteURL').'/modules/'.$session->get('module').'/requests_addProcess.php');
   $form->setFactory(DatabaseFormFactory::create($pdo));

   $form->addHiddenValue('address', $session->get('address'));

   $form->setTitle(__('Submit Request'));

   //Basic Information Section
   $row = $form->addRow();
        $row->addHeading('Basic Information');

    $row = $form->addRow();
        $row->addLabel('eventType', __('Type'));
        $row->addSelect('eventType')->fromArray(['Internal' => __('Internal'), 'External' => __('External')])->required();

    $row = $form->addRow();
        $row->addLabel('eventFocus', __(' Area of Focus (conference or training)'));
        $row->addSelect('eventFocus')->fromArray(['IB' => __('IB'), 'IGCSE' => __('IGCSE'), 'Other' => __('Other')])->required();

    $form->toggleVisibilityByClass('eventFocus')->onSelect('eventFocus')->when('Other');
    $row = $form->addRow()->addClass('eventFocus');
        $row->addContent(__('If you selected "Other" above,  please provide the relevant focus area'));
        $row->addTextArea('eventFocus')->setRows(1)->required();

    $row = $form->addRow();
        $row->addLabel('attendeeRole', __('Participant Role'))->description(__('Are you presenting or an attendee?'));
        $row->addSelect('attendeeRole')->fromArray(['Attendee' => __('Attendee'), 'Presenting' => __('Presenting')])->required();

    $row = $form->addRow();
        $row->addLabel('attendeeCount', __('No. of Particpants'))->description(__m('Total number of people joining the event'));
        $row->addNumber('attendeeCount')->onlyInteger(true)->minimum(0)->maximum(999)->maxLength(3)->required();

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

    $row = $form->addRow();
        $row->addLabel('supportingEvidence', __('Supporting Evidence (If applicable)'))->description(__('Please upload any supporting evidence that you think might be useful in assessing your application'));
        $row->addFileUpload('supportingEvidence');

    $row = $form->addRow()->addClass('notes');
        $row->addLabel('notes', __('Comments/Notes'));
        $row->addTextArea('notes')->setRows(5);

    
    //Date & Time Section
    $row = $form->addRow();
        $row->addHeading(__('Date & Time'));
    
    
    //Template for Date & Time Block
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
                    ->placeholder('End Date');

        $dateTimeBlock->addRow()->addClass('h-2');

        $row = $dateTimeBlock->addRow();
            $row->addLabel('time', 'Start and End Times for each day. Leave blank if all day.')
                ->addClass('font-bold');

        $row = $dateTimeBlock->addRow();
            $row->addLabel('startTime', __('Start Time'));
            $row->addTime('startTime')
                ->placeholder('Start Time');

            $row->addLabel('endTime', __('End Time'));
            $row->addTime('endTime')
                ->placeholder('End Time');

    $addDateTimeBlockButton = $form->getFactory()->createButton(__('Add Date & Time'))->addClass('addBlock');
    
    //Creating Custom Blocks using the template of Date & Time block
    $row = $form->addRow();
        $dateBlocks = $row->addCustomBlocks('dateTime', $session)
            ->fromTemplate($dateTimeBlock)
            ->settings([
                'placeholder' => __('Date/Time will appear here...'),
                'sortable' => true,
                'orderName' => 'dateTimeOrder'
            ])
            ->addToolInput($addDateTimeBlockButton);

    //Participant & Cost Section
    $row = $form->addRow();
        $row->addHeading(__('Participants and Costs'));
    

    //Template for Participant & Cost Block
    $costBlock = $form->getFactory()->createTable()->setClass('blank');

        $row = $costBlock->addRow();
            $row->addLabel('staff', __('Staff Name'));
            $row->addSelectStaff('gibbonPersonID')->photo(true, 'small')->setClass('flex-1 mr-1')->placeholder()->required();

            $row->addLabel('eventRole', __('Participant Role'))->description(__('Are you presenting or an attendee?'));
            $row->addSelect('eventRole')
                ->fromArray(['Attendee' => __('Attendee'), 'Presenting' => __('Presenting')])
                ->required();

        $row = $costBlock->addRow();
          
            $row->addLabel('registrationCost', __('Registration Fee'));
            $row->addCurrency('registrationCost')
                ->addClass('floatNone')
                ->minimum(0);

            $row->addLabel('miscellaneousCost', __('Miscellaneous Expenses'));
            $row->addCurrency('miscellaneousCost')
                ->addClass('floatNone')
                ->minimum(0);
    
        $row = $costBlock->addRow();
            $col = $row->addColumn();
            $col->addLabel('costNotes', __('Expense Notes'));
            $col->addTextArea('costNotes')
                ->setRows(2)
                ->placeholder(__('Please provide a breakdown of all expenses if possible'));

        $row = $costBlock->addRow();
            $row->addLabel('coverRequired', __('Cover Required'));
            $row->addYesNoRadio('coverRequired')
                ->inline()
                ->alignLeft()
                ->required();

        $row = $costBlock->addRow()->addClass('showHide');
                $row->addSelect('coverAmount')->fromArray(['Tutor group' => __('Tutor group'), 'Duty' => __('Duty'), 'Min Periods' => __('1-5 Periods (include year 12 & 13 classes)')])->required()
                    ->addClass('sm:max-w-full w-full');
                
        //$costBlock->toggleVisibilityByClass('coverRequired')->onRadio('coverRequired')->when('Y');
        //$row = $costBlock->addRow()->addClass('coverRequired');
        //$row->addLabel('coverRequired', __('Cover Amount'));
      
    
        //Tool Button
        $addBlockButton = $form->getFactory()
            ->createButton(__("Add Participants"))
            ->addClass('addBlock');
    
        //Custom Blocks
        $row = $form->addRow();
            $costBlocks = $row->addCustomBlocks("cost", $session)
                ->fromTemplate($costBlock)
                ->settings([
                    'placeholder' => __('Participants will appear here...'),
                    'sortable' => true,
                    'orderName' => 'costOrder'
                ])
                ->addToolInput($addBlockButton);

    echo $form->getOutput();
}
?>

<script>

    //This javascript is for the Date & Time Blocks
    var date = 'input[id*="Date"]';
    var time = 'input[id*="Time"]';

    $(document).on('click', '.addBlock', function () {
        $(date).removeClass('hasDatepicker').datepicker({'timeFormat': 'H:i', onSelect: function(){$(this).blur();}, onClose: function(){$(this).change();} });
        $(time).removeClass('hasTimepicker').timepicker({'timeFormat': 'H:i', onSelect: function(){$(this).blur();}, onClose: function(){$(this).change();} });
    });

    function setTimepicker(input) {
        input.removeClass('hasTimepicker').timepicker({
            'scrollDefault': 'now',
            'timeFormat': 'H:i',
            'minTime': '00:00',
            'maxTime': '23:59',
            onSelect: function(){$(this).blur();},
            onClose: function(){$(this).change();}
        });
    }

    $(document).ready(function(){

        $(radio + ':checked').each(coverSwap);

        $(date).removeClass('hasDatepicker').datepicker({onSelect: function(){$(this).blur();}, onClose: function(){$(this).change();} });

        //This is to ensure that loaded blocks have timepickers
        $(time).each(function() {
            setTimepicker($(this));
        });

        //Ensure that loaded dates have correct max and min dates.
        $('input[id^=startDate]').each(function() {
            var endDate = $('#' + $(this).prop('id').replace('start', 'end'));
        });

        //Ensure that loaded endTimes are properly chained.
        $('input[id^=endTime]').each(function() {
            var startTime = $('#' + $(this).prop('id').replace('end', 'start'));
            if (startTime.val() != "") {
                $(this).timepicker('option', {'minTime': startTime.val(), 'timeFormat': 'H:i', 'showDuration': true});
            }
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

    $(document).on('changeTime', 'input[id^=startTime]', function() {
        var endTime = $('#' + $(this).prop('id').replace('start', 'end'));
        if (endTime.val() == "" || $(this).val() > endTime.val()) {
            endTime.val($(this).val());
        }
        endTime.timepicker('option', {'minTime': $(this).val(), 'timeFormat': 'H:i', 'showDuration': true});
    });

    $(document).on('change', radio, coverSwap);

    //Javascript for Cover Amount
    function coverSwap() {
        var block = $(this).closest('tbody');
        if ($(this).prop('id').startsWith('coverRequiredN')) {
            block.find('.showHide').hide();
        } else {
            block.find('.showHide').show();
        }
    }

</script>
