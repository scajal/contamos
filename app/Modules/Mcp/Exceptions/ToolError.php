<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Exceptions;

use RuntimeException;

/**
 * A refusal an MCP tool reports to the assistant as a tool error:
 * a plain English sentence it can relay to the user, such as a
 * record that was not found or a plan that lacks a feature.
 */
class ToolError extends RuntimeException
{
    //
}
