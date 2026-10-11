import { Button } from '@shared/components/ui/button';
import { Calendar } from '@shared/components/ui/calendar';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@shared/components/ui/command';
import { Input } from '@shared/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@shared/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@shared/components/ui/select';
import { useLang } from '@shared/hooks/use-lang';
import { statusChipClasses, statusTone } from '@shared/lib/status-colors';
import { cn } from '@shared/lib/utils';
import { format, parseISO } from 'date-fns';
import { CalendarIcon, Check, X } from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';
import { filterValueLabel } from './filterValueLabel';
import type { FilterProps } from './types';

const Filter = ({ filter, value, onChange, onRemove }: FilterProps): React.ReactElement => {
    const { t } = useLang();
    const semanticTone =
        /status|state|settlement/i.test(filter.label) && (!value.clause || ['equals', '='].includes(value.clause))
            ? statusTone(filter.options?.find((option) => option.value == value.value)?.label ?? value.value)
            : 'muted';
    const inputRef = useRef<HTMLDivElement>(null);
    const [isOpen, setIsOpen] = useState<boolean>(false);
    const optionsRef = useRef<HTMLDivElement>(null);
    const clauseLabel = (clause: string) =>
        ({ with_trashed: 'Include trashed', only_trashed: 'Trashed only', without_trashed: 'Active only' })[clause] ??
        t(`table::table.clause_${clause}`).replace(/^./, (letter) => letter.toUpperCase());
    const clauseSelectRef = useRef<HTMLButtonElement>(null);

    function setFilterClause(newClause: string): void {
        const multiple = ['in', 'not_in', 'between', 'not_between'].includes(newClause);
        const nextValue = multiple
            ? Array.isArray(value.value)
                ? value.value
                : value.value == null || value.value === ''
                  ? []
                  : [value.value]
            : Array.isArray(value.value)
              ? (value.value[0] ?? null)
              : value.value;
        onChange({ ...value, clause: newClause, value: nextValue });
    }

    function setFilterValue(newValue: any): void {
        onChange({ ...value, value: newValue });
    }

    const formatDate = (value: string | string[]): string => {
        if (Array.isArray(value)) {
            return value
                .map(formatDate)
                .filter((value) => !!value)
                .join(' - ');
        }

        return value ?? '';
    };

    const presentableValue = (): string => {
        if (filter.type === 'set') {
            const values = Array.isArray(value.value) ? value.value : [value.value];

            const labels = values.map((value: any) => filter.options?.find((option) => option.value == value)?.label).filter(Boolean);

            return labels.length > 3 ? `${labels.slice(0, 3).join(', ')}, ...` : labels.join(', ');
        }

        if (filter.type === 'date') {
            return formatDate(value.value);
        }

        if (Array.isArray(value.value)) {
            return value.value.filter((value: any) => !!value).join(', ');
        }

        return value.value;
    };

    const focusValueInput = (): void => {
        const focusOptions = { preventScroll: true };

        if (filter.type === 'boolean' || filter.type === 'trashed') {
            (optionsRef.current ?? clauseSelectRef.current)?.focus(focusOptions);
        } else {
            const element = inputRef.current?.querySelector('input,select') as HTMLElement;
            element?.focus(focusOptions);
        }
    };

    useEffect(() => {
        if (value.new) {
            setIsOpen(true);
        }
    }, []);

    useEffect(() => {
        if (!value.value) {
            focusValueInput();
        }
    }, [value]);

    // Multi-select component for filter options
    const MultiSelectFilter = ({ options }: { options: { label: string; value: string | number }[] }) => {
        const selectedValues = Array.isArray(value.value) ? value.value : [];

        const toggleValue = (optionValue: string | number) => {
            const newSelectedValues = selectedValues.includes(optionValue)
                ? selectedValues.filter((v: any) => v !== optionValue)
                : [...selectedValues, optionValue];
            setFilterValue(newSelectedValues);
        };

        return (
            <Command className="rounded-md border">
                {options.length > 7 && <CommandInput placeholder={t('table::table.search_options_placeholder')} className="h-8 text-xs" />}
                <CommandList className="max-h-52">
                    <CommandEmpty>{t('table::table.no_option_found')}</CommandEmpty>
                    <CommandGroup>
                        {options.map((option) => (
                            <CommandItem
                                key={option.value}
                                value={String(option.value)}
                                keywords={[option.label]}
                                onSelect={() => toggleValue(option.value)}
                                className="gap-2 py-1.5 text-xs"
                            >
                                <span className="flex-1">{option.label}</span>
                                <Check className={cn('size-3.5', !selectedValues.includes(option.value) && 'opacity-0')} />
                            </CommandItem>
                        ))}
                    </CommandGroup>
                </CommandList>
            </Command>
        );
    };

    return (
        <Popover
            open={isOpen}
            onOpenChange={(open) => {
                setIsOpen(open);
                if (open) {
                    focusValueInput();
                }
            }}
        >
            <div className={cn('inline-flex max-w-full items-center gap-1 rounded-full border text-xs', statusChipClasses[semanticTone])}>
                <PopoverTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="ui-control hover:bg-accent h-7 min-w-0 gap-1.5 rounded-full border-0 px-3 text-xs shadow-none"
                        style={{ height: 28, minHeight: 28, backgroundColor: 'transparent', color: 'inherit' }}
                        aria-label={`Edit ${filter.label} filter`}
                    >
                        <span className="text-current opacity-75">{filter.label}</span>
                        <span aria-hidden="true" className="text-current opacity-60">
                            :
                        </span>
                        <span className="max-w-48 truncate font-medium">{filterValueLabel(filter, value, presentableValue())}</span>
                    </Button>
                </PopoverTrigger>
                <Button
                    variant="ghost"
                    size="icon"
                    className="ui-control hover:bg-accent mr-1 size-6 min-h-6 rounded-full border-0 p-0 shadow-none"
                    style={{ height: 24, minHeight: 24, width: 24, backgroundColor: 'transparent', color: 'inherit' }}
                    aria-label={`Remove ${filter.label} filter`}
                    onClick={onRemove}
                >
                    <X className="size-3" />
                </Button>
            </div>
            <PopoverContent
                className="w-56 max-w-[calc(100vw-2rem)] p-0"
                align="start"
                sideOffset={8}
                onOpenAutoFocus={(event) => {
                    if (
                        filter.type === 'boolean' ||
                        filter.type === 'trashed' ||
                        (filter.type === 'set' && !filter.multiple && !['in', 'not_in', 'is_set', 'is_not_set'].includes(value.clause))
                    ) {
                        event.preventDefault();
                        optionsRef.current?.focus();
                    }
                }}
            >
                {filter.type === 'boolean' || filter.type === 'trashed' ? (
                    <div className="p-1" role="group" aria-label={filter.label} ref={optionsRef} tabIndex={-1}>
                        <div className="text-muted-foreground px-2 py-1.5 text-xs font-medium">{filter.label}</div>
                        {filter.clauses.map((clause) => (
                            <button
                                key={clause}
                                type="button"
                                aria-pressed={value.clause === clause}
                                className="hover:bg-accent focus-visible:bg-accent flex min-h-8 w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-xs outline-none"
                                onClick={() => {
                                    onChange({ ...value, clause, value: null });
                                    setIsOpen(false);
                                }}
                            >
                                {clauseLabel(clause)}
                                <Check className={cn('size-3.5', value.clause !== clause && 'invisible')} />
                            </button>
                        ))}
                    </div>
                ) : filter.type === 'set' && !filter.multiple && !['in', 'not_in', 'is_set', 'is_not_set'].includes(value.clause) ? (
                    <Command>
                        <div className="text-muted-foreground border-b px-3 py-2 text-xs font-medium">{filter.label}</div>
                        {filter.clauses.length > 1 && (
                            <div className="border-b p-2">
                                <Select value={value.clause} onValueChange={setFilterClause}>
                                    <SelectTrigger aria-label="Filter condition" className="h-7 min-h-7 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filter.clauses.map((clause) => (
                                            <SelectItem key={clause} value={clause} className="py-1.5 text-xs">
                                                {clauseLabel(clause)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        {Array.isArray(filter.options) && filter.options.length > 7 && (
                            <CommandInput placeholder={`Search ${filter.label.toLowerCase()}`} />
                        )}
                        <CommandList>
                            <CommandEmpty>{t('table::table.no_option_found')}</CommandEmpty>
                            <CommandGroup>
                                {(Array.isArray(filter.options) ? filter.options : []).map((option) => (
                                    <CommandItem
                                        className="py-1.5 text-xs"
                                        key={String(option.value)}
                                        value={String(option.value)}
                                        keywords={[option.label]}
                                        onSelect={() => {
                                            setFilterValue(option.value);
                                            setIsOpen(false);
                                        }}
                                    >
                                        <span className="flex-1">{option.label}</span>
                                        <Check className={String(value.value) === String(option.value) ? 'size-4' : 'size-4 opacity-0'} />
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                ) : (
                    <div className="flex flex-col gap-2 p-2">
                        <div className="text-muted-foreground px-1 text-xs font-medium">{filter.label}</div>
                        {filter.clauses.length > 1 && (
                            <div className="flex items-center">
                                <Select value={value.clause} onValueChange={(newClause) => setFilterClause(newClause)}>
                                    <SelectTrigger
                                        ref={clauseSelectRef}
                                        aria-label={`${filter.label} condition`}
                                        className="h-7 min-h-7 w-full text-xs"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filter.clauses.map((clause) => (
                                            <SelectItem key={clause} value={clause} className="py-1.5 text-xs">
                                                {clauseLabel(clause)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        {value.clause !== 'is_set' &&
                            value.clause !== 'is_not_set' &&
                            value.clause !== 'with_trashed' &&
                            value.clause !== 'only_trashed' &&
                            value.clause !== 'without_trashed' && (
                                <div className="flex items-center">
                                    <div ref={inputRef} className="min-w-0 grow">
                                        {filter.type === 'numeric' && (value.clause === 'between' || value.clause === 'not_between') ? (
                                            <div className="flex items-center space-x-2 rtl:space-x-reverse">
                                                <Input
                                                    value={value.value?.[0] ?? ''}
                                                    onChange={(e) => setFilterValue([e.target.value, value.value?.[1]])}
                                                    type="number"
                                                    aria-label="Range value"
                                                    className="h-7 min-h-7 w-0 flex-1 text-xs"
                                                />
                                                <span className="text-sm text-gray-500 dark:text-zinc-500">
                                                    {t('table::table.between_values_and')}
                                                </span>
                                                <Input
                                                    value={value.value?.[1] ?? ''}
                                                    onChange={(e) => setFilterValue([value.value?.[0], e.target.value])}
                                                    type="number"
                                                    aria-label="Range value"
                                                    className="h-7 min-h-7 w-0 flex-1 text-xs"
                                                />
                                            </div>
                                        ) : filter.type === 'text' || filter.type === 'numeric' ? (
                                            <Input
                                                value={value.value ?? ''}
                                                onChange={(e) => setFilterValue(e.target.value)}
                                                type={filter.type === 'text' ? 'text' : 'number'}
                                                aria-label={`${filter.label} value`}
                                                placeholder={`Enter ${filter.label.toLowerCase()}`}
                                                className="h-7 min-h-7 w-full text-xs"
                                            />
                                        ) : filter.type === 'set' ? (
                                            value.clause === 'in' || value.clause === 'not_in' || filter.multiple ? (
                                                // Multiple selection - use custom Shadcn multi-select
                                                <MultiSelectFilter options={filter.options || []} />
                                            ) : (
                                                // Single selection - use Shadcn Select
                                                <Select value={value.value || ''} onValueChange={(newValue) => setFilterValue(newValue)}>
                                                    <SelectTrigger className="h-7 min-h-7 w-full text-xs">
                                                        <SelectValue placeholder={t('table::table.select_placeholder')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {filter.options?.map((option) => (
                                                            <SelectItem key={option.value} value={option.value.toString()}>
                                                                {option.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            )
                                        ) : filter.type === 'date' ? (
                                            <Popover>
                                                <PopoverTrigger asChild>
                                                    <Button
                                                        variant="outline"
                                                        className={cn(
                                                            'h-7 min-h-7 w-full justify-start text-left text-xs font-normal',
                                                            !value.value && 'text-muted-foreground',
                                                        )}
                                                    >
                                                        <CalendarIcon className="mr-2 h-4 w-4" />
                                                        {value.value
                                                            ? (value.clause === 'between' || value.clause === 'not_between') &&
                                                              Array.isArray(value.value)
                                                                ? value.value.length === 2
                                                                    ? `${format(parseISO(value.value[0]), 'PPP')} - ${format(parseISO(value.value[1]), 'PPP')}`
                                                                    : t('table::table.pick_dates')
                                                                : format(parseISO(value.value), 'PPP')
                                                            : t('table::table.pick_a_date')}
                                                    </Button>
                                                </PopoverTrigger>
                                                <PopoverContent className="w-auto p-0" align="start">
                                                    <Calendar
                                                        {...({
                                                            mode: value.clause === 'between' || value.clause === 'not_between' ? 'range' : 'single',
                                                            selected: value.value
                                                                ? (value.clause === 'between' || value.clause === 'not_between') &&
                                                                  Array.isArray(value.value)
                                                                    ? value.value.length === 2
                                                                        ? {
                                                                              from: parseISO(value.value[0]),
                                                                              to: parseISO(value.value[1]),
                                                                          }
                                                                        : undefined
                                                                    : parseISO(value.value)
                                                                : undefined,
                                                            onSelect: (date: any) => {
                                                                if (value.clause === 'between' || value.clause === 'not_between') {
                                                                    if (date?.from && date?.to) {
                                                                        setFilterValue([
                                                                            format(date.from, 'yyyy-MM-dd'),
                                                                            format(date.to, 'yyyy-MM-dd'),
                                                                        ]);
                                                                    }
                                                                } else if (date) {
                                                                    setFilterValue(format(date, 'yyyy-MM-dd'));
                                                                }
                                                            },
                                                            disabled: (date: Date) => date < new Date('1900-01-01'),
                                                            initialFocus: true,
                                                        } as any)}
                                                    />
                                                </PopoverContent>
                                            </Popover>
                                        ) : null}
                                    </div>
                                </div>
                            )}
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
};

export default Filter;
