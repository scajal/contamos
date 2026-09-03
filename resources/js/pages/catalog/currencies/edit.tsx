import { Form, router } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Spinner } from "@/components/ui/spinner";
import currencies from "@/routes/administration/currencies";
import { CurrencyType } from "@/types/app/modules/core/catalog/enums/currency/currency-type";
import { QuoteSource } from "@/types/app/modules/core/catalog/enums/currency/quote/quote-source";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";

import { Fields } from "./_components/fields";

type PageProps = {
    currency: Currency;
    quoteSources: Array<QuoteSource>;
    types: Array<CurrencyType>;
};

export default function Edit({ currency, quoteSources, types }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <Modal>
            {({ rootProps }) => (
                <Sheet {...rootProps}>
                    <SheetContent>
                        <Form
                            {...currencies.update.form(currency.id)}
                            className="contents"
                            disableWhileProcessing
                            onSuccess={() => {
                                rootProps.onOpenChange(false);
                                router.reload({ only: ["currencies"] });
                            }}
                            options={{ only: ["errors"] }}
                        >
                            {(formProps) => (
                                <Fragment>
                                    <SheetHeader>
                                        <SheetTitle>{t("Edit :name", { name: t("currency") })}</SheetTitle>
                                        <SheetDescription>{t("Edit the currency details.")}</SheetDescription>
                                    </SheetHeader>
                                    <div className="min-h-0 flex-1 overflow-y-auto px-4">
                                        <Fields
                                            {...formProps}
                                            defaultValues={currency}
                                            locked={["code", "decimal_places", "type"]}
                                            quoteSources={quoteSources}
                                            types={types}
                                        />
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
