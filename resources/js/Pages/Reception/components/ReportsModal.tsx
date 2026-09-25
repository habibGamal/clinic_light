import React, { useState } from 'react';
import { Modal, Button, Empty, Card, Typography, Space, Tag, Divider } from 'antd';
import { PrinterOutlined, FileTextOutlined, UserOutlined, MedicineBoxOutlined } from '@ant-design/icons';
import { PatientVisit, ReportRecord } from '../types';

const { Title, Text, Paragraph } = Typography;

interface ReportsModalProps {
    open: boolean;
    visit: PatientVisit | null;
    onClose: () => void;
}

export const ReportsModal: React.FC<ReportsModalProps> = ({ open, visit, onClose }) => {
    const [selectedReport, setSelectedReport] = useState<ReportRecord | null>(null);

    if (!visit) {
        return null;
    }

    const reports = visit.reports || [];

    const handlePrintReport = (report: ReportRecord) => {
        setSelectedReport(report);
        setTimeout(() => {
            window.print();
        }, 150);
    };

    return (
        <Modal
            open={open}
            onCancel={onClose}
            width={800}
            centered
            title={
                <Space>
                    <FileTextOutlined className="text-sky-600" />
                    <span>التقارير الطبية للزيارة #{visit.id} ({visit.patient?.full_name})</span>
                </Space>
            }
            footer={[
                <Button key="close" onClick={onClose}>
                    إغلاق
                </Button>,
            ]}
        >
            <div className="py-2">
                <Text type="secondary" className="block mb-4 text-xs">
                    ملاحظة: موظف الاستقبال يمكنه استعراض وطباعة التقارير الطبية للمريض. كتابة التقارير وتعديلها تتم من خلال الطبيب المعالج عبر لوحة الإدارة.
                </Text>

                {reports.length === 0 ? (
                    <Empty
                        image={Empty.PRESENTED_IMAGE_SIMPLE}
                        description="لم يقم الطبيب بإصدار أي تقارير طبية لهذه الزيارة حتى الآن"
                    />
                ) : (
                    <div className="space-y-4">
                        {reports.map((report) => (
                            <Card
                                key={report.id}
                                className="border border-slate-200 shadow-sm rounded-xl"
                                title={
                                    <div className="flex justify-between items-center py-1">
                                        <Space>
                                            <MedicineBoxOutlined className="text-sky-600" />
                                            <span className="font-bold text-slate-800">{report.title}</span>
                                        </Space>
                                        <Tag color="blue">{report.service_name || 'فحص طبي'}</Tag>
                                    </div>
                                }
                                extra={
                                    <Button
                                        type="primary"
                                        size="small"
                                        icon={<PrinterOutlined />}
                                        onClick={() => handlePrintReport(report)}
                                        className="bg-sky-600 hover:bg-sky-500"
                                    >
                                        طباعة التقرير
                                    </Button>
                                }
                            >
                                <div className="flex flex-wrap gap-4 text-xs text-slate-500 mb-3 pb-2 border-b border-slate-100">
                                    <div>
                                        <UserOutlined className="ml-1" />
                                        <span>الطبيب: {report.doctor_name || 'طبيب المركز'}</span>
                                    </div>
                                    <div>
                                        <span>تاريخ التقرير: {report.created_at}</span>
                                    </div>
                                </div>
                                <div className="text-slate-700 whitespace-pre-wrap font-sans text-sm leading-relaxed bg-slate-50 p-4 rounded-lg border border-slate-100">
                                    {report.report_text ? (
                                        <div dangerouslySetInnerHTML={{ __html: report.report_text }} />
                                    ) : (
                                        <Text type="secondary">لا يوجد محتوى مسجل</Text>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Hidden printable report layout */}
            {selectedReport && (
                <div id="printable-report" className="hidden">
                    <div className="p-8 bg-white text-slate-800 max-w-4xl mx-auto">
                        <div className="flex justify-between items-center border-b-2 border-sky-600 pb-4 mb-6">
                            <div>
                                <h1 className="text-2xl font-bold text-sky-800 m-0">مركز عيادتي الطبي</h1>
                                <p className="text-xs text-slate-500 m-0">Clinic Light Diagnostic & Medical Center</p>
                            </div>
                            <div className="text-left text-xs text-slate-500">
                                <p className="m-0">تاريخ التقرير: {selectedReport.created_at}</p>
                                <p className="m-0">رقم الزيارة: #{visit.id}</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-4 gap-2 bg-slate-50 p-3 rounded border border-slate-200 text-xs mb-6">
                            <div><strong>المريض:</strong> {visit.patient?.full_name}</div>
                            <div><strong>الهاتف:</strong> {visit.patient?.phone || '-'}</div>
                            <div><strong>العمر:</strong> {visit.patient?.age ? `${visit.patient.age} سنة` : (visit.patient?.birth_date || '-')}</div>
                            <div><strong>طبيب التقرير:</strong> {selectedReport.doctor_name}</div>
                        </div>

                        <div className="mb-4">
                            <h2 className="text-lg font-bold text-slate-800 border-b pb-1">{selectedReport.title}</h2>
                            <p className="text-xs text-slate-500">الخدمة المفحوصة: {selectedReport.service_name}</p>
                        </div>

                        <div
                            className="text-sm leading-relaxed text-slate-700 min-h-[350px] p-4 border rounded"
                            dangerouslySetInnerHTML={{ __html: selectedReport.report_text }}
                        />

                        <div className="mt-12 flex justify-between items-end text-xs pt-4 border-t border-slate-200">
                            <div>ختم المركز الطبي</div>
                            <div className="text-center">
                                <p className="mb-8 font-semibold">توقيع الطبيب المعالج</p>
                                <p className="m-0">د. {selectedReport.doctor_name}</p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <style>{`
                @media print {
                    body * {
                        visibility: hidden !important;
                    }
                    #printable-report, #printable-report * {
                        visibility: visible !important;
                    }
                    #printable-report {
                        display: block !important;
                        position: fixed !important;
                        left: 0 !important;
                        top: 0 !important;
                        width: 100% !important;
                        background: white !important;
                    }
                }
            `}</style>
        </Modal>
    );
};
