import React, { useState } from 'react';
import {
    Table,
    Button,
    Input,
    Select,
    DatePicker,
    Radio,
    Tag,
    Space,
    Card,
    Popconfirm,
    Tooltip,
    Typography,
    App,
} from 'antd';
import {
    PlusOutlined,
    SearchOutlined,
    PrinterOutlined,
    FileTextOutlined,
    DollarOutlined,
    EditOutlined,
    CheckCircleOutlined,
    CloseCircleOutlined,
    ReloadOutlined,
    CalendarOutlined,
    EyeOutlined,
} from '@ant-design/icons';
import { router } from '@inertiajs/react';
import dayjs from 'dayjs';
import { PatientVisit } from '../types';

const { Text } = Typography;

interface VisitsTabProps {
    visits: {
        data: PatientVisit[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    filters: {
        search: string;
        status: string;
        date_filter: string;
        date_from: string;
        date_to: string;
    };
    hasActiveShift?: boolean;
    onOpenNewVisit: () => void;
    onOpenSearchPatient: () => void;
    onViewDetails: (visit: PatientVisit) => void;
    onEditVisit: (visit: PatientVisit) => void;
    onViewReceipt: (visit: PatientVisit) => void;
    onViewReports: (visit: PatientVisit) => void;
    onRecordPayment: (visit: PatientVisit) => void;
}

export const VisitsTab: React.FC<VisitsTabProps> = ({
    visits,
    filters,
    hasActiveShift = true,
    onOpenNewVisit,
    onOpenSearchPatient,
    onViewDetails,
    onEditVisit,
    onViewReceipt,
    onViewReports,
    onRecordPayment,
}) => {
    const { message } = App.useApp();
    const [searchVal, setSearchVal] = useState(filters.search || '');

    const handleFilterChange = (key: string, val: any) => {
        router.get(
            '/reception',
            {
                ...filters,
                [key]: val,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleSearchSubmit = () => {
        handleFilterChange('search', searchVal);
    };

    const handleResetFilters = () => {
        setSearchVal('');
        router.get(
            '/reception',
            {
                search: '',
                status: 'all',
                date_filter: 'today',
                date_from: '',
                date_to: '',
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleCompleteVisit = (visit: PatientVisit) => {
        if (!hasActiveShift) {
            message.error('لا يمكن إكمال الزيارة بدون وجود وردية مفتوحة.');
            return;
        }

        const inv = visit.invoice;
        if (Number(inv?.remaining_amount || 0) > 0) {
            message.error(
                `لا يمكن إكمال الزيارة: توجد مبالغ مستحقة بقيمة ${Number(
                    inv?.remaining_amount || 0
                ).toFixed(2)} ج.م. يرجى سداد المبلغ أولاً.`
            );
            return;
        }

        router.post(
            `/reception/visits/${visit.id}/complete`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => message.success('تم إكمال الزيارة بنجاح!'),
                onError: (errs) => {
                    const firstError = Object.values(errs)[0] as string;
                    message.error(firstError || 'تعذر إكمال الزيارة');
                },
            }
        );
    };

    const handleCancelVisit = (visit: PatientVisit) => {
        if (!hasActiveShift) {
            message.error('لا يمكن إلغاء الزيارة بدون وجود وردية مفتوحة.');
            return;
        }

        router.post(
            `/reception/visits/${visit.id}/cancel`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    const inv = visit.invoice;
                    const hasPayments = Number(inv?.paid_amount || 0) > 0;
                    message.warning(
                        hasPayments
                            ? 'تم إلغاء الزيارة واسترداد المدفوعات تلقائياً.'
                            : 'تم إلغاء الزيارة بنجاح.'
                    );
                },
                onError: (errs) => {
                    const firstError = Object.values(errs)[0] as string;
                    message.error(firstError || 'تعذر إلغاء الزيارة');
                },
            }
        );
    };

    // Columns matching PatientVisitResource
    const columns = [
        {
            title: 'رقم الزيارة',
            dataIndex: 'id',
            key: 'id',
            width: 90,
            sorter: (a: PatientVisit, b: PatientVisit) => a.id - b.id,
            render: (id: number) => (
                <span className="font-mono font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded border border-sky-100">
                    #{id}
                </span>
            ),
        },
        {
            title: 'المريض',
            key: 'patient_name',
            render: (_: any, record: PatientVisit) => (
                <div>
                    <span className="font-bold text-slate-800 block text-sm">
                        {record.patient?.full_name || 'غير محدد'}
                    </span>
                    <span className="text-xs text-slate-400">
                        {record.patient?.age ? `${record.patient.age} سنة` : (record.patient?.birth_date || '')}
                        {record.patient?.gender_label ? ` • ${record.patient.gender_label}` : ''}
                    </span>
                </div>
            ),
        },
        {
            title: 'هاتف المريض',
            key: 'patient_phone',
            render: (_: any, record: PatientVisit) => (
                <span className="font-mono text-slate-600 text-xs">
                    {record.patient?.phone || '-'}
                </span>
            ),
        },
        {
            title: 'طبيب الإحالة',
            key: 'referring_doctor',
            render: (_: any, record: PatientVisit) => (
                <span className="text-xs">
                    {record.referring_doctor?.name ? (
                        <Tag color="cyan">{record.referring_doctor.name}</Tag>
                    ) : (
                        <Text type="secondary">مباشر</Text>
                    )}
                </span>
            ),
        },
        {
            title: 'تاريخ الزيارة',
            dataIndex: 'visit_date',
            key: 'visit_date',
            render: (val: string) => (
                <span className="text-xs text-slate-600 font-medium">{val}</span>
            ),
        },
        {
            title: 'الحالة',
            dataIndex: 'status',
            key: 'status',
            width: 105,
            render: (status: string, record: PatientVisit) => {
                const color =
                    status === 'completed'
                        ? 'green'
                        : status === 'cancelled'
                            ? 'error'
                            : 'yellow';
                return <Tag color={color}>{record.status_label}</Tag>;
            },
        },
        {
            title: 'عدد الفحوصات',
            dataIndex: 'visit_services_count',
            key: 'visit_services_count',
            width: 105,
            align: 'center' as const,
            render: (count: number) => (
                <Tag color="blue">{count || 0} فحص</Tag>
            ),
        },
        {
            title: 'الحسابات (ج.م)',
            key: 'invoice',
            render: (_: any, record: PatientVisit) => {
                const invoice = record.invoice;
                const hasRefunds = record.payments?.some((p) => p.type === 'refund' || p.amount < 0);
                if (record.status === 'cancelled') {
                    return (
                        <div className="text-xs leading-relaxed">
                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-red-50 text-red-700 border border-red-200">
                                ملغاة
                            </span>
                            {hasRefunds && (
                                <div className="text-amber-600 font-medium text-[11px] mt-0.5">
                                    تم استرداد المبلغ
                                </div>
                            )}
                        </div>
                    );
                }
                if (!invoice) {
                    return <span className="text-slate-400 text-xs">-</span>;
                }
                return (
                    <div className="text-xs leading-relaxed">
                        <div>
                            إجمالي: <span className="font-bold text-slate-700">{Number(invoice.total_amount).toFixed(2)}</span>
                        </div>
                        <div className="text-emerald-600">
                            مسدد: <span>{Number(invoice.paid_amount).toFixed(2)}</span>
                        </div>
                        {Number(invoice.remaining_amount) > 0 && (
                            <div className="text-rose-600 font-bold">
                                متبقي: {Number(invoice.remaining_amount).toFixed(2)}
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            title: 'إجراءات الزيارة',
            key: 'actions',
            width: 260,
            render: (_: any, record: PatientVisit) => (
                <Space size="small" wrap>
                    {/* View Visit Details */}
                    <Tooltip title="عرض كافة تفاصيل الزيارة">
                        <Button
                            size="small"
                            icon={<EyeOutlined />}
                            onClick={() => onViewDetails(record)}
                        >
                            عرض
                        </Button>
                    </Tooltip>

                    {/* Edit Visit (waiting status only, requires open shift) */}
                    {record.status === 'waiting' && (
                        <Tooltip title={!hasActiveShift ? 'لا يمكن تعديل الزيارة بدون وجود وردية مفتوحة' : 'تعديل بيانات الزيارة والفحوصات'}>
                            <Button
                                size="small"
                                icon={<EditOutlined />}
                                disabled={!hasActiveShift}
                                onClick={() => onEditVisit(record)}
                            >
                                تعديل
                            </Button>
                        </Tooltip>
                    )}

                    {/* View Receipt / Invoice */}
                    <Tooltip title="عرض الفاتورة وطباعة الإيصال">
                        <Button
                            size="small"
                            icon={<PrinterOutlined />}
                            onClick={() => onViewReceipt(record)}
                        >
                            الإيصال
                        </Button>
                    </Tooltip>

                    {/* View Reports */}
                    <Tooltip title={`التقارير الطبية (${record.reports?.length || 0})`}>
                        <Button
                            size="small"
                            icon={<FileTextOutlined />}
                            onClick={() => onViewReports(record)}
                        >
                            التقارير {record.reports?.length > 0 && `(${record.reports.length})`}
                        </Button>
                    </Tooltip>

                    {/* Control Payments and Refunds */}
                    {((record.invoice?.remaining_amount || 0) > 0 || Number(record.invoice?.paid_amount || 0) > 0) && record.status !== 'cancelled' && (
                        <Tooltip title="إدارة المدفوعات والاسترداد">
                            <Button
                                size="small"
                                type="primary"
                                ghost
                                icon={<DollarOutlined />}
                                onClick={() => onRecordPayment(record)}
                            >
                                {Number(record.invoice?.remaining_amount || 0) > 0 ? 'سداد' : 'مدفوعات'}
                            </Button>
                        </Tooltip>
                    )}

                    {/* Complete Visit (waiting status only, requires open shift) */}
                    {record.status === 'waiting' && (
                        <Popconfirm
                            title="إكمال الزيارة"
                            description={
                                Number(record.invoice?.remaining_amount || 0) > 0
                                    ? `توجد مبالغ مستحقة (${Number(record.invoice?.remaining_amount || 0).toFixed(2)} ج.م). يجب سدادها أولاً.`
                                    : 'هل أنت متأكد من إكمال الزيارة؟'
                            }
                            okText="تأكيد"
                            cancelText="إلغاء"
                            disabled={!hasActiveShift}
                            onConfirm={() => handleCompleteVisit(record)}
                        >
                            <Tooltip title={!hasActiveShift ? 'لا يمكن إكمال الزيارة بدون وجود وردية مفتوحة' : undefined}>
                                <Button
                                    size="small"
                                    type="primary"
                                    icon={<CheckCircleOutlined />}
                                    disabled={!hasActiveShift}
                                    className="bg-emerald-600 hover:bg-emerald-500"
                                >
                                    إكمال
                                </Button>
                            </Tooltip>
                        </Popconfirm>
                    )}

                    {/* Cancel Visit (waiting status only, requires open shift) */}
                    {record.status === 'waiting' && (
                        <Popconfirm
                            title="إلغاء الزيارة"
                            description={
                                Number(record.invoice?.paid_amount || 0) > 0
                                    ? `هل أنت متأكد من إلغاء هذه الزيارة؟ سيتم استرداد كافة المدفوعات (${Number(record.invoice?.paid_amount || 0).toFixed(2)} ج.م) تلقائياً.`
                                    : 'هل أنت متأكد من إلغاء هذه الزيارة؟'
                            }
                            okText="نعم، إلغاء واسترداد"
                            cancelText="تراجع"
                            disabled={!hasActiveShift}
                            okButtonProps={{ danger: true }}
                            onConfirm={() => handleCancelVisit(record)}
                        >
                            <Tooltip title={!hasActiveShift ? 'لا يمكن إلغاء الزيارة بدون وجود وردية مفتوحة' : 'إلغاء الزيارة واسترداد المدفوعات'}>
                                <Button
                                    size="small"
                                    danger
                                    disabled={!hasActiveShift}
                                    icon={<CloseCircleOutlined />}
                                />
                            </Tooltip>
                        </Popconfirm>
                    )}
                </Space>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            {/* Top Action Toolbar */}
            <div className="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div className="flex flex-wrap items-center gap-3">
                    <Tooltip title={!hasActiveShift ? 'تنبيه: لا توجد وردية مفتوحة. سيتم مطالبتك بفتح وردية أولاً.' : undefined}>
                        <Button
                            type="primary"
                            size="large"
                            icon={<PlusOutlined />}
                            onClick={onOpenNewVisit}
                            className={hasActiveShift ? 'bg-sky-600 hover:bg-sky-500 font-bold shadow-md' : 'bg-amber-600 hover:bg-amber-500 font-bold shadow-md'}
                        >
                            تسجيل زيارة جديدة
                        </Button>
                    </Tooltip>
                    <Button
                        size="large"
                        icon={<SearchOutlined />}
                        onClick={onOpenSearchPatient}
                        className="border-slate-300 font-semibold"
                    >
                        بحث عن مريض
                    </Button>
                </div>

                {/* Quick Date Toggle */}
                <div className="flex items-center gap-2">
                    <span className="text-xs text-slate-500 font-medium">عرض الزيارات:</span>
                    <Radio.Group
                        value={filters.date_filter}
                        onChange={(e) => handleFilterChange('date_filter', e.target.value)}
                        buttonStyle="solid"
                        size="middle"
                    >
                        <Radio.Button value="today">اليوم فقط</Radio.Button>
                        <Radio.Button value="all">كل التواريخ</Radio.Button>
                        <Radio.Button value="custom">مخصص</Radio.Button>
                    </Radio.Group>
                </div>
            </div>

            {/* Filter Bar */}
            <Card size="small" className="border-slate-200 bg-white">
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 items-center">
                    {/* Search */}
                    <div>
                        <Input
                            placeholder="بحث بالاسم أو الهاتف أو رقم الزيارة..."
                            prefix={<SearchOutlined className="text-slate-400" />}
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            onPressEnter={handleSearchSubmit}
                            allowClear
                        />
                    </div>

                    {/* Status Filter */}
                    <div>
                        <Select
                            className="w-full"
                            value={filters.status}
                            onChange={(val) => handleFilterChange('status', val)}
                            options={[
                                { value: 'all', label: 'جميع الحالات' },
                                { value: 'waiting', label: 'في الانتظار' },
                                { value: 'completed', label: 'مكتملة' },
                                { value: 'cancelled', label: 'ملغاة' },
                            ]}
                        />
                    </div>

                    {/* Custom Date Range if active */}
                    {filters.date_filter === 'custom' && (
                        <div className="flex items-center gap-2 md:col-span-2">
                            <DatePicker
                                placeholder="من تاريخ"
                                value={filters.date_from ? dayjs(filters.date_from) : null}
                                onChange={(d) => handleFilterChange('date_from', d ? d.format('YYYY-MM-DD') : '')}
                                className="w-full"
                            />
                            <DatePicker
                                placeholder="إلى تاريخ"
                                value={filters.date_to ? dayjs(filters.date_to) : null}
                                onChange={(d) => handleFilterChange('date_to', d ? d.format('YYYY-MM-DD') : '')}
                                className="w-full"
                            />
                        </div>
                    )}

                    {/* Buttons */}
                    <div className="flex items-center gap-2">
                        <Button
                            type="primary"
                            icon={<SearchOutlined />}
                            onClick={handleSearchSubmit}
                            className="bg-sky-600 hover:bg-sky-500"
                        >
                            تصفية
                        </Button>
                        <Button icon={<ReloadOutlined />} onClick={handleResetFilters}>
                            إعادة ضبط
                        </Button>
                    </div>
                </div>
            </Card>

            {/* Visits Table */}
            <Card size="small" className="border-slate-200 shadow-sm rounded-xl">
                <Table
                    columns={columns}
                    dataSource={visits.data}
                    rowKey="id"
                    pagination={{
                        current: visits.current_page,
                        total: visits.total,
                        pageSize: visits.per_page,
                        showTotal: (total, range) => `عرض ${range[0]}-${range[1]} من أصل ${total} زيارة`,
                        onChange: (page) => handleFilterChange('page', page),
                    }}
                    size="middle"
                    bordered
                    scroll={{ x: 1000 }}
                />
            </Card>
        </div>
    );
};
