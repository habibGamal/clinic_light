import React, { useState, useEffect } from 'react';
import {
    Modal,
    Form,
    Input,
    InputNumber,
    Select,
    Alert,
    Typography,
    App,
    Segmented,
    Table,
    Tag,
    Space,
    Divider,
    Button,
} from 'antd';
import {
    DollarOutlined,
    RollbackOutlined,
    HistoryOutlined,
    LockOutlined,
    CheckCircleOutlined,
} from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { PatientVisit } from '../types';

const { Text } = Typography;

interface PaymentModalProps {
    open: boolean;
    visit: PatientVisit | null;
    paymentMethods: { value: string; label: string }[];
    hasActiveShift?: boolean;
    onClose: () => void;
    onSuccess?: () => void;
}

export const PaymentModal: React.FC<PaymentModalProps> = ({
    open,
    visit,
    paymentMethods,
    hasActiveShift = true,
    onClose,
    onSuccess,
}) => {
    const [actionType, setActionType] = useState<'payment' | 'refund'>('payment');
    const [form] = Form.useForm();
    const [submitting, setSubmitting] = useState(false);
    const { message } = App.useApp();

    const inv = visit?.invoice ?? null;
    const remaining = inv ? Number(inv.remaining_amount || 0) : 0;
    const paidAmount = inv ? Number(inv.paid_amount || 0) : 0;
    const totalAmount = inv ? Number(inv.total_amount || 0) : 0;

    useEffect(() => {
        if (visit && open) {
            if (actionType === 'payment') {
                form.setFieldsValue({
                    amount: remaining > 0 ? remaining : undefined,
                    payment_method: 'cash',
                    notes: 'سداد من شاشة الاستقبال',
                });
            } else {
                form.setFieldsValue({
                    amount: paidAmount > 0 ? paidAmount : undefined,
                    payment_method: 'cash',
                    notes: 'استرداد دفعة من شاشة الاستقبال',
                });
            }
        }
    }, [visit, open, actionType, form, remaining, paidAmount]);

    if (!visit) {
        return null;
    }

    const handleSubmit = async () => {
        if (!hasActiveShift) {
            message.error('لا يمكن تسجيل دفعات أو استرداد مبالغ بدون وجود وردية مفتوحة حالياً.');
            return;
        }

        try {
            const values = await form.validateFields();
            setSubmitting(true);

            const endpoint =
                actionType === 'payment'
                    ? `/reception/visits/${visit.id}/payments`
                    : `/reception/visits/${visit.id}/refund`;

            router.post(
                endpoint,
                values,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        message.success(
                            actionType === 'payment'
                                ? 'تم تسجيل الدفعة بنجاح!'
                                : 'تم تسجيل استرداد المبلغ بنجاح!'
                        );
                        form.resetFields();
                        onClose();
                        onSuccess?.();
                    },
                    onError: (errors) => {
                        const firstError = Object.values(errors)[0] as string;
                        message.error(firstError || 'حدث خطأ أثناء تنفيذ العملية.');
                    },
                    onFinish: () => setSubmitting(false),
                }
            );
        } catch {
            // Form validation failed
        }
    };

    const paymentColumns = [
        {
            title: 'رقم العملية',
            dataIndex: 'id',
            key: 'id',
            width: 80,
            render: (id: number) => <span className="font-mono text-xs">#{id}</span>,
        },
        {
            title: 'التاريخ والتوقيت',
            dataIndex: 'created_at',
            key: 'created_at',
            render: (val: string) => <span className="text-xs text-slate-500">{val}</span>,
        },
        {
            title: 'النوع',
            dataIndex: 'type',
            key: 'type',
            render: (type: string, record: any) => {
                const isRefund = type === 'refund' || record.amount < 0;
                return (
                    <Tag color={isRefund ? 'red' : 'green'}>
                        {isRefund ? 'استرداد' : 'دفعة'}
                    </Tag>
                );
            },
        },
        {
            title: 'المبلغ',
            dataIndex: 'amount',
            key: 'amount',
            render: (amt: number) => {
                const isNegative = amt < 0;
                return (
                    <span className={`font-mono font-bold ${isNegative ? 'text-rose-600' : 'text-emerald-600'}`}>
                        {Number(amt).toFixed(2)} ج.م
                    </span>
                );
            },
        },
        {
            title: 'طريقة الدفع',
            dataIndex: 'payment_method_label',
            key: 'payment_method_label',
            render: (val: string, r: any) => <span className="text-xs">{val || r.payment_method}</span>,
        },
        {
            title: 'الوردية',
            dataIndex: 'shift_id',
            key: 'shift_id',
            width: 70,
            render: (shiftId: number) => (
                <span className="text-xs text-slate-400">
                    {shiftId ? `#${shiftId}` : '-'}
                </span>
            ),
        },
        {
            title: 'ملاحظات',
            dataIndex: 'notes',
            key: 'notes',
            render: (notes: string) => <span className="text-xs text-slate-500">{notes || '-'}</span>,
        },
    ];

    return (
        <Modal
            open={open}
            onCancel={onClose}
            centered
            width={640}
            title={
                <div className="flex items-center gap-2">
                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center text-white ${actionType === 'payment' ? 'bg-sky-600' : 'bg-rose-600'}`}>
                        {actionType === 'payment' ? <DollarOutlined /> : <RollbackOutlined />}
                    </div>
                    <div>
                        <span className="font-bold text-base text-slate-800">
                            إدارة مدفوعات واسترداد الزيارة #{visit.id}
                        </span>
                        <Text type="secondary" className="block text-xs">
                            المريض: {visit.patient?.full_name || 'غير محدد'}
                        </Text>
                    </div>
                </div>
            }
            footer={[
                <Button key="cancel" onClick={onClose} disabled={submitting}>
                    إلغاء
                </Button>,
                <Button
                    key="submit"
                    type="primary"
                    loading={submitting}
                    disabled={!hasActiveShift || (actionType === 'payment' && remaining <= 0) || (actionType === 'refund' && paidAmount <= 0)}
                    danger={actionType === 'refund'}
                    onClick={handleSubmit}
                    className="font-bold"
                >
                    {actionType === 'payment' ? 'تأكيد تسجيل الدفعة' : 'تأكيد استرداد المبلغ'}
                </Button>,
            ]}
        >
            <div className="py-2 space-y-4">
                {!hasActiveShift && (
                    <Alert
                        type="warning"
                        showIcon
                        icon={<LockOutlined />}
                        message="الوردية مغلقة"
                        description="لا يمكن تسجيل مدفوعات جديدة أو استرداد مبالغ بدون وجود وردية مفتوحة حالياً."
                        className="text-xs rounded-lg"
                    />
                )}

                {/* Financial Summary */}
                <div className="bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs grid grid-cols-3 gap-2 text-center">
                    <div>
                        <span className="text-slate-500 block">إجمالي الفاتورة</span>
                        <span className="font-bold text-slate-800 text-sm">
                            {totalAmount.toFixed(2)} ج.م
                        </span>
                    </div>
                    <div>
                        <span className="text-slate-500 block">صافي المسدد</span>
                        <span className="font-bold text-emerald-600 text-sm">
                            {paidAmount.toFixed(2)} ج.م
                        </span>
                    </div>
                    <div>
                        <span className="text-slate-500 block">المتبقي المستحق</span>
                        <span className="font-bold text-rose-600 text-sm">
                            {remaining.toFixed(2)} ج.م
                        </span>
                    </div>
                </div>

                {/* Segmented Switcher */}
                <div className="flex justify-center">
                    <Segmented
                        size="large"
                        value={actionType}
                        onChange={(val) => setActionType(val as 'payment' | 'refund')}
                        options={[
                            {
                                label: (
                                    <div className="flex items-center gap-1.5 px-3 py-1 font-semibold">
                                        <DollarOutlined />
                                        <span>تحصيل دفعة</span>
                                    </div>
                                ),
                                value: 'payment',
                            },
                            {
                                label: (
                                    <div className="flex items-center gap-1.5 px-3 py-1 font-semibold text-rose-600">
                                        <RollbackOutlined />
                                        <span>استرداد مبلغ (Refund)</span>
                                    </div>
                                ),
                                value: 'refund',
                            },
                        ]}
                    />
                </div>

                {/* Form */}
                <Form form={form} layout="vertical">
                    {actionType === 'payment' ? (
                        <>
                            {remaining <= 0 ? (
                                <Alert
                                    type="success"
                                    showIcon
                                    icon={<CheckCircleOutlined />}
                                    message="الفاتورة مسددة بالكامل"
                                    description="تم سداد كامل قيمة الزيارة ولا توجد أي مبالغ مستحقة للتحصيل."
                                    className="rounded-lg text-xs"
                                />
                            ) : (
                                <>
                                    <Form.Item
                                        name="amount"
                                        label="المبلغ المسدد (ج.م)"
                                        rules={[
                                            { required: true, message: 'يرجى إدخال المبلغ' },
                                            {
                                                type: 'number',
                                                min: 0.01,
                                                max: remaining,
                                                message: `المبلغ يجب أن يكون بين 0.01 و ${remaining} ج.م`,
                                            },
                                        ]}
                                    >
                                        <InputNumber
                                            className="!w-full"
                                            size="large"
                                            min={0.01}
                                            max={remaining}
                                            precision={2}
                                            prefix="ج.م"
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="payment_method"
                                        label="طريقة الدفع"
                                        rules={[{ required: true, message: 'يرجى اختيار طريقة الدفع' }]}
                                    >
                                        <Select size="large" options={paymentMethods} />
                                    </Form.Item>

                                    <Form.Item name="notes" label="ملاحظات الدفعة">
                                        <Input.TextArea rows={2} placeholder="أي ملاحظات تخص هذه الدفعة..." />
                                    </Form.Item>
                                </>
                            )}
                        </>
                    ) : (
                        <>
                            {paidAmount <= 0 ? (
                                <Alert
                                    type="info"
                                    showIcon
                                    message="لا توجد مبالغ مسددة للاسترداد"
                                    description="لم يتم تحصيل أي مبالغ بعد على هذه الزيارة، لذا لا يمكن إجراء عملية استرداد."
                                    className="rounded-lg text-xs"
                                />
                            ) : (
                                <>
                                    <Alert
                                        type="warning"
                                        showIcon
                                        message="إجراء استرداد مالي"
                                        description={`الحد الأقصى القابل للاسترداد هو ${paidAmount.toFixed(2)} ج.م (إجمالي ما تم سداده).`}
                                        className="rounded-lg text-xs mb-3"
                                    />

                                    <Form.Item
                                        name="amount"
                                        label="المبلغ المراد استرداده (ج.م)"
                                        rules={[
                                            { required: true, message: 'يرجى إدخال مبلغ الاسترداد' },
                                            {
                                                type: 'number',
                                                min: 0.01,
                                                max: paidAmount,
                                                message: `المبلغ يجب أن يكون بين 0.01 و ${paidAmount} ج.م`,
                                            },
                                        ]}
                                    >
                                        <InputNumber
                                            className="!w-full"
                                            size="large"
                                            min={0.01}
                                            max={paidAmount}
                                            precision={2}
                                            prefix="ج.م"
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="payment_method"
                                        label="طريقة الاسترداد"
                                        rules={[{ required: true, message: 'يرجى اختيار طريقة الاسترداد' }]}
                                    >
                                        <Select size="large" options={paymentMethods} />
                                    </Form.Item>

                                    <Form.Item name="notes" label="سبب وملاحظات الاسترداد">
                                        <Input.TextArea rows={2} placeholder="سبب استرداد المبلغ للمريض..." />
                                    </Form.Item>
                                </>
                            )}
                        </>
                    )}
                </Form>

                {/* Payments & Refunds Log */}
                {visit.payments && visit.payments.length > 0 && (
                    <div className="space-y-2 pt-2 border-t border-slate-200">
                        <div className="flex items-center gap-1.5 text-xs font-bold text-slate-700">
                            <HistoryOutlined className="text-sky-600" />
                            <span>سجل الحركات المالية السابقة لهذه الزيارة ({visit.payments.length})</span>
                        </div>
                        <Table
                            size="small"
                            rowKey="id"
                            dataSource={visit.payments}
                            columns={paymentColumns}
                            pagination={false}
                            className="border border-slate-100 rounded"
                        />
                    </div>
                )}
            </div>
        </Modal>
    );
};
