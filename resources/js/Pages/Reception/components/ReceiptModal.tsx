import React, { useRef } from 'react';
import { Modal, Button } from 'antd';
import { PrinterOutlined } from '@ant-design/icons';
import { PatientVisit } from '../types';

interface ReceiptModalProps {
    open: boolean;
    visit: PatientVisit | null;
    onClose: () => void;
}

export const ReceiptModal: React.FC<ReceiptModalProps> = ({ open, visit, onClose }) => {
    const printAreaRef = useRef<HTMLDivElement>(null);

    if (!visit) {
        return null;
    }

    const invoice = visit.invoice;
    const { patient, payments } = visit;

    const isFullyPaid = Number(invoice?.remaining_amount ?? 0) <= 0;
    const isPartiallyPaid = Number(invoice?.paid_amount ?? 0) > 0 && !isFullyPaid;

    // Calculate subtotal and discounts safely
    const totalAmount = Number(invoice?.total_amount) || 0;
    const paidAmount = Number(invoice?.paid_amount) || 0;
    const remainingAmount = Number(invoice?.remaining_amount) || 0;

    const calculatedDiscount = invoice?.discount_total ??
        (invoice?.items?.reduce((sum: number, item: any) => sum + (Number(item.discount_amount) || 0), 0) || 0);

    const calculatedSubtotal = invoice?.subtotal ?? (totalAmount + calculatedDiscount);

    const handlePrint = () => {
        const receiptElement = printAreaRef.current;
        if (!receiptElement) {
            window.print();
            return;
        }

        // Clean up any previously created print iframe
        const oldFrame = document.getElementById('receipt-print-iframe');
        if (oldFrame) {
            oldFrame.remove();
        }

        // Create an isolated hidden iframe for clean 80mm thermal receipt printing
        // This avoids layout clipping, blank pages, and browser headers/footers
        const printFrame = document.createElement('iframe');
        printFrame.id = 'receipt-print-iframe';
        printFrame.style.position = 'fixed';
        printFrame.style.left = '-9999px';
        printFrame.style.top = '-9999px';
        printFrame.style.width = '80mm';
        printFrame.style.height = '100mm';
        printFrame.style.border = '0';
        document.body.appendChild(printFrame);

        const frameDoc = printFrame.contentWindow?.document;
        if (!frameDoc) {
            window.print();
            return;
        }

        const receiptHtml = `
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <meta charset="utf-8">
                <title>إيصال سداد - ${invoice?.invoice_number || `INV-${visit.id}`}</title>
                <style>
                    @page {
                        size: 80mm auto;
                        margin: 0mm !important;
                    }
                    * {
                        box-sizing: border-box !important;
                        margin: 0;
                        padding: 0;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    html, body {
                        width: 100% !important;
                        max-width: 80mm !important;
                        margin: 0 auto !important;
                        padding: 0 !important;
                        background: #ffffff !important;
                        color: #000000 !important;
                        font-family: 'Segoe UI', Tahoma, Geneva, Arial, sans-serif !important;
                        font-size: 11px !important;
                        line-height: 1.25 !important;
                        direction: rtl !important;
                    }
                    .thermal-sheet {
                        width: 68mm !important;
                        max-width: 68mm !important;
                        margin: 0 auto !important;
                        padding: 2mm 3mm !important;
                        box-sizing: border-box !important;
                        color: #000000 !important;
                        background: #ffffff !important;
                    }
                    .text-center { text-align: center !important; }
                    .text-right { text-align: right !important; }
                    .text-left { text-align: left !important; }
                    .font-bold { font-weight: bold !important; }
                    .font-black { font-weight: 900 !important; }
                    .font-mono { font-family: monospace, 'Courier New', Courier, sans-serif !important; }
                    
                    .dashed-line {
                        width: 100% !important;
                        border: none !important;
                        border-top: 1px dashed #000000 !important;
                        margin: 5px 0 !important;
                        padding: 0 !important;
                        height: 0 !important;
                        line-height: 0 !important;
                        display: block !important;
                        clear: both !important;
                    }
                    .solid-line {
                        width: 100% !important;
                        border: none !important;
                        border-top: 1px solid #000000 !important;
                        margin: 5px 0 !important;
                        height: 0 !important;
                        display: block !important;
                        clear: both !important;
                    }
                    .double-line {
                        width: 100% !important;
                        border: none !important;
                        border-top: 2px solid #000000 !important;
                        margin: 5px 0 !important;
                        height: 0 !important;
                        display: block !important;
                        clear: both !important;
                    }
                    .receipt-row {
                        display: flex !important;
                        justify-content: space-between !important;
                        align-items: flex-start !important;
                        width: 100% !important;
                        margin-bottom: 2px !important;
                        font-size: 11px !important;
                        line-height: 1.3 !important;
                    }
                    .receipt-table {
                        width: 100% !important;
                        border-collapse: collapse !important;
                        font-size: 11px !important;
                        margin: 4px 0 !important;
                    }
                    .receipt-table th {
                        border-bottom: 1px solid #000000 !important;
                        padding: 2px 1px !important;
                        font-weight: bold !important;
                    }
                    .receipt-table td {
                        padding: 3px 1px !important;
                        vertical-align: top !important;
                        border-bottom: 1px dashed #000000 !important;
                    }
                    .receipt-status-stamp {
                        border: 2px solid #000000 !important;
                        padding: 4px 2px !important;
                        text-align: center !important;
                        font-weight: 900 !important;
                        font-size: 11px !important;
                        margin: 6px 0 !important;
                        letter-spacing: 0.5px !important;
                        box-sizing: border-box !important;
                        display: block !important;
                        width: 100% !important;
                    }
                    .receipt-footer {
                        margin-top: 6px !important;
                        text-align: center !important;
                        font-size: 10px !important;
                        line-height: 1.3 !important;
                        display: block !important;
                        width: 100% !important;
                        height: auto !important;
                    }
                    .receipt-payments {
                        margin-top: 4px !important;
                        font-size: 10px !important;
                        display: block !important;
                        width: 100% !important;
                        height: auto !important;
                    }
                </style>
            </head>
            <body>
                <div class="thermal-sheet">
                    ${receiptElement.innerHTML}
                </div>
            </body>
            </html>
        `;

        frameDoc.open();
        frameDoc.write(receiptHtml);
        frameDoc.close();

        // Give the iframe document a moment to parse before invoking print
        setTimeout(() => {
            try {
                printFrame.contentWindow?.focus();
                printFrame.contentWindow?.print();
            } catch (err) {
                console.error('Iframe print failed, falling back to window.print', err);
                window.print();
            }
        }, 250);
    };

    return (
        <Modal
            open={open}
            onCancel={onClose}
            width={440}
            centered
            title={
                <div className="flex items-center gap-2">
                    <PrinterOutlined />
                    <span>معاينة إيصال السداد (طابعة حرارية 80 مم)</span>
                </div>
            }
            footer={[
                <Button key="close" onClick={onClose}>
                    إغلاق
                </Button>,
                <Button
                    key="print"
                    type="primary"
                    icon={<PrinterOutlined />}
                    onClick={handlePrint}
                    className="bg-black hover:bg-zinc-800 text-white font-bold"
                >
                    طباعة الإيصال (80mm)
                </Button>,
            ]}
        >
            {/* Visual 80mm Receipt Preview Container */}
            <div id="printable-receipt-wrapper" className="py-2 flex justify-center bg-zinc-200 rounded-lg p-3">
                <div
                    ref={printAreaRef}
                    id="printable-receipt"
                    className="w-full bg-white text-black p-3 font-sans text-xs leading-tight border border-dashed border-black shadow-md"
                    style={{
                        width: '68mm',
                        maxWidth: '68mm',
                        color: '#000000',
                        backgroundColor: '#ffffff',
                        margin: '0 auto',
                        boxSizing: 'border-box',
                    }}
                >
                    {/* Header */}
                    <div className="text-center pb-1">
                        <h1 className="text-base font-black tracking-tight text-black m-0 mb-0.5">
                            عيادتي التخصصية
                        </h1>
                        <p className="text-[11px] font-bold text-black m-0">
                            CLINIC LIGHT MEDICAL CENTER
                        </p>
                        <p className="text-[10px] font-semibold text-black m-0 mt-0.5">
                            إيصال سداد - خدمات طبية
                        </p>
                    </div>

                    {/* Dashed Separator */}
                    <div className="dashed-line" />

                    {/* Receipt Meta */}
                    <div className="receipt-section">
                        <div className="receipt-row">
                            <span className="font-bold">رقم الإيصال:</span>
                            <span dir="ltr" className="font-mono font-bold">
                                {invoice?.invoice_number || `INV-${visit.id}`}
                            </span>
                        </div>
                        <div className="receipt-row">
                            <span>رقم الزيارة:</span>
                            <span dir="ltr" className="font-mono font-bold">#{visit.id}</span>
                        </div>
                        <div className="receipt-row">
                            <span>التاريخ والوقت:</span>
                            <span dir="ltr" className="font-mono">{visit.visit_date}</span>
                        </div>
                    </div>

                    {/* Dashed Separator */}
                    <div className="dashed-line" />

                    {/* Patient Details */}
                    <div className="receipt-section">
                        <div className="receipt-row">
                            <span className="font-bold">المريض:</span>
                            <span className="font-bold">{patient?.full_name || 'غير مسجل'}</span>
                        </div>
                        {patient?.phone && (
                            <div className="receipt-row">
                                <span>الهاتف:</span>
                                <span dir="ltr" className="font-mono">{patient.phone}</span>
                            </div>
                        )}
                        <div className="receipt-row">
                            <span>السن / النوع:</span>
                            <span>
                                {patient?.age ? `${patient.age} سنة` : (patient?.birth_date || '-')}
                                {patient?.gender_label ? ` / ${patient.gender_label}` : ''}
                            </span>
                        </div>
                        {visit.referring_doctor?.name && (
                            <div className="receipt-row">
                                <span>طبيب الإحالة:</span>
                                <span className="font-semibold">{visit.referring_doctor.name}</span>
                            </div>
                        )}
                    </div>

                    {/* Dashed Separator */}
                    <div className="dashed-line" />

                    {/* Items Table */}
                    <div className="my-1">
                        <table className="receipt-table">
                            <thead>
                                <tr>
                                    <th className="text-right" style={{ width: '44%' }}>الخدمة</th>
                                    <th className="text-center" style={{ width: '12%' }}>ك</th>
                                    <th className="text-left" style={{ width: '22%' }}>السعر</th>
                                    <th className="text-left" style={{ width: '22%' }}>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoice?.items && invoice.items.length > 0 ? (
                                    invoice.items.map((item: any, idx: number) => (
                                        <tr key={idx} className="align-top">
                                            <td className="pr-0.5">
                                                <div className="font-bold">{item.description}</div>
                                                {item.discount_amount > 0 && (
                                                    <div className="text-[10px] font-mono">
                                                        - خصم: {Number(item.discount_amount).toFixed(2)}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="text-center font-mono">{item.quantity}</td>
                                            <td className="text-left font-mono">
                                                {Number(item.unit_price).toFixed(2)}
                                            </td>
                                            <td className="text-left font-mono font-bold">
                                                {Number(item.total).toFixed(2)}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={4} className="py-2 text-center">
                                            لا توجد بنود مسجلة
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Double Solid Line */}
                    <div className="double-line" />

                    {/* Totals Summary */}
                    <div className="receipt-section">
                        {calculatedDiscount > 0 && (
                            <>
                                <div className="receipt-row">
                                    <span>إجمالي الخدمات:</span>
                                    <span dir="ltr" className="font-mono">
                                        {calculatedSubtotal.toFixed(2)} ج.م
                                    </span>
                                </div>
                                <div className="receipt-row font-bold">
                                    <span>إجمالي الخصم:</span>
                                    <span dir="ltr" className="font-mono">
                                        -{calculatedDiscount.toFixed(2)} ج.م
                                    </span>
                                </div>
                                <div className="dashed-line" />
                            </>
                        )}

                        <div className="receipt-row font-bold">
                            <span>الصافي المطلوب:</span>
                            <span dir="ltr" className="font-mono font-bold">
                                {totalAmount.toFixed(2)} ج.م
                            </span>
                        </div>

                        <div className="receipt-row">
                            <span>المبلغ المسدد:</span>
                            <span dir="ltr" className="font-mono font-bold">
                                {paidAmount.toFixed(2)} ج.م
                            </span>
                        </div>

                        <div className="dashed-line" />

                        <div className="receipt-row text-xs font-black">
                            <span>المتبقي المستحق:</span>
                            <span dir="ltr" className="font-mono font-black text-sm">
                                {remainingAmount.toFixed(2)} ج.م
                            </span>
                        </div>
                    </div>

                    {/* High-Contrast Monochrome Status Box */}
                    <div className="receipt-status-stamp">
                        {visit.status === 'cancelled'
                            ? 'زيارة ملغاة ومستردة (CANCELLED)'
                            : isFullyPaid
                                ? 'مدفوع بالكامل'
                                : isPartiallyPaid
                                    ? `سداد جزئي (متبقي ${remainingAmount.toFixed(2)} ج.م)`
                                    : 'غير مسدد'}
                    </div>

                    {/* Payments History if any */}
                    {payments && payments.length > 0 && (
                        <div className="receipt-payments">
                            <div className="dashed-line" />
                            <div className="font-bold mb-0.5">المتحصلات المالية:</div>
                            {payments.map((p, idx) => {
                                const isRefund = p.type === 'refund' || Number(p.amount) < 0;
                                return (
                                    <div key={idx} className="receipt-row py-0.5 text-[10px]">
                                        <span>
                                            <span dir="ltr" className="font-mono">{p.created_at}</span> - {p.payment_method_label}
                                            {isRefund && (
                                                <span className="font-bold text-red-600 mr-1">(استرداد)</span>
                                            )}
                                        </span>
                                        <span dir="ltr" className={`font-mono font-bold ${isRefund ? 'text-red-600' : ''}`}>
                                            {Number(p.amount).toFixed(2)} ج.م
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {/* Footer */}
                    <div className="receipt-footer">
                        <div className="dashed-line" />
                        <p className="m-0 font-bold">نتمنى لكم دوام الصحة والعافية</p>
                        <p className="m-0">شكراً لثقتكم بنا</p>
                        <p className="m-0 text-[9px] pt-0.5 font-mono" dir="ltr">
                            {new Date().toLocaleString('ar-EG')}
                        </p>
                        <p className="m-0 text-[8px] font-mono tracking-widest pt-0.5">
                            * * * * * * * * * * * * * * * *
                        </p>
                    </div>
                </div>
            </div>

            {/* Direct fallback print stylesheet for Ctrl+P */}
            <style>{`
                .dashed-line {
                    width: 100%;
                    border: none;
                    border-top: 1px dashed #000000;
                    margin: 5px 0;
                    padding: 0;
                    height: 0;
                    line-height: 0;
                    display: block;
                    clear: both;
                }
                .solid-line {
                    width: 100%;
                    border: none;
                    border-top: 1px solid #000000;
                    margin: 5px 0;
                    height: 0;
                    display: block;
                    clear: both;
                }
                .double-line {
                    width: 100%;
                    border: none;
                    border-top: 2px solid #000000;
                    margin: 5px 0;
                    height: 0;
                    display: block;
                    clear: both;
                }
                .receipt-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    width: 100%;
                    margin-bottom: 2px;
                    font-size: 11px;
                    line-height: 1.3;
                }
                .receipt-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 11px;
                    margin: 4px 0;
                }
                .receipt-table th {
                    border-bottom: 1px solid #000000;
                    padding: 2px 1px;
                    font-weight: bold;
                }
                .receipt-table td {
                    padding: 3px 1px;
                    vertical-align: top;
                    border-bottom: 1px dashed #000000;
                }
                .receipt-status-stamp {
                    border: 2px solid #000000;
                    padding: 4px 2px;
                    text-align: center;
                    font-weight: 900;
                    font-size: 11px;
                    margin: 6px 0;
                    letter-spacing: 0.5px;
                    display: block;
                    width: 100%;
                    box-sizing: border-box;
                }
                .receipt-footer {
                    margin-top: 6px;
                    text-align: center;
                    font-size: 10px;
                    line-height: 1.3;
                    display: block;
                    width: 100%;
                    height: auto;
                }
                .receipt-payments {
                    margin-top: 4px;
                    font-size: 10px;
                    display: block;
                    width: 100%;
                    height: auto;
                }

                @media print {
                    @page {
                        size: 80mm auto;
                        margin: 0mm !important;
                    }
                    html, body {
                        width: 100% !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        background: #ffffff !important;
                        color: #000000 !important;
                        font-family: 'Segoe UI', Tahoma, Geneva, Arial, sans-serif !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    body * {
                        visibility: hidden !important;
                    }
                    #printable-receipt-wrapper,
                    #printable-receipt-wrapper * {
                        visibility: visible !important;
                        color: #000000 !important;
                        border-color: #000000 !important;
                        background: transparent !important;
                        box-shadow: none !important;
                        text-shadow: none !important;
                    }
                    #printable-receipt-wrapper {
                        position: absolute !important;
                        left: 0 !important;
                        right: 0 !important;
                        top: 0 !important;
                        width: 100% !important;
                        margin: 0 auto !important;
                        padding: 0 !important;
                        display: flex !important;
                        justify-content: center !important;
                        align-items: flex-start !important;
                    }
                    #printable-receipt {
                        width: 68mm !important;
                        max-width: 68mm !important;
                        margin: 0 auto !important;
                        padding: 2mm 3mm !important;
                        font-size: 11px !important;
                        line-height: 1.25 !important;
                        border: none !important;
                        box-sizing: border-box !important;
                    }
                    .ant-modal, .ant-modal-mask, .ant-modal-wrap, .ant-modal-footer, .ant-modal-close, .ant-modal-header {
                        display: none !important;
                    }
                }
            `}</style>
        </Modal>
    );
};
