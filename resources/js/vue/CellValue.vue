<script setup lang="ts">
import { Badge } from '@workspace/ui';
import { computed, type Component } from 'vue';
import { badgeTone } from '../../../../shared/resources/js/lib/status-colors';
import Icon from './Icon.vue';
import type { TableColumn } from './types';
const props = defineProps<{
    value: unknown;
    column: TableColumn;
    image?: {
        url?: string | string[];
        icon?: string;
        position?: string;
        size?: string;
        rounded?: boolean;
        width?: string | number;
        height?: string | number;
        alt?: string;
        title?: string;
        class?: string;
        remaining?: number;
    };
    resolver?: (name: string) => Component | undefined;
}>();
const badge = computed(() =>
    props.value && typeof props.value === 'object' && 'variant' in props.value && 'value' in props.value
        ? (props.value as { value: unknown; variant: string; icon?: string })
        : null,
);
const images = computed(() => (typeof props.image?.url === 'string' ? [props.image.url] : (props.image?.url ?? [])));
const booleanIcon = computed(() => (props.column.type === 'boolean' ? (props.value ? props.column.trueIcon : props.column.falseIcon) : null));
function display(value: unknown): string {
    if (value == null || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (Array.isArray(value)) return value.map(display).join(', ');
    if (typeof value === 'object' && 'value' in value) return display(value.value);
    return String(value);
}
</script>
<template>
    <div
        class="flex min-w-0 items-center gap-2"
        :class="{
            'flex-row-reverse justify-end': image?.position === 'right',
            'flex-col items-start': image?.position === 'above' || image?.position === 'top',
            'flex-col-reverse items-start': image?.position === 'below' || image?.position === 'bottom',
        }"
    >
        <div
            v-if="image"
            class="flex shrink-0 items-center -space-x-2"
        >
            <img
                v-for="(url, index) in images"
                :key="index"
                :src="url"
                :alt="image.alt ?? ''"
                :title="image.title"
                loading="lazy"
                class="size-8 shrink-0 object-cover"
                :class="[image.class, image.rounded ? 'rounded-full' : 'rounded-md']"
                :style="{
                    width: typeof image.width === 'number' ? `${image.width}px` : image.width,
                    height: typeof image.height === 'number' ? `${image.height}px` : image.height,
                }"
            />
            <Icon
                v-if="!images.length && image.icon"
                :name="image.icon"
                :resolver="resolver"
                class="text-muted-foreground size-6"
            />
            <span
                v-if="image.remaining"
                class="bg-muted flex size-8 items-center justify-center rounded-full text-xs"
            >
                +{{ image.remaining }}
            </span>
        </div>
        <template v-if="column.type !== 'image'">
            <Badge
                v-if="badge"
                :variant="badgeTone(badge.variant)"
                class="it-badge gap-1"
            >
                <Icon
                    v-if="badge.icon"
                    :name="badge.icon"
                    :resolver="resolver"
                    class="size-3"
                />
                {{ display(badge.value) }}
            </Badge>
            <span v-else-if="booleanIcon">
                <Icon
                    :name="booleanIcon"
                    :resolver="resolver"
                    class="size-5"
                />
                <span class="sr-only">{{ value ? 'Yes' : 'No' }}</span>
            </span>
            <span v-else>{{ display(value) }}</span>
        </template>
    </div>
</template>
