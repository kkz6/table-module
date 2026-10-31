<script setup lang="ts">
import { Button, DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@workspace/ui';
import Tooltip from '@workspace/ui/Tooltip.vue';
import { computed, type Component } from 'vue';
import Icon from './Icon.vue';
import { actionState } from './state';
import type { TableAction, TableRowData } from './types';
const props = defineProps<{ item: TableRowData; actions: TableAction[]; busy?: boolean; resolver?: (name: string) => Component | undefined }>();
const emit = defineEmits<{ action: [action: TableAction, index: number, item: TableRowData] }>();
const available = computed(() =>
    props.actions
        .map((action, index) => ({ action, index, state: actionState(props.item, index) }))
        .filter(({ action, state }) => action.authorized && action.asRowAction !== false && !state?.hidden),
);
</script>
<template>
    <DropdownMenu v-if="available.length > 1">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="it-row-actions-dropdown"
                aria-label="Open row actions"
            >
                <Icon
                    name="MoreHorizontal"
                    class="size-4"
                />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem
                v-for="entry in available"
                :key="entry.index"
                :disabled="busy || entry.state?.disabled"
                :class="entry.action.variant === 'destructive' ? 'text-destructive' : undefined"
                @select="emit('action', entry.action, entry.index, item)"
            >
                <Icon
                    :name="entry.action.icon"
                    :resolver="resolver"
                    class="size-4"
                />
                {{ entry.action.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
    <div
        v-else
        class="it-row-actions flex items-center gap-1"
    >
        <Tooltip
            v-for="entry in available"
            :key="entry.index"
            :label="entry.action.label"
        >
            <Button
                :variant="entry.action.variant === 'destructive' ? 'destructiveGhost' : 'ghost'"
                :size="entry.action.showLabel ? 'sm' : 'icon'"
                :class="[!entry.action.showLabel && 'it-row-action-icon-button', entry.action.buttonClass]"
                :aria-label="entry.action.label"
                :disabled="busy || entry.state?.disabled"
                @click.stop="emit('action', entry.action, entry.index, item)"
            >
                <Icon
                    :name="entry.action.icon"
                    :resolver="resolver"
                    class="size-4"
                />
                <span v-if="entry.action.showLabel">{{ entry.action.label }}</span>
            </Button>
        </Tooltip>
    </div>
</template>
