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
 *
 * @version v31
 * @since   v31
 */
class StaffResourceGateway extends QueryableGateway 
{
    use TableAware; 
    private static $tableName = 'professionalDevelopmentResource'; 
    private static $primaryKey = 'professionalDevelopmentResourceID';
    private static $searchableColumns = ['professionalDevelopmentResource.name', 'professionalDevelopmentResource.description', 'professionalDevelopmentResource.tags'];

    public function queryResources(QueryCriteria $criteria, $gibbonPersonID = null)
    {
        $query = $this->newQuery()
            ->from($this->getTableName())
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = professionalDevelopmentResource.gibbonPersonIDCreated')
            ->cols([
                'professionalDevelopmentResource.*',
                'gibbonPerson.title',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ]);

        if (!empty($gibbonPersonID)) {
            $query->where('professionalDevelopmentResource.gibbonPersonIDCreated = :gibbonPersonID')
                ->bindValue('gibbonPersonID', $gibbonPersonID);
        }

        $criteria->addFilterRules([
            'category' => function ($query, $category) {
                return $query
                    ->where('professionalDevelopmentResource.category = :category')
                    ->bindValue('category', $category);
            },
            'purpose' => function ($query, $purpose) {
                return $query
                    ->where('professionalDevelopmentResource.purpose = :purpose')
                    ->bindValue('purpose', $purpose);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
}
