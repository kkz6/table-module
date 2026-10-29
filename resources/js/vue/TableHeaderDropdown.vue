<script setup lang="ts">
import { Button, DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@workspace/ui';
import { ArrowDown, ArrowUp, ChevronsUpDown, EyeOff, Lock, Unlock } from 'lucide-vue-next';
import type { TableColumn } from './types';
defineProps<{ column: TableColumn; sort?: string | null; sticky?: boolean }>();
const emit = defineEmits<{ sort: [value: string]; toggle: []; sticky: [] }>();
</script>
<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="it-table-column-dropdown ui-control text-muted-foreground hover:text-muted-foreground h-7 min-h-7 gap-1.5 border-0 bg-transparent px-0 font-medium shadow-none hover:bg-transparent data-[state=open]:bg-transparent"
                :aria-label="`${column.header}${sort ? ', sorted ' + (sort === 'asc' ? 'ascending' : 'descending') : ', column options'}`"
            >
                <Lock
                    v-if="sticky"
                    class="size-4"
                />
                <span :class="column.headerClass">{{ column.header }}</span>
                <ArrowUp
                    v-if="sort === 'asc'"
                    class="text-foreground size-3.5"
                />
                <ArrowDown
                    v-else-if="sort === 'desc'"
                    class="text-foreground size-3.5"
                />
                <ChevronsUpDown
                    v-else-if="column.sortable"
                    class="size-3 opacity-40"
                />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            :align="column.alignment === 'right' ? 'end' : 'start'"
            class="it-dropdown-items w-max min-w-24"
        >
            <template v-if="column.sortable">
                <DropdownMenuItem @select="emit('sort', column.attribute)">
                    <ArrowUp class="size-3.5" />
                    Sort ascending
                </DropdownMenuItem>
                <DropdownMenuItem @select="emit('sort', '-' + column.attribute)">
                    <ArrowDown class="size-3.5" />
                    Sort descending
                </DropdownMenuItem>
            </template>
            <DropdownMenuSeparator
                v-if="column.sortable && (column.toggleable || column.stickable)"
                class="bg-border -mx-1 my-1 h-px"
            />
            <DropdownMenuItem
                v-if="column.stickable"
                @select="emit('sticky')"
            >
                <Unlock
                    v-if="sticky"
                    class="size-3.5"
                />
                <Lock
                    v-else
                    class="size-3.5"
                />
                {{ sticky ? 'Unpin column' : 'Pin column' }}
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="column.toggleable"
                @select="emit('toggle')"
            >
                <EyeOff class="size-3.5" />
                Hide column
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
