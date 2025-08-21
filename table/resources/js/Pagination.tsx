import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@shared/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@shared/components/ui/select';
import { useLang } from '@shared/hooks/use-lang';
import { cn } from '@shared/lib/utils';
import { useMemo } from 'react';

interface PaginationMeta {
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    per_page: number;
    total: number;
    on_first_page: boolean;
    on_last_page: boolean;
    first_page_url?: string;
    prev_page_url?: string | null;
    next_page_url?: string | null;
    last_page_url?: string;
    links?: Array<{ url: string | null; label: string; page: number | null; active: boolean }>;
}

interface TablePaginationProps {
    meta: PaginationMeta;
    options: number[];
    perPage: number;
    type?: 'full' | 'simple';
    onClick: (url: string) => void;
    onChange: (perPage: number) => void;
}

export default function TablePagination({ meta, options, perPage, type = 'full', onClick, onChange }: TablePaginationProps) {
    const { t } = useLang();

    const translatedString = useMemo(() => {
        let params: Record<string, any> = {
            current_page: meta.current_page,
            current: meta.current_page,
            from: meta.from,
            on_first_page: meta.on_first_page,
            on_last_page: meta.on_last_page,
            per_page: meta.per_page,
            to: meta.to,
            type: type,
        };

        if (type === 'full') {
            params = {
                ...params,
                last: meta.last_page,
                last_page: meta.last_page,
                total: meta.total,
            };
        }

        return t(type === 'full' ? 'table::table.current_page_of_last' : 'table::table.current_page', params);
    }, [meta, type, t]);

    // Generate page numbers to show
    const generatePageNumbers = (): (number | string)[] => {
        const current = meta.current_page;
        const total = meta.last_page;
        const delta = 2; // Pages to show on each side of current page
        const pages: (number | string)[] = [];

        // Always show first page
        pages.push(1);

        // Calculate range around current page
        const rangeStart = Math.max(2, current - delta);
        const rangeEnd = Math.min(total - 1, current + delta);

        // Add ellipsis after first page if needed
        if (rangeStart > 2) {
            pages.push('...');
        }

        // Add range around current page
        for (let i = rangeStart; i <= rangeEnd; i++) {
            pages.push(i);
        }

        // Add ellipsis before last page if needed
        if (rangeEnd < total - 1) {
            pages.push('...');
        }

        // Always show last page (if there's more than one page)
        if (total > 1) {
            pages.push(total);
        }

        return pages;
    };

    const pageNumbers = generatePageNumbers();

    return (
        <div className="it-pagination flex w-full flex-col gap-4 md:flex-row md:items-center md:justify-between">
            {/* Page info and navigation */}
            <div className="flex items-center gap-4">
                {/* Page info */}
                <p className="text-muted-foreground text-sm whitespace-nowrap">{translatedString}</p>

                {/* Pagination */}
                {meta.last_page > 1 && (
                    <Pagination>
                        <PaginationContent>
                            {/* Previous button */}
                            <PaginationItem>
                                <PaginationPrevious
                                    href="#"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        if (meta.prev_page_url) {
                                            onClick(meta.prev_page_url);
                                        }
                                    }}
                                    className={cn(meta.on_first_page && 'pointer-events-none opacity-50')}
                                />
                            </PaginationItem>

                            {/* Page numbers */}
                            {pageNumbers.map((page, index) => (
                                <PaginationItem key={index}>
                                    {page === '...' ? (
                                        <PaginationEllipsis />
                                    ) : (
                                        <PaginationLink
                                            href="#"
                                            onClick={(e) => {
                                                e.preventDefault();
                                                // Find the URL for this page number from the links array
                                                const link = meta.links?.find((l) => l.page === page);
                                                if (link?.url) {
                                                    onClick(link.url);
                                                }
                                            }}
                                            isActive={page === meta.current_page}
                                        >
                                            {page}
                                        </PaginationLink>
                                    )}
                                </PaginationItem>
                            ))}

                            {/* Next button */}
                            <PaginationItem>
                                <PaginationNext
                                    href="#"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        if (meta.next_page_url) {
                                            onClick(meta.next_page_url);
                                        }
                                    }}
                                    className={cn(meta.on_last_page && 'pointer-events-none opacity-50')}
                                />
                            </PaginationItem>
                        </PaginationContent>
                    </Pagination>
                )}
            </div>

            {/* Rows per page */}
            <div className="flex flex-shrink-0 items-center gap-2">
                <p className="text-sm font-medium whitespace-nowrap">{t('table::table.rows_per_page')}</p>
                <Select value={perPage.toString()} onValueChange={(value) => onChange(parseInt(value))}>
                    <SelectTrigger className="it-pagination-per-page-select h-8 w-auto min-w-[70px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {options.map((option) => (
                            <SelectItem key={option} value={option.toString()}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
