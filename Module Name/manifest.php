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
along with this program.  If not, see <http:// www.gnu.org/licenses/>.
*/

// This file describes the module, including database tables

// Basic variables
$name        = '';            // The name of the module as it appears to users. Needs to be unique to installation. Also the name of the folder that holds the unit.
$description = '';            // Short text description
$entryURL    = "index.php";   // The landing page for the unit, used in the main menu
$type        = "Additional";  // Do not change.
$category    = '';            // The main menu area to place the module in
$version     = '';            // Version number
$author      = '';            // Your name
$url         = '';            // Your URL

// Module tables & gibbonSettings entries
$moduleTables[] = ''; // One array entry for every database table you need to create. Might be nice to preface the table name with the module name, to keep the db neat. 
$moduleTables[] = ''; // Also can be used to put data into gibbonSettings. Other sql can be run, but resulting data will not be cleaned up on uninstall.

// Add gibbonSettings entries
$gibbonSetting[] = "";

// Action rows 
// One array per action
$actionRows[] = [
    'name'                      => '', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => '', // Optional: subgroups for the right hand side module menu
    'description'               => '', // Text description
    'URLList'                   => '', // List of pages included in this action
    'entryURL'                  => '', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'Y', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'Y', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'Y', // Should this action be available to user roles in the Other category?
];

// Hooks
$hooks[] = ''; // Serialised array to create hook and set options. See Hooks documentation online.
