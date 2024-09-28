<?php
namespace Gibbon\Module\ProfessionalDevelopment\Domain; 

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

/**
 * PD Request Cost Gateway
 *
 * @version v28
 * @since   v28
 */
class RequestCostGateway extends QueryableGateway 
{
    use TableAware;

    private static $tableName = 'professionalDevelopmentRequestCost'; 
    private static $primaryKey = 'professionalDevelopmentRequestCostID';
    private static $searchableColumns = [];

    public function queryRequestCost(QueryCriteria $criteria) {
        $query = $this->newQuery()
        ->from($this->getTableName())
        ->cols([
            'professionalDevelopmentRequestCostID', 'professionalDevelopmentRequestID', 'title', 'description', 'cost'
        ]);

        $criteria->addFilterRules([
            'professionalDevelopmentRequestID' => function ($query, $professionalDevelopmentRequestID) {
                return $query->where('professionalDevelopmentRequestCost.professionalDevelopmentRequestID = :professionalDevelopmentRequestID')
                    ->bindValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function deleteCostsNotInList($professionalDevelopmentRequestID, $costIDList)
    {
        $costIDList = is_array($costIDList) ? implode(',', $costIDList) : $costIDList;

        $data = ['professionalDevelopmentRequestID' => $professionalDevelopmentRequestID, 'costIDList' => $costIDList];
        $sql = "DELETE FROM professionalDevelopmentRequestCost WHERE professionalDevelopmentRequestID=:professionalDevelopmentRequestID AND NOT FIND_IN_SET(professionalDevelopmentRequestCostID, :costIDList)";

        return $this->db()->delete($sql, $data);
    }
}
