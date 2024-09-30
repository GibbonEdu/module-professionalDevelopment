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

require_once __DIR__ . '/moduleFunctions.php';

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestsGateway;


$page->breadcrumbs->add(__('Today\'s Requests'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_reportToday.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $moduleName = $session->get('module');
  
    $date = !empty($_GET['date'])? $_GET['date'] : Format::date(date('Y-m-d'));

    // FILTER
    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/Professional Development/requests_reportToday.php');

    $row = $form->addRow();
        $row->addLabel('date', __('Date'));
        $row->addDate('date')->setValue($date);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($gibbon->session);

    echo $form->getOutput();

    // DATA TABLE
    $requestsGateway = $container->get(RequestsGateway::class);
    $criteria = $requestsGateway->newQueryCriteria(true)
      ->filterBy('tripDay', Format::dateConvert($date))
      ->filterBy('statuses', serialize([
        'Requested',
        'Approved',
        'Awaiting Final Approval'
      ]))
      ->fromPOST();

    $requests = $requestsGateway->queryRequests($criteria, $session->get('gibbonSchoolYearID'));

    $table = DataTable::createPaginated('todaysTrips', $criteria);
    $table->setTitle(__("Today's Trips"));
  
    $table->addExpandableColumn('description')
        ->format(function ($requests) {
            $output = '';

            $output .= formatExpandableSection(__('Description'), $requests['eventDescription']);

            return $output;
        });
  
    $table->addColumn('tripTitle', __('Title'));
    
    $table->addColumn('owner', __('Owner'))
        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]))
        ->sortable('surname');

    $table->addColumn('status', __('Status'));
  
    $table->addActionColumn()
        ->addParam('professionalDevelopmentRequestID')
        ->format(function ($requests, $actions) use ($moduleName) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/' . $moduleName . '/requests_View.php');
        });

    echo $table->render($requests);
}
