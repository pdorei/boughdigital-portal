import { request } from '@/api/client';

export function getStorageSettings() {
    return request('/admin/api/storage-settings');
}

export function updateStorageSettings(data) {
    return request('/admin/api/storage-settings', {
        body: JSON.stringify(data),
        method: 'PATCH',
    });
}

export function testStorageConnection(driver) {
    return request('/admin/api/storage-settings/test', {
        body: JSON.stringify({ driver }),
        method: 'POST',
    });
}
