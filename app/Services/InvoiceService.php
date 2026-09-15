<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Enums\VisitServiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PatientVisit;
use App\Models\VisitService;

final class InvoiceService
{
    public function getOrCreateInvoice(PatientVisit $visit): Invoice
    {
        $existing = $visit->invoice ?? Invoice::query()->where('visit_id', $visit->id)->first();
        if ($existing) {
            return $existing;
        }

        $invoiceNumber = 'INV-'.mb_str_pad((string) $visit->id, 6, '0', STR_PAD_LEFT);

        return Invoice::query()->create([
            'visit_id' => $visit->id,
            'invoice_number' => $invoiceNumber,
            'status' => InvoiceStatus::Unpaid,
            'subtotal' => 0,
            'discount_total' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
        ]);
    }

    public function syncInvoice(PatientVisit $visit): Invoice
    {
        $invoice = $this->getOrCreateInvoice($visit);

        // Delete existing items to rebuild clean mapping
        $invoice->items()->delete();

        $visit->loadMissing(['visitServices.service', 'visitServices.selectedOptions.serviceOption', 'payments']);

        foreach ($visit->visitServices as $vs) {
            // For pending or completed services, add active charges
            if (in_array($vs->status, [VisitServiceStatus::Pending, VisitServiceStatus::Completed], true)) {
                $this->createItemsForVisitService($invoice, $vs);
            } elseif ($vs->status === VisitServiceStatus::Cancelled) {
                // For cancelled services, if they were completed/processed, record charge items AND refund items
                $this->createItemsForVisitService($invoice, $vs);
                $this->createRefundItemsForVisitService($invoice, $vs);
            }
        }

        $this->recalculateInvoiceTotals($invoice, $visit);

        return $invoice->fresh(['items', 'payments']);
    }

    public function refundAndCancelService(VisitService $vs): void
    {
        $vs->update(['status' => VisitServiceStatus::Cancelled]);
        $this->syncInvoice($vs->visit);
    }

    private function createItemsForVisitService(Invoice $invoice, VisitService $vs): void
    {
        $qty = max(1, (int) $vs->quantity);
        $basePrice = (float) ($vs->service?->base_price ?? 0);
        $baseSubtotal = $basePrice * $qty;

        $discountVal = (float) $vs->discount_value;
        $discountType = $vs->discount_type;
        $discountTypeVal = $discountType instanceof DiscountType ? $discountType->value : (string) $discountType;

        $optionsSubtotal = 0.0;
        foreach ($vs->selectedOptions as $opt) {
            $optionsSubtotal += (float) $opt->additional_price * $qty;
        }

        $grossSubtotal = $baseSubtotal + $optionsSubtotal;
        $discountAmount = ($discountTypeVal === DiscountType::Percent->value || $discountTypeVal === 'percent')
            ? ($grossSubtotal * ($discountVal / 100))
            : $discountVal;

        $serviceTotal = max(0, $baseSubtotal - $discountAmount);

        // Base service line item
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'visit_service_id' => $vs->id,
            'visit_service_selected_option_id' => null,
            'type' => 'service',
            'description' => $vs->service?->name ?? 'خدمة',
            'unit_price' => $basePrice,
            'quantity' => $qty,
            'discount_amount' => $discountAmount,
            'total' => $serviceTotal,
        ]);

        // Selected options line items
        foreach ($vs->selectedOptions as $opt) {
            $optPrice = (float) $opt->additional_price;
            $optTotal = $optPrice * $qty;

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'visit_service_id' => $vs->id,
                'visit_service_selected_option_id' => $opt->id,
                'type' => 'option',
                'description' => 'خيار: '.($opt->serviceOption?->name ?? 'خيار إضافي'),
                'unit_price' => $optPrice,
                'quantity' => $qty,
                'discount_amount' => 0,
                'total' => $optTotal,
            ]);
        }
    }

    private function createRefundItemsForVisitService(Invoice $invoice, VisitService $vs): void
    {
        $qty = max(1, (int) $vs->quantity);
        $basePrice = (float) ($vs->service?->base_price ?? 0);
        $baseSubtotal = $basePrice * $qty;

        $discountVal = (float) $vs->discount_value;
        $discountType = $vs->discount_type;
        $discountTypeVal = $discountType instanceof DiscountType ? $discountType->value : (string) $discountType;

        $optionsSubtotal = 0.0;
        foreach ($vs->selectedOptions as $opt) {
            $optionsSubtotal += (float) $opt->additional_price * $qty;
        }

        $grossSubtotal = $baseSubtotal + $optionsSubtotal;
        $discountAmount = ($discountTypeVal === DiscountType::Percent->value || $discountTypeVal === 'percent')
            ? ($grossSubtotal * ($discountVal / 100))
            : $discountVal;

        $serviceTotal = max(0, $baseSubtotal - $discountAmount);

        // Refund line item for base service
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'visit_service_id' => $vs->id,
            'visit_service_selected_option_id' => null,
            'type' => 'refund',
            'description' => 'استرجاع خدمة: '.($vs->service?->name ?? 'خدمة'),
            'unit_price' => -$basePrice,
            'quantity' => $qty,
            'discount_amount' => -$discountAmount,
            'total' => -$serviceTotal,
        ]);

        // Refund line items for options
        foreach ($vs->selectedOptions as $opt) {
            $optPrice = (float) $opt->additional_price;
            $optTotal = $optPrice * $qty;

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'visit_service_id' => $vs->id,
                'visit_service_selected_option_id' => $opt->id,
                'type' => 'refund',
                'description' => 'استرجاع خيار: '.($opt->serviceOption?->name ?? 'خيار إضافي'),
                'unit_price' => -$optPrice,
                'quantity' => $qty,
                'discount_amount' => 0,
                'total' => -$optTotal,
            ]);
        }
    }

    private function recalculateInvoiceTotals(Invoice $invoice, PatientVisit $visit): void
    {
        // Link all payments to invoice
        $visit->payments()->whereNull('invoice_id')->update(['invoice_id' => $invoice->id]);

        $positiveItems = $invoice->items()->where('type', '!=', 'refund')->get();
        $subtotal = (float) $positiveItems->sum(fn (InvoiceItem $item) => (float) $item->unit_price * $item->quantity);
        $discountTotal = (float) $positiveItems->sum('discount_amount');

        $totalAmount = (float) $invoice->items()->sum('total');
        $paidAmount = (float) $visit->payments()->sum('amount');
        $remainingAmount = max(0.0, $totalAmount - $paidAmount);

        $status = match (true) {
            $totalAmount <= 0 && $paidAmount > 0 => InvoiceStatus::Refunded,
            $totalAmount <= 0 => InvoiceStatus::Cancelled,
            $remainingAmount <= 0 => InvoiceStatus::Paid,
            $paidAmount > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Unpaid,
        };

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status' => $status,
        ]);
    }
}
