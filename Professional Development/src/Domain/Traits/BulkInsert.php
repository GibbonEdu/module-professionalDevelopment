<?php

namespace Gibbon\Module\ProfessionalDevelopment\Domain\Traits;

use Gibbon\Domain\Traits\TableAware;

/**
 * Provides method for Professional Development Request Gateways to bulk insert data by professionalDevelopmentRequestID.
 */

trait BulkInsert
{

    use TableAware;

    public function bulkInsert($professionalDevelopmentRequestID, $data) {
        if (empty($data)) {
            return;
        }

        $query = $this
            ->newInsert()
            ->into($this->getTableName());

        foreach ($data as $row) {
            $query->addRow($row);
            $query->set('professionalDevelopmentRequestID', $professionalDevelopmentRequestID);
        }

        return $this->runInsert($query);
    }

}

?>