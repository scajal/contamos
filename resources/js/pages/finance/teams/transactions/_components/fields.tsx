import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ListIcon } from "lucide-react";

import { Combobox, ComboboxContent, ComboboxInput, ComboboxItem, ComboboxList } from "@/components/ui/combobox";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputGroupAddon } from "@/components/ui/input-group";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

type FormProps = Partial<Omit<Transaction, "account" | "currency" | "team" | "user">>;

type Props = FormComponentSlotProps<FormProps> & {
    accounts: Array<Account>;
    defaultValues?: FormProps;
};

export const Fields = ({ accounts, defaultValues, errors, invalid }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <div className="space-y-6">
            {/** Account */}
            <Field>
                <FieldLabel>{t("Account")}</FieldLabel>
                <Combobox<Account>
                    items={accounts}
                    itemToStringLabel={(account) => account.name}
                    itemToStringValue={(account) => account.id}
                >
                    <ComboboxInput placeholder={t("Select an account")}>
                        <InputGroupAddon>
                            <ListIcon />
                        </InputGroupAddon>
                        <ComboboxContent alignOffset={-28}>
                            <ComboboxList>
                                {(account) => (
                                    <ComboboxItem key={account.id} value={account}>
                                        {account.name}
                                    </ComboboxItem>
                                )}
                            </ComboboxList>
                        </ComboboxContent>
                    </ComboboxInput>
                </Combobox>
                {invalid("account_id") && <FieldError>{errors.account_id}</FieldError>}
            </Field>
        </div>
    );
};
