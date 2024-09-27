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
    private static $searchableColumns = []; 

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
