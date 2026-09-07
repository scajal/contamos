import { useLaravelReactI18n } from "laravel-react-i18n";

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";

const QUESTIONS = [
    {
        question: "Is Hormiga free?",
        answer: "Yes. The Free plan covers a household getting started, with one account, one member and 100 transactions a month, no card required.",
    },
    {
        question: "Which currencies can I use?",
        answer: "Any fiat currency or bitcoin you add to your team. Hormiga tracks balances per currency and keeps FX and crypto quotes up to date automatically.",
    },
    {
        question: "Can I share it with my partner or family?",
        answer: "Yes. Invite them to your team and everyone logs transactions into the same shared ledger.",
    },
    {
        question: "Does it connect to my bank?",
        answer: "No. Hormiga is manual by design, so every entry is a deliberate choice, not an imported guess.",
    },
    {
        question: "Can I switch languages?",
        answer: "Yes. Switch between English and Spanish at any time from the language switcher.",
    },
    {
        question: "What happens when I hit the free limits?",
        answer: "You can keep using what you've already added. Upgrade to Pro whenever your household needs more accounts, members or transactions.",
    },
] as const;

/**
 * Frequently asked questions, as a keyboard/ARIA-correct accordion.
 */
export const Faq = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section id="faq" aria-labelledby="faq-heading" className="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-24">
            <h2 id="faq-heading" className="font-heading text-3xl font-semibold tracking-tight md:text-5xl">
                {t("FAQ")}
            </h2>

            <Accordion className="mt-10">
                {QUESTIONS.map(({ question, answer }) => (
                    <AccordionItem key={question} value={question}>
                        <AccordionTrigger className="text-base font-medium">{t(question)}</AccordionTrigger>
                        <AccordionContent>
                            <p className="text-muted-foreground">{t(answer)}</p>
                        </AccordionContent>
                    </AccordionItem>
                ))}
            </Accordion>
        </section>
    );
};
