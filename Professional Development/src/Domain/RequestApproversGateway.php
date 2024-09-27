<?php
namespace Gibbon\Module\ProfessionalDevelopment\Domain; 

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Services\Format;

/**
 * PD Request Approvers Gateway
 *
 * @version v28
 * @since   v28
 */
class RequestApproversGateway extends QueryableGateway 
{
    use TableAware;

    private static $tableName = 'professionalDevelopmentRequestApprovers'; 
    private static $primaryKey = 'professionalDevelopmentRequestRequestApproversID'; //The primaryKey of said table
    private static $searchableColumns = [];

    public function selectApproverByPerson($gibbonPersonID) {
        $approver = $this->selectBy(['gibbonPersonID' => $gibbonPersonID]);
        return $approver->isNotEmpty() ? $approver->fetch() : [];
    }

    public function selectNextApprover($professionalDevelopmentRequestID, $gibbonPersonID = null) { 
        $select = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonPersonID'
            ])
            ->where('sequenceNumber > (
                SELECT COALESCE(MAX(`professionalDevelopmentRequestApprovers`.`sequenceNumber`), -1)
                FROM `professionalDevelopmentRequestLog`
                LEFT JOIN `professionalDevelopmentRequestApprovers` ON (`professionalDevelopmentRequestApprovers`.`gibbonPersonID` = professionalDevelopmentRequestLog.gibbonPersonID)
                WHERE `professionalDevelopmentRequestID` = :professionalDevelopmentRequestID
                AND `requestStatus`=\'Approval - Partial\')')
            ->bindValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID)
            ->orderBy(['sequenceNumber'])
            ->limit(1);

        if (!empty($gibbonPersonID)) {
            $select->where('gibbonPersonID <> :gibbonPersonID')
                ->bindValue('gibbonPersonID', $gibbonPersonID);
        }

        return $this->runSelect($select);
    }
}
