import React, { useState, useEffect } from 'react';
import {
    Modal,
    Form,
    Input,
    InputNumber,
    Select,
    DatePicker,
    Button,
    Card,
    Tag,
    Alert,
    Radio,
    Checkbox,
    Space,
    Divider,
    Typography,
    App,
    Tooltip,
} from 'antd';
import {
    UserOutlined,
    PhoneOutlined,
    PlusOutlined,
    EditOutlined,
    DeleteOutlined,
    CheckCircleOutlined,
    InfoCircleOutlined,
    DollarOutlined,
    MedicineBoxOutlined,
    AppstoreOutlined,
} from '@ant-design/icons';
import dayjs from 'dayjs';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { Patient, PatientVisit, ServiceItem, ReferringDoctor, SelectedServiceLine, ActiveShift } from '../types';
import { ServiceCatalogModal } from './ServiceCatalogModal';

const { Text, Title } = Typography;

interface NewVisitModalProps {
    open: boolean;
    initialPatient?: Patient | null;
    visitToEdit?: PatientVisit | null;
    services: ServiceItem[];
    referringDoctors: ReferringDoctor[];
    paymentMethods: { value: string; label: string }[];
    genderOptions: { value: string; label: string }[];
    activeShift?: ActiveShift | null;
    onClose: () => void;
    onSuccess: (newVisitId?: number) => void;
}

