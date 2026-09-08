import '@vitejs/plugin-react/preamble';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { ConfigProvider, message } from 'antd';
import { ReactFlowProvider } from 'reactflow';
import axios from 'axios';
import ChatBotFlowReactFlow from './legacy/ChatBotFlowReactFlow.jsx';
import { applyAxiosDefaults } from './legacy/api.js';
import { patchAntdMessage } from '../antd-toast-bridge.js';
import './legacy/styles/ChatBotFlow.css';
import './legacy/styles/ChatBotFlowReactFlow.css';
import './legacy/styles/builder-drawer.css';
import './legacy/styles/template-message-preview.css';

applyAxiosDefaults(axios);
patchAntdMessage(message);

const rootEl = document.getElementById('chatbot-react-root');

if (rootEl) {
    createRoot(rootEl).render(
        <React.StrictMode>
            <ConfigProvider
                theme={{
                    token: {
                        zIndexPopupBase: 1100,
                        colorPrimary: '#22c55e',
                        borderRadius: 8,
                        colorBgElevated: '#ffffff',
                        fontFamily: 'inherit',
                    },
                    components: {
                        Drawer: {
                            paddingLG: 20,
                            footerPaddingBlock: 12,
                            footerPaddingInline: 20,
                        },
                        Button: {
                            colorPrimary: '#22c55e',
                            colorPrimaryHover: '#16a34a',
                            colorPrimaryActive: '#15803d',
                        },
                    },
                }}
            >
                <ReactFlowProvider>
                    <ChatBotFlowReactFlow />
                </ReactFlowProvider>
            </ConfigProvider>
        </React.StrictMode>,
    );
}
