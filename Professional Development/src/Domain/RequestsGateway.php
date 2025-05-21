<?php
namespace Gibbon\Module\ProfessionalDevelopment\Domain; 

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * PD Requests Gateway
 *
 * @version v28
 * @since   v28
 */
class RequestsGateway extends QueryableGateway 
{
    use TableAware;

    private static $tableName = 'professionalDevelopmentRequests'; 
    private static $primaryKey = 'professionalDevelopmentRequestID';
    private static $searchableColumns = ['professionalDevelopmentRequests.eventTitle', 'professionalDevelopmentRequests.eventType', 'professionalDevelopmentRequests.eventFocus', 'gibbonPerson.preferredName', 'gibbonPerson.surname'];

    public function queryRequests(QueryCriteria $criteria, $gibbonSchoolYearID, $gibbonPersonID = null, $gibbonDepartmentID = null, $expiredUnapproved = null) {
        
        $query = $this
        ->newQuery()
        ->from('professionalDevelopmentRequests')
        ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = professionalDevelopmentRequests.gibbonPersonIDCreated')
        ->cols([
        'professionalDevelopmentRequests.professionalDevelopmentRequestID',
        'professionalDevelopmentRequests.gibbonPersonIDCreated',
        'professionalDevelopmentRequests.eventTitle as eventTitle',
        'professionalDevelopmentRequests.eventDescription',
        'professionalDevelopmentRequests.eventLocation',
        'professionalDevelopmentRequests.expenseRequest',
        'professionalDevelopmentRequests.status',
        'gibbonPerson.title',
        'professionalDevelopmentRequests.eventType',
        'professionalDevelopmentRequests.eventFocus',
        'gibbonPerson.preferredName',
        'gibbonPerson.surname',
        '(SELECT date FROM professionalDevelopmentRequestDays WHERE professionalDevelopmentRequestID = professionalDevelopmentRequests.professionalDevelopmentRequestID ORDER BY date ASC LIMIT 1) as firstDayOfTrip',
        ])
        ->leftJoin('professionalDevelopmentRequestPerson', "professionalDevelopmentRequestPerson.professionalDevelopmentRequestID = professionalDevelopmentRequests.professionalDevelopmentRequestID AND professionalDevelopmentRequestPerson.gibbonPersonID = :gibbonPersonID")
        ->where('gibbonSchoolYearID=:gibbonSchoolYearID')
        ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->bindValue('gibbonPersonID', $gibbonPersonID);

         // A user has been specified, so Filter only my requests and involved trips for this user
         if (!empty($gibbonPersonID)) {
            $query->where('(professionalDevelopmentRequests.gibbonPersonIDCreated = :gibbonPersonID OR professionalDevelopmentRequestPerson.professionalDevelopmentRequestPersonID IS NOT NULL)');
        }

        if (!empty($gibbonDepartmentID)) {
            $query->innerJoin('gibbonDepartmentStaff', 'gibbonDepartmentStaff.gibbonPersonID = professionalDevelopmentRequests.gibbonPersonIDCreated')
                ->where('gibbonDepartmentStaff.gibbonDepartmentID = :departmentID')
                ->bindValue('departmentID', $gibbonDepartmentID);
        }

        $criteria->addFilterRules([
            'status' => function($query, $status) {
                return $query->where('professionalDevelopmentRequests.status = :status')
                    ->bindValue('status', $status);
            },
            'showActive' => function($query, $expiredUnapproved) {
                if ($expiredUnapproved == 'Y' ) {
                    $query->where("NOT (
                        (SELECT IFNULL(MAX(date),'0000-00-00') FROM professionalDevelopmentRequestDays WHERE professionalDevelopmentRequestID = professionalDevelopmentRequests.professionalDevelopmentRequestID) < CURRENT_DATE 
                        AND (professionalDevelopmentRequests.status = 'Cancelled' OR professionalDevelopmentRequests.status = 'Rejected')
                        )");
                }
                return $query;
            },

            'statuses' => function ($query, $statuses) {
                $statuses = unserialize($statuses);

                if (!is_array($statuses)) {
                    $statuses = array($statuses);
                }

                $inClause = '';
                foreach ($statuses as $key => $status) {
                    $bind = 'status' . $key;
                    $inClause .= ($key > 0 ? ',' : '') . ':' . $bind;
                    $query->bindValue($bind, $status);
                }

                return $query->where('professionalDevelopmentRequests.status IN (' . $inClause . ')'); 
            },

            'year' => function ($query, $gibbonSchoolYearID) {
                return $query->where('professionalDevelopmentRequests.gibbonSchoolYearID = :gibbonSchoolYearID')
                ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
            },

            'tripDay' => function($query, $queryDate) {
                return $query->innerJoin('professionalDevelopmentRequestDays','professionalDevelopmentRequestDays.professionalDevelopmentRequestID = professionalDevelopmentRequests.professionalDevelopmentRequestID')
                    ->where('professionalDevelopmentRequestDays.date = :queryDate')
                    ->bindValue('queryDate',$queryDate);
            },
        ]);

        return $this->runQuery($query, $criteria);

    }

    public function selectParticipantsByRequest($professionalDevelopmentRequestIDList, $allFields = false) {
        $professionalDevelopmentRequestIDList = is_array($professionalDevelopmentRequestIDList) ? implode(',', $professionalDevelopmentRequestIDList) : $professionalDevelopmentRequestIDList;

        $query = $this
            ->newSelect()
            ->cols($allFields
                ? ['professionalDevelopmentRequestPerson.professionalDevelopmentRequestID', 'professionalDevelopmentRequestPerson.*', 'gibbonPerson.*']
                : ['professionalDevelopmentRequestPerson.professionalDevelopmentRequestID', 'professionalDevelopmentRequestPerson.*', 'gibbonPerson.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonPerson.email'])
            ->from('professionalDevelopmentRequestPerson')
            ->innerJoin('gibbonPerson', 'professionalDevelopmentRequestPerson.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('FIND_IN_SET(professionalDevelopmentRequestPerson.professionalDevelopmentRequestID, :professionalDevelopmentRequestIDList)')
            ->bindValue('professionalDevelopmentRequestIDList', $professionalDevelopmentRequestIDList)
            ->orderBy(['gibbonPerson.surname', 'gibbonPerson.preferredName']);

        return $this->runSelect($query);
    }
    
    public function beginTransaction() {
        $this->db()->beginTransaction();
    }

    public function commit() {
        $this->db()->commit();
    }

    public function rollBack() {
        $this->db()->rollBack();
    }
}
