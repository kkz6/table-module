import { Button } from '@shared/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@shared/components/ui/dropdown-menu';
import { useLang } from '@shared/hooks/use-lang';
import { cn } from '@shared/lib/utils';
import { ArrowDown, ArrowUp, ChevronsUpDown, EyeOff, Lock, Unlock } from 'lucide-react';
import React from 'react';
import type { ButtonIconProps, TableHeaderDropdownProps } from './types';

const ButtonIcon = ({ sort, className = 'size-4 ms-2' }: ButtonIconProps): React.ReactElement => {
    if (!sort) {
        return <ChevronsUpDown className={className} />;
    }

    return sort === 'asc' ? <ArrowUp className={className} /> : <ArrowDown className={className} />;
};

export default function TableHeaderDropdown({
    column,
    sort,
    sticky,
    onToggle,
    onSort,
    onStick,
    onUnstick,
}: TableHeaderDropdownProps): React.ReactElement {
    const { t } = useLang();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className={cn(
                        'it-table-column-dropdown ui-control hover:text-foreground h-8 gap-1.5 border-0 bg-transparent px-0 font-medium shadow-none hover:bg-transparent data-[state=open]:bg-transparent',
                        sort ? 'text-foreground' : 'text-muted-foreground',
                    )}
                    style={{ height: 28, minHeight: 28, backgroundColor: 'transparent' }}
                    aria-label={`${column.header}${sort ? `, sorted ${sort === 'asc' ? 'ascending' : 'descending'}` : ', column options'}`}
                >
                    {sticky && <Lock className="me-2 size-4" />}
                    <span className={column.headerClass}>{column.header}</span>
                    {column.sortable && <ButtonIcon sort={sort} className={sort ? 'text-foreground size-3.5' : 'size-3 opacity-40'} />}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="it-dropdown-items w-max min-w-24" align={column.alignment === 'right' ? 'end' : 'start'}>
                {column.sortable && (
                    <>
                        <DropdownMenuItem onClick={() => onSort(column.attribute)} className="it-dropdown-item">
                            <ArrowUp className="me-2 size-3.5" />
                            <span>{t('table::table.sort_asc')}</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => onSort(`-${column.attribute}`)} className="it-dropdown-item">
                            <ArrowDown className="me-2 size-3.5" />
                            <span>{t('table::table.sort_desc')}</span>
                        </DropdownMenuItem>
                    </>
                )}
                {column.sortable && (column.toggleable || column.stickable) && <DropdownMenuSeparator className="it-dropdown-separator" />}

                {column.stickable && !sticky && (
                    <DropdownMenuItem onClick={() => onStick(column)} className="it-dropdown-item">
                        <Lock className="me-2 size-3.5" />
                        <span>{t('table::table.stick')}</span>
                    </DropdownMenuItem>
                )}

                {column.stickable && sticky && (
                    <DropdownMenuItem onClick={() => onUnstick(column)} className="it-dropdown-item">
                        <Unlock className="me-2 size-3.5" />
                        <span>{t('table::table.unstick')}</span>
                    </DropdownMenuItem>
                )}

                {column.toggleable && (
                    <DropdownMenuItem onClick={() => onToggle(column)} className="it-dropdown-item">
                        <EyeOff className="me-2 size-3.5" />
                        <span>{t('table::table.hide_column')}</span>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
