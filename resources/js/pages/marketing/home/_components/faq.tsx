import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon, MinusIcon, PlusIcon } from "lucide-react";
import { m, useReducedMotion } from "motion/react";

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";
import { login } from "@/routes";

/**
 * The doubts that stop someone signing up, in the order they stop them,
 * and not a second telling of what the sections above already said.
 *
 * Every answer is something the app does today, and the limits and
 * plazos in them are the ones the code enforces.
 *
 * Exported because the page's JSON-LD publishes the same pairs as a
 * FAQPage: the answer a search engine is shown has to be the answer on
 * the page, so there is only ever one copy of them.
 */
export const QUESTIONS: Array<{ question: string; answer: string }> = [
    {
        question: "Does it connect to my bank?",
        answer: "No, and that is on purpose. Writing a movement down takes seconds, and in exchange the ledger says exactly what you put in it, instead of waiting on a bank to report it and then arguing with what it filed wrong.",
    },
    {
        question: "What happens to my history on the Free plan?",
        answer: "Nothing is deleted. The Free plan shows the last six months and everything older stays where it is, waiting. The day you move to Pro it is all there again.",
    },
    {
        question: "Who can see what we write down?",
        answer: "Only the people you invited to the team by email, and an invitation link expires after seven days. Every movement carries the name of whoever recorded it, so inside the team there is no guessing either.",
    },
    {
        question: "Can I take my data with me?",
        answer: "Yes. Export what you are looking at to CSV, with whatever filters you have on, and open it wherever you like.",
    },
    {
        question: "Where do the exchange rates come from?",
        answer: "They sync once a day from Uruguay's central bank, and every movement stores the rate of the day it happened, so today's rate never rewrites last year's entries. If you need a different one, you set it on the movement itself.",
    },
    {
        question: "Is Contamos finished?",
        answer: "No, and it will not be for a while. It is in active development: new features land regularly, and the ones described on this page are the ones that work today. Nothing here is a promise about later.",
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
                    <h2 className="font-heading max-w-[20ch] text-[clamp(2rem,4vw,3.25rem)] leading-[1.05] font-semibold tracking-tight text-balance">
                        {t("The things people ask before they start.")}
                    </h2>
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
                        {dashboardUrl ? t("Go to dashboard") : t("Start free")} <ArrowRightIcon />
                    </Button>
                    {!dashboardUrl && (
                        <p className="text-muted-foreground text-sm">{t("Free plan. No card required.")}</p>
                    )}
                </m.div>
            </div>
        </section>
    );
};
