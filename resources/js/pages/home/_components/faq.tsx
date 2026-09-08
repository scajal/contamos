import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon, MinusIcon, PlusIcon } from "lucide-react";
import { m, useReducedMotion } from "motion/react";

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";
import { login } from "@/routes";

/**
 * Every answer here is something the app actually does; none of them
 * describe behaviour that is still on the roadmap.
 *
 * Exported because the page's JSON-LD publishes the same pairs as a
 * FAQPage: the answer a search engine is shown has to be the answer on
 * the page, so there is only ever one copy of them.
 */
export const QUESTIONS: Array<{ question: string; answer: string }> = [
    {
        question: "Do I need a card to start?",
        answer: "No. The free plan stays free, and nothing asks for a card until you decide you want Pro.",
    },
    {
        question: "How do I share it with someone?",
        answer: "Invite them by email. The link works for seven days, and once they accept you both write to the same ledger and can see who recorded what.",
    },
    {
        question: "Where do the exchange rates come from?",
        answer: "They sync once a day from the Uruguayan central bank, and every transaction stores the rate of the day it happened. Today's rate never rewrites last year's entries.",
    },
    {
        question: "Can a single account hold several currencies?",
        answer: "Yes. A bank, cash or crypto account keeps a separate balance for every currency you enable on it, so one account can hold euros and dollars at once.",
    },
    {
        question: "Does it add everything up into one total?",
        answer: "No, and that is on purpose. Each currency keeps its own balance, because a single blended number hides more than it tells you.",
    },
    {
        question: "What if I delete a transaction by mistake?",
        answer: "The balance goes back exactly where it was before it. There is nothing to correct by hand afterwards.",
    },
    {
        question: "How do I cancel Pro?",
        answer: "From the billing portal, whenever you want. There is no form to fill in and nobody to talk to.",
    },
];

interface FaqProps {
    dashboardUrl: string | null;
}

/**
 * The objections that land right after a price, answered, and then the
 * page's closing call to action — so a visitor who read all the way
 * down is never left without somewhere to go.
 */
export const Faq = ({ dashboardUrl }: FaqProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    return (
        <section id="faq" className="bg-background relative scroll-mt-28 border-t">
            <div className="mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-80px" }}
                    transition={{ duration: 0.6, ease: "easeOut" }}
                    className="flex flex-col items-center gap-5 text-center"
                >
                    <span className="text-muted-foreground text-xs font-medium tracking-[0.2em] uppercase">
                        {t("Questions")}
                    </span>
                    <h2 className="font-heading max-w-[20ch] text-[clamp(2rem,4vw,3.25rem)] leading-[1.05] font-semibold tracking-tight text-balance">
                        {t("The things people ask before they start.")}
                    </h2>
                    <p className="text-muted-foreground max-w-[46ch]">
                        {t("Short answers, and none of them describe something we have not built yet.")}
                    </p>
                </m.div>

                {/*
                 * A list, not a widget: the shared accordion's box, fill
                 * and chevron are all stripped back to hairline rules and
                 * a plus/minus, so it reads like the rest of the page
                 * instead of like a form control. The first answer is
                 * open on arrival, so the section is never a stack of
                 * closed grey bars.
                 */}
                <Accordion
                    defaultValue={[QUESTIONS[0].question]}
                    className="mx-auto mt-14 max-w-3xl rounded-none border-0 sm:mt-16"
                >
                    {QUESTIONS.map(({ question, answer }) => (
                        <AccordionItem key={question} value={question} className="border-b data-open:bg-transparent">
                            <AccordionTrigger className="group items-center px-0 py-6 hover:no-underline **:data-[slot=accordion-trigger-icon]:hidden">
                                <span className="font-heading text-lg font-semibold tracking-tight sm:text-xl">
                                    {t(question)}
                                </span>
                                <span
                                    aria-hidden="true"
                                    className="text-muted-foreground group-hover/accordion-trigger:text-foreground ml-6 shrink-0 transition-colors"
                                >
                                    <PlusIcon className="size-5 group-aria-expanded/accordion-trigger:hidden" />
                                    <MinusIcon className="hidden size-5 group-aria-expanded/accordion-trigger:block" />
                                </span>
                            </AccordionTrigger>
                            <AccordionContent className="text-muted-foreground -mx-4 max-w-[62ch] pb-6 text-base leading-relaxed">
                                {t(answer)}
                            </AccordionContent>
                        </AccordionItem>
                    ))}
                </Accordion>

                {/* The page's last word: everything above has been argued, so this only has to be easy to reach. */}
                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-60px" }}
                    transition={{ duration: 0.6, ease: "easeOut" }}
                    className="border-border/60 mt-20 flex flex-col items-center gap-6 border-t pt-16 text-center sm:mt-24 sm:pt-20"
                >
                    <h2 className="font-heading max-w-[20ch] text-[clamp(1.75rem,3.5vw,2.75rem)] leading-[1.08] font-semibold tracking-tight text-balance">
                        {t("Know exactly what you have, starting tonight.")}
                    </h2>
                    <Button nativeButton={false} render={<a href={dashboardUrl ?? login.url()} />} size="lg">
                        {t("Start")} <ArrowRightIcon />
                    </Button>
                    {!dashboardUrl && (
                        <p className="text-muted-foreground text-sm">{t("Free plan. No card required.")}</p>
                    )}
                </m.div>
            </div>
        </section>
    );
};
