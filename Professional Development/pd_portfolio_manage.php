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
use Gibbon\Module\ProfessionalDevelopment\Domain\PortfolioGateway;

$page->breadcrumbs->add(__('Manage Portfolio'));

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_portfolio_manage.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
    // Proceed
	$highestAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_portfolio_manage.php', $connection2);

	if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;   
    }

    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    $gibbonPersonID = $session->get('gibbonPersonID');
    $search = $_POST['search'] ?? '';

    // SEARCH
    $form = Form::create('search', $session->get('absoluteURL').'/index.php?q='.$_GET['q']);
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setTitle(__('Search'));
    $form->setClass('noIntBorder fullWidth');

    $row = $form->addRow();
        $row->addLabel('search', 'Search For')->description(__('Title, type, role, key focus'));
        $row->addTextField('search')->setValue($search);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));
        
    echo $form->getOutput();

    // Query For DATA TABLE
    $portfolioGateway = $container->get(PortfolioGateway::class);
    $criteria = $portfolioGateway->newQueryCriteria(true)
        ->searchBy($portfolioGateway->getSearchableColumns(), $search)
        ->sortBy('completionDate', 'DESC')
        ->fromPOST();

    $gibbonPersonIDFilter = $highestAction == 'Manage Portfolio_full' ? null : $gibbonPersonID;
    $portfolio = $portfolioGateway->queryPortfolio($criteria, $gibbonSchoolYearID, $gibbonPersonIDFilter);

    // Portfolio Records Data Table
    $table = DataTable::createPaginated('portfolio', $criteria);
    $table->setTitle($highestAction == 'Manage Portfolio_full' ? __('All Portfolio Records') : __('My Portfolio'));

    $table->addMetaData('post', ['gibbonSchoolYearID' => $gibbonSchoolYearID]);

    $table->addMetaData('filterOptions', [
        'type:Internal' => __('Type').': '.__('Internal'),
        'type:External - Local'  => __('Type').': '.__('External - Local'),
        'type:External - Overseas'  => __('Type').': '.__('External - Overseas'),
        'role:Attendee' => __('Role').': '.__('Attendee'),
        'role:Presenter' => __('Role').': '.__('Presenter'),
        'role:Organiser' => __('Role').': '.__('Organiser'),
        'role:Other' => __('Role').': '.__('Other'),
    ]);
    
    $table->addHeaderAction('add', __('New Record'))
        ->displayLabel()
        ->setURL('/modules/Professional Development/pd_portfolio_addRecord.php');

    $table->addExpandableColumn('contents')
        ->format(function ($record) {
            $output = '<h6>' . 'Key Takeaways' . '</h6></br>';
            $output .= nl2br($record['keyTakeaways']);
            return $output;
        });

    $table->addColumn('recordTitle', __('Title'));
    $table->addColumn('type', __('type'));
    
    $table->addColumn('gibbonPersonID', __('Staff'))
        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]))
        ->sortable('surname');

    $table->addColumn('role', __('Role'));

    $table->addColumn('completionDate', __('Date of Completion'))
        ->format(Format::using('dateReadable', ['completionDate']));

    $table->addColumn('role', __('Role'));

    $table->addColumn('timeSpent', __('Time Spent'))
        ->format(function($record) {
            $output = number_format(floatval($record['timeSpent']), 2).' '.__('hours');
            return $output;
    });

    $table->addActionColumn()
        ->addParam('professionalDevelopmentPortfolioID')
        ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->format(function ($record, $actions) use ($gibbonPersonID, $highestAction) {
            if ($highestAction == 'Manage Portfolio_full' || $gibbonPersonID == $record['gibbonPersonID']) {
                $actions->addAction('edit', __('Edit'))
                    ->addParam('mode', 'edit')
                    ->setURL('/modules/Professional Development/pd_portfolio_editRecord.php');
            }

            $actions->addAction('view', __('View Details'))
            ->setURL('/modules/Professional Development/pd_portfolio_editRecord.php');
    });

    echo $table->render($portfolio);
}



