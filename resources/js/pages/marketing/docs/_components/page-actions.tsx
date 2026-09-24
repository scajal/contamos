import { useLaravelReactI18n } from "laravel-react-i18n";
import { CheckIcon, ChevronDownIcon, CopyIcon, ExternalLinkIcon, FileTextIcon } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

interface Props {
    markdownUrl: string;
}

/**
 * Take the page being read into an AI assistant: copy its Markdown,
 * or open Claude or ChatGPT with a prompt that points the assistant
 * at the page's Markdown URL.
 */
export const PageActions = ({ markdownUrl }: Props) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the Markdown has just been copied.
     */
    const [copied, setCopied] = useState(false);

    /**
     * The prompt an assistant is opened with.
     */
    const prompt = t("Read :url so I can ask questions about it.", { url: markdownUrl });

    /**
     * Copy the page's Markdown. The text is fetched inside the
     * clipboard item so the write still counts as part of the click,
     * which Safari requires.
     */
    const copy = () => {
        const text = fetch(markdownUrl)
            .then((response) => response.text())
            .then((markdown) => new Blob([markdown], { type: "text/plain" }));

        void navigator.clipboard.write([new ClipboardItem({ "text/plain": text })]).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <div className="flex w-fit items-center">
            <Button className="rounded-r-none" onClick={copy} size="sm" variant="outline">
                {copied ? <CheckIcon /> : <CopyIcon />}
                {copied ? t("Copied") : t("Copy page")}
            </Button>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            aria-label={t("More ways to use this page")}
                            className="-ml-px rounded-l-none"
                            size="icon-sm"
                            variant="outline"
                        />
                    }
                >
                    <ChevronDownIcon />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-60">
                    <DropdownMenuItem
                        render={
                            <a
                                href={`https://claude.ai/new?q=${encodeURIComponent(prompt)}`}
                                rel="noopener noreferrer"
                                target="_blank"
                            />
                        }
                    >
                        <ExternalLinkIcon />
                        {t("Open in Claude")}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        render={
                            <a
                                href={`https://chatgpt.com/?q=${encodeURIComponent(prompt)}`}
                                rel="noopener noreferrer"
                                target="_blank"
                            />
                        }
                    >
                        <ExternalLinkIcon />
                        {t("Open in ChatGPT")}
                    </DropdownMenuItem>
                    <DropdownMenuItem render={<a href={markdownUrl} rel="noopener" target="_blank" />}>
                        <FileTextIcon />
                        {t("View as Markdown")}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
};
