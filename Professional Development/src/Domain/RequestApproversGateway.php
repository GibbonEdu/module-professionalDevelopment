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
    private static $primaryKey = 'professionalDevelopmentRequestApproversID'; //The primary Key of said table
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

    public function selectStaffForApprover($ignore = true) {
        $select = $this
            ->newSelect()
            ->from('gibbonPerson')
            ->cols([
                'gibbonPerson.gibbonPersonID', 'title', 'surname', 'preferredName', 'username'
            ])
            ->innerJoin('gibbonStaff', 'gibbonStaff.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonPerson.status=:status')
            ->bindValue('status', 'Full')
            ->orderBy(['surname', 'preferredName']);

        if ($ignore) {
            $select->leftJoin($this->getTableName(), 'professionalDevelopmentRequestApprovers.gibbonPersonID=gibbonPerson.gibbonPersonID')
                ->where('professionalDevelopmentRequestApprovers.gibbonPersonID IS NULL');
        }

        $result = $this->runSelect($select);
        $users = array_reduce($result->fetchAll(), function ($group, $item) {
            $group[$item['gibbonPersonID']] = Format::name($item['title'], $item['preferredName'], $item['surname'], 'Staff', true, true) . ' (' . $item['username'] . ')';
            return $group;
        }, array());

        return $users;
    }

    public function insertApprover($gibbonPersonID, $finalApprover) {
        $select = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['MAX(sequenceNumber) + 1 as sequenceNumber']);
        $result = $this->runSelect($select);

        if ($result->rowCount() > 0) {
            $sequenceNumber = $result->fetch()['sequenceNumber'];
        } else {
            return false;
        }

        $this->insert(['gibbonPersonID' => $gibbonPersonID, 'sequenceNumber' => $sequenceNumber, 'finalApprover' => $finalApprover]);
        return true;
    }

    public function queryApprovers($critera) {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'professionalDevelopmentRequestApproversID', 'gibbonPerson.gibbonPersonID', 'title', 'preferredName', 'surname', 'sequenceNumber', 'finalApprover'
            ])
            ->leftJoin('gibbonPerson', 'professionalDevelopmentRequestApprovers.gibbonPersonID=gibbonPerson.gibbonPersonID');

        return $this->runQuery($query, $critera);
    }

    public function updateSequence($order) {
        $this->db()->beginTransaction();

        for ($count = 0; $count < count($order); $count++) {
            if (!$this->update($order[$count], ['sequenceNumber' => $count])) {
                $this->db()->rollback();
                return false;
            }
        }

        $this->db()->commit();
        return true;
    }

}
