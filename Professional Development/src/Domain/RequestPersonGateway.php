<?php
namespace Gibbon\Module\ProfessionalDevelopment\Domain; 

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

/**
 * PD Requests Gateway
 *
 * @version v28
 * @since   v28
 */
class RequestPersonGateway extends QueryableGateway 
{
    use TableAware;

    private static $tableName = 'professionalDevelopmentRequestPerson'; 
    private static $primaryKey = 'professionalDevelopmentRequestPersonID'; //The primaryKey of said table
    private static $searchableColumns = [];

    public function queryRequestPeople(QueryCriteria $criteria) {
        $query = $this->newQuery()
        ->from($this->getTableName())
        ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = professionalDevelopmentRequestPerson.gibbonPersonID')
        ->cols([
            'gibbonPerson.gibbonPersonID', 'professionalDevelopmentRequestPerson.professionalDevelopmentRequestPersonID', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonPerson.image_240',
            'professionalDevelopmentRequestPerson.professionalDevelopmentRequestID'
        ]);

        $criteria->addFilterRules([
            'professionalDevelopmentRequestID' => function ($query, $professionalDevelopmentRequestID) {
                return $query->where('professionalDevelopmentRequestPerson.professionalDevelopmentRequestID = :professionalDevelopmentRequestID')
                    ->bindValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function isInvolved($professionalDevelopmentRequestID, $gibbonPersonID) {
        $result = $this->selectBy([
            'professionalDevelopmentRequestID'  => $professionalDevelopmentRequestID,
            'gibbonPersonID'        => $gibbonPersonID
        ]);

        return $result->isNotEmpty();
    }

    public function deleteParticipantsNotInList($professionalDevelopmentRequestID, $participantIDList)
    {
        $participantIDList = is_array($participantIDList) ? implode(',', $participantIDList) : $participantIDList;
        
        $data = ['professionalDevelopmentRequestID' => $professionalDevelopmentRequestID, 'participantIDList' => $participantIDList];
        $sql = "DELETE FROM professionalDevelopmentRequestPerson WHERE professionalDevelopmentRequestID=:professionalDevelopmentRequestID AND NOT FIND_IN_SET(professionalDevelopmentRequestPersonID, :participantIDList)";

        return $this->db()->delete($sql, $data);
    }

}
