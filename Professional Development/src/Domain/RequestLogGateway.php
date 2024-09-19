<?php
namespace Gibbon\Module\ProfessionalDevelopment\Domain; 

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 *
 * @version v28
 * @since   v28
 */
class RequestLogGateway extends QueryableGateway 
{
    use TableAware;

    private static $tableName = 'professionalDevelopmentRequestLog'; 
    private static $primaryKey = 'professionalDevelopmentRequestLogID';
    private static $searchableColumns = []; 

    public function queryRequestLogs(QueryCriteria $criteria) {
        $query = $this->newQuery()
        ->from($this->getTableName())
        ->leftJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = professionalDevelopmentRequestLog.gibbonPersonID')
        ->cols([
            'professionalDevelopmentRequestLogID', 'professionalDevelopmentRequestID', 'professionalDevelopmentRequestLog.requestStatus', 'professionalDevelopmentRequestLog.comment', 'professionalDevelopmentRequestLog.timestamp',
            'professionalDevelopmentRequestLog.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname'
        ]);

        $criteria->addFilterRules([
            'professionalDevelopmentRequestID' => function ($query, $professionalDevelopmentRequestID) {
                return $query->where('professionalDevelopmentRequestLog.professionalDevelopmentRequestID = :professionalDevelopmentRequestID')
                    ->bindValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
            },
            'requestStatus' => function ($query, $requestStatus) {
                return $query->where('professionalDevelopmentRequestLog.requestStatus = :requestStatus')
                    ->bindValue('requestStatus', $requestStatus);
            },
            'gibbonPersonID' => function($query, $gibbonPersonID) {
                return $query->where('professionalDevelopmentRequestLog.gibbonPersonID = :gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            }
        ]);

        return $this->runQuery($query, $criteria);
    }


}
