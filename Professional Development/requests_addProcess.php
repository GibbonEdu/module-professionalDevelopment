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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Data\Validator;
use Gibbon\Services\Format;

require_once '../../gibbon.php';
require_once  './moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML']);

$URL = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/Professional Development/requests_add.php';

$gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_add.php')) {
    // Access denied
    $URL = $URL.'&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed!
    $thing = $_POST['thing']; // The variables you will be processing

    // Check that your required variables are present
    if (empty($thing)) { 
        $URL = $URL.'&return=error3';
        header("Location: {$URL}");
        exit;
    } 
    
    // Your SQL or Gateway insert query
    $URL .= "&return=success0&editID=$AI";
    header("Location: {$URL}");
}
