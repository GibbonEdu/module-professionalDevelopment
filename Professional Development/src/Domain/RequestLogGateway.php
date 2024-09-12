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

}
