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
use Gibbon\Module\ProfessionalDevelopment\Domain\StaffResourceGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$professionalDevelopmentResourceID = $_POST['professionalDevelopmentResourceID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/pd_resources_manage.php';

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_resources_delete.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($professionalDevelopmentResourceID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if (empty($highestAction)) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $staffResourceGateway = $container->get(StaffResourceGateway::class);

    if ($highestAction == 'Manage Resources_all') {
        $existing = $staffResourceGateway->getByID($professionalDevelopmentResourceID);
    } else {
        $result = $staffResourceGateway->selectBy([
            'professionalDevelopmentResourceID' => $professionalDevelopmentResourceID,
            'gibbonPersonIDCreated'             => $session->get('gibbonPersonID'),
        ]);
        $existing = $result->isNotEmpty() ? $result->fetch() : null;
    }

    if (empty($existing)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $deleted = $staffResourceGateway->delete($professionalDevelopmentResourceID);

    $URL .= !$deleted ? '&return=error2' : '&return=success0';
    header("Location: {$URL}");
}