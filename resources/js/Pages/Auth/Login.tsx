import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Form, Input, Button, Checkbox, Card, Typography, Alert } from 'antd';
import { UserOutlined, LockOutlined, MedicineBoxOutlined } from '@ant-design/icons';

const { Title, Text } = Typography;

interface LoginProps {
    status?: string;
    canResetPassword?: boolean;
}

export default function Login({ status }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const onFinish = () => {
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-sky-50 via-slate-50 to-indigo-50 flex items-center justify-center p-4">
            <Head title="تسجيل الدخول - نظام العيادة" />
            <div className="w-full max-w-md">
                <div className="text-center mb-6">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-sky-600 text-white shadow-lg mb-3">
                        <MedicineBoxOutlined style={{ fontSize: 32 }} />
                    </div>
                    <Title level={2} style={{ marginBottom: 4 }}>عيادتي (Clinic Light)</Title>
                    <Text type="secondary">تسجيل الدخول إلى قسم الاستقبال</Text>
                </div>

                <Card className="shadow-xl rounded-2xl border-slate-100">
                    {status && (
                        <Alert
                            message={status}
                            type="success"
                            showIcon
                            className="mb-4"
                        />
                    )}

                    {Object.keys(errors).length > 0 && (
                        <Alert
                            message={Object.values(errors)[0]}
                            type="error"
                            showIcon
                            className="mb-4"
                        />
                    )}

                    <Form
                        layout="vertical"
                        onFinish={onFinish}
                        initialValues={{ remember: false }}
                        size="large"
                    >
                        <Form.Item
                            label="البريد الإلكتروني"
                            name="email"
                            rules={[{ required: true, message: 'يرجى إدخال البريد الإلكتروني' }]}
                        >
                            <Input
                                prefix={<UserOutlined className="text-slate-400" />}
                                placeholder="name@example.com"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                            />
                        </Form.Item>

                        <Form.Item
                            label="كلمة المرور"
                            name="password"
                            rules={[{ required: true, message: 'يرجى إدخال كلمة المرور' }]}
                        >
                            <Input.Password
                                prefix={<LockOutlined className="text-slate-400" />}
                                placeholder="••••••••"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                        </Form.Item>

                        <div className="flex items-center justify-between mb-4">
                            <Checkbox
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            >
                                تذكرني
                            </Checkbox>
                        </div>

                        <Button
                            type="primary"
                            htmlType="submit"
                            block
                            loading={processing}
                            className="bg-sky-600 hover:bg-sky-500 font-bold h-11"
                        >
                            دخول الاستقبال
                        </Button>
                    </Form>
                </Card>
            </div>
        </div>
    );
}
