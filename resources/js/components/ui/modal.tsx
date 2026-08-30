import { HeadlessModal } from "@inertiaui/modal-react";
import {
    forwardRef,
    useCallback,
    useEffect,
    useState,
    type ComponentRef,
    type ForwardRefExoticComponent,
    type ReactNode,
    type RefAttributes,
} from "react";

/** Subset of the modal stack instance returned by getParentModal / getChildModal. */
export type ModalStackHandle = {
    close: () => void;
    emit: (event: string, ...args: unknown[]) => void;
};

/** Render props for app Modal consumers (mirrors @inertiaui/modal-react HeadlessModal). */
export type HeadlessModalRenderProps = {
    afterLeave: () => void;
    close: () => void;
    config: {
        slideover: boolean;
        closeButton: boolean;
        closeExplicitly: boolean;
        closeOnClickOutside: boolean;
        maxWidth: string;
        paddingClasses: string;
        panelClasses: string;
        position: string;
    };
    emit: (event: string, ...args: unknown[]) => void;
    getChildModal: () => ModalStackHandle | null;
    getParentModal: () => ModalStackHandle | null;
    id: string;
    index: number;
    isOpen: boolean;
    modalContext: ModalStackHandle;
    onTopOfStack: boolean;
    reload: (options?: { only?: string[]; except?: string[] }) => void;
    setOpen: (open: boolean) => void;
    shouldRender: boolean;
};

/** Props to spread onto Sheet or Dialog root. */
export type ModalRootProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onOpenChangeComplete: (open: boolean) => void;
};

export type ModalRenderProps = HeadlessModalRenderProps & {
    /** Spread onto Sheet or Dialog — handles close, afterLeave, and enter animation. */
    rootProps: ModalRootProps;
};

type AppModalKnownProps = {
    name?: string;
    onFocus?: () => void;
    onBlur?: () => void;
    onClose?: () => void;
    onSuccess?: () => void;
    onAfterLeave?: () => void;
    slideover?: boolean;
    closeButton?: boolean;
    closeExplicitly?: boolean;
    closeOnClickOutside?: boolean;
    maxWidth?: string;
    paddingClasses?: string;
    panelClasses?: string;
    position?: string;
    children: (props: ModalRenderProps) => ReactNode;
};

/** Known modal options plus arbitrary props (e.g. `onHalt`), same as the package HeadlessModal. */
export type AppModalProps = AppModalKnownProps & Record<string, unknown>;

function useModalRootProps(isOpen: boolean, close: () => void, afterLeave: () => void): ModalRootProps {
    const [uiOpen, setUiOpen] = useState(false);

    useEffect(() => {
        if (!isOpen) {
            setUiOpen(false);

            return;
        }

        let cancelled = false;

        const frame = requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (!cancelled) {
                    setUiOpen(true);
                }
            });
        });

        return () => {
            cancelled = true;
            cancelAnimationFrame(frame);
        };
    }, [isOpen]);

    const onOpenChange = useCallback(
        (open: boolean) => {
            if (!open) {
                close();
            }
        },
        [close],
    );

    const onOpenChangeComplete = useCallback(
        (open: boolean) => {
            if (!open) {
                afterLeave();
            }
        },
        [afterLeave],
    );

    return { open: uiOpen, onOpenChange, onOpenChangeComplete };
}

function ModalBridge({
    renderProps,
    children,
}: {
    renderProps: HeadlessModalRenderProps;
    children: (props: ModalRenderProps) => ReactNode;
}) {
    const rootProps = useModalRootProps(renderProps.isOpen, renderProps.close, renderProps.afterLeave);

    return children({ ...renderProps, rootProps });
}

export const Modal: ForwardRefExoticComponent<AppModalProps & RefAttributes<ComponentRef<typeof HeadlessModal>>> =
    forwardRef<ComponentRef<typeof HeadlessModal>, AppModalProps>(function Modal(allProps, ref) {
        const { children, ...headlessProps } = allProps as AppModalKnownProps & Record<string, unknown>;

        return (
            <HeadlessModal {...headlessProps} ref={ref}>
                {(renderProps: HeadlessModalRenderProps) => (
                    <ModalBridge renderProps={renderProps}>{children}</ModalBridge>
                )}
            </HeadlessModal>
        );
    });
