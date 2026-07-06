import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@shared/components/ui/dialog';
import { DraggableList, DraggableWrapper } from '@shared/components/ui/draggable-wrapper';
import { Input } from '@shared/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@shared/components/ui/select';
import { useLang } from '@shared/hooks/use-lang';
import { cn } from '@shared/lib/utils';
import { Check, Download, FileSpreadsheet, FileText } from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import type { ExportColumnDef, ExportModalPayload, ExportOptionField, TableExport } from './types/actions';

interface ExportModalProps {
    show: boolean;
    tableExport: TableExport | null;
    visibleColumns?: Record<string, boolean>;
    onClose: () => void;
    onSubmit: (payload: ExportModalPayload) => void;
}

function FormatIcon({ format }: { format: string }): React.ReactElement {
    if (format === 'xlsx') {
        return <FileSpreadsheet className="size-4 shrink-0" />;
    }

    return <FileText className="size-4 shrink-0" />;
}

export default function ExportModal({ show, tableExport, visibleColumns, onClose, onSubmit }: ExportModalProps): React.ReactElement {
    const { t } = useLang();

    const [columnMap, setColumnMap] = useState<Record<string, { isEnabled: boolean; label: string }>>({});
    const [columnOrder, setColumnOrder] = useState<string[]>([]);
    const [formats, setFormats] = useState<string[]>([]);
    const [options, setOptions] = useState<Record<string, unknown>>({});

    const visibleColumnsRef = useRef(visibleColumns);
    visibleColumnsRef.current = visibleColumns;

    useEffect(() => {
        if (!tableExport) {
            return;
        }

        const initialColumnMap: Record<string, { isEnabled: boolean; label: string }> = {};

        (tableExport.columns ?? []).forEach((col: ExportColumnDef) => {
            const isEnabled = col.enabledByDefault && (!tableExport.enableVisibleTableColumnsByDefault || (visibleColumnsRef.current?.[col.name] ?? true));

            initialColumnMap[col.name] = { isEnabled, label: '' };
        });

        setColumnMap(initialColumnMap);
        setColumnOrder((tableExport.columns ?? []).map((col: ExportColumnDef) => col.name));
        setFormats(tableExport.formats ?? []);

        const initialOptions: Record<string, unknown> = {};

        (tableExport.optionsForm ?? []).forEach((field: ExportOptionField) => {
            initialOptions[field.name] = field.type === 'checkbox' ? (field.default ?? false) : (field.default ?? '');
        });

        setOptions(initialOptions);
    }, [tableExport]);

    const columns = tableExport?.columns ?? [];
    const availableFormats = tableExport?.formats ?? [];
    const optionsForm = tableExport?.optionsForm ?? [];

    const columnByName = useMemo(() => Object.fromEntries(columns.map((col) => [col.name, col])), [columns]);

    const enabledColumnCount = useMemo(() => Object.values(columnMap).filter((c) => c.isEnabled).length, [columnMap]);
    const canSubmit = (tableExport?.hasColumnMapping === false || enabledColumnCount > 0) && formats.length > 0;

    const setAllColumnsEnabled = (enabled: boolean): void => {
        setColumnMap((prev) =>
            Object.fromEntries(Object.entries(prev).map(([k, v]) => [k, { ...v, isEnabled: enabled }])),
        );
    };

    const toggleFormat = (format: string): void => {
        setFormats((prev) => (prev.includes(format) ? prev.filter((f) => f !== format) : [...prev, format]));
    };

    const handleSubmit = (): void => {
        const payload: ExportModalPayload = { formats, options };

        if (tableExport?.hasColumnMapping !== false) {
            // Send in the drag-chosen order — the backend preserves columnMap key order
            // for the exported headers and rows.
            payload.columnMap = Object.fromEntries(columnOrder.map((name) => [name, columnMap[name]]));
        }

        onSubmit(payload);
        onClose();
    };

    const summaryParts: string[] = [];

    if (tableExport?.hasColumnMapping !== false) {
        summaryParts.push(`${enabledColumnCount} ${t('table::table.export_modal_columns_header').toLowerCase()}`);
    }

    if (formats.length > 0) {
        summaryParts.push(formats.map((f) => t(`table::table.export_format_${f}`)).join(', '));
    }

    const summaryLine = summaryParts.join(' · ');

    return (
        <Dialog open={show} onOpenChange={(open) => !open && onClose()}>
            <DialogContent
                className="gap-0 p-0 sm:max-w-[540px] focus:outline-none"
                tabIndex={-1}
                onOpenAutoFocus={(event) => {
                    // Focus the dialog surface itself: not the first control (avoids auto-highlighting
                    // "Select all") and not the closing dropdown in the now aria-hidden background
                    // (avoids the "aria-hidden on a focused element" warning). The surface is not a
                    // tab stop, so focus:outline-none hides the ring without hurting keyboard nav.
                    event.preventDefault();
                    (event.currentTarget as HTMLElement).focus();
                }}
            >
                {/* Header */}
                <div className="flex items-start gap-3 px-5 pt-5 pr-12">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Download className="size-4" />
                    </div>
                    <div className="flex flex-col gap-0.5">
                        <DialogTitle>
                            {tableExport?.resourceLabel
                                ? `${t('table::table.export_modal_title')} ${tableExport.resourceLabel}`
                                : t('table::table.export_modal_title')}
                        </DialogTitle>
                        <DialogDescription className="text-sm text-muted-foreground">{t('table::table.export_modal_subtitle')}</DialogDescription>
                    </div>
                </div>

                {/* Content sections */}
                <div className="flex flex-col gap-4 px-5 py-4">
                    {/* Columns section */}
                    {columns.length > 0 && tableExport?.hasColumnMapping !== false && (
                        <div className="flex flex-col gap-2">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium">{t('table::table.export_modal_columns_header')}</p>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">
                                        {enabledColumnCount}/{columns.length}
                                    </span>
                                    {columns.length > 1 && (
                                        <>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setAllColumnsEnabled(true)}
                                                className="h-7 cursor-pointer px-2 text-xs"
                                            >
                                                {t('table::table.export_select_all')}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setAllColumnsEnabled(false)}
                                                className="h-7 cursor-pointer px-2 text-xs"
                                            >
                                                {t('table::table.export_deselect_all')}
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                            <DraggableList
                                items={columnOrder.map((name) => ({ id: name }))}
                                onReorder={(items) => setColumnOrder(items.map((item) => item.id))}
                                className="max-h-64 divide-y overflow-y-auto rounded-md border"
                            >
                                {columnOrder.map((name) => {
                                    const col = columnByName[name];

                                    if (!col) {
                                        return null;
                                    }

                                    const colState = columnMap[name];

                                    return (
                                        <DraggableWrapper
                                            key={name}
                                            id={name}
                                            canDrag={columns.length > 1}
                                            showDragHandle
                                            dragHandleClassName="left-2.5"
                                            className={cn(
                                                'flex items-center gap-3 py-2 pr-3 transition-colors hover:bg-muted/50',
                                                columns.length > 1 ? 'pl-9' : 'pl-3',
                                            )}
                                        >
                                            <Checkbox
                                                id={`export-col-${name}`}
                                                aria-label={col.label}
                                                className="cursor-pointer"
                                                checked={colState.isEnabled}
                                                onCheckedChange={(checked) => {
                                                    setColumnMap((prev) => ({
                                                        ...prev,
                                                        [name]: { ...prev[name], isEnabled: !!checked },
                                                    }));
                                                }}
                                            />
                                            <Input
                                                id={`export-col-label-${name}`}
                                                value={colState.label}
                                                placeholder={col.label}
                                                disabled={!colState.isEnabled}
                                                onChange={(e) => {
                                                    setColumnMap((prev) => ({
                                                        ...prev,
                                                        [name]: { ...prev[name], label: e.target.value },
                                                    }));
                                                }}
                                                className="h-7 border-0 bg-transparent px-0 shadow-none focus-visible:ring-0 text-sm"
                                            />
                                        </DraggableWrapper>
                                    );
                                })}
                            </DraggableList>
                        </div>
                    )}

                    {/* Format section */}
                    {availableFormats.length > 0 && (
                        <div className="flex flex-col gap-2">
                            <p className="text-sm font-medium">{t('table::table.export_formats_header')}</p>
                            <div className="flex flex-wrap gap-2">
                                {availableFormats.map((format: string) => {
                                    const isSelected = formats.includes(format);

                                    return (
                                        <button
                                            key={format}
                                            type="button"
                                            role="checkbox"
                                            aria-checked={isSelected}
                                            onClick={() => toggleFormat(format)}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition',
                                                isSelected
                                                    ? 'border-primary bg-primary/5 text-foreground'
                                                    : 'border-border text-muted-foreground hover:border-foreground/30',
                                            )}
                                        >
                                            <FormatIcon format={format} />
                                            {t(`table::table.export_format_${format}`)}
                                            {isSelected && <Check className="size-3.5 shrink-0" />}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Options form */}
                    {optionsForm.length > 0 && (
                        <div className="flex flex-col gap-3">
                            {optionsForm.map((field: ExportOptionField) => (
                                <div key={field.name} className="flex flex-col gap-1.5">
                                    {field.type !== 'checkbox' && (
                                        <label htmlFor={`export-option-${field.name}`} className="text-sm font-medium">
                                            {field.label}
                                        </label>
                                    )}
                                    {field.type === 'text' && (
                                        <Input
                                            id={`export-option-${field.name}`}
                                            value={String(options[field.name] ?? '')}
                                            onChange={(e) => setOptions((prev) => ({ ...prev, [field.name]: e.target.value }))}
                                        />
                                    )}
                                    {field.type === 'select' && (
                                        <Select
                                            value={String(options[field.name] ?? '')}
                                            onValueChange={(value) => setOptions((prev) => ({ ...prev, [field.name]: value }))}
                                        >
                                            <SelectTrigger id={`export-option-${field.name}`}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(field.options ?? {}).map(([value, label]) => (
                                                    <SelectItem key={value} value={value}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                    {field.type === 'checkbox' && (
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id={`export-option-${field.name}`}
                                                className="cursor-pointer"
                                                checked={!!options[field.name]}
                                                onCheckedChange={(checked) =>
                                                    setOptions((prev) => ({ ...prev, [field.name]: !!checked }))
                                                }
                                            />
                                            <label htmlFor={`export-option-${field.name}`} className="cursor-pointer text-sm">
                                                {field.label}
                                            </label>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between border-t px-5 py-4">
                    <p className="text-sm text-muted-foreground">{summaryLine}</p>
                    <div className="flex items-center gap-2">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            {t('table::table.export_cancel_button')}
                        </Button>
                        <Button type="button" disabled={!canSubmit} onClick={handleSubmit}>
                            {t('table::table.export_submit_button')}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
