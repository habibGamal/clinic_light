export interface Patient {
    id?: number;
    full_name: string;
    phone: string;
    birth_date?: string | null;
    age?: number | null;
    gender?: 'male' | 'female' | string | null;
    gender_label?: string | null;
    address?: string | null;
    notes?: string | null;
    visits_count?: number;
    visits?: PatientVisitSummary[];
}

export interface ReferringDoctor {
    id: number;
    name: string;
    phone?: string;
    specialization?: string;
}

export interface ServiceOption {
    id: number;
    name: string;
    additional_price: number;
}

export interface ServiceOptionGroup {
    id: number;
    name: string;
    selection_type: 'single' | 'multiple';
    is_required: boolean;
    options: ServiceOption[];
}

export interface ServiceItem {
    id: number;
    name: string;
    code?: string;
    base_price: number;
    category_name?: string;
    option_groups: ServiceOptionGroup[];
}

export interface SelectedServiceLine {
    key: string;
    id?: number;
    service_id: number;
    service_name: string;
    quantity: number;
    base_price: number;
    unit_price: number;
    selected_options: number[]; // option IDs
    discount_type: 'fixed' | 'percent';
    discount_value: number;
    discount_amount: number;
    total: number;
    has_report?: boolean;
    available_groups: ServiceOptionGroup[];
}

export interface InvoiceItemData {
    id: number;
    description: string;
    type: 'service' | 'option' | 'refund' | string;
    unit_price: number;
    quantity: number;
    discount_amount: number;
    total: number;
}

export interface Invoice {
    id: number;
    visit_id: number;
    invoice_number: string;
    status: string;
    status_label?: string;
    subtotal: number;
    discount_total: number;
    total_amount: number;
    paid_amount: number;
    remaining_amount: number;
    items?: InvoiceItemData[];
}

export interface PaymentRecord {
    id: number;
    shift_id?: number | null;
    type: string;
    amount: number;
    payment_method: string;
    payment_method_label: string;
    created_at: string;
    notes?: string | null;
}

export interface ReportRecord {
    id: number;
    title: string;
    report_text: string;
    doctor_name?: string;
    service_name?: string;
    created_at: string;
}

export interface PatientVisit {
    id: number;
    shift_id?: number;
    visit_date: string;
    raw_visit_date?: string;
    status: 'waiting' | 'completed' | 'cancelled';
    status_label: string;
    status_color?: string;
    notes?: string | null;
    visit_services_count: number;
    patient: Patient | null;
    referring_doctor: { id: number; name: string } | null;
    invoice: Invoice | null;
    payments: PaymentRecord[];
    visit_services: {
        id: number;
        service_id: number;
        service_name: string;
        base_price?: number;
        quantity: number;
        unit_price: number;
        discount_type?: string;
        discount_value: number;
        total: number;
        status?: string;
        status_label?: string;
        has_report?: boolean;
        reports_count?: number;
        selected_options: { id: number; service_option_id?: number; name: string; additional_price: number }[];
    }[];
    reports: ReportRecord[];
}

export interface PatientVisitSummary {
    id: number;
    visit_date: string;
    status: string;
    status_label: string;
    referring_doctor_name: string;
    invoice?: Invoice | null;
    total_amount?: number;
    paid_amount?: number;
    remaining_amount?: number;
    services: string[];
}

export interface ExpenseRecord {
    id: number;
    category_id: number;
    category_name: string;
    amount: number;
    creator_name: string;
    notes?: string | null;
    created_at: string;
    date: string;
    is_editable?: boolean;
}

export interface ExpenseCategory {
    id: number;
    name: string;
}

export interface ActiveShift {
    id: number;
    opened_at: string;
    opening_balance?: number;
}

export interface ReceptionProps {
    visits: {
        data: PatientVisit[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    todayExpenses: ExpenseRecord[];
    todayExpensesTotal: number;
    services: ServiceItem[];
    referringDoctors: ReferringDoctor[];
    expenseCategories: ExpenseCategory[];
    activeShift: ActiveShift | null;
    filters: {
        search: string;
        status: string;
        date_filter: string;
        date_from: string;
        date_to: string;
    };
    paymentMethods: { value: string; label: string }[];
    genderOptions: { value: string; label: string }[];
}
