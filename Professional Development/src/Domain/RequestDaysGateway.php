<?php
namespace Gibbon\Module\ProfessionalDevelopment\Domain; 

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

/**
 * PD Request Days Gateway
 *
 * @version v28
 * @since   v28
 */
class RequestDaysGateway extends QueryableGateway 
{
    use TableAware; 
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

    public function deleteDatesNotInList($professionalDevelopmentRequestID, $dateIDList)
    {
        $dateIDList = is_array($dateIDList) ? implode(',', $dateIDList) : $dateIDList;
        
        $data = ['professionalDevelopmentRequestID' => $professionalDevelopmentRequestID, 'dateIDList' => $dateIDList];
        $sql = "DELETE FROM professionalDevelopmentRequestDays WHERE professionalDevelopmentRequestID=:professionalDevelopmentRequestID AND NOT FIND_IN_SET(professionalDevelopmentRequestDaysID, :dateIDList)";

        return $this->db()->delete($sql, $data);
    }
}