export const NewVisitModal: React.FC<NewVisitModalProps> = ({
    open,
    initialPatient,
    visitToEdit,
    services,
    referringDoctors,
    paymentMethods,
    genderOptions,
    activeShift,
    onClose,
    onSuccess,
}) => {
    const isEditing = !!visitToEdit;
    const [form] = Form.useForm();
    const { message } = App.useApp();

    const [searchingPhone, setSearchingPhone] = useState(false);
    const [patientFound, setPatientFound] = useState<boolean | null>(null);
    const [selectedPatientId, setSelectedPatientId] = useState<number | null>(null);
    const [calculatedAge, setCalculatedAge] = useState<number | null>(null);

    // Selected Services List
    const [serviceLines, setServiceLines] = useState<SelectedServiceLine[]>([]);
    const [collectPaymentNow, setCollectPaymentNow] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [catalogModalOpen, setCatalogModalOpen] = useState(false);

    // Initial setup when modal opens
    useEffect(() => {
        if (open) {
            if (visitToEdit) {
                if (visitToEdit.patient) {
                    setSelectedPatientId(visitToEdit.patient.id || null);
                    setPatientFound(true);
                    const birthDay = visitToEdit.patient.birth_date ? dayjs(visitToEdit.patient.birth_date) : null;
                    if (birthDay) {
                        setCalculatedAge(dayjs().diff(birthDay, 'year'));
                    } else if (visitToEdit.patient.age) {
                        setCalculatedAge(visitToEdit.patient.age);
                    } else {
                        setCalculatedAge(null);
                    }

                    const vDate = visitToEdit.raw_visit_date
                        ? dayjs(visitToEdit.raw_visit_date)
                        : (visitToEdit.visit_date ? dayjs(visitToEdit.visit_date) : dayjs());

                    form.setFieldsValue({
                        phone: visitToEdit.patient.phone,
                        full_name: visitToEdit.patient.full_name,
                        birth_date: birthDay,
                        gender: visitToEdit.patient.gender,
                        address: visitToEdit.patient.address,
                        patient_notes: visitToEdit.patient.notes,
                        visit_date: vDate,
                        referring_doctor_id: visitToEdit.referring_doctor?.id || null,
                        notes: visitToEdit.notes,
                        payment_method: 'cash',
                    });
                }

                const reconstructedLines: SelectedServiceLine[] = (visitToEdit.visit_services || []).map((vs, idx) => {
                    const targetService = services.find((s) => s.id === vs.service_id) || services.find((s) => s.name === vs.service_name);
                    const selectedOptIds = (vs.selected_options || []).map((o) => o.service_option_id || o.id);
                    const unitPrice = vs.unit_price ?? (targetService?.base_price || 0);
                    const qty = vs.quantity || 1;
                    const discType = (vs.discount_type as 'fixed' | 'percent') || 'fixed';
                    const discVal = vs.discount_value || 0;
                    const subtotal = unitPrice * qty;
                    const discAmt = discType === 'percent' ? (subtotal * (discVal / 100)) : discVal;
                    const total = vs.total ?? Math.max(0, subtotal - discAmt);

                    return {
                        key: `vs_${vs.id || idx}_${Date.now()}_${idx}`,
                        id: vs.id,
                        service_id: vs.service_id || targetService?.id || 0,
                        service_name: vs.service_name,
                        quantity: qty,
                        base_price: vs.base_price ?? (targetService?.base_price || unitPrice),
                        unit_price: unitPrice,
                        selected_options: selectedOptIds,
                        discount_type: discType,
                        discount_value: discVal,
                        discount_amount: discAmt,
                        total: total,
                        has_report: !!vs.has_report,
                        available_groups: targetService?.option_groups || [],
                    };
                });
                setServiceLines(reconstructedLines);
                setCollectPaymentNow(false);
            } else if (initialPatient) {
                setSelectedPatientId(initialPatient.id || null);
                setPatientFound(true);
                const birthDay = initialPatient.birth_date ? dayjs(initialPatient.birth_date) : null;
                if (birthDay) {
                    setCalculatedAge(dayjs().diff(birthDay, 'year'));
                } else if (initialPatient.age) {
                    setCalculatedAge(initialPatient.age);
                }

                form.setFieldsValue({
                    phone: initialPatient.phone,
                    full_name: initialPatient.full_name,
                    birth_date: birthDay,
                    gender: initialPatient.gender,
                    address: initialPatient.address,
                    visit_date: dayjs(),
                    payment_method: 'cash',
                });
                setServiceLines([]);
                setCollectPaymentNow(true);
            } else {
                form.resetFields();
                form.setFieldsValue({
                    visit_date: dayjs(),
                    payment_method: 'cash',
                });
                setSelectedPatientId(null);
                setPatientFound(null);
                setCalculatedAge(null);
                setServiceLines([]);
                setCollectPaymentNow(true);
            }
        }
    }, [open, visitToEdit, initialPatient, form, services]);

    // Handle Phone Search
    const lookupPatientByPhone = async (phoneVal: string) => {
        const cleaned = phoneVal.trim();
        if (!cleaned || cleaned.length < 5) {
            return;
        }

        setSearchingPhone(true);
        try {
            const res = await axios.get('/reception/patient-by-phone', {
                params: { phone: cleaned },
            });

            if (res.data.found && res.data.patient) {
                const p: Patient = res.data.patient;
                setSelectedPatientId(p.id || null);
                setPatientFound(true);

                const birthDay = p.birth_date ? dayjs(p.birth_date) : null;
                if (birthDay) {
                    setCalculatedAge(dayjs().diff(birthDay, 'year'));
                }

                form.setFieldsValue({
                    full_name: p.full_name,
                    birth_date: birthDay,
                    gender: p.gender,
                    address: p.address,
                });
                message.success(`تم العثور على ملف المريض: ${p.full_name}`);
            } else {
                setSelectedPatientId(null);
                setPatientFound(false);
            }
        } catch {
            setSelectedPatientId(null);
            setPatientFound(false);
        } finally {
            setSearchingPhone(false);
        }
    };

    // Calculate Age when birth_date changes
    const handleBirthDateChange = (date: dayjs.Dayjs | null) => {
        if (date) {
            const age = dayjs().diff(date, 'year');
            setCalculatedAge(age);
        } else {
            setCalculatedAge(null);
        }
    };

    // Handle confirmed services list from ServiceCatalogModal
    const handleConfirmCatalogServices = (updatedLines: SelectedServiceLine[]) => {
        setServiceLines(updatedLines);
    };

    // Update Quantity or Discount of a line
    const handleLineChange = (
        lineKey: string,
        field: 'quantity' | 'discount_type' | 'discount_value',
        val: any
    ) => {
        setServiceLines((prev) =>
            prev.map((line) => {
                if (line.key !== lineKey) return line;

                const updated = { ...line, [field]: val };
                const subtotal = updated.unit_price * updated.quantity;
                const discAmt =
                    updated.discount_type === 'percent'
                        ? subtotal * (updated.discount_value / 100)
                        : updated.discount_value;
                const total = Math.max(0, subtotal - discAmt);

                return {
                    ...updated,
                    discount_amount: discAmt,
                    total: total,
                };
            })
        );
    };

    const handleRemoveLine = (lineKey: string) => {
        const line = serviceLines.find((l) => l.key === lineKey);
        if (line?.has_report) {
            message.warning('لا يمكن حذف هذا الفحص لوجود تقرير طبي مسجل له.');
            return;
        }
        setServiceLines((prev) => prev.filter((l) => l.key !== lineKey));
    };

    // Calculations for entire visit
    const grossTotal = serviceLines.reduce((acc, l) => acc + l.unit_price * l.quantity, 0);
    const totalDiscounts = serviceLines.reduce((acc, l) => acc + l.discount_amount, 0);
    const netTotal = Math.max(0, grossTotal - totalDiscounts);


    // Handle Form Submit
    const handleSubmit = async () => {
        try {
            const values = await form.validateFields();

            if (serviceLines.length === 0) {
                message.error('يرجى إضافة خدمة أو فحص واحد على الأقل للزيارة!');
                return;
            }

            setSubmitting(true);

            const payload = {
                patient_id: selectedPatientId,
                full_name: values.full_name,
                phone: values.phone,
                birth_date: values.birth_date && dayjs(values.birth_date).isValid()
                    ? dayjs(values.birth_date).format('YYYY-MM-DD')
                    : null,
                gender: values.gender,
                address: values.address,
                patient_notes: values.patient_notes,

                visit_date: values.visit_date && dayjs(values.visit_date).isValid()
                    ? dayjs(values.visit_date).format('YYYY-MM-DD HH:mm:ss')
                    : null,
                referring_doctor_id: values.referring_doctor_id,
                notes: values.notes,

                services: serviceLines.map((l) => ({
                    id: l.id,
                    service_id: l.service_id,
                    quantity: l.quantity,
                    discount_type: l.discount_type,
                    discount_value: l.discount_value,
                    selected_options: l.selected_options,
                })),

                paid_amount: collectPaymentNow ? values.paid_amount : 0,
                payment_method: values.payment_method || 'cash',
                payment_notes: values.payment_notes,
            };

            if (isEditing && visitToEdit) {
                router.put(`/reception/visits/${visitToEdit.id}`, payload, {
                    onSuccess: () => {
                        message.success('تم تعديل بيانات الزيارة بنجاح!');
                        onClose();
                        onSuccess(visitToEdit.id);
                    },
                    onError: (errs) => {
                        const firstError = Object.values(errs)[0] as string;
                        message.error(firstError || 'حدث خطأ أثناء تعديل الزيارة');
                    },
                    onFinish: () => setSubmitting(false),
                });
            } else {
                if (!activeShift) {
                    message.error('لا يمكن تسجيل زيارة جديدة لعدم وجود وردية مفتوحة. يرجى فتح وردية أولاً.');
                    setSubmitting(false);
                    return;
                }

                router.post('/reception/visits', payload, {
                    onSuccess: (page: any) => {
                        message.success('تم تسجيل الزيارة بنجاح!');
                        onClose();
                        const newId = (page?.props as any)?.new_visit_id || (page?.props as any)?.flash?.new_visit_id;
                        onSuccess(newId);
                    },
                    onError: (errs) => {
                        const firstError = Object.values(errs)[0] as string;
                        message.error(firstError || 'حدث خطأ أثناء حفظ الزيارة');
                    },
                    onFinish: () => setSubmitting(false),
                });
            }
        } catch {
            // Form validation failed
        }
    };

    return (
        <Modal
            open={open}
            onCancel={onClose}
            width={920}
            centered
            title={
                <div className="flex items-center gap-2">
                    <div className={`w-8 h-8 rounded-lg ${isEditing ? 'bg-amber-600' : 'bg-sky-600'} text-white flex items-center justify-center`}>
                        {isEditing ? <EditOutlined /> : <PlusOutlined />}
                    </div>
                    <div>
                        <span className="font-bold text-base text-slate-800">
                            {isEditing ? `تعديل زيارة المريض: ${visitToEdit?.patient?.full_name || ''} (#${visitToEdit?.id})` : 'تسجيل زيارة مريض جديدة'}
                        </span>
                        <Text type="secondary" className="block text-xs">
                            {isEditing ? 'تعديل بيانات المريض، الفحوصات والخدمات، وتفاصيل الزيارة' : 'إدخال بيانات المريض، اختيار الفحوصات والخدمات، وتسجيل الدفعة الأولى'}
                        </Text>
                    </div>
                </div>
            }
            footer={[
                <Button key="cancel" onClick={onClose}>
                    إلغاء
                </Button>,
                <Button
                    key="submit"
                    type="primary"
                    loading={submitting}
                    disabled={!isEditing && !activeShift}
                    onClick={handleSubmit}
                    className={isEditing ? 'bg-amber-600 hover:bg-amber-500 font-bold px-6' : 'bg-sky-600 hover:bg-sky-500 font-bold px-6'}
                >
                    {isEditing ? 'حفظ تعديلات الزيارة' : 'حفظ الزيارة وطباعة الإيصال'}
                </Button>,
            ]}
        >
            <Form form={form} layout="vertical" className="py-2 flex flex-col gap-4" initialValues={{ paid_amount: 0 }}>
                {!isEditing && !activeShift && (
                    <Alert
                        type="error"
                        showIcon
                        title="تنبيه: لا توجد وردية عمل نشطة"
                        description="يجب فتح وردية عمل جديدة أولاً لتتمكن من تسجيل زيارات المرضى والتحصيل المالي."
                    />
                )}
                {/* 1. Patient Section */}
                <Card
                    size="small"
                    className="border-slate-200 bg-slate-50/50"
                    title={
                        <Space>
                            <UserOutlined className="text-sky-600" />
                            <span className="font-semibold text-slate-700">بيانات المريض</span>
                        </Space>
                    }
                >
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                        <Form.Item
                            name="phone"
                            label="رقم الهاتف"
                            rules={[{ required: true, message: 'رقم الهاتف مطلوب' }]}
                        >
                            <Input
                                prefix={<PhoneOutlined className="text-slate-400" />}
                                placeholder="01#########"
                                onBlur={(e) => lookupPatientByPhone(e.target.value)}
                                onPressEnter={(e: any) => lookupPatientByPhone(e.target.value)}
                            />
                        </Form.Item>

                        <Form.Item
                            name="full_name"
                            label="اسم المريض بالكامل"
                            rules={[{ required: true, message: 'اسم المريض مطلوب' }]}
                        >
                            <Input
                                prefix={<UserOutlined className="text-slate-400" />}
                                placeholder="مثال: أحمد محمد علي"
                            />
                        </Form.Item>

                        <Form.Item
                            name="birth_date"
                            label={
                                <div className="flex justify-between w-full">
                                    <span>تاريخ الميلاد</span>
                                    {calculatedAge !== null && (
                                        <span className="text-sky-600 font-bold mr-1">
                                            ({calculatedAge} سنة)
                                        </span>
                                    )}
                                </div>
                            }
                        >
                            <DatePicker
                                className="w-full"
                                placeholder="اختر تاريخ الميلاد"
                                maxDate={dayjs()}
                                onChange={handleBirthDateChange}
                                format="YYYY-MM-DD"
                            />
                        </Form.Item>

                        <Form.Item name="gender" label="الجنس">
                            <Select
                                placeholder="اختر الجنس"
                                allowClear
                                options={genderOptions}
                            />
                        </Form.Item>
                    </div>

                    {patientFound === true && (
                        <Alert
                            type="success"
                            showIcon
                            icon={<CheckCircleOutlined />}
                            title="تم العثور على ملف المريض مسجلاً مسبقاً، تم تحميل البيانات تلقائياً."
                            className="text-xs mb-2 py-1"
                        />
                    )}

                    {patientFound === false && (
                        <Alert
                            type="info"
                            showIcon
                            icon={<InfoCircleOutlined />}
                            title="رقم هاتف جديد. سيتم إنشاء ملف جديد للمريض تلقائياً."
                            className="text-xs mb-2 py-1"
                        />
                    )}

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-1">
                        <Form.Item name="address" label="العنوان" style={{ marginBottom: 0 }}>
                            <Input placeholder="العنوان أو المدينة (اختياري)" />
                        </Form.Item>
                        <Form.Item name="patient_notes" label="ملاحظات المريض" style={{ marginBottom: 0 }}>
                            <Input placeholder="أي ملاحظات تخص المريض..." />
                        </Form.Item>
                    </div>
                </Card>

                {/* 2. Visit Info */}
                <Card
                    size="small"
                    className="border-slate-200 bg-slate-50/50"
                    title={
                        <Space>
                            <MedicineBoxOutlined className="text-sky-600" />
                            <span className="font-semibold text-slate-700">بيانات الزيارة والإحالة</span>
                        </Space>
                    }
                >
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Form.Item
                            name="visit_date"
                            label="تاريخ وتوقيت الزيارة"
                            rules={[{ required: true, message: 'تاريخ الزيارة مطلوب' }]}
                        >
                            <DatePicker
                                showTime
                                format="YYYY-MM-DD HH:mm"
                                className="w-full"
                            />
                        </Form.Item>

                        <Form.Item name="referring_doctor_id" label="طبيب الإحالة (خارجي)">
                            <Select
                                placeholder="مباشر (بدون إحالة)"
                                allowClear
                                showSearch
                                options={referringDoctors.map((doc) => ({
                                    value: doc.id,
                                    label: `${doc.name} ${doc.specialization ? '(' + doc.specialization + ')' : ''}`,
                                }))}
                            />
                        </Form.Item>

                        <Form.Item name="notes" label="ملاحظات الزيارة">
                            <Input placeholder="شكوى المريض، ملاحظات أخرى..." />
                        </Form.Item>
                    </div>
                </Card>

                {/* 3. Services Selection */}
                <Card
                    size="small"
                    className="border-slate-200"
                    title={
                        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                            <Space>
                                <DollarOutlined className="text-sky-600" />
                                <span className="font-semibold text-slate-700">الفحوصات والخدمات المطلوبة</span>
                                <Tag color="blue">{serviceLines.length} خدمات</Tag>
                            </Space>
                        </div>
                    }
                >
                    {serviceLines.length === 0 ? (
                        <div className="text-center py-6 text-slate-400 border border-dashed rounded-lg space-y-2">
                            <Text type="secondary">لم يتم اختيار أي خدمات لهذه الزيارة بعد.</Text>
                            <div className="text-xs">
                                انقر على الزر بالأسفل لاختيار الفحوصات وتحديد الخيارات المطلوبة مباشرة من الدليل المنظم.
                            </div>
                            <Button
                                type="primary"
                                icon={<AppstoreOutlined />}
                                onClick={() => setCatalogModalOpen(true)}
                                className="bg-sky-600 hover:bg-sky-500 font-bold mt-1"
                            >
                                فتح دليل الفحوصات والخدمات
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {serviceLines.map((line, idx) => {
                                const selectedOpts = line.selected_options
                                    .map((optId) => {
                                        for (const group of line.available_groups || []) {
                                            const found = group.options.find((o) => o.id === optId);
                                            if (found) return { groupName: group.name, ...found };
                                        }
                                        return null;
                                    })
                                    .filter(Boolean);

                                return (
                                    <div
                                        key={line.key}
                                        className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-2"
                                    >
                                        <div className="flex justify-between items-start">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span className="font-bold text-sm text-slate-800 ml-2">
                                                    {idx + 1}. {line.service_name}
                                                </span>
                                                <Tag color="volcano">
                                                    سعر أساسي: {Number(line.base_price).toFixed(2)} ج.م
                                                </Tag>
                                                {line.has_report && (
                                                    <Tag color="purple" className="font-semibold">
                                                        📄 يوجد تقرير طبي
                                                    </Tag>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    size="small"
                                                    type="link"
                                                    onClick={() => setCatalogModalOpen(true)}
                                                    className="text-sky-600 text-xs px-1"
                                                >
                                                    تعديل
                                                </Button>
                                                {line.has_report ? (
                                                    <Tooltip title="لا يمكن حذف هذا الفحص لوجود تقرير طبي مسجل له">
                                                        <span>
                                                            <Button
                                                                type="text"
                                                                disabled
                                                                icon={<DeleteOutlined />}
                                                                className="opacity-40 cursor-not-allowed"
                                                            />
                                                        </span>
                                                    </Tooltip>
                                                ) : (
                                                    <Button
                                                        type="text"
                                                        danger
                                                        icon={<DeleteOutlined />}
                                                        onClick={() => handleRemoveLine(line.key)}
                                                    />
                                                )}
                                            </div>
                                        </div>

                                        {/* Display Chosen Options as Badges */}
                                        {selectedOpts.length > 0 ? (
                                            <div className="flex flex-wrap items-center gap-1.5 bg-white p-2 rounded border border-slate-200/80">
                                                <span className="text-[11px] font-semibold text-slate-500">
                                                    الخيارات المحددة:
                                                </span>
                                                <div className="flex flex-col gap-1">
                                                    {selectedOpts.map((opt: any) => (
                                                        <Tag key={opt.id} color="blue" className="m-0 text-[11px]">
                                                            {opt.groupName}: {opt.name}{' '}
                                                            {opt.additional_price > 0 && (
                                                                <span className="font-mono font-bold">
                                                                    (+{Number(opt.additional_price).toFixed(0)} ج.م)
                                                                </span>
                                                            )}
                                                        </Tag>
                                                    ))}
                                                </div>
                                            </div>
                                        ) : line.available_groups && line.available_groups.length > 0 ? (
                                            <div className="text-[11px] text-slate-400">
                                                بدون خيارات إضافية.{' '}
                                                <Button
                                                    type="link"
                                                    size="small"
                                                    onClick={() => setCatalogModalOpen(true)}
                                                    className="text-sky-600 p-0 text-xs"
                                                >
                                                    تحديد خيارات
                                                </Button>
                                            </div>
                                        ) : null}

                                        {/* Line calculations: Quantity, Discount, Total */}
                                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 items-center pt-1 border-t border-slate-200">
                                            <div className="flex items-center gap-1">
                                                <span>الكمية:</span>
                                                <InputNumber
                                                    size="small"
                                                    min={1}
                                                    value={line.quantity}
                                                    onChange={(v) => handleLineChange(line.key, 'quantity', v || 1)}
                                                    style={{ width: 65 }}
                                                />
                                            </div>

                                            <div className="flex items-center gap-1">
                                                <span>نوع الخصم:</span>
                                                <Select
                                                    size="small"
                                                    value={line.discount_type}
                                                    onChange={(v) => handleLineChange(line.key, 'discount_type', v)}
                                                    style={{ width: 95 }}
                                                    options={[
                                                        { value: 'fixed', label: 'مبلغ ثابت' },
                                                        { value: 'percent', label: 'نسبة مئوية' },
                                                    ]}
                                                />
                                            </div>

                                            <div className="flex items-center gap-1">
                                                <span>قيمة الخصم:</span>
                                                <InputNumber
                                                    size="small"
                                                    min={0}
                                                    value={line.discount_value}
                                                    onChange={(v) => handleLineChange(line.key, 'discount_value', v || 0)}
                                                    style={{ width: 75 }}
                                                />
                                            </div>

                                            <div className="text-left font-bold text-slate-800">
                                                <span>الإجمالي: </span>
                                                <span className="text-sky-700 text-sm">{Number(line.total).toFixed(2)} ج.م</span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}

                            <div className="pt-2 flex justify-end">
                                <Button
                                    icon={<AppstoreOutlined />}
                                    onClick={() => setCatalogModalOpen(true)}
                                    className="text-sky-700 border-sky-300 hover:bg-sky-50 font-medium"
                                >
                                    إضافة أو تعديل الفحوصات والخيارات من الدليل
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Financial Totals Preview */}
                    {serviceLines.length > 0 && (
                        <div className="mt-3 p-3 bg-sky-50 border border-sky-100 rounded-lg flex flex-wrap justify-between items-center text-xs">
                            <div>
                                <span className="text-slate-500">إجمالي الفحوصات: </span>
                                <span className="font-semibold text-slate-700">{Number(grossTotal).toFixed(2)} ج.م</span>
                            </div>
                            <div>
                                <span className="text-slate-500">إجمالي الخصم: </span>
                                <span className="font-semibold text-emerald-600">-{Number(totalDiscounts).toFixed(2)} ج.م</span>
                            </div>
                            <div>
                                <span className="text-slate-700 font-bold text-sm">الصافي المطلوب: </span>
                                <span className="font-bold text-sky-800 text-base">{Number(netTotal).toFixed(2)} ج.م</span>
                            </div>
                        </div>
                    )}
                </Card>

                {/* 4. Payment / Financial Status */}
                {isEditing ? (
                    <Card
                        size="small"
                        className="border-amber-200 bg-amber-50/40"
                        title={
                            <Space>
                                <DollarOutlined className="text-amber-600" />
                                <span className="font-semibold text-slate-700">الموقف المالي للزيارة والفاتورة</span>
                            </Space>
                        }
                    >
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
                            <div className="p-2 bg-white rounded border border-amber-100">
                                <span className="text-xs text-slate-500 block">إجمالي الفاتورة المحدث</span>
                                <span className="font-bold text-sm text-slate-800">{Number(netTotal).toFixed(2)} ج.م</span>
                            </div>
                            <div className="p-2 bg-white rounded border border-amber-100">
                                <span className="text-xs text-slate-500 block">المدفوع سابقاً</span>
                                <span className="font-bold text-sm text-emerald-600">
                                    {Number(visitToEdit?.invoice?.paid_amount ?? 0).toFixed(2)} ج.م
                                </span>
                            </div>
                            <div className="p-2 bg-white rounded border border-amber-100">
                                <span className="text-xs text-slate-500 block">المتبقي المتوقع</span>
                                <span className={`font-bold text-sm ${Math.max(0, netTotal - (visitToEdit?.invoice?.paid_amount ?? 0)) > 0 ? 'text-amber-700' : 'text-slate-700'}`}>
                                    {Number(Math.max(0, netTotal - (visitToEdit?.invoice?.paid_amount ?? 0))).toFixed(2)} ج.م
                                </span>
                            </div>
                        </div>
                        <div className="mt-2 text-xs text-slate-500 flex items-center gap-1.5">
                            <InfoCircleOutlined className="text-amber-600" />
                            <span>
                                سيتم تحديث بنود الفاتورة وإعادة احتساب الرصيد المتبقي تلقائياً بعد حفظ التعديلات. لسداد دفعات نقدية جديدة، يرجى استخدام زر &quot;سداد&quot; من جدول الزيارات.
                            </span>
                        </div>
                    </Card>
                ) : (
                    <Card
                        size="small"
                        className="border-slate-200 bg-slate-50/50"
                        title={
                            <div className="flex justify-between items-center">
                                <Space>
                                    <DollarOutlined className="text-emerald-600" />
                                    <span className="font-semibold text-slate-700">تحصيل الدفعة النقدية</span>
                                </Space>
                                <Checkbox
                                    checked={collectPaymentNow}
                                    onChange={(e) => setCollectPaymentNow(e.target.checked)}
                                >
                                    تحصيل دفعة الآن عند التسجيل
                                </Checkbox>
                            </div>
                        }
                    >
                        {collectPaymentNow && (
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Form.Item
                                    name="paid_amount"
                                    label="المبلغ المدفوع الآن"
                                    rules={[{ required: true, message: 'يرجى إدخال المبلغ المدفوع' }]}
                                >
                                    <InputNumber
                                        className="w-full"
                                        min={0}
                                        max={netTotal}
                                        precision={2}
                                        prefix="ج.م"
                                    />
                                </Form.Item>

                                <Form.Item
                                    name="payment_method"
                                    label="طريقة الدفع"
                                    rules={[{ required: true, message: 'طريقة الدفع مطلوبة' }]}
                                >
                                    <Select options={paymentMethods} />
                                </Form.Item>

                                <Form.Item name="payment_notes" label="ملاحظات الدفعة">
                                    <Input placeholder="مثال: دفعة تأكيد الحجز..." />
                                </Form.Item>
                            </div>
                        )}
                    </Card>
                )}
            </Form>

            <ServiceCatalogModal
                open={catalogModalOpen}
                services={services}
                currentServiceLines={serviceLines}
                onConfirm={(updatedLines) => {
                    handleConfirmCatalogServices(updatedLines);
                    message.success(`تم تحديث قائمة الفحوصات (${updatedLines.length} خدمات)`);
                }}
                onClose={() => setCatalogModalOpen(false)}
            />
        </Modal>
    );
};
