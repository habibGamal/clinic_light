import React from 'react';
import {
    Modal,
    Descriptions,
    Tag,
    Table,
    Typography,
    Divider,
    Button,
    Card,
    Space,
    Alert,
} from 'antd';
import {
    EyeOutlined,
    PrinterOutlined,
    DollarOutlined,
    UserOutlined,
    FileTextOutlined,
    MedicineBoxOutlined,
    CalendarOutlined,
    InfoCircleOutlined,
    RollbackOutlined,
} from '@ant-design/icons';
import { PatientVisit } from '../types';

const { Text, Title } = Typography;

interface VisitDetailsModalProps {
    open: boolean;
    visit: PatientVisit | null;
    hasActiveShift?: boolean;
    onClose: () => void;
    onOpenReceipt?: (visit: PatientVisit) => void;
    onOpenPayments?: (visit: PatientVisit) => void;
    onOpenReports?: (visit: PatientVisit) => void;
}

export const VisitDetailsModal: React.FC<VisitDetailsModalProps> = ({
    open,
    visit,
    hasActiveShift = true,
    onClose,
    onOpenReceipt,
    onOpenPayments,
    onOpenReports,
}) => {
    if (!visit) {
        return null;
    }

    const { patient, referring_doctor, visit_services, payments, reports } = visit;
    const invoice = visit.invoice;

    const serviceColumns = [
        {
            title: '#',
            key: 'idx',
            width: 45,
            render: (_: any, __: any, index: number) => (
                <span className="text-slate-400 font-mono text-xs">{index + 1}</span>
            ),
        },
        {
            title: 'اسم الفحص / الخدمة',
            dataIndex: 'service_name',
            key: 'service_name',
            render: (name: string, record: any) => (
                <div>
                    <span className="font-bold text-slate-800 text-xs block">{name}</span>
                    {record.selected_options && record.selected_options.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-1">
                            {record.selected_options.map((opt: any) => (
                                <Tag key={opt.id} color="blue" className="text-[10px] m-0">
                                    {opt.name} {opt.additional_price > 0 && `(+${Number(opt.additional_price).toFixed(0)} ج.م)`}
                                </Tag>
                            ))}
                        </div>
                    )}
                </div>
            ),
        },
        {
            title: 'الكمية',
            dataIndex: 'quantity',
            key: 'quantity',
            width: 60,
            align: 'center' as const,
            render: (qty: number) => <span className="font-mono text-xs">{qty}</span>,
        },
        {
            title: 'سعر الوحدة',
            dataIndex: 'unit_price',
            key: 'unit_price',
            width: 90,
            align: 'right' as const,
            render: (price: number) => (
                <span className="font-mono text-xs">{Number(price).toFixed(2)} ج.م</span>
            ),
        },
        {
            title: 'الخصم',
            key: 'discount',
            width: 90,
            align: 'right' as const,
            render: (_: any, record: any) => {
                const discVal = Number(record.discount_value || 0);
                if (discVal <= 0) return <span className="text-slate-300 text-xs">-</span>;
                return (
                    <span className="font-mono text-xs text-amber-600">
                        {discVal} {record.discount_type === 'percent' ? '%' : 'ج.م'}
                    </span>
                );
            },
        },
        {
            title: 'الإجمالي',
            dataIndex: 'total',
            key: 'total',
            width: 100,
            align: 'right' as const,
            render: (total: number) => (
                <span className="font-mono font-bold text-xs text-sky-700">
                    {Number(total).toFixed(2)} ج.م
                </span>
            ),
        },
        {
            title: 'الحالة والتقرير',
            key: 'status_report',
            width: 120,
            render: (_: any, record: any) => (
                <Space orientation="vertical" size={2}>
                    <Tag color={record.status === 'completed' ? 'green' : record.status === 'cancelled' ? 'red' : 'orange'}>
                        {record.status_label || record.status}
                    </Tag>
                    {record.has_report && (
                        <Tag color="purple" className="text-[10px]">
                            📄 تقرير مسجل
                        </Tag>
                    )}
                </Space>
            ),
        },
    ];

    const paymentColumns = [
        {
            title: 'رقم الحركة',
            dataIndex: 'id',
            key: 'id',
            width: 75,
            render: (id: number) => <span className="font-mono text-xs">#{id}</span>,
        },
        {
            title: 'التاريخ',
            dataIndex: 'created_at',
            key: 'created_at',
            render: (val: string) => <span className="text-xs text-slate-500">{val}</span>,
        },
        {
            title: 'النوع',
            dataIndex: 'type',
            key: 'type',
            width: 80,
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
            render: (amount: number) => {
                const isNeg = amount < 0;
                return (
                    <span className={`font-mono font-bold text-xs ${isNeg ? 'text-rose-600' : 'text-emerald-600'}`}>
                        {Number(amount).toFixed(2)} ج.م
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
            render: (sId: number) => <span className="text-xs text-slate-400">{sId ? `#${sId}` : '-'}</span>,
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
            width={850}
            title={
                <div className="flex items-center justify-between pl-4">
                    <div className="flex items-center gap-2">
                        <div className="w-8 h-8 rounded-lg bg-sky-600 text-white flex items-center justify-center">
                            <EyeOutlined />
                        </div>
                        <div>
                            <span className="font-bold text-base text-slate-800">
                                تفاصيل الزيارة رقم #{visit.id}
                            </span>
                            <Text type="secondary" className="block text-xs">
                                {visit.visit_date} • {visit.shift_id ? `الوردية #${visit.shift_id}` : 'بدون وردية'}
                            </Text>
                        </div>
                    </div>
                    <Tag
                        color={
                            visit.status === 'completed'
                                ? 'green'
                                : visit.status === 'cancelled'
                                    ? 'red'
                                    : 'gold'
                        }
                        className="text-xs font-bold px-2 py-0.5"
                    >
                        {visit.status_label}
                    </Tag>
                </div>
            }
            footer={[
                <Button key="close" onClick={onClose}>
                    إغلاق
                </Button>,
                onOpenReceipt && (
                    <Button
                        key="receipt"
                        icon={<PrinterOutlined />}
                        onClick={() => {
                            onClose();
                            onOpenReceipt(visit);
                        }}
                    >
                        طباعة الإيصال
                    </Button>
                ),
                onOpenPayments && (
                    <Button
                        key="payments"
                        type="primary"
                        icon={<DollarOutlined />}
                        className="bg-sky-600 hover:bg-sky-500"
                        onClick={() => {
                            onClose();
                            onOpenPayments(visit);
                        }}
                    >
                        إدارة المدفوعات والاسترداد
                    </Button>
                ),
            ]}
        >
            <div className="py-2 space-y-4 max-h-[75vh] overflow-y-auto px-1">
                {/* 1. Patient & Visit General Information */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Patient Card */}
                    <Card
                        size="small"
                        className="border-slate-200"
                        title={
                            <Space className="text-xs font-bold text-slate-700">
                                <UserOutlined className="text-sky-600" />
                                <span>بيانات المريض</span>
                            </Space>
                        }
                    >
                        <Descriptions size="small" column={1} className="text-xs">
                            <Descriptions.Item label="الاسم الكامل">
                                <span className="font-bold text-slate-800">{patient?.full_name || '-'}</span>
                            </Descriptions.Item>
                            <Descriptions.Item label="رقم الهاتف">
                                <span className="font-mono text-slate-700">{patient?.phone || '-'}</span>
                            </Descriptions.Item>
                            <Descriptions.Item label="السن / الجنس">
                                <span>
                                    {patient?.age ? `${patient.age} سنة` : (patient?.birth_date || '-')}
                                    {patient?.gender_label ? ` • ${patient.gender_label}` : ''}
                                </span>
                            </Descriptions.Item>
                            <Descriptions.Item label="العنوان">
                                <span className="text-slate-600">{patient?.address || 'غير محدد'}</span>
                            </Descriptions.Item>
                            {patient?.notes && (
                                <Descriptions.Item label="ملاحظات المريض">
                                    <span className="text-slate-500">{patient.notes}</span>
                                </Descriptions.Item>
                            )}
                        </Descriptions>
                    </Card>

                    {/* Visit Info Card */}
                    <Card
                        size="small"
                        className="border-slate-200"
                        title={
                            <Space className="text-xs font-bold text-slate-700">
                                <CalendarOutlined className="text-sky-600" />
                                <span>بيانات الزيارة والإحالة</span>
                            </Space>
                        }
                    >
                        <Descriptions size="small" column={1} className="text-xs">
                            <Descriptions.Item label="تاريخ وتوقيت الزيارة">
                                <span className="text-slate-700 font-medium">{visit.visit_date}</span>
                            </Descriptions.Item>
                            <Descriptions.Item label="طبيب الإحالة">
                                {referring_doctor ? (
                                    <Tag color="cyan">{referring_doctor.name}</Tag>
                                ) : (
                                    <Text type="secondary">مباشر (بدون طبيب إحالة)</Text>
                                )}
                            </Descriptions.Item>
                            <Descriptions.Item label="رقم الفاتورة">
                                <span className="font-mono font-bold text-sky-700">
                                    {invoice?.invoice_number || '-'}
                                </span>
                            </Descriptions.Item>
                            <Descriptions.Item label="الوردية">
                                <span className="font-mono text-slate-600">
                                    {visit.shift_id ? `وردية #${visit.shift_id}` : 'غير محددة'}
                                </span>
                            </Descriptions.Item>
                            {visit.notes && (
                                <Descriptions.Item label="ملاحظات الزيارة">
                                    <span className="text-slate-500">{visit.notes}</span>
                                </Descriptions.Item>
                            )}
                        </Descriptions>
                    </Card>
                </div>

                {/* 2. Services Table */}
                <Card
                    size="small"
                    className="border-slate-200"
                    title={
                        <div className="flex justify-between items-center">
                            <Space className="text-xs font-bold text-slate-700">
                                <MedicineBoxOutlined className="text-sky-600" />
                                <span>الفحوصات والخدمات المطلوبة ({visit_services?.length || 0})</span>
                            </Space>
                        </div>
                    }
                >
                    <Table
                        size="small"
                        rowKey="id"
                        dataSource={visit_services || []}
                        columns={serviceColumns}
                        pagination={false}
                        className="text-xs border border-slate-100 rounded"
                    />
                </Card>

                {/* 3. Financial Summary */}
                <div className="bg-gradient-to-l from-slate-50 to-sky-50/40 p-4 rounded-xl border border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                    <div>
                        <span className="text-xs text-slate-500 block">إجمالي الخدمات</span>
                        <span className="font-bold text-slate-800 text-sm font-mono">
                            {Number(invoice?.total_amount || 0).toFixed(2)} ج.م
                        </span>
                    </div>
                    <div>
                        <span className="text-xs text-slate-500 block">إجمالي الخصومات</span>
                        <span className="font-bold text-amber-600 text-sm font-mono">
                            {Number(invoice?.discount_total || 0).toFixed(2)} ج.م
                        </span>
                    </div>
                    <div>
                        <span className="text-xs text-slate-500 block">المسدد فعلياً</span>
                        <span className="font-bold text-emerald-600 text-sm font-mono">
                            {Number(invoice?.paid_amount || 0).toFixed(2)} ج.م
                        </span>
                    </div>
                    <div>
                        <span className="text-xs text-slate-500 block">المتبقي المستحق</span>
                        <span className={`font-bold text-sm font-mono ${Number(invoice?.remaining_amount || 0) > 0 ? 'text-rose-600' : 'text-slate-700'}`}>
                            {Number(invoice?.remaining_amount || 0).toFixed(2)} ج.م
                        </span>
                    </div>
                </div>

                {/* 4. Payments and Refunds Log */}
                <Card
                    size="small"
                    className="border-slate-200"
                    title={
                        <Space className="text-xs font-bold text-slate-700">
                            <DollarOutlined className="text-emerald-600" />
                            <span>سجل المدفوعات والاستردادات ({payments?.length || 0})</span>
                        </Space>
                    }
                >
                    {payments && payments.length > 0 ? (
                        <Table
                            size="small"
                            rowKey="id"
                            dataSource={payments}
                            columns={paymentColumns}
                            pagination={false}
                            className="text-xs border border-slate-100 rounded"
                        />
                    ) : (
                        <div className="text-center py-4 text-xs text-slate-400">
                            لا توجد مدفوعات مسجلة لهذه الزيارة حتى الآن.
                        </div>
                    )}
                </Card>

                {/* 5. Medical Reports */}
                {reports && reports.length > 0 && (
                    <Card
                        size="small"
                        className="border-slate-200"
                        title={
                            <div className="flex justify-between items-center">
                                <Space className="text-xs font-bold text-slate-700">
                                    <FileTextOutlined className="text-purple-600" />
                                    <span>التقارير الطبية ({reports.length})</span>
                                </Space>
                                {onOpenReports && (
                                    <Button
                                        size="small"
                                        type="link"
                                        onClick={() => {
                                            onClose();
                                            onOpenReports(visit);
                                        }}
                                        className="text-xs text-purple-600 p-0"
                                    >
                                        فتح نافذة التقارير
                                    </Button>
                                )}
                            </div>
                        }
                    >
                        <div className="space-y-3">
                            {reports.map((rep) => (
                                <div
                                    key={rep.id}
                                    className="p-3 bg-purple-50/50 rounded-lg border border-purple-100 space-y-1.5"
                                >
                                    <div className="flex justify-between items-center text-xs">
                                        <span className="font-bold text-purple-900">{rep.title}</span>
                                        <span className="text-slate-400 font-mono text-[11px]">{rep.created_at}</span>
                                    </div>
                                    <div className="text-xs text-slate-600 flex items-center gap-2">
                                        <span>الطبيب: {rep.doctor_name || 'غير محدد'}</span>
                                        {rep.service_name && (
                                            <Tag color="purple" className="text-[10px] m-0">
                                                {rep.service_name}
                                            </Tag>
                                        )}
                                    </div>
                                    <div className="text-xs text-slate-700 bg-white p-2.5 rounded border border-purple-100/60 leading-relaxed font-sans">
                                        {rep.report_text}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </Modal>
    );
};
