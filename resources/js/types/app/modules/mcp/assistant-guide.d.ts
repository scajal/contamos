/**
 * The steps one assistant's settings take to connect it.
 */
export interface AssistantConnectionGuide {
    name: string;
    steps: Array<string>;
}

/**
 * An example of what to ask a connected assistant, marked with
 * whether the team needs Pro to run it.
 */
export interface AssistantExamplePrompt {
    prompt: string;
    pro: boolean;
}

/**
 * JSON shape of App\Modules\Mcp\Helpers\AssistantGuide, which the
 * in-app AI assistants page and the docs share.
 */
export interface AssistantGuide {
    server_url: string;
    guides: Array<AssistantConnectionGuide>;
    prompts: Array<AssistantExamplePrompt>;
}
