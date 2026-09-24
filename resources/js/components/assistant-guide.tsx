import { useLaravelReactI18n } from "laravel-react-i18n";
import { CheckIcon, CopyIcon } from "lucide-react";
import { useState } from "react";

import { ProBadge } from "@/components/pro-badge";
import { Button } from "@/components/ui/button";
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import type { AssistantConnectionGuide, AssistantExamplePrompt } from "@/types/app/modules/mcp/assistant-guide";

/**
 * Copy a text, and report for a moment that it was copied.
 */
const useCopy = () => {
    /**
     * The text copied last, while its confirmation shows.
     */
    const [copied, setCopied] = useState<string | null>(null);

    const copy = (text: string) => {
        void navigator.clipboard.writeText(text).then(
            () => {
                setCopied(text);
                window.setTimeout(() => setCopied(null), 1500);
            },
            () => undefined,
        );
    };

    return { copied, copy };
};

/**
 * The address of the MCP server, read-only with a button that
 * copies it.
 */
export const ServerAddress = ({ url }: { url: string }) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    const { copied, copy } = useCopy();

    return (
        <InputGroup>
            <InputGroupInput aria-label={t("Server address")} className="font-mono" readOnly value={url} />
            <InputGroupAddon align="inline-end">
                <InputGroupButton
                    aria-label={copied ? t("Copied") : t("Click to copy")}
                    onClick={() => copy(url)}
                    size="icon-xs"
                >
                    {copied ? <CheckIcon /> : <CopyIcon />}
                </InputGroupButton>
            </InputGroupAddon>
        </InputGroup>
    );
};

/**
 * The steps each assistant's settings take to connect it, side by
 * side.
 */
export const ConnectionSteps = ({ guides }: { guides: Array<AssistantConnectionGuide> }) => (
    <div className="grid gap-4 sm:grid-cols-2">
        {guides.map((guide) => (
            <div className="flex flex-col gap-3 rounded-xl border p-4" key={guide.name}>
                <p className="font-medium">{guide.name}</p>
                <ol className="text-muted-foreground flex list-decimal flex-col gap-1.5 pl-5 text-[15px] leading-relaxed">
                    {guide.steps.map((step) => (
                        <li key={step}>{step}</li>
                    ))}
                </ol>
            </div>
        ))}
    </div>
);

/**
 * What to ask a connected assistant first, each with a button that
 * copies it and a badge on the ones that need Pro.
 */
export const ExamplePrompts = ({ prompts }: { prompts: Array<AssistantExamplePrompt> }) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    const { copied, copy } = useCopy();

    return (
        <ul className="divide-y rounded-xl border">
            {prompts.map(({ prompt, pro }) => (
                <li className="flex items-center gap-3 py-2.5 pr-2.5 pl-4" key={prompt}>
                    <p className="flex-1 text-[15px] leading-relaxed">
                        {prompt}
                        {pro && <ProBadge className="ml-2 align-middle" />}
                    </p>
                    <Button
                        aria-label={copied === prompt ? t("Copied") : t("Copy prompt")}
                        onClick={() => copy(prompt)}
                        size="icon-sm"
                        variant="ghost"
                    >
                        {copied === prompt ? <CheckIcon /> : <CopyIcon />}
                    </Button>
                </li>
            ))}
        </ul>
    );
};
