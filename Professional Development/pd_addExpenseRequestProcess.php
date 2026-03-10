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

use Gibbon\Data\Validator;
use Gibbon\Services\Format;
use Gibbon\Comms\NotificationEvent;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestCostGateway;
use Gibbon\Module\ProfessionalDevelopment\Domain\RequestPersonGateway;
use Gibbon\Contracts\Filesystem\FileHandler;

include '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['body' => 'HTML']);

// Module includes
include './moduleFunctions.php';

$gibbonFinanceBudgetCycleID = $_POST['gibbonFinanceBudgetCycleID'] ?? '';
$gibbonFinanceBudgetID = $_POST['gibbonFinanceBudgetID'] ?? '';
$professionalDevelopmentRequestID = $_POST['professionalDevelopmentRequestID'] ?? '';

if ($gibbonFinanceBudgetCycleID == '' or $gibbonFinanceBudgetID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/pd_addExpenseRequest.php&gibbonFinanceBudgetCycleID=$gibbonFinanceBudgetCycleID";
    $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/Finance/expenseRequest_manage_view.php&gibbonFinanceBudgetCycleID='.$gibbonFinanceBudgetCycleID;

    if (isActionAccessible($guid, $connection2, '/modules/Professional Development/pd_manage.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        $title = $_POST['title'] ?? '';
        $body = $_POST['body'] ?? '';
        $cost = $_POST['cost'] ?? '';
        $countAgainstBudget = $_POST['countAgainstBudget'] ?? '';
        $purchaseBy = $_POST['purchaseBy'] ?? '';
        $purchaseDetails = $_POST['purchaseDetails'] ?? '';

        if ($purchaseBy == 'School') {
            $status = $_POST['status'] ?? '';
            $attachment = '';
        } else {
            $status = 'Paid';
            
            // Upload the receipt or ss of payment
            $fileMetaData = null;
            $fileUploader = new Gibbon\FileUploader($pdo, $session);
            $file = (isset($_FILES['file']))? $_FILES['file'] : null;

            // Upload the file, return the /uploads relative path
            $attachment = $fileUploader->uploadFromPost($file, $title);

            if (!empty($file) && empty($attachment)) {
                $URL .= '&return=error5';
                header("Location: {$URL}");
                exit();
            } elseif (!empty($attachment)) {
                $fileMetaData = $fileUploader->getFileMetaData($attachment);
            }

            // Get Reimbursement data if paid by "Self"
            $paymentDate = !empty($_POST['paymentDate']) ? Format::dateConvert($_POST['paymentDate']) : null;
            $paymentAmount = $_POST['paymentAmount'] ?? '';
            $gibbonPersonIDPayment = $_POST['gibbonPersonIDPayment'] ?? '';
            $paymentMethod = $_POST['paymentMethod'] ?? '';
        }

        if ($title == '' or $cost == '' or $purchaseBy == '' or $countAgainstBudget == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            // Prepare approval settings
            $settingGateway = $container->get(SettingGateway::class);
            $requestCostGateway = $container->get(RequestCostGateway::class);
            $expenseApprovalType = $settingGateway->getSettingByScope('Finance', 'expenseApprovalType');
            $budgetLevelExpenseApproval = $settingGateway->getSettingByScope('Finance', 'budgetLevelExpenseApproval');
            if ($budgetLevelExpenseApproval == '' or $expenseApprovalType == '' ) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            } else {
                if ($budgetLevelExpenseApproval == 'N') { // Skip budget-level approval
                    $statusApprovalBudgetCleared = 'Y';
                } else {
                    $budget = $requestCostGateway->getProfessionalDevelopmentBudgetAccess($gibbonFinanceBudgetID, $session->get('gibbonPersonID'));
                    if ($budget['access'] == 'Full') { // I can self-approve budget-level, as have Full access
                        $statusApprovalBudgetCleared = 'Y';
                    } else { // I cannot self-approve budget-level
                        $statusApprovalBudgetCleared = 'N';
                    }
                }
            }
            
            $insertion = $requestCostGateway->addFinanceExpenseRecord($gibbonFinanceBudgetCycleID, $gibbonFinanceBudgetID, $title, $body, $status, $statusApprovalBudgetCleared, $cost, $countAgainstBudget, $purchaseBy, $purchaseDetails, $session->get('gibbonPersonID'), $paymentDate, $paymentAmount, $gibbonPersonIDPayment, $paymentMethod, $attachment);

            if(!$insertion) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            $gibbonFinanceExpenseID = str_pad($connection2->lastInsertID(), 14, '0', STR_PAD_LEFT);

            // Record file tracking
            if (!empty($fileMetaData) && !empty($gibbonFinanceExpenseID)) {
                $gibbonFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'gibbonFinanceExpense', $gibbonFinanceExpenseID, 'paymentReimbursementReceipt');
                
                if (empty($gibbonFileID)) {
                    $partialFail = true;
                }
            }

            $requestPersonGateway = $container->get(RequestPersonGateway::class);
            if (!empty($professionalDevelopmentRequestID)) {
                $updateResult = $requestPersonGateway->updateWhere(['professionalDevelopmentRequestID' => $professionalDevelopmentRequestID, 'gibbonPersonID' => $session->get('gibbonPersonID')], ['gibbonFinanceExpenseID' => $gibbonFinanceExpenseID]);
            }          

            // Get the logged in user name who ahs submitted the request
            $personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);
            
            if ($purchaseBy == 'Self') {
                $action = 'Reimbursement Request';
                $comment = 'Request submitted for reimbursement of expense for '.$title;
                $notificationText = __('{person} has requested reimbursement for {title} in budget Professional Development.', ['person' => $personName, 'title' => $title]);
            } else {
                $action = 'Order';
                $comment = 'Request submitted for school to purchase for '.$title;
                $notificationText = __('{person} has submitted a purchase request for {title} in budget Professional Development.', ['person' => $personName, 'title' => $title]);
            }
            
            // Add a log entry with action and comment
            $logInsertion = $requestCostGateway->addFinanceExpenseLogEntry($gibbonFinanceExpenseID, $session->get('gibbonPersonID'), $action, $comment);
            if(!$logInsertion) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }
            
            // Last insert ID of log entry
            $AI = str_pad($connection2->lastInsertID(), 14, '0', STR_PAD_LEFT);

            // Send Notifications when a request is submitted
   
            $notificationGateway = $container->get(NotificationGateway::class);
            $notificationSender = new NotificationSender($notificationGateway, $session);

            $event = new NotificationEvent('Professional Development', 'Expense Request Notifications');

            $event->setNotificationText($notificationText);
            $event->setActionLink("/index.php?q=/modules/Finance/expenses_manage_edit.php&gibbonFinanceExpenseID=$gibbonFinanceExpenseID&gibbonFinanceBudgetCycleID=$gibbonFinanceBudgetCycleID");

             // Notify reimbursement officer that action is required
             $reimbursementOfficer = $settingGateway->getSettingByScope('Finance', 'reimbursementOfficer');

             if ($reimbursementOfficer != false and $reimbursementOfficer != '') {
                 $event->addRecipient($reimbursementOfficer);
             }

            // Send all notifications
            $event->pushNotifications($notificationGateway, $notificationSender);

            // Add a notification for the person submitting the expense request
            $notificationSender->addNotification($session->get('gibbonPersonID'), __('You have submitted a new expense request for {title}', ['title' => $title]), 'Professional Development', "/index.php?q=/modules/Finance/expenseRequest_manage_view.php&gibbonFinanceBudgetCycleID=$gibbonFinanceBudgetCycleID&gibbonFinanceExpenseID=$gibbonFinanceExpenseID");

            $notificationSender->sendNotifications();

            if ($partialFail == true) {
                $URL .= "&return=success1&editID=$AI";
                header("Location: {$URL}");
            } else {
                $URLSuccess .= '&gibbonFinanceExpenseID='.$gibbonFinanceExpenseID."&return=success0&editID=$AI";
                header("Location: {$URLSuccess}");
            }
        }
    }
}
