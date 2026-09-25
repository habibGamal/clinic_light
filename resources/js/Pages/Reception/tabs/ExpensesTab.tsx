import React, { useState } from 'react';
import {
    Form,
    Input,
    InputNumber,
    Select,
    Button,
    Card,
    Table,
    Tag,
    Popconfirm,
    Typography,
    Statistic,
    Space,
    App,
    Modal,
    Alert,
    Tooltip,
} from 'antd';
import {
    DollarOutlined,
    PlusCircleOutlined,
    DeleteOutlined,
    EditOutlined,
    WalletOutlined,
    ClockCircleOutlined,
    UserOutlined,
    LockOutlined,
} from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { ExpenseRecord, ExpenseCategory } from '../types';

const { Title, Text } = Typography;

interface ExpensesTabProps {
    todayExpenses: ExpenseRecord[];
    todayExpensesTotal: number;
    expenseCategories: ExpenseCategory[];
    hasActiveShift?: boolean;
}

export const ExpensesTab: React.FC<ExpensesTabProps> = ({
    todayExpenses,
    todayExpensesTotal,
    expenseCategories,
    hasActiveShift = true,
}) => {
    const [form] = Form.useForm();
    const [editForm] = Form.useForm();
    const [submitting, setSubmitting] = useState(false);
    const [updating, setUpdating] = useState(false);
    const [editingExpense, setEditingExpense] = useState<ExpenseRecord | null>(null);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const { message } = App.useApp();

    const handleSubmit = async () => {
        try {
            const values = await form.validateFields();
            setSubmitting(true);

            router.post('/reception/expenses', values, {
                preserveScroll: true,
                onSuccess: () => {
                    message.success('تم تسجيل المصروف بنجاح!');
                    form.resetFields();
                },
                onError: (errs) => {
                    const firstError = Object.values(errs)[0] as string;
                    message.error(firstError || 'تعذر تسجيل المصروف');
                },
                onFinish: () => setSubmitting(false),
            });
        } catch {
            // Form validation error
        }
    };

    const handleOpenEdit = (record: ExpenseRecord) => {
        setEditingExpense(record);
        editForm.setFieldsValue({
            expense_category_id: record.category_id,
            amount: record.amount,
            notes: record.notes ?? '',
        });
        setEditModalOpen(true);
    };

    const handleUpdateSubmit = async () => {
        if (!editingExpense) return;
        try {
            const values = await editForm.validateFields();
            setUpdating(true);

            router.put(`/reception/expenses/${editingExpense.id}`, values, {
                preserveScroll: true,
                onSuccess: () => {
                    message.success('تم تعديل المصروف بنجاح!');
                    setEditModalOpen(false);
                    setEditingExpense(null);
                },
                onError: (errs) => {
                    const firstError = Object.values(errs)[0] as string;
                    message.error(firstError || 'تعذر تعديل المصروف');
                },
                onFinish: () => setUpdating(false),
            });
        } catch {
            // Validation error
        }
    };

    const handleDeleteExpense = (id: number) => {
        router.delete(`/reception/expenses/${id}`, {
            preserveScroll: true,
            onSuccess: () => message.success('تم حذف المصروف بنجاح.'),
            onError: (errs) => {
                const firstError = Object.values(errs)[0] as string;
                message.error(firstError || 'تعذر حذف المصروف.');
            },
        });
    };

    const columns = [
        {
            title: '#',
            key: 'index',
            width: 50,
            render: (_: any, __: any, index: number) => index + 1,
        },
        {
            title: 'التصنيف',
            dataIndex: 'category_name',
            key: 'category_name',
            render: (name: string) => <Tag color="blue">{name}</Tag>,
        },
        {
            title: 'المبلغ',
            dataIndex: 'amount',
            key: 'amount',
            render: (val: number) => (
                <span className="font-bold text-rose-600 text-sm">
                    {Number(val).toFixed(2)} ج.م
                </span>
            ),
        },
        {
            title: 'البيان / ملاحظات',
            dataIndex: 'notes',
            key: 'notes',
            render: (notes: string) => (
                <span className="text-slate-600 text-xs">{notes || '-'}</span>
            ),
        },
        {
            title: 'بواسطة',
            dataIndex: 'creator_name',
            key: 'creator_name',
            render: (name: string) => (
                <span className="text-slate-500 text-xs">
                    <UserOutlined className="ml-1" />
                    {name}
                </span>
            ),
        },
        {
            title: 'التوقيت',
            dataIndex: 'created_at',
            key: 'created_at',
            width: 85,
            render: (time: string) => (
                <span className="text-slate-400 text-xs font-mono">
                    <ClockCircleOutlined className="ml-1" />
                    {time}
                </span>
            ),
        },
        {
            title: 'إجراءات',
            key: 'actions',
            width: 95,
            render: (_: any, record: ExpenseRecord) => {
                const canEditOrDelete = record.is_editable ?? true;

                if (!canEditOrDelete) {
                    return (
                        <Tooltip title="لا يمكن تعديل أو حذف المصروف لأن ورديته مغلقة">
                            <Tag color="default" className="text-xs select-none">
                                وردية مغلقة
                            </Tag>
                        </Tooltip>
                    );
                }

                return (
                    <Space size="small">
                        <Tooltip title="تعديل المصروف">
                            <Button
                                type="text"
                                size="small"
                                icon={<EditOutlined className="text-sky-600" />}
                                onClick={() => handleOpenEdit(record)}
                            />
                        </Tooltip>
                        <Popconfirm
                            title="حذف المصروف"
                            description="هل أنت متأكد من حذف هذا المصروف؟"
                            okText="نعم، حذف"
                            cancelText="إلغاء"
                            okButtonProps={{ danger: true }}
                            onConfirm={() => handleDeleteExpense(record.id)}
                        >
                            <Tooltip title="حذف المصروف">
                                <Button type="text" danger size="small" icon={<DeleteOutlined />} />
                            </Tooltip>
                        </Popconfirm>
                    </Space>
                );
            },
        },
    ];

    return (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {/* Right Side in RTL: Expense Form */}
            <div className="lg:col-span-5 order-1 lg:order-1">
                <Card
                    className="border-slate-200 shadow-sm rounded-xl"
                    title={
                        <Space>
                            <PlusCircleOutlined className="text-sky-600" />
                            <span className="font-bold text-slate-800">تسجيل مصروف جديد</span>
                        </Space>
                    }
                >
                    {!hasActiveShift && (
                        <Alert
                            type="warning"
                            showIcon
                            icon={<LockOutlined />}
                            message="الوردية مغلقة"
                            description="لا توجد وردية مفتوحة حالياً. يرجى فتح وردية أولاً لتتمكن من تسجيل المصروفات."
                            className="mb-4"
                        />
                    )}

                    <Form form={form} layout="vertical" onFinish={handleSubmit}>
                        <Form.Item
                            name="expense_category_id"
                            label="تصنيف المصروف"
                            rules={[{ required: true, message: 'يرجى اختيار تصنيف المصروف' }]}
                        >
                            <Select
                                size="large"
                                placeholder="اختر تصنيف المصروف..."
                                showSearch
                                disabled={!hasActiveShift}
                                options={expenseCategories.map((cat) => ({
                                    value: cat.id,
                                    label: cat.name,
                                }))}
                            />
                        </Form.Item>

                        <Form.Item
                            name="amount"
                            label="المبلغ (ج.م)"
                            rules={[
                                { required: true, message: 'يرجى إدخال مبلغ المصروف' },
                                { type: 'number', min: 0.01, message: 'المبلغ يجب أن يكون أكبر من 0' },
                            ]}
                        >
                            <InputNumber
                                size="large"
                                className="!w-full"
                                min={0.01}
                                precision={2}
                                placeholder="0.00"
                                prefix="ج.م"
                                disabled={!hasActiveShift}
                            />
                        </Form.Item>

                        <Form.Item name="notes" label="البيان / تفاصيل المصروف">
                            <Input.TextArea
                                rows={4}
                                placeholder="اكتب سبب الصرف أو البند (مثال: أدوات نظافة، مشتريات عيادة، ضيافة...)"
                                disabled={!hasActiveShift}
                            />
                        </Form.Item>

                        <Button
                            type="primary"
                            size="large"
                            block
                            htmlType="submit"
                            loading={submitting}
                            disabled={!hasActiveShift}
                            icon={<DollarOutlined />}
                            className="bg-sky-600 hover:bg-sky-500 font-bold h-11"
                        >
                            تسجيل المصروف
                        </Button>
                    </Form>
                </Card>
            </div>

            {/* Left Side in RTL: Today's Expenses Table & Total at the bottom */}
            <div className="lg:col-span-7 order-2 lg:order-2 space-y-4">
                <Card
                    className="border-slate-200 shadow-sm rounded-xl"
                    title={
                        <div className="flex justify-between items-center">
                            <Space>
                                <WalletOutlined className="text-sky-600" />
                                <span className="font-bold text-slate-800">مصروفات الوردية الحالية</span>
                            </Space>
                            <Tag color="cyan">عدد العمليات: {todayExpenses.length}</Tag>
                        </div>
                    }
                >
                    <Table
                        columns={columns}
                        dataSource={todayExpenses}
                        rowKey="id"
                        pagination={false}
                        size="middle"
                        bordered
                        locale={{ emptyText: 'لم يتم تسجيل أي مصروفات حتى الآن' }}
                    />

                    {/* Total in the Bottom */}
                    <div className="mt-4 p-4 rounded-xl bg-gradient-to-r from-rose-50 to-orange-50 border border-rose-100 flex justify-between items-center">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-rose-600 text-white flex items-center justify-center shadow">
                                <DollarOutlined style={{ fontSize: 20 }} />
                            </div>
                            <div>
                                <span className="text-xs text-rose-700 font-semibold block">
                                    إجمالي مصروفات الوردية الحالية
                                </span>
                                <Text type="secondary" className="text-xs">
                                    المجموع الكلي لجميع بنود الصرف الصادرة في هذه الوردية
                                </Text>
                            </div>
                        </div>
                        <div className="text-left font-mono">
                            <Statistic
                                value={todayExpensesTotal}
                                precision={2}
                                suffix="ج.م"
                                styles={{ content: { color: '#e11d48', fontWeight: 800, fontSize: '1.5rem' } }}
                            />
                        </div>
                    </div>
                </Card>
            </div>

            {/* Edit Expense Modal */}
            <Modal
                title={
                    <Space>
                        <EditOutlined className="text-sky-600" />
                        <span className="font-bold text-slate-800">تعديل المصروف</span>
                    </Space>
                }
                open={editModalOpen}
                onCancel={() => {
                    setEditModalOpen(false);
                    setEditingExpense(null);
                }}
                footer={null}
                destroyOnClose
            >
                <Form form={editForm} layout="vertical" onFinish={handleUpdateSubmit} className="mt-4">
                    <Form.Item
                        name="expense_category_id"
                        label="تصنيف المصروف"
                        rules={[{ required: true, message: 'يرجى اختيار تصنيف المصروف' }]}
                    >
                        <Select
                            size="large"
                            placeholder="اختر تصنيف المصروف..."
                            showSearch
                            options={expenseCategories.map((cat) => ({
                                value: cat.id,
                                label: cat.name,
                            }))}
                        />
                    </Form.Item>

                    <Form.Item
                        name="amount"
                        label="المبلغ (ج.م)"
                        rules={[
                            { required: true, message: 'يرجى إدخال مبلغ المصروف' },
                            { type: 'number', min: 0.01, message: 'المبلغ يجب أن يكون أكبر من 0' },
                        ]}
                    >
                        <InputNumber
                            size="large"
                            className="!w-full"
                            min={0.01}
                            precision={2}
                            placeholder="0.00"
                            prefix="ج.م"
                        />
                    </Form.Item>

                    <Form.Item name="notes" label="البيان / تفاصيل المصروف">
                        <Input.TextArea
                            rows={3}
                            placeholder="اكتب سبب الصرف أو التعديل..."
                        />
                    </Form.Item>

                    <div className="flex justify-end gap-2 mt-6">
                        <Button
                            onClick={() => {
                                setEditModalOpen(false);
                                setEditingExpense(null);
                            }}
                        >
                            إلغاء
                        </Button>
                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={updating}
                            className="bg-sky-600 hover:bg-sky-500 font-bold"
                        >
                            حفظ التعديلات
                        </Button>
                    </div>
                </Form>
            </Modal>
        </div>
    );
};
