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

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Http\Url;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/requests_add.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
   // For a form
   // Check out https:// gist.github.com/SKuipers/3a4de3a323ab9d0969951894c29940ae for a cheatsheet / guide

    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Requests'), 'requests_manage.php')
        ->add(__('Submit Request'));

   if (isset($_GET['editID'])) {
      $page->return->setEditLink($session->get('absoluteURL').'/index.php?q=/modules/Professional Development/requests_edit.php&professionalDevelopmentRequestID='.$_GET['editID']);
   }
 
   $form = Form::create('request', $session->get('absoluteURL').'/modules/'.$session->get('module').'/requests_addProcess.php');
   $form->setFactory(DatabaseFormFactory::create($pdo));

   
}	
