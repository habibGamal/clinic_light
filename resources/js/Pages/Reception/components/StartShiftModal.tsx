import React, { useState } from 'react';
import { Modal, Form, InputNumber, Button, Alert, Typography, App } from 'antd';
import { ClockCircleOutlined, DollarOutlined, UnlockOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';

const { Text } = Typography;

interface StartShiftModalProps {
    open: boolean;
    onClose: () => void;
    onSuccess?: () => void;
    isForVisit?: boolean;
}

export const StartShiftModal: React.FC<StartShiftModalProps> = ({
    open,
    onClose,
    onSuccess,
    isForVisit = false,
}) => {
    const [form] = Form.useForm();
    const { message } = App.useApp();
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = async () => {
        try {
            const values = await form.validateFields();
            setSubmitting(true);

            router.post(
                '/reception/shifts/start',
                {
                    opening_balance: values.opening_balance ?? 0,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        message.success('تم فتح الوردية بنجاح!');
                        form.resetFields();
                        onClose();
                        onSuccess?.();
                    },
                    onError: (errors) => {
                        const firstError = Object.values(errors)[0] as string;
                        message.error(firstError || 'حدث خطأ أثناء فتح الوردية.');
                    },
                    onFinish: () => {
                        setSubmitting(false);
                    },
                }
            );
        } catch {
            // Form validation failure
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
                    <div className="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center">
                        <UnlockOutlined />
                    </div>
                    <div>
                        <span className="font-bold text-base text-slate-800">
                            فتح وردية عمل جديدة
                        </span>
                        <Text type="secondary" className="block text-xs">
                            بدء الوردية لتسجيل الزيارات والتحصيل المالي
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
                    onClick={handleSubmit}
                    className="bg-emerald-600 hover:bg-emerald-500 font-semibold"
                >
                    {isForVisit ? 'فتح الوردية ومتابعة التسجيل' : 'تأكيد فتح الوردية'}
                </Button>,
            ]}
        >
            <div className="py-2 !space-y-4">
                <Alert
                    type="warning"
                    showIcon
                    icon={<ClockCircleOutlined />}
                    title={
                        isForVisit
                            ? 'يجب فتح وردية عمل أولاً لتتمكن من تسجيل زيارة المريض'
                            : 'لا توجد وردية نشطة حالياً'
                    }
                    description="العمليات المالية وتسجيل الزيارات والمصروفات تتطلب ربطها بوردية عمل نشطة."
                    className="rounded-lg text-xs"
                />

                <Form
                    form={form}
                    layout="vertical"
                    initialValues={{
                        opening_balance: 0,
                    }}
                >
                    <Form.Item
                        name="opening_balance"
                        label={<span className="font-semibold text-slate-700">الرصيد الافتتاحي (النقدية المتوفرة بالدرج)</span>}
                        rules={[
                            { required: true, message: 'يرجى إدخال الرصيد الافتتاحي' },
                            { type: 'number', min: 0, message: 'الرصيد يجب أن يكون أكبر من أو يساوي صفر' },
                        ]}
                    >
                        <InputNumber
                            size="large"
                            className="!w-full "
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
