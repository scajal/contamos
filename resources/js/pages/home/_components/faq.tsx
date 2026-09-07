import { useLaravelReactI18n } from "laravel-react-i18n";

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";

import { Reveal } from "./reveal";

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
 * Frequently asked questions: a two-column layout, heading pinned
 * left like the other sections, accordion in the wide column, so the
 * left edge stays aligned with the rest of the page's max-w-6xl grid.
 * Questions stagger into place as the section scrolls into view.
 */
export const Faq = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section
            id="faq"
            aria-labelledby="faq-heading"
            className="bg-muted/40 relative overflow-hidden border-y bg-[repeating-linear-gradient(to_bottom,transparent,transparent_31px,var(--border)_32px)]"
        >
            <span
                aria-hidden="true"
                className="font-heading text-foreground/[0.04] pointer-events-none absolute -top-10 right-[4%] rotate-6 text-[12rem] leading-none font-bold select-none"
            >
                ?
            </span>

            <div className="relative mx-auto grid max-w-6xl gap-8 px-4 py-20 sm:px-6 sm:py-28 md:grid-cols-[280px_1fr] md:gap-16">
                <h2 id="faq-heading" className="font-heading text-3xl font-semibold tracking-tight md:text-5xl">
                    {t("FAQ")}
                </h2>

                <Accordion className="max-w-2xl">
                    {QUESTIONS.map(({ question, answer }, index) => (
                        <Reveal key={question} delay={index * 0.06}>
                            <AccordionItem value={question}>
                                <AccordionTrigger className="text-base font-medium">{t(question)}</AccordionTrigger>
                                <AccordionContent>
                                    <p className="text-muted-foreground">{t(answer)}</p>
                                </AccordionContent>
                            </AccordionItem>
                        </Reveal>
                    ))}
                </Accordion>
            </div>
        </section>
    );
};
