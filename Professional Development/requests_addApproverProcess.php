<?php

use Gibbon\Module\ProfessionalDevelopment\Domain\RequestApproversGateway;
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

require_once '../../gibbon.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module');

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_addApprover.php')) {
    //Acess denied
    $URL .= '/requests_manageApprovers.php&return=error0';
    header("Location: {$URL}");
    exit();
}
else {
    $URL .= '/requests_addApprover.php';

    $requestApproversGateway = $container->get(RequestApproversGateway::class);

    $gibbonPersonID = $_POST['gibbonPersonID'] ?? '';

    if (empty($gibbonPersonID) || !$requestApproversGateway->unique(['gibbonPersonID' => $gibbonPersonID], ['gibbonPersonID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit();
    } else {
        $finalApprover = isset($_POST['finalApprover']) ? 1 : 0;

        if ($requestApproversGateway->insertApprover($gibbonPersonID, $finalApprover)) {
            $URL .= '&return=success0';
        } else {
            $URL .= '&return=error2';
        }

        header("Location: {$URL}");
        exit();
    }
}
?>
