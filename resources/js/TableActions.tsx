import { useLang } from '@shared/hooks/use-lang';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import ConfirmDialog, { ConfirmActionDialog } from './ConfirmDialog';
import ExportModal from './ExportModal';
import FailedActionDialog from './FailedActionDialog';
import type { ActionsProps, ActionSuccessResult, CustomActionResult, ExportModalPayload, ExportSuccessResult, TableAction, TableExport } from './types/actions';
import { visitUrl } from './urlHelpers';

interface AsyncExportContext {
    dialogTitle?: string;
    dialogMessage?: string;
    targetUrl?: string;
}

interface ConfirmContext {
    action: TableAction;
}

export default function Actions({
    actions,
    keys,
    performAction,
    performAsyncExport = null,
    item,
    iconResolver,
    onSuccess = null,
    onError = null,
    onHandle = null,
    visibleColumns,
    children,
}: ActionsProps) {
    const { t } = useLang();
    const [asyncExportDialogIsOpen, setAsyncExportDialogIsOpen] = useState<boolean>(false);
    const [asyncExportContext, setAsyncExportContext] = useState<AsyncExportContext | null>(null);

    const [confirmDialogIsOpen, setConfirmDialogIsOpen] = useState<boolean>(false);
    const [confirmContext, setConfirmContext] = useState<ConfirmContext | null>(null);

    const [exportModalContext, setExportModalContext] = useState<TableExport | null>(null);

    const handle = (action: TableAction): void => {
        // Check if confirmation is actually needed (has confirmation content)
        const needsConfirmation = action.confirmationRequired && (action.confirmationTitle || action.confirmationMessage);

        if (!needsConfirmation) {
            return perform(action);
        }

        setConfirmDialogIsOpen(true);
        setConfirmContext({ action });
    };

    function asyncExport(tableExport: TableExport): void {
        if (tableExport.hasExporter) {
            // Defer opening until after the dropdown has fully closed. Opening a
            // controlled Radix dialog within the same interaction that closes the
            // dropdown makes the dialog's outside-interaction guard swallow the
            // open. A macrotask reliably lands after the menu's close handlers.
            setTimeout(() => setExportModalContext(tableExport), 0);
            return;
        }

        if (!performAsyncExport) {
            return;
        }

        performAsyncExport(tableExport)
            .then(({ response }: ExportSuccessResult) => {
                if (response.data.targetUrl) {
                    return;
                }

                setAsyncExportDialogIsOpen(!!(response.data.dialogTitle || response.data.dialogMessage));
                setAsyncExportContext(response.data);
            })
            .catch(() => {
                setActionFailed(true);
            });
    }

    function submitExport(tableExport: TableExport, payload: ExportModalPayload): void {
        if (!performAsyncExport) {
            return;
        }

        performAsyncExport(tableExport, payload)
            .then(({ response }: ExportSuccessResult) => {
                if (response.data.started) {
                    toast.info(response.data.toastTitle, { description: response.data.toastBody });
                }
            })
            .catch((errorData: unknown) => {
                const message = (errorData as { error?: { response?: { data?: { message?: string } } } } | null)?.error?.response?.data?.message;

                if (message) {
                    toast.error(message);
                } else {
                    setActionFailed(true);
                }
            });
    }

    const [actionFailed, setActionFailed] = useState<boolean>(false);

    const perform = (action: TableAction): void => {
        if (action.isLink) {
            const actionKey = actions.findIndex((a) => a === action);
            const actionData = item?._actions?.[actionKey];

            // Extract the URL and options from the action data
            let url: string | null = null;
            let openInNewTab = false;

            if (typeof actionData === 'string') {
                url = actionData;
            } else if (actionData && typeof actionData === 'object') {
                url = actionData.url || null;
                openInNewTab = actionData.openInNewTab || false;
            }

            // Handle navigation based on openInNewTab flag
            if (url) {
                if (openInNewTab) {
                    window.open(url, '_blank', 'noopener,noreferrer');
                } else {
                    visitUrl(url);
                }
            }
            return;
        }

        // Close any open dialogs
        setConfirmDialogIsOpen(false);
        setAsyncExportDialogIsOpen(false);

        const performPromise = performAction(action, keys);

        if (action.isCustom) {
            performPromise.then((result) => {
                if ('onFinish' in result) {
                    const customResult = result as CustomActionResult;
                    onHandle?.(action, customResult.keys, customResult.onFinish);
                }
            });
        } else {
            performPromise
                .then((result) => {
                    if ('response' in result) {
                        const successResult = result as ActionSuccessResult;
                        onSuccess?.(action, successResult.keys);
                    }
                })
                .catch((errorData) => {
                    // Make sure we have the expected error structure
                    if (errorData && typeof errorData === 'object' && 'keys' in errorData) {
                        const { keys, error } = errorData as { keys: (string | number)[]; error: any };
                        onError ? onError(action, keys, error) : setActionFailed(true);
                    } else {
                        // If error structure is unexpected, still handle it
                        console.error('Unexpected error structure in table action:', errorData);
                        onError ? onError(action, keys, errorData) : setActionFailed(true);
                    }
                });
        }
    };

    return (
        <>
            {children({ handle, asyncExport })}

            <ConfirmActionDialog
                show={confirmDialogIsOpen}
                action={confirmContext?.action as any}
                iconResolver={iconResolver}
                onCancel={() => setConfirmDialogIsOpen(false)}
                onConfirm={() => confirmContext && perform(confirmContext.action)}
            />

            <FailedActionDialog show={actionFailed} onConfirm={() => setActionFailed(false)} />

            <ConfirmDialog
                show={asyncExportDialogIsOpen && !!(asyncExportContext?.dialogTitle || asyncExportContext?.dialogMessage)}
                title={asyncExportContext?.dialogTitle ?? ''}
                message={asyncExportContext?.dialogMessage ?? ''}
                icon={'MoreHorizontal' as any}
                iconResolver={() => MoreHorizontal}
                confirmButton={t('table::table.export_processing_dialog_button')}
                onConfirm={(() => setAsyncExportDialogIsOpen(false)) as any}
                onCancel={() => setAsyncExportDialogIsOpen(false)}
            />

            <ExportModal
                show={!!exportModalContext}
                tableExport={exportModalContext}
                visibleColumns={visibleColumns}
                onClose={() => setExportModalContext(null)}
                onSubmit={(payload) => submitExport(exportModalContext!, payload)}
            />
        </>
    );
}
