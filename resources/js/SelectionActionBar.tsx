import { Button } from '@shared/components/ui/button';
import { FloatingSelectionBar } from '@shared/components/ui/floating-selection-bar';
import { Download } from 'lucide-react';
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
        <FloatingSelectionBar count={selectedItems.length} selectionLabel={all ? `All ${total} selected` : undefined} busy={busy} onClear={onClear}>
            {available.map(({ action, index, keys, item, partial }) => {
                const rowAction = item?._actions?.[String(index)];
                const downloadUrl = action.asDownload ? (typeof rowAction === 'string' ? rowAction : rowAction?.url) : null;
                const label = (
                    <>
                        {action.icon && <DynamicIcon icon={action.icon} resolver={handlers.iconResolver} context={action} className="size-4" />}
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
                                    href={tableExport.limitToSelectedRows ? `${tableExport.url}&keys=${selectedItems.join(',')}` : tableExport.url}
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
        </FloatingSelectionBar>
    );
}
