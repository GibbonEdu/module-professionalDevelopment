<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Finance\FinanceBudgetCycleGateway;
use Gibbon\Domain\Finance\FinanceExpenseApproverGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestCostGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_manage.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Applications'), 'pd_manage.php')
        ->add(__('Add Expense Request'));

    $page->return->addReturns(['success1' => __('Your request was completed successfully, but notifications could not be sent out.')]);

    $title = $_GET['title'] ?? '';
    $body = $_GET['eventDescription'] ?? '';
    $professionalDevelopmentRequestID = $_GET['professionalDevelopmentRequestID'] ?? '';
    $expenseRequest = $_GET['expenseRequest'] ?? '';

    $gibbonFinanceBudgetCycleID = '';                
    $result = $container->get(FinanceBudgetCycleGateway::class)->selectBy(['status' => 'Current']);
    if (empty($result)) {
        $page->addError(__('The current budget cycle cannot be determined.'));
    } else {
        $row = $result->fetch();
        $gibbonFinanceBudgetCycleID = $row['gibbonFinanceBudgetCycleID'];
    }

    // Check if Professional Development Budget is specified 
    $requestCostGateway = $container->get(RequestCostGateway::class);
    $gibbonFinanceBudgetID='';
    $budgetResult = $requestCostGateway->getProfessionalDevelopmentBudget();
    if (empty($budgetResult)) {
        $page->addError(__('Please create a budget for Professional Development.'));
    } else {
        $gibbonFinanceBudgetID = $budgetResult['gibbonFinanceBudgetID'];
    }

    if ($gibbonFinanceBudgetCycleID == '' || $gibbonFinanceBudgetID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        // Check if have Full or Write in any budgets
        $budgetRight = $requestCostGateway->getProfessionalDevelopmentBudgetAccess($gibbonFinanceBudgetID, $session->get('gibbonPersonID'));
        $budgetAccess = false;
        if (is_array($budgetRight)) {
                if ($budgetRight['access'] == 'Full' or $budgetRight['access'] == 'Write') {
                    $budgetsAccess = true;
                }
        }

        if ($budgetsAccess == false) {
            $page->addError(__('You do not have Full or Write access to the Professional Development budget.'));
        } else {
            // Get and check settings
            $settingGateway = $container->get(SettingGateway::class);
            $expenseApprovalType = $settingGateway->getSettingByScope('Finance', 'expenseApprovalType');
            $budgetLevelExpenseApproval = $settingGateway->getSettingByScope('Finance', 'budgetLevelExpenseApproval');
            if ($expenseApprovalType == '' or $budgetLevelExpenseApproval == '') {
                $page->addError(__('An error has occurred with your expense request and professsional development budget settings.'));
            } else {
                // Check if there are approvers
                $result = $container->get(FinanceExpenseApproverGateway::class)->selectExpenseApprovers();

                if ($result->rowCount() < 1) {
                    $page->addError(__('An error has occurred with your expense request and professsional development budget settings.'));
                } else {
                    // Ready to go!
                    $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module').'/pd_addExpenseRequestProcess.php');

                    $form->addHiddenValue('address', $session->get('address'));
                    $form->addHiddenValue('gibbonFinanceBudgetID', $gibbonFinanceBudgetID);
                    $form->addHiddenValue('gibbonFinanceBudgetCycleID', $gibbonFinanceBudgetCycleID);
                    $form->addHiddenValue('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
                    $form->addHiddenValue('status', 'Ordered');
                    $form->addHiddenValue('countAgainstBudget', 'N');
                    $form->addHiddenValue('body', $body);

                    $cycleResult = $requestCostGateway->getCurrentBudgetCycleName($gibbonFinanceBudgetCycleID);
                    $cycleName = $cycleResult['name'];
            
                    $row = $form->addRow();
                        $row->addLabel('name', __('Budget Cycle'));
                        $row->addTextField('name')->setValue($cycleName)->maxLength(20)->required()->readonly();

                    $budgetName = $budgetResult['name'];
                    $row = $form->addRow();
                        $row->addLabel('budget', __('Budget'));
                        $row->addTextField('budget')->setValue($budgetName)->maxLength(30)->required()->readonly();

                    $row = $form->addRow();
                        $row->addLabel('title', __('Title'));
                        $row->addTextField('title')->setValue($title)->maxLength(60)->required();

                    $row = $form->addRow();
                        $row->addLabel('statusText', __('Status'));
                        $row->addTextField('statusText')->setValue(__('Approved'))->required()->readonly();

                    // Show the cost Breakdown Table
                    $row = $form->addRow();
                        $row->addHeading(__('PD Cost Breakdown'));
                
                    $row = $form->addRow()->addClass('costBreakdown');
                
                    $requestCostGateway = $container->get(RequestCostGateway::class);
                    $costCriteria = $requestCostGateway->newQueryCriteria()
                        ->filterBy('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
                    $requestCosts = $requestCostGateway->queryRequestCost($costCriteria);
                
                    $table = DataTable::create('costBreakdown');
                
                    $table->addColumn('title', __('Cost Name'));
                    $table->addColumn('description', __('Cost Description'));
                    $table->addColumn('quantity', __('Quantity'));
                    $table->addColumn('cost', __('Cost / Item'))
                          ->format(Format::using('currency', ['cost']));
                    $row->addContent($table->render($requestCosts));

                    $row = $form->addRow();
                    $row->addLabel('expenseRequestLabel', __('Expense Request Application By'))
                        ->description(__('Who will submit the expense request?'));
                    $row->addTextField('expenseRequest')
                        ->setValue($expenseRequest)
                        ->readonly();

                    $row = $form->addRow();
                    	$row->addLabel('cost', __('Total Expense'));
            			$row->addCurrency('cost')->required()->maxLength(15);

                    $row = $form->addRow();
                        $row->addLabel('purchaseBy', __('Purchase By'));
                        $row->addSelect('purchaseBy')->fromArray(['School' => __('School'), 'Self' => __('Self')])->required()->placeholder();

                    $form->toggleVisibilityByClass('schoolPurchase')->onSelect('purchaseBy')->when('School');
                    $form->toggleVisibilityByClass('paymentSelf')->onSelect('purchaseBy')->when('Self');

                    $row = $form->addRow()->addClass('schoolPurchase');
                        $column = $row->addColumn();
                        $column->addLabel('purchaseDetails', __('Purchase Instructions for School'));
                        $column->addTextArea('purchaseDetails')->setRows(5)->setClass('w-full')->required();

                    // If paid by self, submit reimbursement request
                    $form->addRow()->addClass('paymentSelf')->addHeading('Payment Information2', __('Payment Information'));

                    $row = $form->addRow()->addClass('paymentSelf');
                        $row->addLabel('paymentDate', __('Date Paid'))->description(__('Date of payment, not entry to system.'));
                        $row->addDate('paymentDate')->required();

                    $row = $form->addRow()->addClass('paymentSelf');
                    	$row->addLabel('paymentAmount', __('Amount paid'))->description(__('Final amount paid.'));
            			$row->addCurrency('paymentAmount')->required()->maxLength(15);

                    $form->addHiddenValue('gibbonPersonIDPayment', $session->get('gibbonPersonID'));
                    $row = $form->addRow()->addClass('paymentSelf');
                        $row->addLabel('name', __('Payee'))->description(__('Staff who made, or arranged, the payment.'));
                        $row->addTextField('name')->required()->readonly()->setValue(Format::name('', ($session->get('preferredName')), htmlPrep($session->get('surname')), 'Staff', true, true));

                    $methods = [
                        'Bank Transfer' => __('Bank Transfer'),
                        'Cash' => __('Cash'),
                        'Cheque' => __('Cheque'),
                        'Credit Card' => __('Credit Card'),
                        'Other' => __('Other')
                    ];
                    $row = $form->addRow()->addClass('payment');
                        $row->addLabel('paymentMethod', __('Payment Method'));
                        $row->addSelect('paymentMethod')->fromArray($methods)->placeholder()->required();

                    $row = $form->addRow()->addClass('payment');;
                        $row->addLabel('file', __('Payment Receipt'))->description(__('Digital copy of the receipt for this payment.'));
                        $row->addFileUpload('file')
                            ->accepts('.jpg,.jpeg,.gif,.png,.pdf')
                            ->required();

                    $row = $form->addRow();
                        $row->addFooter();
                        $row->addSubmit();

                    echo $form->getOutput();
                }
            }
        }
    }
}
?>
