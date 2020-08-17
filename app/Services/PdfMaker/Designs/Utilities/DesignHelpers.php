<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\PdfMaker\Designs\Utilities;

use DOMDocument;
use DOMXPath;

trait DesignHelpers
{
    public $document;

    public $xpath;

    public function setup(): self
    {
        if (isset($this->context['client'])) {
            $this->client = $this->context['client'];
        }

        if (isset($this->context['entity'])) {
            $this->entity = $this->context['entity'];
        }

        $this->document();

        return $this;
    }

    /**
     * Initialize local dom document instance. Used for getting raw HTML out of template.
     * 
     * @return $this 
     */
    public function document(): self
    {
        $document = new DOMDocument();

        $document->validateOnParse = true;
        @$document->loadHTML($this->html());

        $this->document = $document;
        $this->xpath = new DOMXPath($document);

        return $this;
    }

    /**
     * Get specific section HTML.
     * 
     * @param string $section 
     * @param bool $id 
     * @return null|string 
     */
    public function getSectionHTML(string $section, $id = true): ?string
    {
        if ($id) {
            $element = $this->document->getElementById($section);
        } else {
            $elements = $this->document->getElementsByTagName($section);
            $element = $elements[0];
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        if ($element) {
            $document->appendChild(
                $document->importNode($element, true)
            );

            return $document->saveHTML();
        }

        return null;
    }

    /**
     * This method will help us decide either we show
     * one "tax rate" column in the table or 3 custom tax rates.
     * 
     * Logic below will help us calculate that & inject the result in the
     * global state of the $context (design state).
     * 
     * @return void
     */
    public function processTaxColumns(): void
    {
        if (in_array('$product.tax', $this->context['product-table-columns'])) {
            $line_items = collect($this->entity->line_items);

            $tax1 = $line_items->where('tax_name1', '<>', '')->where('type_id', 1)->count();
            $tax2 = $line_items->where('tax_name2', '<>', '')->where('type_id', 1)->count();
            $tax3 = $line_items->where('tax_name3', '<>', '')->where('type_id', 1)->count();
            $taxes = [];

            if ($tax1 > 0) {
                array_push($taxes, '$product.tax_rate1');
            }

            if ($tax2 > 0) {
                array_push($taxes, '$product.tax_rate2');
            }

            if ($tax3 > 0) {
                array_push($taxes, '$product.tax_rate3');
            }

            $key = array_search('$product.tax', $this->context['product-table-columns'], true);

            if ($key) {
                array_splice($this->context['product-table-columns'], $key, 1, $taxes);
            }
        }
    }

    /**
     * Calculates the remaining colspans.
     * 
     * @param int $taken 
     * @return int 
     */
    public function calculateColspan(int $taken): int
    {
        $total = (int) count($this->context['product-table-columns']);

        return (int)$total - $taken;
    }

    /**
     * Return "true" or "false" based on null or empty check.
     * We need to return false as string because of HTML parsing.
     * 
     * @param mixed $property 
     * @return string 
     */
    public function toggleHiddenProperty($property): string
    {
        if (is_null($property)) {
            return 'false';
        }

        if (empty($property)) {
            return 'false';
        }

        return 'true';
    }

    public function sharedFooterElements()
    {
        return ['element' => 'div', 'properties' => ['class' => 'flex items-center justify-between mt-10'], 'content' => '', 'elements' => [
            ['element' => 'img', 'content' => '', 'properties' => ['src' => '$contact.signature', 'class' => 'h-32']],
            ['element' => 'img', 'content' => '', 'properties' => ['src' => '$app_url/images/created-by-invoiceninja-new.png', 'class' => 'h-24', 'hidden' => $this->entity->user->account->isPaid() ? 'true' : 'false']],
        ]];
    }

    public function entityVariableCheck(string $variable): bool
    {
        // Extract $invoice.date => date
        // so we can append date as $entity->date and not $entity->$invoice.date;

        try {
            $_variable = explode('.', $variable)[1];
        } catch (\Exception $e) {
            throw new \Exception('Company settings seems to be broken. Missing $entity.variable type.');
        }

        if (is_null($this->entity->{$_variable})) {
            info("{$this->entity->id} $_variable is null!");
            return true;
        }

        if (empty($this->entity->{$_variable})) {
            info("{$this->entity->id} $_variable is empty!");
            return true;
        }

        info("{$this->entity->id} $_variable ALL GOOD!!");
        return false;
    }
}