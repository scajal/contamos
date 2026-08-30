import { usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { MoreHorizontalIcon, PencilIcon, PlusIcon, SearchIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Table, TableBody, TableCell, TableRow } from "@/components/ui/table";
import { ApplicationLayout } from "@/layouts/application/layout";
import routes from "@/routes/catalog/currencies";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    currencies: LengthAwarePaginator<Currency>;
};

const Index = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { currencies } = usePage<PageProps>().props;

    return (
        <div className="flex flex-col gap-4">
            {/** Header */}
            <header className="flex flex-col gap-8">
                <h1 className="text-2xl font-medium">{t("Currencies")}</h1>
                {/** Filters and actions */}
                <div className="flex items-center justify-between">
                    <div className="flex-2">
                        <InputGroup>
                            <InputGroupAddon>
                                <SearchIcon />
                            </InputGroupAddon>
                            <InputGroupInput placeholder={t("Search by name or code")} />
                        </InputGroup>
                    </div>
                    <div className="flex flex-1 justify-end">
                        <Button
                            nativeButton={false}
                            render={
                                <ModalLink href={routes.create.url()}>
                                    <PlusIcon />
                                    <span>{t("Create")}</span>
                                </ModalLink>
                            }
                        />
                    </div>
                </div>
            </header>

            {/** Content */}
            <div>
                {/** Currencies */}
                <div className="grid [&>div]:rounded-sm [&>div]:border">
                    <Table>
                        <TableBody>
                            {currencies?.data?.map((currency) => (
                                <TableRow key={currency.id}>
                                    <TableCell>
                                        <div className="flex flex-col gap-1">
                                            <span className="font-medium">{currency.name}</span>
                                            <span className="text-sm text-gray-500">{currency.code}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                                                <MoreHorizontalIcon />
                                                <span className="sr-only">{t("Actions")}</span>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem>
                                                    <PencilIcon />
                                                    <span>{t("Edit")}</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
