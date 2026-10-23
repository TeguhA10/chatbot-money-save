import api from '../services/api';

/**
 * Direct file downloader for official statement PDF and Excel reports.
 * Eliminates modal popups and provides a clean 1-click download experience.
 *
 * @param {'pdf' | 'excel'} format
 * @param {Record<string, any>} params
 * @param {any} toastStore
 */
export async function downloadExportReport(format, params = {}, toastStore = null) {
  const isPdf = format === 'pdf';
  const endpoint = isPdf ? '/export/pdf' : '/export/excel';

  const cleanParams = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== '' && value !== 'ALL') {
      cleanParams[key] = value;
    }
  }

  const response = await api.get(endpoint, {
    params: cleanParams,
    responseType: 'blob',
  });

  const mimeType = isPdf
    ? 'application/pdf'
    : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
  const ext = isPdf ? 'pdf' : 'xlsx';
  const dateTag = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const prefix = isPdf ? 'Rekening_Koran_HematWoy' : 'Laporan_Keuangan_HematWoy';
  const periodLabel = cleanParams.month ? `_${cleanParams.month}` : `_${dateTag}`;
  const filename = `${prefix}${periodLabel}.${ext}`;

  const blob = new Blob([response.data], { type: mimeType });
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', filename);
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);

  if (toastStore) {
    toastStore.success(`Dokumen ${isPdf ? 'PDF Rekening Koran Resmi' : 'Excel Spreadsheet'} berhasil diunduh.`);
  }
}
