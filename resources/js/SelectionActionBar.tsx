import { Button } from '@shared/components/ui/button';
import { Download, LoaderCircle, X } from 'lucide-react';
import DynamicIcon from './DynamicIcon';
import TableActions from './TableActions';
import { getSelectionActions, type SelectionRow } from './selectionActions';
import type { ActionsDropdownProps } from './types/actions';

interface SelectionActionBarProps extends ActionsDropdownProps {
    rows: SelectionRow[];
    total: number;
    busy: boolean;
    onClear: () => void;
}

export default function SelectionActionBar({ actions, exports, selectedItems, rows, total, busy, onClear, ...handlers }: SelectionActionBarProps) {
    if (!selectedItems.length) return null;
    const all = selectedItems.includes('*');
    const available = getSelectionActions(actions, rows, selectedItems, total);

    return (
        <div className="motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-6 pointer-events-none fixed inset-x-4 bottom-5 z-40 flex justify-center pb-[env(safe-area-inset-bottom)] motion-safe:duration-200 motion-safe:ease-out">
            <section
                aria-label="Selected item actions"
                aria-busy={busy}
                className="border-border/60 bg-popover text-popover-foreground pointer-events-auto flex max-h-[40vh] max-w-full flex-wrap items-center gap-2 overflow-y-auto rounded-xl border p-2 shadow-lg sm:gap-3 sm:px-3"
            >
                <p role="status" className="px-2 text-sm font-medium tabular-nums">
                    {busy ? (
                        <span className="inline-flex items-center gap-2">
                            <LoaderCircle aria-hidden="true" className="size-4 animate-spin" />
                            Working…
                        </span>
                    ) : (
                        `${all ? `All ${total}` : selectedItems.length} selected`
                    )}
                </p>
                <span aria-hidden="true" className="bg-border h-5 w-px" />
                <div className="flex flex-wrap items-center gap-2">
                    {available.map(({ action, index, keys, item, partial }) => {
                        const rowAction = item?._actions?.[String(index)];
                        const downloadUrl = action.asDownload ? (typeof rowAction === 'string' ? rowAction : rowAction?.url) : null;
                        const label = (
                            <>
                                {action.icon && (
                                    <DynamicIcon icon={action.icon} resolver={handlers.iconResolver} context={action} className="size-4" />
                                )}
                                {action.label}
                                {partial && <span className="tabular-nums">({keys.length})</span>}
                            </>
                        );
                        return (
                            <TableActions key={index} actions={actions} keys={keys} item={item} {...handlers}>
                                {({ handle }) => (
                                    <Button
                                        variant={action.variant === 'destructive' ? 'destructiveGhost' : 'outline'}
                                        disabled={busy}
                                        asChild={Boolean(downloadUrl)}
                                        onClick={downloadUrl ? undefined : () => handle(action)}
                                        title={
                                            partial
                                                ? `Applies to ${keys.length} of ${selectedItems.length} selected items`
                                                : all
                                                  ? 'Applies to eligible items across all matching rows'
                                                  : undefined
                                        }
                                    >
                                        {downloadUrl ? (
                                            <a href={downloadUrl} download>
                                                {label}
                                            </a>
                                        ) : (
                                            label
                                        )}
                                    </Button>
                                )}
                            </TableActions>
                        );
                    })}
                    <TableActions actions={actions} keys={selectedItems} {...handlers}>
                        {({ asyncExport }) =>
                            exports.map((tableExport, index) =>
                                tableExport.asDownload ? (
                                    <Button key={index} variant="outline" asChild disabled={busy}>
                                        <a
                                            href={
                                                tableExport.limitToSelectedRows
                                                    ? `${tableExport.url}&keys=${selectedItems.join(',')}`
                                                    : tableExport.url
                                            }
                                            download
                                        >
                                            <Download className="size-4" />
                                            {tableExport.label}
                                        </a>
                                    </Button>
                                ) : (
                                    <Button key={index} variant="outline" disabled={busy} onClick={() => asyncExport(tableExport)}>
                                        <Download className="size-4" />
                                        {tableExport.label}
                                    </Button>
                                ),
                            )
                        }
                    </TableActions>
                    {!available.length && !exports.length && <span className="text-muted-foreground px-2 text-sm">No available actions</span>}
                </div>
                <Button variant="ghost" size="icon" aria-label="Clear selection" title="Clear selection" disabled={busy} onClick={onClear}>
                    <X className="size-4" />
                </Button>
            </section>
        </div>
    );
}
