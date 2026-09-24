import { useLaravelReactI18n } from "laravel-react-i18n";
import { SparklesIcon } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { ActivityAssistant } from "@/types/app/modules/core/identity/models/team/activity";

type Props = {
    assistant: ActivityAssistant | null;
};

/**
 * Marks an activity entry the user wrote through an AI assistant,
 * naming the assistant when it is known.
 */
export const AssistantBadge = ({ assistant }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    if (!assistant) {
        return null;
    }

    return (
        <Badge variant="outline">
            <SparklesIcon data-icon="inline-start" />
            {assistant.name ? t("via :name", { name: assistant.name }) : t("via an AI assistant")}
        </Badge>
    );
};
