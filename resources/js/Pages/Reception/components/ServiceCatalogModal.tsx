import React, { useState, useEffect, useMemo } from 'react';
import {
    Modal,
    Input,
    Tabs,
    Tag,
    Button,
    Space,
    Typography,
    Empty,
    Badge,
    Checkbox,
    InputNumber,
    Radio,
    Divider,
    App,
} from 'antd';
import {
    AppstoreOutlined,
    SearchOutlined,
    CheckCircleFilled,
    CheckOutlined,
    CloseOutlined,
    ClearOutlined,
} from '@ant-design/icons';
import { ServiceItem, SelectedServiceLine, ServiceOptionGroup, ServiceOption } from '../types';

const { Text } = Typography;

interface ServiceCatalogModalProps {
    open: boolean;
    services: ServiceItem[];
    currentServiceLines: SelectedServiceLine[];
    onConfirm: (lines: SelectedServiceLine[]) => void;
    onClose: () => void;
}

interface ServiceDraftState {
    isSelected: boolean;
    quantity: number;
    selected_options: number[]; // option IDs
}

export const ServiceCatalogModal: React.FC<ServiceCatalogModalProps> = ({
    open,
    services,
    currentServiceLines,
    onConfirm,
    onClose,
}) => {
    const { message } = App.useApp();
    const [searchQuery, setSearchQuery] = useState('');
    const [activeCategory, setActiveCategory] = useState<string>('all');
    const [onlySelectedFilter, setOnlySelectedFilter] = useState(false);

    // Draft selections state mapped by service_id
    const [draftSelections, setDraftSelections] = useState<Record<number, ServiceDraftState>>({});

    // When modal opens, sync with currentServiceLines from the visit
    useEffect(() => {
        if (open) {
            const initial: Record<number, ServiceDraftState> = {};

            // Initialize all services as unselected
            services.forEach((s) => {
                initial[s.id] = {
                    isSelected: false,
                    quantity: 1,
                    selected_options: [],
                };
            });

            // Mark currently selected service lines
            currentServiceLines.forEach((line) => {
                initial[line.service_id] = {
                    isSelected: true,
                    quantity: line.quantity || 1,
                    selected_options: [...line.selected_options],
                };
            });

            setDraftSelections(initial);
            setSearchQuery('');
            setOnlySelectedFilter(false);
        }
    }, [open, services, currentServiceLines]);

    // Extract unique categories from services
    const categories = useMemo(() => {
        const set = new Set<string>();
        services.forEach((s) => {
            if (s.category_name) {
                set.add(s.category_name);
            } else {
                set.add('أخرى');
            }
        });
        return Array.from(set);
    }, [services]);

    // Toggle service selection
    const handleToggleService = (serviceId: number) => {
        const existingLine = currentServiceLines.find((l) => l.service_id === serviceId);
        if (existingLine?.has_report && draftSelections[serviceId]?.isSelected) {
            message.warning('لا يمكن إلغاء اختيار هذا الفحص لوجود تقرير طبي مسجل له.');
            return;
        }

        setDraftSelections((prev) => {
            const current = prev[serviceId] || { isSelected: false, quantity: 1, selected_options: [] };
            const nextSelected = !current.isSelected;

            return {
                ...prev,
                [serviceId]: {
                    ...current,
                    isSelected: nextSelected,
                },
            };
        });
    };

    // Toggle option inside a service
    const handleToggleOption = (
        serviceId: number,
        group: ServiceOptionGroup,
        optionId: number
    ) => {
        setDraftSelections((prev) => {
            const current = prev[serviceId] || { isSelected: false, quantity: 1, selected_options: [] };
            const isSingle = group.selection_type === 'single';

            let nextOptions = [...current.selected_options];

            if (isSingle) {
                const groupOptionIds = group.options.map((o) => o.id);
                // If already selected, allow toggling off unless required
                if (nextOptions.includes(optionId)) {
                    if (!group.is_required) {
                        nextOptions = nextOptions.filter((id) => id !== optionId);
                    }
                } else {
                    // Replace other options in this single-choice group
                    nextOptions = nextOptions.filter((id) => !groupOptionIds.includes(id));
                    nextOptions.push(optionId);
                }
            } else {
                // Multiple choice group
                if (nextOptions.includes(optionId)) {
                    nextOptions = nextOptions.filter((id) => id !== optionId);
                } else {
                    nextOptions.push(optionId);
                }
            }

            return {
                ...prev,
                [serviceId]: {
                    ...current,
                    // If user selects an option, auto-select the service
                    isSelected: true,
                    selected_options: nextOptions,
                },
            };
        });
    };

    // Update quantity for a service
    const handleQuantityChange = (serviceId: number, quantity: number) => {
        setDraftSelections((prev) => {
            const current = prev[serviceId] || { isSelected: false, quantity: 1, selected_options: [] };
            return {
                ...prev,
                [serviceId]: {
                    ...current,
                    quantity: Math.max(1, quantity),
                },
            };
        });
    };

    // Filter services based on category, search, and "only selected"
    const filteredServices = useMemo(() => {
        return services.filter((s) => {
            const isSelected = draftSelections[s.id]?.isSelected || false;

            if (onlySelectedFilter && !isSelected) {
                return false;
            }

            const cat = s.category_name || 'أخرى';
            const matchesCat = activeCategory === 'all' || cat === activeCategory;

            const q = searchQuery.trim().toLowerCase();
            const matchesSearch =
                !q ||
                s.name.toLowerCase().includes(q) ||
                (s.code && s.code.toLowerCase().includes(q)) ||
                (s.category_name && s.category_name.toLowerCase().includes(q));

            return matchesCat && matchesSearch;
        });
    }, [services, activeCategory, searchQuery, onlySelectedFilter, draftSelections]);

    // Calculate total summary of selected items
    const selectedStats = useMemo(() => {
        let count = 0;
        let totalPrice = 0;

        services.forEach((s) => {
            const draft = draftSelections[s.id];
            if (draft && draft.isSelected) {
                count += 1;
                let unitPrice = s.base_price;
                s.option_groups.forEach((g) => {
                    g.options.forEach((o) => {
                        if (draft.selected_options.includes(o.id)) {
                            unitPrice += o.additional_price;
                        }
                    });
                });
                totalPrice += unitPrice * draft.quantity;
            }
        });

        return { count, totalPrice };
    }, [services, draftSelections]);

    // Handle Confirm and Apply to Visit
    const handleSaveAndApply = () => {
        const newLines: SelectedServiceLine[] = [];

        services.forEach((s) => {
            const draft = draftSelections[s.id];
            if (draft && draft.isSelected) {
                // Calculate unit price based on selected options
                let optionsAddPrice = 0;
                s.option_groups.forEach((g) => {
                    g.options.forEach((o) => {
                        if (draft.selected_options.includes(o.id)) {
                            optionsAddPrice += o.additional_price;
                        }
                    });
                });

                const unitPrice = s.base_price + optionsAddPrice;
                const qty = draft.quantity || 1;

                // Check if this service was already present in current lines to preserve discounts
                const existing = currentServiceLines.find((l) => l.service_id === s.id);
                const discountType = existing?.discount_type || 'fixed';
                const discountValue = existing?.discount_value || 0;

                const subtotal = unitPrice * qty;
                const discountAmount =
                    discountType === 'percent'
                        ? (subtotal * discountValue) / 100
                        : discountValue;
                const total = Math.max(0, subtotal - discountAmount);

                newLines.push({
                    key: existing?.key || `${Date.now()}_${Math.random()}_${s.id}`,
                    id: existing?.id,
                    service_id: s.id,
                    service_name: s.name,
                    quantity: qty,
                    base_price: s.base_price,
                    unit_price: unitPrice,
                    selected_options: [...draft.selected_options],
                    discount_type: discountType,
                    discount_value: discountValue,
                    discount_amount: discountAmount,
                    total: total,
                    has_report: existing?.has_report,
                    available_groups: s.option_groups || [],
                });
            }
        });

        onConfirm(newLines);
        onClose();
    };

    const tabItems = [
        {
            key: 'all',
            label: (
                <Space size={6}>
                    <span>الكل</span>
                    <Badge
                        count={services.length}
                        overflowCount={999}
                        style={{ backgroundColor: activeCategory === 'all' ? '#0284c7' : '#94a3b8' }}
                    />
                </Space>
            ),
        },
        ...categories.map((cat) => {
            const count = services.filter((s) => (s.category_name || 'أخرى') === cat).length;
            return {
                key: cat,
                label: (
                    <Space size={6}>
                        <span>{cat}</span>
                        <Badge
                            count={count}
                            overflowCount={999}
                            style={{
                                backgroundColor: activeCategory === cat ? '#0284c7' : '#94a3b8',
                            }}
                        />
                    </Space>
                ),
            };
        }),
    ];

    return (
        <Modal
            open={open}
            onCancel={onClose}
            width={'100%'}
            centered
            title={
                <div className="flex items-center gap-2 pb-1 border-b border-slate-100">
                    <div className="w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center text-sky-600">
                        <AppstoreOutlined className="text-lg" />
                    </div>
                    <div>
                        <h3 className="text-base font-bold text-slate-800 m-0 leading-tight">
                            دليل الفحوصات والخدمات والخيارات
                        </h3>
                        <p className="text-xs text-slate-500 m-0 mt-0.5">
                            اختر الخدمات المطلوبة وحدد خيارات كل خدمة مباشرة، وسيتم تطبيقها على الزيارة فوراً
                        </p>
                    </div>
                </div>
            }
            footer={
                <div className="flex flex-col sm:flex-row justify-between items-center gap-3 pt-2 border-t border-slate-100">
                    <div className="flex items-center gap-3 text-xs">
                        <div className="bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                            <span className="text-slate-500 ml-1">الخدمات المحددة:</span>
                            <strong className="text-sky-700 text-sm">{selectedStats.count}</strong>
                        </div>
                        <div className="bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                            <span className="text-slate-500 ml-1">إجمالي التكلفة المقدرة:</span>
                            <strong className="text-slate-800 font-mono text-sm">
                                {selectedStats.totalPrice.toFixed(2)} ج.م
                            </strong>
                        </div>
                    </div>
                    <Space>
                        <Button onClick={onClose}>إلغاء</Button>
                        <Button
                            type="primary"
                            icon={<CheckOutlined />}
                            onClick={handleSaveAndApply}
                            className="bg-sky-600 hover:bg-sky-500 font-bold px-5"
                        >
                            تأكيد واعتماد الخدمات ({selectedStats.count})
                        </Button>
                    </Space>
                </div>
            }
        >
            <div className="py-2 space-y-3">
                {/* Search & Filter Toolbar */}
                <div className="flex flex-col sm:flex-row justify-between items-center gap-2 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                    <Input
                        placeholder="ابحث باسم الفحص، الخدمة، الكود أو القسم..."
                        prefix={<SearchOutlined className="text-slate-400" />}
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        allowClear
                        className="w-full sm:w-80"
                    />

                    <div className="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-end">
                        <Button
                            size="small"
                            type={onlySelectedFilter ? 'primary' : 'default'}
                            onClick={() => setOnlySelectedFilter((v) => !v)}
                            className={onlySelectedFilter ? 'bg-sky-600' : ''}
                        >
                            {onlySelectedFilter ? 'عرض جميع الخدمات' : `المختارة فقط (${selectedStats.count})`}
                        </Button>

                        {selectedStats.count > 0 && (
                            <Button
                                size="small"
                                danger
                                type="text"
                                icon={<ClearOutlined />}
                                onClick={() => {
                                    const cleared: Record<number, ServiceDraftState> = {};
                                    services.forEach((s) => {
                                        cleared[s.id] = { isSelected: false, quantity: 1, selected_options: [] };
                                    });
                                    setDraftSelections(cleared);
                                }}
                            >
                                مسح التحديد
                            </Button>
                        )}
                    </div>
                </div>

                {/* Category Navigation Tabs */}
                {!onlySelectedFilter && (
                    <Tabs
                        activeKey={activeCategory}
                        onChange={(k) => setActiveCategory(k)}
                        items={tabItems}
                        size="small"
                    />
                )}

                {/* Services Cards List / Grid */}
                {filteredServices.length === 0 ? (
                    <div className="py-12 bg-white rounded-lg border border-dashed border-slate-200 text-center">
                        <Empty
                            description={
                                <span className="text-slate-500 font-semibold">
                                    {onlySelectedFilter
                                        ? 'لم تقم بتحديد أي خدمات بعد'
                                        : 'لا توجد خدمات مطابقة لبحثك في هذا القسم'}
                                </span>
                            }
                        />
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[680px] overflow-y-auto pr-1 pl-1">
                        {filteredServices.map((service) => {
                            const draft = draftSelections[service.id] || {
                                isSelected: false,
                                quantity: 1,
                                selected_options: [],
                            };
                            const isSelected = draft.isSelected;

                            // Calculate additions from options
                            let optionsAddition = 0;
                            service.option_groups.forEach((g) => {
                                g.options.forEach((o) => {
                                    if (draft.selected_options.includes(o.id)) {
                                        optionsAddition += o.additional_price;
                                    }
                                });
                            });

                            const unitPrice = service.base_price + optionsAddition;
                            const totalPrice = unitPrice * (draft.quantity || 1);

                            return (
                                <div
                                    key={service.id}
                                    className={`rounded-xl border p-3.5 transition-all duration-200 flex flex-col justify-between ${isSelected
                                        ? 'border-sky-500 bg-sky-50/40 shadow-sm ring-1 ring-sky-400'
                                        : 'border-slate-200 bg-white hover:border-slate-300'
                                        }`}
                                >
                                    <div>
                                        {/* Card Header: Checkbox + Name + Category Tag */}
                                        <div className="flex items-start justify-between gap-2 mb-1.5">
                                            <div className="flex items-start gap-2.5">
                                                <Checkbox
                                                    checked={isSelected}
                                                    onChange={() => handleToggleService(service.id)}
                                                    className="mt-0.5 text-base"
                                                />
                                                <div>
                                                    <h4
                                                        className={`text-sm font-bold m-0 leading-tight cursor-pointer ${isSelected ? 'text-sky-900' : 'text-slate-800'
                                                            }`}
                                                        onClick={() => handleToggleService(service.id)}
                                                    >
                                                        {service.name}
                                                    </h4>
                                                    {service.code && (
                                                        <span className="text-[10px] font-mono text-slate-400 block mt-0.5">
                                                            كود: {service.code}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            <Tag
                                                color={
                                                    service.category_name?.includes('3D')
                                                        ? 'purple'
                                                        : service.category_name?.includes('2D')
                                                            ? 'blue'
                                                            : 'cyan'
                                                }
                                                className="m-0 text-[10px] font-semibold shrink-0"
                                            >
                                                {service.category_name || 'عام'}
                                            </Tag>
                                        </div>

                                        {/* Option Groups Selector (Directly inside modal) */}
                                        {service.option_groups && service.option_groups.length > 0 && (
                                            <div className="mt-2.5 pt-2 border-t border-slate-100 space-y-2 bg-slate-50/70 p-2 rounded-lg border border-slate-200/60">
                                                <div className="text-[11px] font-bold text-slate-600 mb-1">
                                                    خيارات الخدمة الإضافية:
                                                </div>
                                                {service.option_groups.map((group) => (
                                                    <div key={group.id} className="space-y-1">
                                                        <div className="text-[11px] font-semibold text-slate-700 flex items-center gap-1">
                                                            <span>{group.name}:</span>
                                                            <span className="text-[10px] text-slate-400 font-normal">
                                                                ({group.selection_type === 'single' ? 'اختيار واحد' : 'متعدد'})
                                                            </span>
                                                        </div>

                                                        <div className="flex flex-wrap gap-1.5">
                                                            {group.options.map((opt) => {
                                                                const isOptSelected = draft.selected_options.includes(opt.id);

                                                                return (
                                                                    <Tag.CheckableTag
                                                                        key={opt.id}
                                                                        checked={isOptSelected}
                                                                        onChange={() =>
                                                                            handleToggleOption(service.id, group, opt.id)
                                                                        }
                                                                        className={`text-xs px-2 py-0.5 rounded cursor-pointer border transition-colors ${isOptSelected
                                                                            ? 'bg-sky-600 text-white border-sky-600 font-bold'
                                                                            : 'bg-white text-slate-700 border-slate-300 hover:border-sky-400'
                                                                            }`}
                                                                    >
                                                                        {opt.name}
                                                                        {opt.additional_price > 0 && (
                                                                            <span
                                                                                className={`mr-1 font-mono text-[10px] ${isOptSelected ? 'text-white' : 'text-sky-700 font-bold'
                                                                                    }`}
                                                                            >
                                                                                (+{Number(opt.additional_price).toFixed(0)} ج.م)
                                                                            </span>
                                                                        )}
                                                                    </Tag.CheckableTag>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* Card Footer: Quantity + Pricing + Toggle Button */}
                                    <div className="pt-2.5 border-t border-slate-100 flex items-center justify-between mt-3 text-xs">
                                        <div className="flex items-center gap-2">
                                            <span className="text-slate-500">الكمية:</span>
                                            <InputNumber
                                                size="small"
                                                min={1}
                                                max={50}
                                                value={draft.quantity}
                                                disabled={!isSelected}
                                                onChange={(val) => handleQuantityChange(service.id, val || 1)}
                                                className="w-14"
                                            />
                                        </div>

                                        <div className="text-left">
                                            <span className="text-[10px] text-slate-400 block -mb-0.5">
                                                {optionsAddition > 0 ? (
                                                    <span>
                                                        أساسي: {service.base_price.toFixed(0)} + إضافات: {optionsAddition.toFixed(0)}
                                                    </span>
                                                ) : (
                                                    'السعر الإجمالي'
                                                )}
                                            </span>
                                            <span className="text-sm font-black text-slate-800 font-mono">
                                                {totalPrice.toFixed(2)}{' '}
                                                <span className="text-[11px] font-normal font-sans text-slate-500">
                                                    ج.م
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </Modal>
    );
};
