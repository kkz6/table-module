import { Button } from '@shared/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@shared/components/ui/dialog';
import { cn } from '@shared/lib/utils';
import React from 'react';
import type { ConfirmActionDialogProps, ConfirmDialogProps } from './types';

// Combined ConfirmDialog component that handles both direct props and action-based props
export default function ConfirmDialog({
    title,
    message,
    confirmButton,
    cancelButton = false,
    show,
    variant = 'primary',
    customVariantClass = '',
    onCancel,
    onConfirm = null,
    dialogClassName = '',
    overlayClassName = '',
    icon,
    iconResolver,
}: ConfirmDialogProps & { dialogClassName?: string; overlayClassName?: string }): React.ReactElement {
    return (
        <Dialog open={show} onOpenChange={(open) => !open && onCancel?.()}>
            <DialogContent className={cn('gap-3 p-5 sm:max-w-[425px]', dialogClassName, overlayClassName)}>
                <DialogHeader className="text-left">
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <p className="text-muted-foreground">{message}</p>
                <DialogFooter className="mt-4">
                    {cancelButton !== false && (
                        <DialogClose asChild>
                            <Button type="button" variant="outline" onClick={onCancel || undefined}>
                                {cancelButton || 'Cancel'}
                            </Button>
                        </DialogClose>
                    )}
                    <Button
                        type="button"
                        variant={variant}
                        className={cn(customVariantClass)}
                        onClick={onConfirm || undefined}
                    >
                        {confirmButton}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// Action-based wrapper component for backward compatibility and cleaner API
export function ConfirmActionDialog({ show, action = {}, onConfirm, onCancel, iconResolver }: ConfirmActionDialogProps): React.ReactElement {
    const {
        confirmationTitle = '',
        confirmationMessage = '',
        confirmationCancelButton = '',
        confirmationConfirmButton = '',
        icon,
        variant,
        buttonClass,
    } = action;

    return (
        <ConfirmDialog
            show={show}
            title={confirmationTitle}
            message={confirmationMessage}
            cancelButton={confirmationCancelButton || 'Cancel'}
            confirmButton={confirmationConfirmButton || 'Confirm'}
            variant={variant || null}
            customVariantClass={buttonClass || ''}
            icon={icon || null}
            iconResolver={iconResolver || null}
            onCancel={onCancel || null}
            onConfirm={onConfirm || null}
        />
    );
}
