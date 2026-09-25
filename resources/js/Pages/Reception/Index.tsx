import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Tabs, Tag, Button, Space, Typography, Card, Alert } from 'antd';
import {
    CalendarOutlined,
    WalletOutlined,
    MedicineBoxOutlined,
    UserOutlined,
    LogoutOutlined,
    DashboardOutlined,
    ClockCircleOutlined,
    UnlockOutlined,
    LockOutlined,
    WarningOutlined,
} from '@ant-design/icons';

import { ReceptionProps, Patient, PatientVisit } from './types';
import { VisitsTab } from './tabs/VisitsTab';
import { ExpensesTab } from './tabs/ExpensesTab';
import { NewVisitModal } from './components/NewVisitModal';
import { SearchPatientModal } from './components/SearchPatientModal';
import { ReceiptModal } from './components/ReceiptModal';
import { ReportsModal } from './components/ReportsModal';
import { PaymentModal } from './components/PaymentModal';
import { StartShiftModal } from './components/StartShiftModal';
import { CloseShiftModal } from './components/CloseShiftModal';
import { VisitDetailsModal } from './components/VisitDetailsModal';

const { Title, Text } = Typography;

export default function ReceptionIndex({
    visits,
    todayExpenses,
    todayExpensesTotal,
    services,
    referringDoctors,
    expenseCategories,
    activeShift,
    filters,
    paymentMethods,
    genderOptions,
}: ReceptionProps) {
    const [activeTab, setActiveTab] = useState('visits');

    // Modals state
    const [newVisitModalOpen, setNewVisitModalOpen] = useState(false);
    const [searchPatientModalOpen, setSearchPatientModalOpen] = useState(false);
    const [receiptModalOpen, setReceiptModalOpen] = useState(false);
    const [reportsModalOpen, setReportsModalOpen] = useState(false);
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const [visitDetailsModalOpen, setVisitDetailsModalOpen] = useState(false);
    const [startShiftModalOpen, setStartShiftModalOpen] = useState(false);
    const [closeShiftModalOpen, setCloseShiftModalOpen] = useState(false);

    // Selected items for modals
    const [selectedVisit, setSelectedVisit] = useState<PatientVisit | null>(null);
    const [editingVisit, setEditingVisit] = useState<PatientVisit | null>(null);
    const [prefilledPatient, setPrefilledPatient] = useState<Patient | null>(null);
    const [pendingPatientForVisit, setPendingPatientForVisit] = useState<Patient | null | undefined>(undefined);

    const handleOpenNewVisit = (patient?: Patient | null) => {
        if (!activeShift) {
            setPendingPatientForVisit(patient ?? null);
            setStartShiftModalOpen(true);
            return;
        }
        setEditingVisit(null);
        setPrefilledPatient(patient || null);
        setNewVisitModalOpen(true);
    };

    const handleStartShiftSuccess = () => {
        if (pendingPatientForVisit !== undefined) {
            const patient = pendingPatientForVisit;
            setPendingPatientForVisit(undefined);
            setEditingVisit(null);
            setPrefilledPatient(patient);
            setNewVisitModalOpen(true);
        }
    };

    const handleEditVisit = (visit: PatientVisit) => {
        setEditingVisit(visit);
        setPrefilledPatient(null);
        setNewVisitModalOpen(true);
    };

    const handleNewVisitSuccess = (newVisitId?: number) => {
        // If a new visit was created, find it or open receipt modal
        if (newVisitId) {
            const found = visits.data.find((v) => v.id === newVisitId);
            if (found) {
                setSelectedVisit(found);
                setReceiptModalOpen(true);
            }
        }
    };

    const handleViewReceiptById = (visitId: number) => {
        const found = visits.data.find((v) => v.id === visitId);
        if (found) {
            setSelectedVisit(found);
            setReceiptModalOpen(true);
        } else {
            // Reload with search for that visit ID
            router.get(
                '/reception',
                { search: visitId, date_filter: 'all' },
                {
                    preserveState: true,
                    onSuccess: (page: any) => {
                        const vList: PatientVisit[] = page.props?.visits?.data || [];
                        const matched = vList.find((v) => v.id === visitId);
                        if (matched) {
                            setSelectedVisit(matched);
                            setReceiptModalOpen(true);
                        }
                    },
                }
            );
        }
    };

    const handleLogout = () => {
        router.post('/logout');
    };

    const tabItems = [
        {
            key: 'visits',
            label: (
                <span className="flex items-center gap-2 px-2 py-1 text-sm font-semibold">
                    <CalendarOutlined />
                    <span>زيارات المرضى</span>
                    <Tag color="blue" className="mr-1">{visits.total}</Tag>
                </span>
            ),
            children: (
                <VisitsTab
                    visits={visits}
                    filters={filters}
                    hasActiveShift={!!activeShift}
                    onOpenNewVisit={() => handleOpenNewVisit(null)}
                    onOpenSearchPatient={() => setSearchPatientModalOpen(true)}
                    onViewDetails={(v) => {
                        setSelectedVisit(v);
                        setVisitDetailsModalOpen(true);
                    }}
                    onEditVisit={handleEditVisit}
                    onViewReceipt={(v) => {
                        setSelectedVisit(v);
                        setReceiptModalOpen(true);
                    }}
                    onViewReports={(v) => {
                        setSelectedVisit(v);
                        setReportsModalOpen(true);
                    }}
                    onRecordPayment={(v) => {
                        setSelectedVisit(v);
                        setPaymentModalOpen(true);
                    }}
                />
            ),
        },
        {
            key: 'expenses',
            label: (
                <span className="flex items-center gap-2 px-2 py-1 text-sm font-semibold">
                    <WalletOutlined />
                    <span>المصروفات اليومية</span>
                </span>
            ),
            children: (
                <ExpensesTab
                    todayExpenses={todayExpenses}
                    todayExpensesTotal={todayExpensesTotal}
                    expenseCategories={expenseCategories}
                    hasActiveShift={!!activeShift}
                />
            ),
        },
    ];

    return (
        <div className="min-h-screen bg-slate-50/70 text-slate-800">
            <Head title="شاشة الاستقبال - نظام العيادة" />

            {/* Top Navigation Bar */}
            <header className="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
                <div className="mx-auto px-4 sm:px-6 lg:px-8 py-3">
                    <div className="flex justify-between items-center">
                        {/* Brand & Clinic Title */}
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center shadow-md">
                                <MedicineBoxOutlined style={{ fontSize: 22 }} />
                            </div>
                            <div>
                                <Title level={4} style={{ margin: 0, color: '#0369a1', lineHeight: 1.2 }}>
                                    قسم الاستقبال
                                </Title>
                                <Text type="secondary" className="text-xs">
                                    Clinic Light • إدارة الزيارات والتحصيل والمصروفات
                                </Text>
                            </div>
                        </div>

                        {/* Shift Info & User Actions */}
                        <div className="flex items-center gap-3">
                            {activeShift ? (
                                <div className="flex items-center gap-2">
                                    <Button
                                        danger
                                        icon={<LockOutlined />}
                                        onClick={() => setCloseShiftModalOpen(true)}
                                        size="middle"
                                        title="إغلاق الوردية الحالية"
                                    >
                                        إغلاق الوردية
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex items-center gap-2">
                                    <Tag color="error" icon={<LockOutlined />} className="px-2 py-1 text-xs">
                                        بدون وردية نشطة
                                    </Tag>
                                    <Button
                                        type="primary"
                                        icon={<UnlockOutlined />}
                                        onClick={() => {
                                            setPendingPatientForVisit(undefined);
                                            setStartShiftModalOpen(true);
                                        }}
                                        size="middle"
                                        className="bg-emerald-600 hover:bg-emerald-500 font-semibold shadow-sm"
                                    >
                                        فتح وردية
                                    </Button>
                                </div>
                            )}

                            {/* Filament Admin Link */}
                            <Button
                                href="/admin"
                                icon={<DashboardOutlined />}
                                size="middle"
                                className="hidden sm:inline-flex items-center text-slate-600"
                            >
                                لوحة الإدارة
                            </Button>

                            {/* Logout */}
                            <Button
                                danger
                                icon={<LogoutOutlined />}
                                onClick={handleLogout}
                                size="middle"
                                title="تسجيل الخروج"
                            >
                                خروج
                            </Button>
                        </div>
                    </div>
                </div>
            </header>

            {/* Main Content Area */}
            <main className="mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <Tabs
                    activeKey={activeTab}
                    onChange={setActiveTab}
                    type="card"
                    size="large"
                    items={tabItems}
                    className="reception-tabs"
                />
            </main>

            {/* Modals */}
            <NewVisitModal
                open={newVisitModalOpen}
                initialPatient={prefilledPatient}
                visitToEdit={editingVisit}
                services={services}
                referringDoctors={referringDoctors}
                paymentMethods={paymentMethods}
                genderOptions={genderOptions}
                activeShift={activeShift}
                onClose={() => {
                    setNewVisitModalOpen(false);
                    setEditingVisit(null);
                }}
                onSuccess={(newId) => {
                    setEditingVisit(null);
                    handleNewVisitSuccess(newId);
                }}
            />

            <SearchPatientModal
                open={searchPatientModalOpen}
                onClose={() => setSearchPatientModalOpen(false)}
                onNewVisitForPatient={(p) => handleOpenNewVisit(p)}
                onViewReceipt={(vId) => handleViewReceiptById(vId)}
            />

            <ReceiptModal
                open={receiptModalOpen}
                visit={selectedVisit}
                onClose={() => setReceiptModalOpen(false)}
            />

            <ReportsModal
                open={reportsModalOpen}
                visit={selectedVisit}
                onClose={() => setReportsModalOpen(false)}
            />

            <PaymentModal
                open={paymentModalOpen}
                visit={selectedVisit}
                paymentMethods={paymentMethods}
                onClose={() => setPaymentModalOpen(false)}
            />

            <VisitDetailsModal
                open={visitDetailsModalOpen}
                visit={selectedVisit}
                hasActiveShift={!!activeShift}
                onClose={() => setVisitDetailsModalOpen(false)}
                onOpenReceipt={(v) => {
                    setSelectedVisit(v);
                    setReceiptModalOpen(true);
                }}
                onOpenPayments={(v) => {
                    setSelectedVisit(v);
                    setPaymentModalOpen(true);
                }}
                onOpenReports={(v) => {
                    setSelectedVisit(v);
                    setReportsModalOpen(true);
                }}
            />

            <StartShiftModal
                open={startShiftModalOpen}
                isForVisit={pendingPatientForVisit !== undefined}
                onClose={() => {
                    setStartShiftModalOpen(false);
                    setPendingPatientForVisit(undefined);
                }}
                onSuccess={handleStartShiftSuccess}
            />

            <CloseShiftModal
                open={closeShiftModalOpen}
                activeShift={activeShift}
                onClose={() => setCloseShiftModalOpen(false)}
            />
        </div>
    );
}

