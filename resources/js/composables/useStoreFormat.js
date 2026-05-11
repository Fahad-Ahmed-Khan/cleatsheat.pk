export function formatPKR(value) {
    const n = Number(value);
    if (Number.isNaN(n)) {
        return '—';
    }
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(n);
}

export function useStoreFormat() {
    return { formatPKR };
}
