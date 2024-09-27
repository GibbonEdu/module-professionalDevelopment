<?php
namespace Gibbon\Module\ProfessionalDevelopment\Domain; 

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Module\ProfessionalDevelopment\Domain\Traits\BulkInsert;

/**
 * PD Requests Gateway
 *
 * @version v28
 * @since   v28
 */
class RequestCostGateway extends QueryableGateway 
{
    use TableAware;
    use BulkInsert;

    private static $tableName = 'professionalDevelopmentRequestCost'; 
    private static $primaryKey = 'professionalDevelopmentRequesCostID'; //The primaryKey of said table
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
}
