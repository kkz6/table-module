import type { AxiosResponse } from 'axios';

export function downloadExportResponse(response: AxiosResponse<Blob>, fallbackFilename: string): void {
    const filename = getFilename(response.headers['content-disposition']) ?? fallbackFilename;
    const url = window.URL.createObjectURL(response.data);
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = filename;
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();

    window.setTimeout(() => window.URL.revokeObjectURL(url), 1000);
}

export async function getExportErrorMessage(error: unknown): Promise<string | null> {
    const responseData = (error as { error?: { response?: { data?: unknown } } } | null)?.error?.response?.data;
    const data = responseData instanceof Blob ? await parseBlob(responseData) : responseData;

    if (!data || typeof data !== 'object') {
        return null;
    }

    const payload = data as { message?: unknown; errors?: Record<string, unknown> };

    if (typeof payload.message === 'string' && payload.message !== '') {
        return payload.message;
    }

    const firstError = Object.values(payload.errors ?? {})[0];

    if (Array.isArray(firstError) && typeof firstError[0] === 'string') {
        return firstError[0];
    }

    return typeof firstError === 'string' ? firstError : null;
}

function getFilename(contentDisposition: unknown): string | null {
    if (typeof contentDisposition !== 'string') {
        return null;
    }

    const encodedFilename = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];

    if (encodedFilename) {
        return decodeURIComponent(encodedFilename);
    }

    return contentDisposition.match(/filename="?([^";]+)"?/i)?.[1] ?? null;
}

async function parseBlob(blob: Blob): Promise<unknown> {
    try {
        return JSON.parse(await blob.text());
    } catch {
        return null;
    }
}
