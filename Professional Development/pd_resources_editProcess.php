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
use Gibbon\Module\ProfessionalDevelopment\Domain\StaffResourceTagGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['content' => 'URL']);

$professionalDevelopmentResourceID = $_POST['professionalDevelopmentResourceID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/pd_resources_edit.php&professionalDevelopmentResourceID='.$professionalDevelopmentResourceID;

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_resources_edit.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    if (empty($_POST)) {
        $URL .= '&return=warning1';
        header("Location: {$URL}");
        exit;
    }

    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if (empty($highestAction)) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    if (empty($professionalDevelopmentResourceID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $staffResourceGateway = $container->get(StaffResourceGateway::class);
    $staffResourceTagGateway = $container->get(StaffResourceTagGateway::class);

    // Verify the record exists and the user has permission to edit it
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

    $resourceData = [
        'name'                   => $_POST['name'] ?? '',
        'description'            => $_POST['description'] ?? '',
        'category'               => $_POST['category'] ?? '',
        'purpose'                => $_POST['purpose'] ?? '',
        'content'                => $_POST['content'] ?? '',
        'gibbonPersonIDModified' => $session->get('gibbonPersonID'),
        'timestampModified'      => date('Y-m-d H:i:s'),
    ];

    if (empty($resourceData['name']) || empty($resourceData['category']) || empty($resourceData['purpose']) || empty($resourceData['content'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Normalise tags: trim whitespace, title-case each entry, store as CSV
    $tagList = '';
    if (!empty($_POST['tags'])) {
        $tagList = implode(',', array_filter(array_map(function ($tag) {
            return trim(ucwords($tag));
        }, explode(',', $_POST['tags']))));
    }
    $resourceData['tags'] = $tagList;

    $updated = $staffResourceGateway->update($professionalDevelopmentResourceID, $resourceData);

    if (!$updated) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Sync each tag into the tag lookup table
    $tags = array_unique(array_filter(explode(',', $tagList)));
    foreach ($tags as $tag) {
        $staffResourceTagGateway->insertAndUpdate(['tag' => $tag], ['tag' => $tag]);
    }

    $URL .= '&return=success0';
    header("Location: {$URL}");
}