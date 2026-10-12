import clsx from 'clsx';
import { MoreHorizontal } from 'lucide-react';
import React, { useCallback, useMemo } from 'react';

import { Button } from '@shared/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@shared/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@shared/components/ui/tooltip';
import DynamicIcon from './DynamicIcon';
import TableActions from './TableActions';
import type { RowActionsProps, TableAction } from './types';
import { getActionForItem } from './urlHelpers';

// Inline row actions use the quieter destructive treatment; a solid red slab in
// every row reads as an alarm rather than an action.
function toRowVariant(variant?: string) {
    return variant === 'destructive' ? 'destructiveGhost' : variant;
}

export default function RowActions({
    item,
    actions,
    performAction,
    iconResolver,
    onSuccess = null,
    onError = null,
    onHandle = null,
}: RowActionsProps): React.ReactElement | null {
    const actionIsVisible = useCallback(
        (action: TableAction, key: number): boolean => {
            // Check if we have action data for this item
            if (!item._actions || !item._actions[key]) {
                return true; // Default to visible
            }

            return getActionForItem(item._actions[key], action)?.isVisible ?? true;
        },
        [item],
    );

    const visibleActions = useMemo(
        () =>
            actions.map((action, key) => ({ action, key })).filter(({ action, key }) => action.asRowAction !== false && actionIsVisible(action, key)),
        [actions, actionIsVisible],
    );
    const asDropdown = visibleActions.length > 3;

    const componentType = useCallback(
        (action: TableAction, key: number): React.ComponentType<any> | string => {
            // Check if we have action data for this item
            if (!item._actions || !item._actions[key]) {
                // Fallback based on action type
                return action.type === 'link' ? 'a' : Button;
            }

            const actionItem = getActionForItem(item._actions[key] as any, action);

            if (
                actionItem?.componentType === 'button-component' ||
                (actionItem && 'style' in actionItem && actionItem.style === 'button') ||
                actionItem?.type === 'button'
            ) {
                return asDropdown ? 'button' : Button;
            }

            // Return the component type or default to 'a' for links, Button for others
            return actionItem?.componentType || (action.type === 'link' ? 'a' : Button);
        },
        [item, asDropdown],
    );

    const componentBindings = useCallback(
        (action: TableAction, key: number, handle: (action: TableAction) => void): Record<string, any> => {
            // Check if we have action data for this item
            if (!item._actions) {
                return {
                    onClick: () => handle(action),
                    disabled: !action.authorized,
                };
            }

            const actionData = item._actions[key];

            if (!actionData) {
                // Use the variant directly from the action since item._actions doesn't have the data
                return {
                    onClick: () => handle(action),
                    disabled: !action.authorized,
                    variant: toRowVariant(action.variant) || 'outline',
                };
            }

            const actionItem = getActionForItem(actionData as any, action);

            if (!actionItem) {
                return {
                    onClick: () => handle(action),
                    disabled: !action.authorized,
                };
            }

            // Create a mutable copy to avoid readonly issues
            const mutableActionItem = { ...actionItem };

            mutableActionItem.bindings = mutableActionItem.bindings || {};

            // Pass through the variant directly from the backend for all components
            if ('variant' in mutableActionItem && mutableActionItem.variant) {
                mutableActionItem.bindings.variant = toRowVariant(mutableActionItem.variant as string);
            }

            if (mutableActionItem.bindings?.class) {
                mutableActionItem.bindings.className = mutableActionItem.bindings.class;
                delete mutableActionItem.bindings.class;
            }

            // Handle openInNewTab for link actions
            if (actionData && typeof actionData === 'object' && 'openInNewTab' in actionData && actionData.openInNewTab) {
                mutableActionItem.bindings.target = '_blank';
                mutableActionItem.bindings.rel = 'noopener noreferrer';
            }

            if (!mutableActionItem.asDownload) {
                mutableActionItem.bindings.onClick = () => handle(action);
            }

            if (mutableActionItem.type === 'link') {
                mutableActionItem.bindings.className = mutableActionItem.bindings?.className || '';
                mutableActionItem.bindings.className += asDropdown ? '' : ' it-row-actions-link flex flex-row items-center';

                if (mutableActionItem.bindings?.disabled) {
                    mutableActionItem.bindings.className += ' cursor-not-allowed opacity-50';
                }
            }

            // Convert legacy size prop for buttons
            if (mutableActionItem.bindings.small) {
                mutableActionItem.bindings.size = 'sm';
                delete mutableActionItem.bindings.small;
            }

            // Remove custom props that don't exist in shadcn
            delete mutableActionItem.bindings.customVariantClass;
            delete mutableActionItem.bindings.sr;

            if (asDropdown && mutableActionItem.bindings) {
                delete mutableActionItem.bindings.primary;
                delete mutableActionItem.bindings.danger;
                delete mutableActionItem.bindings.small;
            }

            return mutableActionItem.bindings || {};
        },
        [item, asDropdown],
    );

    const hasVisibleActionsWithIcons = visibleActions.some(({ action }) => action.icon);
    if (visibleActions.length === 0) return null;

    return (
        <TableActions
            actions={actions}
            iconResolver={iconResolver!}
            item={item}
            performAction={performAction}
            onError={onError}
            keys={[item._primary_key]}
            onHandle={onHandle}
            onSuccess={onSuccess}
        >
            {({ handle }: { handle: (action: TableAction) => void }) =>
                asDropdown ? (
                    <DropdownMenu>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className={clsx(
                                            'it-row-actions-dropdown text-muted-foreground hover:bg-accent hover:text-foreground data-[state=open]:bg-accent inline-flex size-8 cursor-pointer items-center justify-center rounded-md transition-colors',
                                        )}
                                        aria-label="More actions"
                                    >
                                        <MoreHorizontal className="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                            </TooltipTrigger>
                            <TooltipContent>More actions</TooltipContent>
                        </Tooltip>
                        <DropdownMenuContent className="it-dropdown-items border-border bg-popover text-popover-foreground w-48">
                            {visibleActions.map(({ action, key }) =>
                                actionIsVisible(action, key) ? (
                                    <DropdownMenuItem
                                        key={key}
                                        disabled={!action.authorized}
                                        onClick={() => handle(action)}
                                        className="it-dropdown-item focus:bg-accent focus:text-accent-foreground"
                                    >
                                        {hasVisibleActionsWithIcons && action.icon && (
                                            <DynamicIcon
                                                className="it-row-actions-dropdown-icon text-muted-foreground me-2 size-3.5"
                                                resolver={iconResolver as any}
                                                icon={action.icon}
                                                context={action}
                                            />
                                        )}
                                        <span>{action.label}</span>
                                    </DropdownMenuItem>
                                ) : null,
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : (
                    <div className="it-row-actions flex items-center space-x-2 text-sm font-medium rtl:space-x-reverse">
                        {visibleActions.map(({ action, key }) => {
                            const Component = componentType(action, key);
                            const bindings = componentBindings(action, key, handle);
                            delete bindings.title;
                            // Safety check to ensure Component is never undefined
                            if (!Component || action.asRowAction === false || !actionIsVisible(action, key)) {
                                return null;
                            }
                            return (
                                <Tooltip key={key}>
                                    <TooltipTrigger asChild>
                                        <Component
                                            {...bindings}
                                            aria-label={action.label}
                                            className={clsx(
                                                bindings.className,
                                                'it-row-action-icon-button inline-flex size-8 shrink-0 items-center justify-center rounded-md p-0',
                                            )}
                                        >
                                            {action.icon ? (
                                                <DynamicIcon
                                                    resolver={iconResolver as any}
                                                    icon={action.icon}
                                                    context={action}
                                                    className="it-row-actions-icon size-4"
                                                />
                                            ) : (
                                                <MoreHorizontal className="size-4" aria-hidden="true" />
                                            )}
                                            <span className="sr-only">{action.label}</span>
                                        </Component>
                                    </TooltipTrigger>
                                    <TooltipContent>{action.label}</TooltipContent>
                                </Tooltip>
                            );
                        })}
                    </div>
                )
            }
        </TableActions>
    );
}
