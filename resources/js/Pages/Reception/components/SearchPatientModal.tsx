import React, { useState } from 'react';
import { Modal, Form, Input, Button, Card, Table, Tag, Typography, Space, Empty, Spin } from 'antd';
import { SearchOutlined, UserOutlined, PhoneOutlined, PlusOutlined, EyeOutlined } from '@ant-design/icons';
import axios from 'axios';
import { Patient, PatientVisit } from '../types';

const { Text, Title } = Typography;

interface SearchPatientModalProps {
    open: boolean;
    onClose: () => void;
    onNewVisitForPatient: (patient: Patient) => void;
    onViewReceipt: (visitId: number) => void;
}

export const SearchPatientModal: React.FC<SearchPatientModalProps> = ({
    open,
    onClose,
    onNewVisitForPatient,
    onViewReceipt,
}) => {
    const [form] = Form.useForm();
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);
    const [results, setResults] = useState<Patient[]>([]);
    const [selectedPatient, setSelectedPatient] = useState<Patient | null>(null);

    const handleSearch = async (values: any) => {
        setLoading(true);
        setSearched(true);
        setSelectedPatient(null);
        try {
            const res = await axios.post('/reception/search-patients', values);
            setResults(res.data.patients || []);
            if (res.data.patients && res.data.patients.length === 1) {
                setSelectedPatient(res.data.patients[0]);
            }
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    };

    const handleReset = () => {
        form.resetFields();
        setResults([]);
        setSelectedPatient(null);
        setSearched(false);
    };

    const visitColumns = [
        {
            title: '#',
            dataIndex: 'id',
            key: 'id',
            width: 60,
            render: (id: number) => <span className="font-mono font-bold text-sky-700">#{id}</span>,
        },
        {
            title: 'تاريخ الزيارة',
            dataIndex: 'visit_date',
            key: 'visit_date',
        },
        {
            title: 'طبيب الإحالة',
            dataIndex: 'referring_doctor_name',
            key: 'referring_doctor_name',
        },
        {
            title: 'الخدمات والفحوصات',
            dataIndex: 'services',
            key: 'services',
            render: (services: string[]) => (
                <div className="flex flex-wrap gap-1">
                    {services && services.length > 0 ? (
                        services.map((s, idx) => (
                            <Tag key={idx} color="blue">{s}</Tag>
                        ))
                    ) : (
                        <Text type="secondary">-</Text>
                    )}
                </div>
            ),
        },
        {
            title: 'الحالة',
            dataIndex: 'status',
            key: 'status',
            width: 100,
            render: (status: string, record: any) => {
                const color = status === 'completed' ? 'success' : status === 'cancelled' ? 'error' : 'warning';
                return <Tag color={color}>{record.status_label}</Tag>;
            },
        },
        {
            title: 'الفاتورة',
            key: 'invoice',
            render: (_: any, record: any) => (
                <div className="text-xs">
                    <div>إجمالي: <span className="font-semibold">{Number(record.invoice?.total_amount ?? 0).toFixed(2)}</span> ج.م</div>
                    <div className="text-emerald-600">مسدد: {Number(record.invoice?.paid_amount ?? 0).toFixed(2)} ج.م</div>
                    {Number(record.invoice?.remaining_amount ?? 0) > 0 && (
                        <div className="text-rose-600 font-bold">متبقي: {Number(record.invoice?.remaining_amount ?? 0).toFixed(2)} ج.م</div>
                    )}
                </div>
            ),
        },
        {
            title: 'إجراءات',
            key: 'actions',
            width: 110,
            render: (_: any, record: any) => (
                <Button
                    size="small"
                    icon={<EyeOutlined />}
                    onClick={() => {
                        onClose();
                        onViewReceipt(record.id);
                    }}
                >
                    الإيصال
                </Button>
            ),
        },
    ];

    return (
        <Modal
            open={open}
            onCancel={onClose}
            width={900}
            centered
            title={
                <Space>
                    <SearchOutlined className="text-sky-600" />
                    <span>البحث عن ملف مريض واستعراض زياراته السابقة</span>
                </Space>
            }
            footer={[
                <Button key="close" onClick={onClose}>
                    إغلاق
                </Button>,
            ]}
        >
            <div className="py-2 space-y-4">
                {/* Search Inputs Form */}
                <Card size="small" className="bg-slate-50 border-slate-200">
                    <Form form={form} layout="vertical" onFinish={handleSearch}>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <Form.Item name="phone" label="رقم الهاتف">
                                <Input
                                    prefix={<PhoneOutlined className="text-slate-400" />}
                                    placeholder="مثال: 01012345678"
                                    allowClear
                                />
                            </Form.Item>
                            <Form.Item name="name" label="اسم المريض">
                                <Input
                                    prefix={<UserOutlined className="text-slate-400" />}
                                    placeholder="الاسم الكامل أو جزء منه"
                                    allowClear
                                />
                            </Form.Item>
                            <Form.Item name="query" label="بحث عام / العمر">
                                <Input
                                    prefix={<SearchOutlined className="text-slate-400" />}
                                    placeholder="بحث بأي كلمة..."
                                    allowClear
                                />
                            </Form.Item>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button onClick={handleReset}>إعادة تعيين</Button>
                            <Button
                                type="primary"
                                htmlType="submit"
                                icon={<SearchOutlined />}
                                loading={loading}
                                className="bg-sky-600 hover:bg-sky-500"
                            >
                                بحث عن المريض
                            </Button>
                        </div>
                    </Form>
                </Card>

                {/* Results Section */}
                {loading && (
                    <div className="text-center py-8">
                        <Spin size="large" description="جاري البحث في قاعدة البيانات..." />
                    </div>
                )}

                {!loading && searched && results.length === 0 && (
                    <Empty
                        image={Empty.PRESENTED_IMAGE_SIMPLE}
                        description="لم يتم العثور على أي مريض بهذه البيانات"
                    />
                )}

                {!loading && results.length > 0 && (
                    <div className="space-y-4">
                        {/* Patients Selector if multiple */}
                        {results.length > 1 && (
                            <div>
                                <Text strong className="block mb-2 text-slate-700 text-xs">
                                    نتائج البحث ({results.length} مريض): انقر لاختيار المريض
                                </Text>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-1">
                                    {results.map((patient) => {
                                        const isSelected = selectedPatient?.id === patient.id;
                                        return (
                                            <div
                                                key={patient.id}
                                                onClick={() => setSelectedPatient(patient)}
                                                className={`p-3 rounded-lg border cursor-pointer transition flex justify-between items-center ${
                                                    isSelected
                                                        ? 'bg-sky-50 border-sky-500 shadow-sm'
                                                        : 'bg-white border-slate-200 hover:bg-slate-50'
                                                }`}
                                            >
                                                <div>
                                                    <span className="font-bold text-slate-800 block text-sm">
                                                        {patient.full_name}
                                                    </span>
                                                    <span className="text-xs text-slate-500 font-mono">
                                                        {patient.phone}
                                                    </span>
                                                    <span className="text-xs text-slate-400 mr-2">
                                                        {patient.age ? `(${patient.age} سنة)` : ''}
                                                    </span>
                                                </div>
                                                <Tag color="cyan">زيارات: {patient.visits_count || 0}</Tag>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Selected Patient Details & Visits Table */}
                        {selectedPatient && (
                            <Card
                                className="border border-sky-200 bg-sky-50/20 shadow-sm rounded-xl"
                                title={
                                    <div className="flex justify-between items-center">
                                        <Space>
                                            <UserOutlined className="text-sky-600" />
                                            <span className="font-bold text-slate-800">
                                                سجل زيارات: {selectedPatient.full_name}
                                            </span>
                                            <span className="text-xs text-slate-500">({selectedPatient.phone})</span>
                                        </Space>
                                        <Button
                                            type="primary"
                                            size="small"
                                            icon={<PlusOutlined />}
                                            onClick={() => {
                                                onClose();
                                                onNewVisitForPatient(selectedPatient);
                                            }}
                                            className="bg-emerald-600 hover:bg-emerald-500"
                                        >
                                            بدء زيارة جديدة لهذا المريض
                                        </Button>
                                    </div>
                                }
                            >
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs text-slate-600 bg-white p-3 rounded-lg border border-slate-100 mb-3">
                                    <div><strong>رقم الهاتف:</strong> {selectedPatient.phone}</div>
                                    <div>
                                        <strong>العمر / تاريخ الميلاد:</strong>{' '}
                                        {selectedPatient.age ? `${selectedPatient.age} سنة` : ''}{' '}
                                        {selectedPatient.birth_date ? `(${selectedPatient.birth_date})` : '-'}
                                    </div>
                                    <div><strong>الجنس:</strong> {selectedPatient.gender_label || selectedPatient.gender || '-'}</div>
                                    <div><strong>إجمالي الزيارات:</strong> {selectedPatient.visits_count || 0}</div>
                                </div>

                                <Table
                                    columns={visitColumns}
                                    dataSource={selectedPatient.visits || []}
                                    rowKey="id"
                                    pagination={false}
                                    size="small"
                                    locale={{ emptyText: 'لا توجد زيارات سابقة مسجلة لهذا المريض' }}
                                />
                            </Card>
                        )}
                    </div>
                )}
            </div>
        </Modal>
    );
};
