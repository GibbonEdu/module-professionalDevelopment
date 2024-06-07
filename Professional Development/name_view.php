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

use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Module Name/name_view.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
    // SQL or Gateway query, as a dataset
    // For a OO datatable, see https:// gist.github.com/SKuipers/e176454a2feb555126c2147865bd0626
    // Don't forget to put header and column actions if you're using add/edit/delete pages AND include the ID/primary key as a param
}	
