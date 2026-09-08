import { showToast } from './toast.js';

function extractMessageContent(content) {
    if (typeof content === 'string' || typeof content === 'number') {
        return String(content);
    }

    if (content && typeof content === 'object') {
        if ('content' in content) {
            return extractMessageContent(content.content);
        }

        if ('message' in content) {
            return extractMessageContent(content.message);
        }
    }

    return '';
}

function mapAntdType(type) {
    if (type === 'loading') {
        return 'info';
    }

    return type;
}

export function patchAntdMessage(messageApi) {
    if (!messageApi || messageApi.__wapappToastPatched) {
        return messageApi;
    }

    const types = ['success', 'error', 'info', 'warning', 'loading'];

    types.forEach((type) => {
        const original = messageApi[type]?.bind(messageApi);

        messageApi[type] = (content, duration) => {
            const text = extractMessageContent(content);

            if (text) {
                showToast({
                    type: mapAntdType(type),
                    message: text,
                    duration: typeof duration === 'number' ? duration : undefined,
                });
            }

            if (typeof original === 'function') {
                return original({ content: '', duration: 0 });
            }

            return undefined;
        };
    });

    messageApi.__wapappToastPatched = true;

    if (typeof messageApi.config === 'function') {
        messageApi.config({
            top: -9999,
            duration: 0,
            maxCount: 0,
        });
    }

    return messageApi;
}
