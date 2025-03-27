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
            'professionalDevelopmentRequestCostID', 'professionalDevelopmentRequestID', 'title', 'description', 'cost', 'quantity'
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

    public function getProfessionalDevelopmentBudget() {
        $data = ['name' => 'Professional Development'];
        $sql = "SELECT * FROM gibbonFinanceBudget WHERE gibbonFinanceBudget.name=:name";

        return $this->db()->selectOne($sql, $data);
    }

    public function getProfessionalDevelopmentBudgetAccess($gibbonFinanceBudgetID, $gibbonPersonID) {
        $data = ['gibbonFinanceBudgetID' => $gibbonFinanceBudgetID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT * FROM gibbonFinanceBudgetPerson WHERE gibbonFinanceBudgetPerson.gibbonPersonID=:gibbonPersonID AND gibbonFinanceBudgetPerson.gibbonFinanceBudgetID=:gibbonFinanceBudgetID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getCurrentBudgetCycleName($gibbonFinanceBudgetCycleID) {
        $data = ['gibbonFinanceBudgetCycleID' => $gibbonFinanceBudgetCycleID];
        $sql = "SELECT * FROM gibbonFinanceBudgetCycle WHERE gibbonFinanceBudgetCycleID=:gibbonFinanceBudgetCycleID";

        return $this->db()->selectOne($sql, $data);
    }

    public function addFinanceExpenseRecord($gibbonFinanceBudgetCycleID, $gibbonFinanceBudgetID, $title, $body, $status, $statusApprovalBudgetCleared, $cost, $countAgainstBudget, $purchaseBy, $purchaseDetails, $gibbonPersonIDCreator, $paymentDate, $paymentAmount, $gibbonPersonIDPayment, $paymentMethod, $attachment)
    {
        $data = ['gibbonFinanceBudgetCycleID' => $gibbonFinanceBudgetCycleID, 'gibbonFinanceBudgetID' => $gibbonFinanceBudgetID, 'title' => $title, 'body' => $body, 'status' => $status, 'statusApprovalBudgetCleared' => $statusApprovalBudgetCleared, 'cost' => $cost, 'countAgainstBudget' => $countAgainstBudget, 'purchaseBy' => $purchaseBy, 'purchaseDetails' => $purchaseDetails, 'gibbonPersonIDCreator' => $gibbonPersonIDCreator, 'paymentDate' => $paymentDate, 'paymentAmount' => $paymentAmount, 'gibbonPersonIDPayment' => $gibbonPersonIDPayment, 'paymentMethod' => $paymentMethod, 'paymentReimbursementReceipt' => $attachment, 'paymentReimbursementStatus' => 'Requested'];
        $sql = "INSERT INTO gibbonFinanceExpense SET gibbonFinanceBudgetCycleID=:gibbonFinanceBudgetCycleID, gibbonFinanceBudgetID=:gibbonFinanceBudgetID, title=:title, body=:body, status=:status, statusApprovalBudgetCleared=:statusApprovalBudgetCleared, cost=:cost, countAgainstBudget=:countAgainstBudget, purchaseBy=:purchaseBy, purchaseDetails=:purchaseDetails, gibbonPersonIDCreator=:gibbonPersonIDCreator, timestampCreator='".date('Y-m-d H:i:s')."', paymentDate=:paymentDate, paymentAmount=:paymentAmount, gibbonPersonIDPayment=:gibbonPersonIDPayment, paymentMethod=:paymentMethod, paymentReimbursementReceipt=:paymentReimbursementReceipt, paymentReimbursementStatus=:paymentReimbursementStatus";

        return $this->db()->statement($sql, $data);
    }

    public function addFinanceExpenseLogEntry($gibbonFinanceExpenseID, $gibbonPersonID, $action, $comment)
    {
        $data = ['gibbonFinanceExpenseID' => $gibbonFinanceExpenseID, 'gibbonPersonID' => $gibbonPersonID, 'action' => $action, 'comment' => $comment];
        $sql = "INSERT INTO gibbonFinanceExpenseLog SET gibbonFinanceExpenseID=:gibbonFinanceExpenseID, gibbonPersonID=:gibbonPersonID, timestamp='".date('Y-m-d H:i:s')."', action=:action, comment=:comment";
        
        return $this->db()->statement($sql, $data);
    }
}
