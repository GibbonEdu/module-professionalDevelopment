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

use Gibbon\Data\Validator;
use Gibbon\Module\ProfessionalDevelopment\Domain\PortfolioGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\PortfolioTagGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);
$professionalDevelopmentPortfolioID = $_POST['professionalDevelopmentPortfolioID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Professional Development/pd_portfolio_editRecord.php&professionalDevelopmentPortfolioID='. $professionalDevelopmentPortfolioID;

if (isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_portfolio_manage.php') == false) {    
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $portfolioGateway = $container->get(PortfolioGateway::class);
    $portfolioTagGateway = $container->get(PortfolioTagGateway::class);

    $highestAction = getHighestGroupedAction($guid, '/modules/Professional Development/pd_portfolio_manage.php', $connection2);
    $portfolioRecord = $portfolioGateway->getByID($professionalDevelopmentPortfolioID);

    if ($highestAction != 'Manage Portfolio_full' && $portfolioRecord['gibbonPersonID'] != $session->get('gibbonPersonID')) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }
    
    $data = [
        'role'                              => $_POST['role'],
        'type'                              => $_POST['type'],
        'title'                             => $_POST['title'],
        'completionDate'                    => $_POST['completionDate'],
        'timeSpent'                         => $_POST['timeSpent'],
        'keyFocus'                          => $_POST['keyFocus'],
        'keyTakeaways'                      => $_POST['keyTakeaways'],
        'resourcesLinks'                    => $_POST['resourcesLinks']
    ];

    // Ensure tags are uppercase and trimmed
    if (!empty($data['keyFocus'])) {
        $data['keyFocus'] = implode(',', array_filter(array_map(function ($item) {
            return trim(ucwords($item));
        }, explode(',', $data['keyFocus']))));
    }

    // Validate the required values are present
    if (empty($professionalDevelopmentPortfolioID) || empty($data['title']) ) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$portfolioGateway->exists($professionalDevelopmentPortfolioID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $portfolioGateway->update($professionalDevelopmentPortfolioID, $data);
    $partialFail = !$updated;
    
    // Update the tags
    $tags = array_unique(array_filter(array_merge(explode(',', $data['keyFocus'] ?? ''), explode(',', $data['keyFocus'] ?? ''))));
    foreach ($tags as $tag) {
        $portfolioTagGateway->insertAndUpdate(['tag' => $tag], ['tag' => $tag]);
    }

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}