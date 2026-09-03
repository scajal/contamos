import { Form, router, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Spinner } from "@/components/ui/spinner";
import currencies from "@/routes/administration/currencies";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";

import { Fields } from "./_components/fields";

type PageProps = {
    currency: Currency;
};

export default function Create() {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { currency } = usePage<PageProps>().props;

    return (
        <Modal>
            {({ rootProps }) => (
                <Sheet {...rootProps}>
                    <SheetContent>
                        <Form
                            {...currencies.quotes.store.form({ currency: currency.id })}
                            className="contents"
                            disableWhileProcessing
                            onSuccess={() => {
                                rootProps.onOpenChange(false);
                                router.reload({ only: ["quotes"] });
                            }}
                            options={{ only: ["errors"] }}
                        >
                            {(formProps) => (
                                <Fragment>
                                    <SheetHeader>
                                        <SheetTitle>{t("Create :name", { name: t("quote") })}</SheetTitle>
                                        <SheetDescription>
                                            {t("Add a new quote for :name.", { name: currency.name })}
                                        </SheetDescription>
                                    </SheetHeader>
                                    <div className="min-h-0 flex-1 overflow-y-auto px-4">
                                        <Fields {...formProps} />
                                    </div>
                                    <SheetFooter>
                                        <Button disabled={formProps.processing || !formProps.isDirty} type="submit">
                                            {formProps.processing && <Spinner />}
                                            {t("Save")}
                                        </Button>
                                    </SheetFooter>
                                </Fragment>
                            )}
                        </Form>
                    </SheetContent>
                </Sheet>
            )}
        </Modal>
    );
}
