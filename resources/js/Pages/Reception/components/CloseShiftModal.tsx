import React, { useState } from 'react';
import { Modal, Form, InputNumber, Button, Alert, Typography, App } from 'antd';
import { LockOutlined, DollarOutlined, ClockCircleOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { ActiveShift } from '../types';

const { Text } = Typography;

interface CloseShiftModalProps {
    open: boolean;
    activeShift: ActiveShift | null;
    onClose: () => void;
    onSuccess?: () => void;
}

export const CloseShiftModal: React.FC<CloseShiftModalProps> = ({
    open,
    activeShift,
    onClose,
    onSuccess,
}) => {
    const [form] = Form.useForm();
    const { message } = App.useApp();
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = async () => {
        try {
            const values = await form.validateFields();
            setSubmitting(true);

            router.post(
                '/reception/shifts/close',
                {
                    closing_balance: values.closing_balance ?? 0,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        message.success('تم إغلاق الوردية بنجاح!');
                        form.resetFields();
                        onClose();
                        onSuccess?.();
                    },
                    onError: (errors) => {
                        const firstError = Object.values(errors)[0] as string;
                        message.error(firstError || 'حدث خطأ أثناء إغلاق الوردية.');
                    },
                    onFinish: () => {
                        setSubmitting(false);
                    },
                }
            );
        } catch {
            // Validation failed
        }
    };

    return (
        <Modal
            open={open}
            onCancel={onClose}
            centered
            width={480}
            title={
                <div className="flex items-center gap-2">
                    <div className="w-8 h-8 rounded-lg bg-rose-600 text-white flex items-center justify-center">
                        <LockOutlined />
                    </div>
                    <div>
                        <span className="font-bold text-base text-slate-800">
                            إغلاق الوردية الحالية
                        </span>
                        <Text type="secondary" className="block text-xs">
                            {activeShift ? `الوردية رقم #${activeShift.id} • بدأت في ${activeShift.opened_at}` : ''}
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
                    danger
                    type="primary"
                    loading={submitting}
                    onClick={handleSubmit}
                    className="font-semibold"
                >
                    تأكيد إغلاق الوردية
                </Button>,
            ]}
        >
            <div className="py-2 !space-y-4">
                <Alert
                    type="info"
                    showIcon
                    icon={<ClockCircleOutlined />}
                    title="جرد الخزينة الختامي"
                    description="يرجى عد النقدية الفعلية الموجودة في درج الخزينة وإدخالها لإغلاق الوردية."
                    className="rounded-lg text-xs"
                />

                <Form
                    form={form}
                    layout="vertical"
                    initialValues={{
                        closing_balance: 0,
                    }}
                >
                    <Form.Item
                        name="closing_balance"
                        label={<span className="font-semibold text-slate-700">النقدية الفعلية الختامية (جرد الدرج)</span>}
                        rules={[
                            { required: true, message: 'يرجى إدخال الرصيد الختامي' },
                            { type: 'number', min: 0, message: 'الرصيد يجب أن يكون أكبر من أو يساوي صفر' },
                        ]}
                    >
                        <InputNumber
                            size="large"
                            className="!w-full"
                            prefix={<DollarOutlined className="text-slate-400" />}
                            suffix="EGP"
                            min={0}
                            step={50}
                            precision={2}
                            placeholder="0.00"
                            autoFocus
                        />
                    </Form.Item>
                </Form>
            </div>
        </Modal>
    );
};
