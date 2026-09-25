import { Head } from '@inertiajs/react';
import { Button, Card, Flex, theme, Typography } from 'antd';
import { LoginOutlined, MedicineBoxOutlined } from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;

export default function Welcome() {
    const { token } = theme.useToken();

    return (
        <>
            <Head title="Welcome" />
            <Flex
                align="center"
                justify="center"
                style={{
                    minHeight: '100vh',
                    background: `linear-gradient(135deg, ${token.colorBgContainer} 0%, ${token.colorPrimaryBg} 100%)`,
                    padding: token.paddingLG,
                }}
            >
                <Card
                    style={{
                        maxWidth: 520,
                        width: '100%',
                        borderRadius: token.borderRadiusLG,
                        boxShadow: '0 20px 40px -15px rgba(0, 0, 0, 0.08)',
                        textAlign: 'center',
                    }}
                >
                    <Flex vertical align="center" gap="middle">
                        <div
                            style={{
                                width: 64,
                                height: 64,
                                borderRadius: '50%',
                                background: token.colorPrimaryBgHover,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                color: token.colorPrimary,
                                fontSize: 32,
                            }}
                        >
                            <MedicineBoxOutlined />
                        </div>

                        <Title level={2} style={{ margin: 0, fontWeight: 700 }}>
                            Welcome in our system
                        </Title>

                        <Paragraph type="secondary" style={{ fontSize: 16, margin: 0 }}>
                            Clinic Light Management System
                        </Paragraph>

                        <div style={{ marginTop: token.marginMD, width: '100%' }}>
                            <Button
                                type="primary"
                                size="large"
                                icon={<LoginOutlined />}
                                href="/admin"
                                block
                            >
                                Enter System
                            </Button>
                        </div>

                        <Text type="secondary" style={{ fontSize: 12 }}>
                            Manage patients, visits, shifts, and medical services seamlessly.
                        </Text>
                    </Flex>
                </Card>
            </Flex>
        </>
    );
}
