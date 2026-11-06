<?php

namespace Javaabu\Workflows\Tests\TestSupport\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Javaabu\Workflows\Http\Controllers\WorkflowsController;

class CustomWorkflowsController extends WorkflowsController
{
    public function index(Request $request): JsonResponse
    {
        return new JsonResponse(['custom' => true]);
    }
}
