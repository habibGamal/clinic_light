import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { App as AntdApp, ConfigProvider } from 'antd';
import arEG from 'antd/locale/ar_EG';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Clinic Light';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ) as any,
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ConfigProvider
                direction="rtl"
                locale={arEG}
                theme={{
                    token: {
                        colorPrimary: '#0284c7',
                        borderRadius: 8,
                        fontFamily: "'Cairo', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
                    },
                }}
            >
                <AntdApp>
                    <App {...props} />
                </AntdApp>
            </ConfigProvider>,
        );
    },
    progress: {
        color: '#0284c7',
    },
});
