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

namespace Gibbon\Module\ProfessionalDevelopment\Domain; 

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

/**
 * PD Portfolio Gateway
 *
 * @version v28
 * @since   v28
 */
class PortfolioGateway extends QueryableGateway 
{
    use TableAware; 
    private static $tableName = 'professionalDevelopmentPortfolio'; 
    private static $primaryKey = 'professionalDevelopmentPortfolioID';
    private static $searchableColumns = ['professionalDevelopmentPortfolio.title', 'type', 'role', 'keyFocus'];

    public function queryPortfolio(QueryCriteria $criteria, $gibbonSchoolYearID, $gibbonPersonID = null) {
        $query = $this->newQuery()
        ->from($this->getTableName())
        ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = professionalDevelopmentPortfolio.gibbonPersonID')
        ->cols([
        'professionalDevelopmentPortfolio.*',
        'professionalDevelopmentPortfolio.title as recordTitle',
        'gibbonPerson.title',
        'gibbonPerson.preferredName',
        'gibbonPerson.surname',
        ])
        ->where('professionalDevelopmentPortfolio.gibbonSchoolYearID=:gibbonSchoolYearID')
        ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);


        if (!empty($gibbonPersonID)) {
            $query->where('professionalDevelopmentPortfolio.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);
        }

        $criteria->addFilterRules([
            'year' => function ($query, $gibbonSchoolYearID) {
                return $query->where('professionalDevelopmentPortfolio.gibbonSchoolYearID = :gibbonSchoolYearID')
                ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectCoordinatorByDepartmentID($gibbonDepartmentID)
    {
        $data = ['gibbonDepartmentID' => $gibbonDepartmentID, 'role' => 'Coordinator'];
        $sql = "SELECT gibbonDepartmentStaff.gibbonPersonID FROM gibbonDepartment JOIN gibbonDepartmentStaff ON (gibbonDepartmentStaff.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID) WHERE gibbonDepartment.gibbonDepartmentID=:gibbonDepartmentID AND gibbonDepartmentStaff.role=:role";

        return $this->db()->select($sql, $data);
    }
}
