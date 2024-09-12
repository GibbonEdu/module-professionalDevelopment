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
class RequestDaysGateway extends QueryableGateway 
{
    use TableAware;
    use BulkInsert;

    private static $tableName = 'professionalDevelopmentRequestDays'; 
    private static $primaryKey = 'professionalDevelopmentRequestDaysID'; //The primaryKey of said table
    private static $searchableColumns = [];

    public function queryRequestDays(QueryCriteria $criteria) {
        $query = $this->newQuery()
        ->from($this->getTableName())
        ->cols([
            'professionalDevelopmentRequestDaysID', 'professionalDevelopmentRequestID', 'startDate', 'endDate', 'allDay'
        ]);

        $criteria->addFilterRules([
            'professionalDevelopmentRequestID' => function ($query, $professionalDevelopmentRequestID) {
                return $query->where('professionalDevelopmentRequestDays.professionalDevelopmentRequestID = :professionalDevelopmentRequestID ')
                    ->bindValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }    
}
