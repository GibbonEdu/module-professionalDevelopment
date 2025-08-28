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
use Gibbon\Module\ProfessionalDevelopment\Domain\PortfolioGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\PortfolioTagGateway;

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_portfolio_manage.php')) {
	$page->addError(__('You do not have access to this action.'));
} else {
    // Proceed  
    $highestManageAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_portfolio_manage.php', $connection2);
    
    $portfolioGateway = $container->get(PortfolioGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    $professionalDevelopmentPortfolioID = $_GET['professionalDevelopmentPortfolioID'] ?? '';
    $mode = $_REQUEST['mode'] ?? '';
    $edit = false;
    $gibbonPersonID = $session->get('gibbonPersonID') ?? '';
    
    if (!empty($professionalDevelopmentPortfolioID) && $portfolioGateway->exists($professionalDevelopmentPortfolioID)) {
        $portfolioRecord = $portfolioGateway->getByID($professionalDevelopmentPortfolioID);
    } else {
        $page->addError('The specified record cannot be found.');
        return;
    }
    
    if(!empty($mode) && $mode == 'edit') {
    $edit = $highestManageAction == 'Manage Portfolio_full' || $portfolioRecord['gibbonPersonID'] == $gibbonPersonID;
    }

    $page->breadcrumbs->add(__('Manage Portfolio'), 'pd_portfolio_manage.php')
    ->add($edit ? __('Edit Record') : __('View Record'));
    
    $formLink = $edit ? $session->get('absoluteURL') . '/modules/Professional Development/pd_portfolio_editRecordProcess.php' : $session->get('absoluteURL') . '/modules/Professional Development/pd_portfolio_editRecord.php&professionalDevelopmentPortfolioID='. $professionalDevelopmentPortfolioID;
     
    $form = Form::create('recordForm', $formLink);
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('professionalDevelopmentPortfolioID', $professionalDevelopmentPortfolioID);

    $form->setTitle($edit ? __('Edit Record') : __('View Record'));

    $row = $form->addRow();
        $row->addHeading(__('Record Details', __('Record Details')));

    $pdTypes = $settingGateway->getSettingByScope('Professional Development', 'pdTypes');
    $row = $form->addRow();
        $row->addLabel('type', __('PD Type'));
        $row->addSelect('type')->fromString($pdTypes)->required()->readonly(!$edit);

    $row = $form->addRow();
        $row->addLabel('title', __('PD Title'));
        $row->addTextField('title')
            ->required()
            ->readonly(!$edit);

    $applicant = $container->get(UserGateway::class)->getByID($portfolioRecord['gibbonPersonID'], ['preferredName', 'surname']);
    
    $row = $form->addRow();
        $row->addLabel('applicant', __('Applicant'));
            $row->addContent(Format::nameLinked($portfolioRecord['gibbonPersonID'], '', $applicant['preferredName'], $applicant['surname'], 'Staff', false, true))
                ->wrap('<div class="text-left w-full text-sm">', '</div>');
                
    $participantRoles = $settingGateway->getSettingByScope('Professional Development', 'participantRoles');
    $row = $form->addRow();
        $row->addLabel('role', __('PD Role'));
        $row->addSelect('role')->fromString($participantRoles)->required()->readonly(!$edit);
    
    $row = $form->addRow();
        $row->addLabel('completionDate', __('Date of Completion'))->description(__('Last date of the activity'));
        $row->addDate('completionDate')
            ->setValue($portfolioRecord['completionDate'] ?? '')->required()
            ->readonly(!$edit)
            ->placeholder(__('Date'))
            ->setClass('w-auto');

    if (isset($portfolioRecord['timeSpent'])) {
    $portfolioRecord['timeSpent'] = number_format((float)$portfolioRecord['timeSpent'], 2, '.', '');
    }

    $row = $form->addRow();
        $row->addLabel('timeSpent', __('Time spent (Hours)'));
        $row->addNumber('timeSpent')->decimalPlaces(2)->minimum(0)->maximum(999)->maxLength(3)
            ->required()
            ->readonly(!$edit);
            
    $tags = $container->get(PortfolioTagGateway::class)->selectAllKeyFocusTags()->fetchAll(\PDO::FETCH_COLUMN);
    
    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('keyFocus', __('Key Focus'));
        $col->addFinder('keyFocus')
            ->fromArray($tags)
            ->setParameter('hintText', __('Type a tag...'))
            ->setParameter('allowFreeTagging', true)
            ->readonly(!$edit);

    $row = $form->addRow();
            $row->addLabel('resourcesLinks', __('Resource Link'))->description(__('Share the resource/website link.'));

    if (!$edit && !empty($portfolioRecord['resourcesLinks'])) {
        $row->addContent(Format::link($portfolioRecord['resourcesLinks'], __('View Resource'), ['target' => '_blank']));
    } else {
        $row->addURL('resourcesLinks')
            ->readonly(!$edit);
    }

    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('keyTakeaways', __m('Key Takeaways'))->description(__('What are your key takeaways from this activity?'));
        $col->addTextArea('keyTakeaways')->setRows(3)
            ->required()
            ->readonly(!$edit);

    if ($edit) {    
        $row = $form->addRow('stickySubmit');
        $col = $row->addColumn()->addClass('items-center');
        $col->addSubmit();
    }

    $form->loadAllValuesFrom($portfolioRecord);
    echo $form->getOutput();
}
?>
