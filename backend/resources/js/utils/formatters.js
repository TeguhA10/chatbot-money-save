import { getActivePinia } from 'pinia';
import { useVaultStore } from '../stores/vault';

export function formatRawRupiah(value) {
    if (value === null || value === undefined || isNaN(value)) return 'Rp 0';
    const num = Math.round(Number(value));
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(num);
}

export function formatRupiah(value, forceUnmask = false) {
    if (!forceUnmask && getActivePinia()) {
        try {
            const vaultStore = useVaultStore();
            if (vaultStore.isMasked) {
                return 'Rp ••••••••';
            }
        } catch {
            // fallback
        }
    }
    return formatRawRupiah(value);
}

export function formatMaskedRupiah(value, isMasked = false) {
    if (isMasked) return 'Rp ••••••••';
    return formatRawRupiah(value);
}

export function formatDate(dateStr, includeTime = false) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    const options = {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    };
    if (includeTime) {
        options.hour = '2-digit';
        options.minute = '2-digit';
    }
    return new Intl.DateTimeFormat('id-ID', options).format(date);
}

export function formatPercent(num) {
    if (num === null || num === undefined || isNaN(num)) return '0%';
    return `${Math.round(num)}%`;
}
