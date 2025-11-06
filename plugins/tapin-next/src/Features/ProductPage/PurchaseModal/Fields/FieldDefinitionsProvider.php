<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Fields;

use Tapin\Events\Support\AttendeeFields;

final class FieldDefinitionsProvider
{
    /** @var array<string,array<string,mixed>>|null */
    private ?array $definitions = null;

    /**
     * @return array<string,array<string,mixed>>
     */
    public function getDefinitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = AttendeeFields::definitions();
        $labels = apply_filters('tapin_purchase_modal_fields', AttendeeFields::labels());

        if (is_array($labels)) {
            foreach ($definitions as $key => &$definition) {
                if (isset($labels[$key])) {
                    $definition['label'] = (string) $labels[$key];
                }
            }
            unset($definition);
        }

        foreach ($definitions as $key => &$definition) {
            $definition['required_for'] = AttendeeFields::requiredFor($key);
        }
        unset($definition);

        $this->definitions = $definitions;

        return $this->definitions;
    }
}
