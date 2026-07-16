<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Workshop\WorkOrder;
use App\Enum\Currency;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;

class WorkOrderFactory
{
    public function __construct(
        private readonly WorkOrderNumberGenerator $numberGenerator,
        private readonly SettingsProvider $settings,
    ) {
    }

    public function createForUser(User $createdBy): WorkOrder
    {
        $workOrder = new WorkOrder();
        $workOrder->setNumber($this->numberGenerator->generate());
        $workOrder->setCreatedBy($createdBy);
        $workOrder->setCurrencyCode($this->settings->getString(
            SettingsRegistry::SECTION_CURRENCY,
            SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE,
        ) ?? Currency::DEFAULT->value);

        return $workOrder;
    }
}
